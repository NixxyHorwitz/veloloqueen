<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$memberships = $pdo->query("SELECT * FROM memberships WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
$flash = $flashType = '';

// Active membership info
$active_membership = null;
$can_refund = false;
$user_settings = ['refund_cut_percent' => 20, 'is_refund_enabled' => 1];

if ($user['membership_id'] && $user['membership_expires_at'] && strtotime($user['membership_expires_at']) > time()) {
    $ms = $pdo->prepare("SELECT * FROM memberships WHERE id=?");
    $ms->execute([$user['membership_id']]);
    $active_membership = $ms->fetch();
    
    $uSet = $pdo->prepare("SELECT refund_cut_percent, is_refund_enabled FROM users WHERE id=?");
    $uSet->execute([$user['id']]);
    $user_settings = $uSet->fetch() ?: $user_settings;
    
    if ($user_settings['is_refund_enabled']) {
        $lastUp = $pdo->prepare("SELECT confirmed_at FROM upgrade_orders WHERE user_id=? AND membership_id=? AND status='confirmed' ORDER BY id DESC LIMIT 1");
        $lastUp->execute([$user['id'], $active_membership['id']]);
        $last_confirmed = $lastUp->fetchColumn();
        if ($last_confirmed && strtotime($last_confirmed) > time() - (12 * 3600)) {
            $can_refund = true;
        }
    }
}

// AJAX Check Voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'check_voucher') {
    header('Content-Type: application/json');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $mid  = (int)($_POST['membership_id'] ?? 0);
    
    if (!$code) { echo json_encode(['error' => 'Masukkan kode voucher.']); exit; }
    if (!$mid) { echo json_encode(['error' => 'Pilih paket terlebih dahulu.']); exit; }
    
    $stmt = $pdo->prepare("SELECT * FROM discount_vouchers WHERE code = ?");
    $stmt->execute([$code]);
    $v = $stmt->fetch();
    
    if (!$v) { echo json_encode(['error' => 'Kode voucher tidak ditemukan atau tidak valid.']); exit; }
    if ($v['expires_at'] && strtotime($v['expires_at']) < time()) { echo json_encode(['error' => 'Voucher ini sudah kedaluwarsa.']); exit; }
    if ($v['max_claims'] > 0 && $v['claims_count'] >= $v['max_claims']) { echo json_encode(['error' => 'Kuota voucher ini sudah habis.']); exit; }
    
    $chk = $pdo->prepare("SELECT id FROM user_discount_claims WHERE user_id = ? AND voucher_id = ?");
    $chk->execute([$user['id'], $v['id']]);
    if ($chk->fetch()) { echo json_encode(['error' => 'Kamu sudah menggunakan voucher ini sebelumnya.']); exit; }
    
    $discounts = json_decode($v['discounts'], true) ?: [];
    
    if (isset($discounts['*'])) {
        $val = $discounts['*'];
    } elseif (isset($discounts[$mid])) {
        $val = $discounts[$mid];
    } else {
        echo json_encode(['error' => 'Voucher ini tidak dapat digunakan untuk paket pilihanmu.']); exit;
    }
    
    $ms = $pdo->prepare("SELECT price FROM memberships WHERE id=? AND is_active=1");
    $ms->execute([$mid]);
    $price = (float)$ms->fetchColumn();
    if (!$price) { echo json_encode(['error' => 'Paket tidak valid.']); exit; }
    
    $is_rp = false;
    $pct = 0;
    if (is_string($val) && stripos($val, 'rp') !== false) {
        $discount_amount = (float)str_ireplace('rp', '', $val);
        $is_rp = true;
    } elseif (is_numeric($val) && $val > 100) {
        $discount_amount = (float)$val; // legacy format fallback
        $is_rp = true;
    } else {
        $pct = (float)$val;
        $discount_amount = ($price * $pct) / 100;
    }
    
    $final_price = $price - $discount_amount;
    if ($final_price < 0) $final_price = 0;
    
    $discount_text = $is_rp ? 'Rp ' . number_format($discount_amount, 0, ',', '.') : $pct . '%';
    
    echo json_encode([
        'ok' => true,
        'discount_text' => $discount_text,
        'discount_amount' => $discount_amount,
        'discount_amount_formatted' => format_rp($discount_amount),
        'final_price' => $final_price,
        'final_price_formatted' => format_rp($final_price)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'refund_level') {
        $stmtPending = $pdo->prepare("SELECT id FROM admin_requests WHERE user_id=? AND type='refund_level' AND status='pending'");
        $stmtPending->execute([$user['id']]);
        
        if ($stmtPending->fetchColumn()) {
            $flash = 'âŒ Permintaan pengembalian dana kamu sebelumnya masih dalam proses verifikasi otomatis.'; $flashType = 'error';
        } else {
            $s = $pdo->prepare("SELECT m.id as membership_id, m.name, m.price, u.refund_cut_percent, u.is_refund_enabled FROM users u LEFT JOIN memberships m ON u.membership_id = m.id WHERE u.id=?");
            $s->execute([$user['id']]);
            $uInfo = $s->fetch();
            $mName = $uInfo['name'] ?? null;
            
            if (!$mName) {
                $flash = 'âŒ Kamu tidak memiliki paket aktif.'; $flashType = 'error';
            } elseif (!$uInfo['is_refund_enabled']) {
                $flash = 'âŒ Akses refund kamu telah dinonaktifkan.'; $flashType = 'error';
            } else {
                $pdo->prepare("INSERT INTO admin_requests (user_id, type) VALUES (?, 'refund_level')")->execute([$user['id']]);
                $req_id = $pdo->lastInsertId();
                
                $oStmt = $pdo->prepare("SELECT amount FROM upgrade_orders WHERE user_id=? AND membership_id=? AND status='confirmed' ORDER BY id DESC LIMIT 1");
                $oStmt->execute([$user['id'], $uInfo['membership_id']]);
                $basePrice = (float)$oStmt->fetchColumn();
                if (!$basePrice) $basePrice = (float)$uInfo['price'];
                
                $pct = (float)$uInfo['refund_cut_percent'];
                $cutAmount = ($basePrice * $pct) / 100;
                $afterCut = $basePrice - $cutAmount;
                
                $msg  = "ðŸ’° <b>REQUEST REFUND LEVEL</b>\n\n";
                $msg .= "ðŸ‘¤ User: <code>{$user['username']}</code>\n";
                $msg .= "ðŸ† Level: <b>{$mName}</b>\n";
                $msg .= "ðŸ’µ Harga Awal: <b>" . format_rp($basePrice) . "</b>\n";
                $msg .= "âœ‚ï¸ Setelah Dipotong ({$pct}%): <b>" . format_rp($afterCut) . "</b>\n\n";
                $msg .= "âš ï¸ <i>Refund ini akan membatalkan level user dan mengembalikan saldo dengan potongan {$pct}% (jika di-Approve).</i>\n";
                $kb = [
                    [['text'=>'âœ… Approve Refund', 'callback_data'=>'req_approve_'.$req_id], ['text'=>'âŒ Reject', 'callback_data'=>'req_reject_'.$req_id]],
                    [['text'=>"âš™ï¸ Ubah Potongan ({$pct}%)", 'callback_data'=>'edit_refcut_'.$user['id']], ['text'=>'ðŸ”’ Cabut Akses Refund', 'callback_data'=>'toggle_ref_'.$user['id']]]
                ];
                send_telegram_notif($pdo, $msg, $kb, 'permintaan');
                
                $flash = 'âœ… Permintaan pengembalian dana kamu telah masuk dan sedang diverifikasi oleh sistem secara otomatis.';
            }
        }
        goto end_post;
    }

    $mid = (int)($_POST['membership_id'] ?? 0);
    $ms  = $pdo->prepare("SELECT * FROM memberships WHERE id=? AND is_active=1");
    $ms->execute([$mid]);
    $chosen = $ms->fetch();

    if (!$chosen) {
        $flash = 'Duh, paketnya gak ketemu nih.'; $flashType = 'error';
    } elseif ((float)$chosen['price'] == 0) {
        $flash = 'Paket Free gak usah diupgrade ya!'; $flashType = 'error';
    } else {
        $voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));
        $price = (float)$chosen['price'];
        $final_price = $price;
        $v_data = null;
        
        if ($voucher_code !== '') {
            $v_stmt = $pdo->prepare("SELECT * FROM discount_vouchers WHERE code = ? FOR UPDATE");
            $v_stmt->execute([$voucher_code]);
            $v_data = $v_stmt->fetch();
            
            if (!$v_data) {
                $flash = 'Kode vouchermu gak valid nih.'; $flashType = 'error';
                goto end_post;
            }
            if ($v_data['expires_at'] && strtotime($v_data['expires_at']) < time()) {
                $flash = 'Wah, voucher diskon ini udah kedaluwarsa.'; $flashType = 'error';
                goto end_post;
            }
            if ($v_data['max_claims'] > 0 && $v_data['claims_count'] >= $v_data['max_claims']) {
                $flash = 'Kuota voucher diskon ini udah abis ya.'; $flashType = 'error';
                goto end_post;
            }
            
            $chk = $pdo->prepare("SELECT id FROM user_discount_claims WHERE user_id = ? AND voucher_id = ?");
            $chk->execute([$user['id'], $v_data['id']]);
            if ($chk->fetch()) {
                $flash = 'Kamu udah pernah pakai voucher diskon ini sebelumnya.'; $flashType = 'error';
                goto end_post;
            }
            
            $discounts = json_decode($v_data['discounts'], true) ?: [];
            
            if (isset($discounts['*'])) {
                $val = $discounts['*'];
            } elseif (isset($discounts[$mid])) {
                $val = $discounts[$mid];
            } else {
                $flash = 'Voucher diskon ini gak bisa dipakai buat paket pilihanmu ya.'; $flashType = 'error';
                goto end_post;
            }
            
            if (is_string($val) && stripos($val, 'rp') !== false) {
                $discount_amount = (float)str_ireplace('rp', '', $val);
            } elseif (is_numeric($val) && $val > 100) {
                $discount_amount = (float)$val; // legacy format fallback
            } else {
                $pct = (float)$val;
                $discount_amount = ($price * $pct) / 100;
            }
            
            $final_price = $price - $discount_amount;
            if ($final_price < 0) $final_price = 0;
        }
        
        if ((float)$user['balance_dep'] < $final_price) {
            $flash = 'Saldo Beli kamu kurang nih. Yuk deposit dulu!'; $flashType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE users SET balance_dep=balance_dep-? WHERE id=? AND balance_dep >= ?");
                $stmt->execute([$final_price, $user['id'], $final_price]);
                
                if ($stmt->rowCount() > 0) {
                    if ($v_data) {
                        $v_lock = $pdo->prepare("SELECT claims_count, max_claims FROM discount_vouchers WHERE id = ? FOR UPDATE");
                        $v_lock->execute([$v_data['id']]);
                        $v_current = $v_lock->fetch();
                        if ($v_current['max_claims'] > 0 && $v_current['claims_count'] >= $v_current['max_claims']) {
                            throw new \Exception("Kuota voucher diskon sudah habis.");
                        }
                        
                        $pdo->prepare("INSERT INTO user_discount_claims (user_id, voucher_id) VALUES (?, ?)")
                            ->execute([$user['id'], $v_data['id']]);
                        
                        $pdo->prepare("UPDATE discount_vouchers SET claims_count = claims_count + 1 WHERE id = ?")
                            ->execute([$v_data['id']]);
                    }
                    
                    $pdo->prepare("INSERT INTO upgrade_orders (user_id,membership_id,amount,status,confirmed_at) VALUES (?,?,?,'confirmed',NOW())")
                        ->execute([$user['id'], $mid, $final_price]);
                    
                    $new_expires = date('Y-m-d H:i:s', strtotime("+{$chosen['duration_days']} days"));
                    $pdo->prepare("UPDATE users SET membership_id=?, membership_expires_at=? WHERE id=?")
                        ->execute([$mid, $new_expires, $user['id']]);
                    
                    $pdo->commit();
                    
                    $us = $pdo->prepare("SELECT * FROM users WHERE id=?"); $us->execute([$user['id']]); $user = $us->fetch();
                    $flash = 'ðŸŽ‰ Hore! Upgrade ke ' . htmlspecialchars($chosen['name']) . ' berhasil! Berlaku s/d ' . date('d M Y', strtotime($new_expires)) . ' ya.';
                    $active_membership = $chosen;
                    $can_refund = true; // just upgraded, well within 12 hours
                    
                    $msgNotif = "ðŸŽ‰ <b>MEMBER UPGRADE LEVEL</b>\n\n";
                    $msgNotif .= "ðŸ‘¤ User: <code>{$user['username']}</code>\n";
                    $msgNotif .= "ðŸ† Level Baru: <b>{$chosen['name']}</b>\n";
                    $msgNotif .= "ðŸ’° Harga: " . format_rp((float)$final_price) . "\n";
                    $msgNotif .= "ðŸ• Waktu: " . date('d M Y H:i:s');
                    send_telegram_notif($pdo, $msgNotif, [], 'log');
                } else {
                    $pdo->rollBack();
                    $flash = 'Saldo Beli kamu kurang nih. Transaksi gagal ya.'; $flashType = 'error';
                }
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $flash = 'Terjadi kesalahan: ' . $e->getMessage(); $flashType = 'error';
            }
        }
    }
}
end_post:



// Auto-rename ranks to Game/RPG theme if they don't match yet
$rankMap = [
  0 => ['name' => 'Pejuang',   'icon' => 'âš”ï¸'],
  1 => ['name' => 'Jagoan',    'icon' => 'ðŸ†'],
  2 => ['name' => 'Legenda',   'icon' => 'ðŸŒŸ'],
];
$paid_ids = array_values(array_filter($memberships, fn($m) => (float)$m['price'] > 0));
foreach ($paid_ids as $idx => $m) {
  if (isset($rankMap[$idx])) {
    $pdo->prepare("UPDATE memberships SET name=?, icon=? WHERE id=?")->execute([
      $rankMap[$idx]['name'], $rankMap[$idx]['icon'], $m['id']
    ]);
  }
}
// Reload after rename
$memberships = $pdo->query("SELECT * FROM memberships WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();

$pageTitle  = 'Upgrade Paket';
$activePage = 'upgrade';
require dirname(__DIR__) . '/partials/header.php';
?>
<style>
/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   UPGRADE PAGE â€” GAME UI (Home Match)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

/* Override global app.css background */
html body { background: #f97316 !important; background-image: none !important; }

/* â”€â”€â”€ HERO â”€â”€â”€ */
.up-hero {
  background: linear-gradient(160deg, #a855f7 0%, #7c3aed 55%, #6d28d9 100%);
  padding: 14px 14px 0; position: relative; overflow: hidden;
}
.up-hero::before {
  content:''; position:absolute; top:-60px; right:-40px;
  width:180px; height:180px; background:rgba(255,255,255,0.07);
  border-radius:50%; pointer-events:none;
}
.up-hero::after {
  content:''; position:absolute; bottom:20px; left:-30px;
  width:100px; height:100px; background:rgba(255,255,255,0.05);
  border-radius:50%; pointer-events:none;
}
.up-hero-top {
  display:flex; align-items:center; justify-content:space-between; margin-bottom:14px;
}
.up-hero-badge {
  display:inline-flex; align-items:center; gap:4px;
  background:rgba(255,255,255,0.22); border:1.5px solid rgba(255,255,255,0.4);
  border-radius:20px; padding:4px 12px; font-size:11px; font-weight:900; color:#fff;
}
.up-hero-back {
  width:34px; height:34px; display:flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,0.18); border:1.5px solid rgba(255,255,255,0.35);
  border-radius:10px; color:#fff; text-decoration:none;
}
.up-hero-title {
  font-size:26px; font-weight:900; color:#fff;
  text-shadow:0 2px 8px rgba(0,0,0,0.25); letter-spacing:-0.5px; line-height:1.1;
  margin-bottom:4px;
}
.up-hero-sub {
  font-size:12px; font-weight:700; color:rgba(255,255,255,0.8); margin-bottom:14px;
}
.up-hero-wave { display:block; width:100%; height:28px; margin-bottom:-2px; }

/* â”€â”€â”€ BODY AREA â”€â”€â”€ */
.up-body { background:#fff8f0; padding:16px 14px calc(var(--nav-h,72px) + 24px); }

/* â”€â”€â”€ SECTION HEADER â”€â”€â”€ */
.sh2 { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.sh2__title { display:flex; align-items:center; gap:6px; font-size:14px; font-weight:900; color:#0f172a; }

/* â”€â”€â”€ BASE CARD â”€â”€â”€ */
.gc {
  background:#fff; border:3px solid #0f172a; border-radius:22px;
  padding:14px; margin-bottom:14px; box-shadow:0 6px 0 #0f172a;
}
.gc--green  { border-color:#064e3b; box-shadow:0 6px 0 #064e3b; background:#f0fdf4; }
.gc--red    { border-color:#b91c1c; box-shadow:0 4px 0 #7f1d1d; background:#fef2f2; }

/* â”€â”€â”€ SALDO TILE â”€â”€â”€ */
.saldo-tile {
  background:#fff; border:3px solid #1e3a8a; border-radius:20px;
  padding:14px 12px; box-shadow:0 6px 0 #1e3a8a;
  position:relative; overflow:hidden; margin-bottom:10px;
}
.saldo-tile__deco {
  position:absolute; bottom:-12px; right:-10px; font-size:56px; opacity:0.06; pointer-events:none;
}
.saldo-tile__lbl { font-size:10px; font-weight:800; color:#64748b; margin-bottom:4px; display:flex; align-items:center; gap:4px; }
.saldo-tile__val { font-size:22px; font-weight:900; color:#0f172a; letter-spacing:-0.5px; margin-bottom:8px; }
.saldo-tile__tag {
  display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:900;
  background:#dbeafe; color:#1e40af; border:2px solid #93c5fd; padding:4px 12px; border-radius:20px;
}

/* â”€â”€â”€ QUICK ACTION BUTTONS â”€â”€â”€ */
.qa-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; }
.qa-btn {
  display:flex; align-items:center; justify-content:center; gap:8px;
  border-radius:16px; padding:13px 10px; font-size:12px; font-weight:900;
  text-decoration:none; border:3px solid; transition:transform 0.1s; cursor:pointer;
}
.qa-btn:active { transform:translateY(4px); }
.qa-btn--green { background:#dcfce7; color:#064e3b; border-color:#064e3b; box-shadow:0 5px 0 #064e3b; }
.qa-btn--pink  { background:#fce7f3; color:#9d174d; border-color:#9d174d; box-shadow:0 5px 0 #9d174d; }
.qa-btn--green:active { box-shadow:0 1px 0 #064e3b; }
.qa-btn--pink:active  { box-shadow:0 1px 0 #9d174d; }

/* â”€â”€â”€ LEVEL CARDS â”€â”€â”€ */
.lvl {
  background:#fff; border:3px solid #0f172a; border-radius:24px;
  box-shadow:0 7px 0 #0f172a; padding:18px 16px; margin-bottom:16px;
  position:relative; overflow:visible; cursor:pointer; transition:transform 0.12s;
}
.lvl:active { transform:translateY(5px); box-shadow:0 2px 0 #0f172a; }

.lvl--legend { background:linear-gradient(135deg,#fef9c3,#fef08a); border-color:#d97706; box-shadow:0 7px 0 #92400e; }
.lvl--legend:active { box-shadow:0 2px 0 #92400e; }

.lvl--jagoan { background:#fff; border-color:#0ea5e9; box-shadow:0 7px 0 #0369a1; }
.lvl--jagoan:active { box-shadow:0 2px 0 #0369a1; }

.lvl--pejuang { background:#f8fafc; border-color:#475569; box-shadow:0 7px 0 #1e293b; }
.lvl--pejuang:active { box-shadow:0 2px 0 #1e293b; }

/* Badge sticker on card */
.lvl-sticker {
  position:absolute; top:-13px; right:14px;
  font-size:10px; font-weight:900; padding:5px 12px; border-radius:20px;
  border:2.5px solid #fff; z-index:6; white-space:nowrap;
}
.lvl-sticker--red  { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; box-shadow:0 3px 0 #7f1d1d; transform:rotate(2deg); }
.lvl-sticker--org  { background:linear-gradient(135deg,#f97316,#ea580c); color:#fff; box-shadow:0 3px 0 #9a3412; transform:rotate(-2deg); }

/* Card header row */
.lvl-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; }
.lvl-icon {
  width:52px; height:52px; border-radius:16px; border:2.5px solid #0f172a;
  display:flex; align-items:center; justify-content:center; font-size:26px;
  box-shadow:0 4px 0 #0f172a; flex-shrink:0; background:#fff;
}
.lvl-meta { padding-left:12px; flex:1; }
.lvl-name { font-size:19px; font-weight:900; color:#0f172a; line-height:1.1; }
.lvl-dur  { font-size:11px; font-weight:700; color:#64748b; margin-top:3px; display:flex; align-items:center; gap:4px; }
.lvl-price-block { text-align:right; }
.lvl-price-old { font-size:11px; font-weight:800; color:#94a3b8; text-decoration:line-through; margin-bottom:1px; }
.lvl-price { font-size:22px; font-weight:900; color:#0f172a; letter-spacing:-0.5px; line-height:1.1; }
.lvl--jagoan .lvl-price { color:#0284c7; }
.lvl--legend .lvl-price { color:#92400e; }

/* Spec grid */
.lvl-specs { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:14px; }
.lvl-spec {
  background:rgba(255,255,255,0.65); border:2px solid rgba(15,23,42,0.35);
  border-radius:12px; padding:8px 10px; font-size:11px; font-weight:800;
  color:#0f172a; display:flex; align-items:center; gap:6px;
  box-shadow:0 2px 0 rgba(15,23,42,0.2);
}
.lvl-spec--full { grid-column:1/-1; }
.lvl--legend .lvl-spec { background:rgba(255,255,255,0.5); border-color:rgba(146,64,14,0.3); }

/* CTA button */
.lvl-cta {
  display:block; width:100%; padding:13px; border-radius:14px;
  font-size:14px; font-weight:900; text-align:center; cursor:pointer;
  border:3px solid rgba(255,255,255,0.7);
  text-shadow:0 1px 2px rgba(0,0,0,0.2); transition:transform 0.1s;
}
.lvl-cta:active { transform:translateY(3px); box-shadow:none !important; }
.lvl-cta--legend  { background:linear-gradient(135deg,#a855f7,#7c3aed); color:#fff; box-shadow:0 5px 0 #5b21b6; }
.lvl-cta--jagoan  { background:linear-gradient(135deg,#0ea5e9,#0284c7); color:#fff; box-shadow:0 5px 0 #075985; }
.lvl-cta--pejuang { background:linear-gradient(135deg,#475569,#334155); color:#fff; box-shadow:0 5px 0 #0f172a; }
.lvl-cta--disabled { background:#cbd5e1 !important; border-color:#e2e8f0 !important; box-shadow:0 3px 0 #94a3b8 !important; color:#64748b; text-shadow:none; cursor:not-allowed; }

/* â”€â”€â”€ INFO / STEP CARD â”€â”€â”€ */
.step-row { display:flex; align-items:flex-start; gap:12px; margin-bottom:12px; }
.step-num {
  width:30px; height:30px; border-radius:10px; border:2.5px solid #0f172a;
  box-shadow:0 3px 0 #0f172a; display:flex; align-items:center; justify-content:center;
  font-size:13px; font-weight:900; color:#0f172a; flex-shrink:0;
}
.step-txt { font-size:12px; font-weight:800; color:#334155; line-height:1.45; padding-top:5px; }

.ben-grid { display:flex; flex-wrap:wrap; gap:8px; }
.ben-pill {
  font-size:11px; font-weight:900; color:#0f172a; padding:8px 12px;
  border-radius:14px; border:2.5px solid #0f172a; box-shadow:0 3px 0 #0f172a;
}

/* â”€â”€â”€ MODAL â”€â”€â”€ */
.cg-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.6); align-items:center; justify-content:center; backdrop-filter:blur(3px); padding:20px; }
.cg-modal-card { background:#fff; border-radius:24px; border:3px solid #0f172a; width:100%; max-width:380px; box-shadow:0 8px 0 #0f172a; animation:popIn .3s cubic-bezier(.175,.885,.32,1.275); padding:24px; }
@keyframes popIn { 0%{transform:scale(0.8);opacity:0} 100%{transform:scale(1);opacity:1} }
.cg-mc-hdr { font-size:18px; font-weight:900; color:#0f172a; margin-bottom:6px; display:flex; align-items:center; gap:8px; }
.cg-mc-sub { font-size:12px; font-weight:800; color:#64748b; margin-bottom:16px; }
.cg-mc-box { background:#f0f9ff; border:2.5px solid #bae6fd; border-radius:16px; padding:16px; margin-bottom:16px; }
.cg-mc-lbl { font-size:11px; font-weight:800; color:#0284c7; text-transform:uppercase; margin-bottom:4px; }
.cg-mc-val { font-size:20px; font-weight:900; color:#0c4a6e; letter-spacing:-0.5px; }
.cg-mc-price { font-size:14px; font-weight:900; color:#0f172a; margin-top:8px; }
.cg-mc-discount { font-size:14px; font-weight:900; color:#ef4444; margin-top:4px; display:none; }
.cg-mc-total { font-size:16px; font-weight:900; color:#10b981; margin-top:8px; border-top:2px dashed #bae6fd; padding-top:8px; display:none; }
.cg-mc-dur { font-size:11px; font-weight:800; color:#64748b; margin-top:8px; }
.cg-btn-row { display:flex; gap:10px; }
.cg-btn { flex:1; border:2.5px solid #0f172a; border-radius:12px; font-size:13px; font-weight:900; padding:12px; box-shadow:0 4px 0 #0f172a; cursor:pointer; text-align:center; }
.cg-btn:active { transform:translateY(3px); box-shadow:0 1px 0 #0f172a; }
.cg-btn--cancel  { background:#f1f5f9; color:#475569; }
.cg-btn--confirm { background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-color:#fff; box-shadow:0 4px 0 #047857; text-shadow:0 1px 1px rgba(0,0,0,0.2); }
</style>

<!-- â•â•â•â•â•â• HERO â•â•â•â•â•â• -->
<div class="up-hero">
  <div class="up-hero-top">
    <span class="up-hero-badge"><i class="ph-bold ph-crown"></i> Level Up</span>
    <a href="/home" class="up-hero-back"><i class="ph-bold ph-arrow-left" style="font-size:16px;"></i></a>
  </div>
  <div class="up-hero-title">âš”ï¸ Pilih Rankmu<br>&amp; Gas Cuan!</div>
  <div class="up-hero-sub">Limit makin besar Â· Cuan makin deras Â· Upgrade sekarang</div>
  <svg class="up-hero-wave" viewBox="0 0 375 28" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M0 28 C80 6, 220 24, 375 8 L375 28 Z" fill="#fff8f0"/>
  </svg>
</div>

<!-- â•â•â•â•â•â• BODY â•â•â•â•â•â• -->
<div class="up-body">

  <?php if ($flash): ?>
  <div class="gc <?= ($flashType === 'error') ? 'gc--red' : 'gc--green' ?>" style="display:flex;align-items:center;gap:10px;padding:12px 14px;margin-bottom:14px;">
    <i class="ph-fill ph-<?= ($flashType === 'error') ? 'warning-circle' : 'check-circle' ?>" style="font-size:20px;flex-shrink:0;color:<?= ($flashType === 'error') ? '#ef4444' : '#16a34a' ?>;"></i>
    <div style="font-size:12px;font-weight:900;color:<?= ($flashType === 'error') ? '#991b1b' : '#065f46' ?>;"><?= htmlspecialchars($flash) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($active_membership): ?>
  <!-- PAKET AKTIF -->
  <div class="gc gc--green" style="margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:900;color:#065f46;margin-bottom:8px;">
      <i class="ph-fill ph-shield-star" style="color:#16a34a;font-size:16px;"></i> Rank Aktif Sekarang
    </div>
    <div style="font-size:20px;font-weight:900;color:#0f172a;margin-bottom:8px;"><?= htmlspecialchars($active_membership['icon'] ?? '') ?> <?= htmlspecialchars($active_membership['name']) ?></div>
    <div style="display:inline-flex;align-items:center;gap:6px;background:#dcfce7;border:2px solid #16a34a;border-radius:12px;padding:5px 12px;font-size:11px;font-weight:900;color:#14532d;box-shadow:0 2px 0 #14532d;margin-bottom:<?= $can_refund ? '12' : '0' ?>px;">
      <i class="ph-bold ph-video-camera"></i> <?= $active_membership['watch_limit'] ?>Ã— /hari &bull; s/d <?= date('d M Y', strtotime($user['membership_expires_at'])) ?>
    </div>
    <?php if ($can_refund): ?>
    <button type="button" onclick="document.getElementById('refund-modal').style.display='flex'" style="width:100%;background:#fff;border:2.5px solid #ef4444;color:#ef4444;border-radius:12px;padding:10px;font-size:12px;font-weight:900;box-shadow:0 3px 0 #ef4444;display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;">
      <i class="ph-bold ph-arrow-u-up-left"></i> Minta Refund
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- â”€â”€ SALDO BELI â”€â”€ -->
  <div class="sh2"><div class="sh2__title">ðŸ’³ Saldo Beli</div></div>
  <div class="saldo-tile">
    <div class="saldo-tile__deco">ðŸ’°</div>
    <div class="saldo-tile__lbl"><i class="ph-fill ph-wallet" style="color:#1e40af;font-size:14px;"></i> Saldo Beli (Khusus Upgrade)</div>
    <div class="saldo-tile__val"><?= format_rp((float)$user['balance_dep']) ?></div>
    <div class="saldo-tile__tag"><i class="ph-bold ph-info"></i> Hanya untuk beli paket, tidak bisa ditarik</div>
  </div>
  <div class="qa-grid">
    <a href="/deposit" class="qa-btn qa-btn--green">
      <i class="ph-bold ph-plus-circle" style="font-size:18px;"></i> Topup Saldo
    </a>
    <a href="/checkin" class="qa-btn qa-btn--pink">
      <i class="ph-bold ph-calendar-check" style="font-size:18px;"></i> Check-in Harian
    </a>
  </div>

  <!-- â”€â”€ PILIH RANK â”€â”€ -->
  <div class="sh2" style="margin-bottom:16px;"><div class="sh2__title">ðŸ”¥ Pilih Rankmu</div></div>

  <form method="POST" id="upgrade-form">
    <?= csrf_field() ?>
    <input type="hidden" name="membership_id" id="chosen-id" value="">
    <input type="hidden" name="voucher_code" id="applied-voucher-code" value="">

    <?php
    $paid   = array_values(array_filter($memberships, fn($m) => (float)$m['price'] > 0));
    // Sort by price desc so Legenda first
    usort($paid, fn($a,$b) => (float)$b['price'] <=> (float)$a['price']);
    $legend  = $paid[0] ?? null;
    $jagoan  = $paid[1] ?? null;
    $pejuang = $paid[2] ?? null;
    ?>

    <?php if ($legend): $m = $legend; $can_afford = (float)$user['balance_dep'] >= (float)$m['price']; ?>
    <div class="lvl lvl--legend" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)">
      <div class="lvl-sticker lvl-sticker--red">ðŸ‘‘ BEST DEAL!</div>
      <div class="lvl-head">
        <div class="lvl-icon" style="background:#fef08a;"><?= htmlspecialchars($m['icon'] ?: 'ðŸŒŸ') ?></div>
        <div class="lvl-meta">
          <div class="lvl-name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="lvl-dur"><i class="ph-bold ph-hourglass"></i> <?= $m['duration_days'] ?> Hari</div>
        </div>
        <div class="lvl-price-block">
          <div class="lvl-price-old"><?= format_rp((float)$m['original_price']) ?></div>
          <div class="lvl-price"><?= format_rp((float)$m['price']) ?></div>
        </div>
      </div>
      <div class="lvl-specs">
        <div class="lvl-spec"><i class="ph-bold ph-video-camera" style="color:#7c3aed;"></i> <?= $m['watch_limit'] ?> Video/hari</div>
        <div class="lvl-spec"><i class="ph-bold ph-trend-up" style="color:#10b981;"></i> WD Maks Bebas</div>
        <div class="lvl-spec lvl-spec--full"><i class="ph-bold ph-star" style="color:#d97706;"></i> Semua fitur premium + prioritas admin</div>
      </div>
      <button type="button" class="lvl-cta lvl-cta--legend <?= !$can_afford ? 'lvl-cta--disabled' : '' ?>">
        <?= $can_afford ? 'ðŸŒŸ Gas Jadi Legenda!' : 'ðŸ’³ Saldo Kurang â€“ Topup Dulu' ?>
      </button>
    </div>
    <?php endif; ?>

    <?php if ($jagoan): $m = $jagoan; $can_afford = (float)$user['balance_dep'] >= (float)$m['price']; ?>
    <div class="lvl lvl--jagoan" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)">
      <div class="lvl-sticker lvl-sticker--org">ðŸ”¥ POPULER</div>
      <div class="lvl-head">
        <div class="lvl-icon" style="background:#e0f2fe;"><?= htmlspecialchars($m['icon'] ?: 'ðŸ†') ?></div>
        <div class="lvl-meta">
          <div class="lvl-name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="lvl-dur"><i class="ph-bold ph-hourglass"></i> <?= $m['duration_days'] ?> Hari</div>
        </div>
        <div class="lvl-price-block">
          <div class="lvl-price-old"><?= format_rp((float)$m['original_price']) ?></div>
          <div class="lvl-price"><?= format_rp((float)$m['price']) ?></div>
        </div>
      </div>
      <div class="lvl-specs">
        <div class="lvl-spec"><i class="ph-bold ph-video-camera" style="color:#0ea5e9;"></i> <?= $m['watch_limit'] ?> Video/hari</div>
        <div class="lvl-spec"><i class="ph-bold ph-arrow-circle-down" style="color:#10b981;"></i> Min. WD rendah</div>
        <div class="lvl-spec lvl-spec--full"><i class="ph-bold ph-lightning" style="color:#f59e0b;"></i> Paling banyak dipilih â€“ nilai terbaik!</div>
      </div>
      <button type="button" class="lvl-cta lvl-cta--jagoan <?= !$can_afford ? 'lvl-cta--disabled' : '' ?>">
        <?= $can_afford ? 'ðŸ† Jadilah Jagoan!' : 'ðŸ’³ Saldo Kurang â€“ Topup Dulu' ?>
      </button>
    </div>
    <?php endif; ?>

    <?php if ($pejuang): $m = $pejuang; $can_afford = (float)$user['balance_dep'] >= (float)$m['price']; ?>
    <div class="lvl lvl--pejuang" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)">
      <div class="lvl-head">
        <div class="lvl-icon" style="background:#f1f5f9;"><?= htmlspecialchars($m['icon'] ?: 'âš”ï¸') ?></div>
        <div class="lvl-meta">
          <div class="lvl-name"><?= htmlspecialchars($m['name']) ?></div>
          <div class="lvl-dur"><i class="ph-bold ph-hourglass"></i> <?= $m['duration_days'] ?> Hari</div>
        </div>
        <div class="lvl-price-block">
          <div class="lvl-price-old"><?= format_rp((float)$m['original_price']) ?></div>
          <div class="lvl-price"><?= format_rp((float)$m['price']) ?></div>
        </div>
      </div>
      <div class="lvl-specs">
        <div class="lvl-spec"><i class="ph-bold ph-video-camera" style="color:#64748b;"></i> <?= $m['watch_limit'] ?> Video/hari</div>
        <div class="lvl-spec"><i class="ph-bold ph-coins" style="color:#64748b;"></i> Mulai cuan instan</div>
      </div>
      <button type="button" class="lvl-cta lvl-cta--pejuang <?= !$can_afford ? 'lvl-cta--disabled' : '' ?>">
        <?= $can_afford ? 'âš”ï¸ Mulai Jadi Pejuang!' : 'ðŸ’³ Saldo Kurang â€“ Topup Dulu' ?>
      </button>
    </div>
    <?php endif; ?>

  </form>

  <!-- â”€â”€ INFO CARD â”€â”€ -->
  <div class="gc" style="margin-top:4px;margin-bottom:24px;">
    <div style="font-size:14px;font-weight:900;color:#0f172a;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
      <i class="ph-bold ph-lightbulb" style="color:#f59e0b;font-size:18px;"></i> Cara Upgrade
    </div>
    <div class="step-row">
      <div class="step-num" style="background:#a7f3d0;">1</div>
      <div class="step-txt"><strong>Topup Saldo Beli</strong> via menu Deposit â€” digunakan khusus untuk beli rank.</div>
    </div>
    <div class="step-row">
      <div class="step-num" style="background:#fde047;">2</div>
      <div class="step-txt"><strong>Pilih rank</strong> di atas dan klik tombol upgrade.</div>
    </div>
    <div class="step-row" style="margin-bottom:16px;">
      <div class="step-num" style="background:#f9a8d4;">3</div>
      <div class="step-txt"><strong>Konfirmasi</strong> di pop-up â€” saldo terpotong &amp; rank langsung aktif!</div>
    </div>

    <div style="border-top:2.5px dashed #e2e8f0;padding-top:14px;margin-bottom:14px;">
      <div style="font-size:13px;font-weight:900;color:#0f172a;margin-bottom:10px;display:flex;align-items:center;gap:5px;">
        <i class="ph-bold ph-check-circle" style="color:#10b981;font-size:16px;"></i> Keuntungan Naik Rank
      </div>
      <div class="ben-grid">
        <span class="ben-pill" style="background:#e0f2fe;">ðŸ“¹ Nonton Lebih Banyak</span>
        <span class="ben-pill" style="background:#dcfce7;">ðŸ’¸ Min. WD Makin Rendah</span>
        <span class="ben-pill" style="background:#fef08a;">ðŸ“ˆ Cuan Makin Deras</span>
        <span class="ben-pill" style="background:#fce7f3;">ðŸ’° Max WD Meroket</span>
      </div>
    </div>

    <div style="background:#f8fafc;border:2px solid #cbd5e1;border-radius:14px;padding:12px;">
      <div style="font-size:12px;font-weight:900;color:#0f172a;margin-bottom:6px;display:flex;align-items:center;gap:4px;"><i class="ph-bold ph-warning" style="color:#f59e0b;"></i> Catatan</div>
      <div style="font-size:11px;font-weight:800;color:#475569;line-height:1.5;">
        â€¢ Rank baru menggantikan rank aktif (sisa masa aktif hangus).<br>
        â€¢ Saldo Beli tidak bisa ditarik ke rekening.
      </div>
    </div>
  </div>

</div>

<!-- â•â•â•â•â•â• CONFIRMATION MODAL â•â•â•â•â•â• -->
<div id="upgrade-modal" class="cg-modal">
  <div class="cg-modal-card">
    <div class="cg-mc-hdr">ðŸš€ Konfirmasi Upgrade</div>
    <div class="cg-mc-sub">Pastikan kamu yakin sebelum memproses!</div>
    <div class="cg-mc-box">
      <div class="cg-mc-lbl">Rank Dipilih</div>
      <div class="cg-mc-val" id="modal-name">â€”</div>
      <div class="cg-mc-price" id="price-row">Harga: <span id="modal-price">â€”</span></div>
      <div class="cg-mc-discount" id="discount-row">Diskon: -<span id="modal-discount">â€”</span> (<span id="modal-pct">â€”</span>)</div>
      <div class="cg-mc-total" id="final-price-row">Total Bayar: <span id="modal-final-price">â€”</span></div>
      <div class="cg-mc-dur">Berlaku <span id="modal-days">â€”</span> hari setelah aktivasi</div>
    </div>
    <div style="margin-bottom:16px;">
      <button type="button" id="toggle-voucher-btn" onclick="toggleVoucher()" style="background:none;border:none;color:#0ea5e9;font-weight:900;font-size:12px;cursor:pointer;padding:0;display:flex;align-items:center;gap:4px;"><i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?</button>
      <div id="voucher-box" style="display:none;margin-top:8px;gap:8px;">
        <input type="text" id="voucher-input" placeholder="KODE VOUCHER" style="flex:1;border:2.5px solid #cbd5e1;border-radius:10px;padding:8px 12px;font-weight:900;text-transform:uppercase;font-size:12px;outline:none;">
        <button type="button" onclick="applyVoucher()" style="background:linear-gradient(135deg,#fde047,#f59e0b);color:#78350f;border:2px solid #fff;border-radius:10px;padding:8px 14px;font-weight:900;font-size:12px;cursor:pointer;box-shadow:0 3px 0 #d97706;">Gunakan</button>
      </div>
      <div id="voucher-msg" style="font-size:11px;font-weight:800;margin-top:6px;display:none;"></div>
    </div>
    <div id="modal-warn" style="display:none;font-size:11px;color:#ef4444;font-weight:800;margin-bottom:16px;background:#fef2f2;border:2px solid #fca5a5;border-radius:10px;padding:10px 12px;"></div>
    <div style="font-size:11px;color:#94a3b8;font-weight:800;margin-bottom:16px;text-align:center;">âš ï¸ Saldo Beli otomatis terpotong. Aksi ini permanen.</div>
    <div class="cg-btn-row">
      <button type="button" class="cg-btn cg-btn--cancel" onclick="closeConfirm()">Batal</button>
      <button type="button" id="modal-confirm-btn" class="cg-btn cg-btn--confirm" onclick="submitUpgrade()">âœ… YA, GAS!</button>
    </div>
  </div>
</div>

<!-- â•â•â•â•â•â• REFUND MODAL â•â•â•â•â•â• -->
<div id="refund-modal" class="cg-modal">
  <div class="cg-modal-card" style="border-color:#b91c1c;box-shadow:0 8px 0 #b91c1c;">
    <div class="cg-mc-hdr" style="color:#ef4444;">âš ï¸ Minta Refund?</div>
    <div class="cg-mc-sub">Kamu yakin ingin refund rank aktifmu?</div>
    <div style="background:#fef2f2;border:2.5px solid #fca5a5;border-radius:14px;padding:12px;margin-bottom:16px;font-size:11px;font-weight:800;color:#991b1b;line-height:1.5;">
      Saldo dikembalikan ke <strong>Saldo Beli</strong> dengan potongan admin (jika ada). Rank kamu akan hangus!
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="refund_level">
      <div class="cg-btn-row">
        <button type="button" class="cg-btn cg-btn--cancel" onclick="document.getElementById('refund-modal').style.display='none'">Gak Jadi</button>
        <button type="submit" class="cg-btn" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border-color:#fff;box-shadow:0 4px 0 #991b1b;">Refund Sekarang</button>
      </div>
    </form>
  </div>
</div>

<script>
const userBal = <?= (float)$user['balance_dep'] ?>;
let currentPrice = 0;

function checkAffordability(p) {
  const btn = document.getElementById('modal-confirm-btn');
  const warn = document.getElementById('modal-warn');
  if (userBal < p) {
    btn.disabled = true; btn.style.opacity = '0.5'; btn.style.cursor = 'not-allowed';
    btn.innerText = 'ðŸ’³ Saldo Kurang';
    warn.style.display = 'block';
    warn.innerHTML = 'âš ï¸ Kurang <strong>Rp ' + (p - userBal).toLocaleString('id-ID') + '</strong>. Silakan deposit dulu.';
  } else {
    btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer';
    btn.innerText = 'âœ… YA, GAS!';
    warn.style.display = 'none';
  }
}
function openConfirm(id, name, price, days) {
  currentPrice = price;
  document.getElementById('chosen-id').value = id;
  document.getElementById('modal-name').textContent = name;
  document.getElementById('modal-price').textContent = 'Rp ' + price.toLocaleString('id-ID');
  document.getElementById('modal-days').textContent = days;
  document.getElementById('applied-voucher-code').value = '';
  document.getElementById('discount-row').style.display = 'none';
  document.getElementById('final-price-row').style.display = 'none';
  const vi = document.getElementById('voucher-input'); if(vi) vi.value = '';
  const vm = document.getElementById('voucher-msg'); if(vm) vm.style.display = 'none';
  const vb = document.getElementById('voucher-box'); if(vb) vb.style.display = 'none';
  const tb = document.getElementById('toggle-voucher-btn'); if(tb){ tb.style.display='flex'; tb.innerHTML='<i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?'; }
  checkAffordability(price);
  document.getElementById('upgrade-modal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeConfirm() {
  document.getElementById('upgrade-modal').style.display = 'none';
  document.body.style.overflow = '';
}
function toggleVoucher() {
  const vb = document.getElementById('voucher-box');
  const tb = document.getElementById('toggle-voucher-btn');
  if(vb.style.display === 'none') { vb.style.display='flex'; tb.innerHTML='âœ– Tutup Voucher'; }
  else { vb.style.display='none'; tb.innerHTML='<i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?'; }
}
function applyVoucher() {
  const code = document.getElementById('voucher-input').value.toUpperCase().trim();
  const mid = document.getElementById('chosen-id').value;
  const msg = document.getElementById('voucher-msg');
  if(!code){ msg.style.color='#ef4444'; msg.innerText='âš ï¸ Masukkan kode voucher.'; msg.style.display='block'; return; }
  msg.style.color='#94a3b8'; msg.innerText='â³ Mengecek...'; msg.style.display='block';
  fetch('', {
    method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=check_voucher&code='+encodeURIComponent(code)+'&membership_id='+encodeURIComponent(mid)+'&_csrf='+encodeURIComponent(document.querySelector('input[name="_csrf"]')?.value||'')
  })
  .then(r=>r.json())
  .then(res=>{
    if(res.error){ msg.style.color='#ef4444'; msg.innerText='âŒ '+res.error; msg.style.display='block';
      document.getElementById('applied-voucher-code').value='';
      document.getElementById('discount-row').style.display='none';
      document.getElementById('final-price-row').style.display='none';
      checkAffordability(currentPrice);
    } else {
      msg.style.color='#10b981'; msg.innerText='âœ… Diskon '+res.discount_text+' aktif!'; msg.style.display='block';
      document.getElementById('applied-voucher-code').value=code;
      document.getElementById('modal-discount').textContent=res.discount_amount_formatted;
      document.getElementById('modal-pct').textContent=res.discount_text;
      document.getElementById('modal-final-price').textContent=res.final_price_formatted;
      document.getElementById('discount-row').style.display='block';
      document.getElementById('final-price-row').style.display='block';
      document.getElementById('voucher-box').style.display='none';
      document.getElementById('toggle-voucher-btn').style.display='none';
      checkAffordability(res.final_price);
    }
  })
  .catch(()=>{ msg.style.color='#ef4444'; msg.innerText='âŒ Gagal cek voucher.'; msg.style.display='block'; });
}
function submitUpgrade() {
  const btn = document.getElementById('modal-confirm-btn');
  btn.disabled = true; btn.textContent = 'â³ Memproses...';
  document.getElementById('upgrade-form').submit();
}
document.getElementById('upgrade-modal').addEventListener('click', e=>{ if(e.target===this||e.target.id==='upgrade-modal') closeConfirm(); });
document.getElementById('refund-modal').addEventListener('click', e=>{ if(e.target.id==='refund-modal') e.target.style.display='none'; });
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

