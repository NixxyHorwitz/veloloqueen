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
            $flash = '❌ Permintaan pengembalian dana kamu sebelumnya masih dalam proses verifikasi otomatis.'; $flashType = 'error';
        } else {
            $s = $pdo->prepare("SELECT m.id as membership_id, m.name, m.price, u.refund_cut_percent, u.is_refund_enabled FROM users u LEFT JOIN memberships m ON u.membership_id = m.id WHERE u.id=?");
            $s->execute([$user['id']]);
            $uInfo = $s->fetch();
            $mName = $uInfo['name'] ?? null;
            
            if (!$mName) {
                $flash = '❌ Kamu tidak memiliki paket aktif.'; $flashType = 'error';
            } elseif (!$uInfo['is_refund_enabled']) {
                $flash = '❌ Akses refund kamu telah dinonaktifkan.'; $flashType = 'error';
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
                
                $msg  = "💰 <b>REQUEST REFUND LEVEL</b>\n\n";
                $msg .= "👤 User: <code>{$user['username']}</code>\n";
                $msg .= "🏆 Level: <b>{$mName}</b>\n";
                $msg .= "💵 Harga Awal: <b>" . format_rp($basePrice) . "</b>\n";
                $msg .= "✂️ Setelah Dipotong ({$pct}%): <b>" . format_rp($afterCut) . "</b>\n\n";
                $msg .= "⚠️ <i>Refund ini akan membatalkan level user dan mengembalikan saldo dengan potongan {$pct}% (jika di-Approve).</i>\n";
                $kb = [
                    [['text'=>'✅ Approve Refund', 'callback_data'=>'req_approve_'.$req_id], ['text'=>'❌ Reject', 'callback_data'=>'req_reject_'.$req_id]],
                    [['text'=>"⚙️ Ubah Potongan ({$pct}%)", 'callback_data'=>'edit_refcut_'.$user['id']], ['text'=>'🔒 Cabut Akses Refund', 'callback_data'=>'toggle_ref_'.$user['id']]]
                ];
                send_telegram_notif($pdo, $msg, $kb, 'permintaan');
                
                $flash = '✅ Permintaan pengembalian dana kamu telah masuk dan sedang diverifikasi oleh sistem secara otomatis.';
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
                    $flash = '🎉 Hore! Upgrade ke ' . htmlspecialchars($chosen['name']) . ' berhasil! Berlaku s/d ' . date('d M Y', strtotime($new_expires)) . ' ya.';
                    $active_membership = $chosen;
                    $can_refund = true; // just upgraded, well within 12 hours
                    
                    $msgNotif = "🎉 <b>MEMBER UPGRADE LEVEL</b>\n\n";
                    $msgNotif .= "👤 User: <code>{$user['username']}</code>\n";
                    $msgNotif .= "🏆 Level Baru: <b>{$chosen['name']}</b>\n";
                    $msgNotif .= "💰 Harga: " . format_rp((float)$final_price) . "\n";
                    $msgNotif .= "🕐 Waktu: " . date('d M Y H:i:s');
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

$pageTitle  = 'Upgrade Paket — Meloton';
$activePage = 'upgrade';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   UPGRADE PAGE — TRUE CASUAL GAME STYLE
   ══════════════════════════════════════════════ */
body { background: #fff8f0 !important; }
.up-page { padding: 0 0 20px; }

/* ══ BENTO GRID ══ */
.bento-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  grid-template-rows: auto auto auto;
  gap: 8px;
  margin-bottom: 16px;
}
/* Big item: spans 2 cols × 2 rows */
.bento-big {
  grid-column: span 2;
  grid-row: span 2;
  border-radius: 22px;
  text-decoration: none;
  display: flex; flex-direction: column;
  align-items: flex-start; justify-content: flex-end;
  padding: 14px;
  min-height: 120px;
  position: relative;
  border: 3px solid rgba(255,255,255,0.25);
  box-shadow: 0 6px 0 rgba(0,0,0,0.2);
  transition: transform 0.1s;
}
.bento-big:active { transform: translateY(4px); box-shadow: none !important; }

/* Small item: 1 col × 1 row */
.bento-sm {
  border-radius: 18px;
  text-decoration: none;
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 5px;
  padding: 10px 4px;
  border: 2.5px solid rgba(255,255,255,0.22);
  box-shadow: 0 4px 0 rgba(0,0,0,0.18);
  transition: transform 0.1s;
  min-height: 76px;
}
.bento-sm:active { transform: translateY(3px); box-shadow: none !important; }
.bento-sm i { font-size: 24px; color: #fff; }
.bento-sm__label {
  font-size: 11px; font-weight: 900; color: #fff;
  text-align: center; line-height: 1.2;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
  box-shadow: 0 6px 0 rgba(0,0,0,0.2);
  transition: transform 0.1s;
}
.bento-big:active { transform: translateY(4px); box-shadow: none !important; }

/* Wide item: spans 2 cols */
.bento-wide {
  grid-column: span 2;
  border-radius: 18px;
  text-decoration: none;
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px;
  border: 2.5px solid rgba(255,255,255,0.22);
  box-shadow: 0 4px 0 rgba(0,0,0,0.18);
  transition: transform 0.1s;
}
.bento-wide:active { transform: translateY(3px); box-shadow: none !important; }
.bento-wide__txt { }
.bento-wide__label { font-size: 12px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.bento-wide__sub { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.75); }



/* Modal Override */
.cg-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.6); align-items:center; justify-content:center; backdrop-filter:blur(3px); padding:20px; }
.cg-modal-card { background:#fff; border-radius:24px; border:3px solid #0f172a; width:100%; max-width:380px; box-shadow:0 8px 0 #0f172a; animation:popIn .3s cubic-bezier(.175,.885,.32,1.275); padding:24px; position:relative; overflow:hidden; }
@keyframes popIn { 0% { transform:scale(0.8); opacity:0; } 100% { transform:scale(1); opacity:1; } }

.cg-mc-hdr { font-size: 18px; font-weight: 900; color: #0f172a; margin-bottom: 6px; display:flex; align-items:center; gap:8px; }
.cg-mc-sub { font-size: 12px; font-weight: 800; color: #64748b; margin-bottom: 16px; }

.cg-mc-box { background: #f0f9ff; border: 2.5px solid #bae6fd; border-radius: 16px; padding: 16px; margin-bottom: 16px; }
.cg-mc-lbl { font-size: 11px; font-weight: 800; color: #0284c7; text-transform: uppercase; margin-bottom: 4px; }
.cg-mc-val { font-size: 20px; font-weight: 900; color: #0c4a6e; letter-spacing: -0.5px; }
.cg-mc-price { font-size: 14px; font-weight: 900; color: #0f172a; margin-top: 8px; }
.cg-mc-discount { font-size: 14px; font-weight: 900; color: #ef4444; margin-top: 4px; display: none; }
.cg-mc-total { font-size: 16px; font-weight: 900; color: #10b981; margin-top: 8px; border-top: 2px dashed #bae6fd; padding-top: 8px; display: none; }
.cg-mc-dur { font-size: 11px; font-weight: 800; color: #64748b; margin-top: 8px; }

.cg-btn-row { display: flex; gap: 10px; }
.cg-btn { flex: 1; border: 2.5px solid #0f172a; border-radius: 12px; font-size: 13px; font-weight: 900; padding: 12px; box-shadow: 0 4px 0 #0f172a; cursor: pointer; text-align:center; }
.cg-btn:active { transform: translateY(3px); box-shadow: 0 1px 0 #0f172a; }
.cg-btn--cancel { background: #f1f5f9; color: #475569; }
.cg-btn--confirm { background: linear-gradient(135deg, #10b981, #059669); color: #fff; border-color: #fff; box-shadow: 0 4px 0 #047857; text-shadow:0 1px 1px rgba(0,0,0,0.2); }
</style>

<div class="up-page">

  <!-- HERO BANNER (Game UI Style) -->
  <div class="cg-card" style="background:linear-gradient(135deg, #0ea5e9, #0284c7); border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; border-radius:24px; padding:20px; text-align:center; position:relative; overflow:hidden; margin-bottom:16px; margin-top:4px;">
    <i class="ph-fill ph-star" style="position:absolute; top:10px; right:20px; color:#fde047; font-size:40px; opacity:0.8; transform:rotate(15deg);"></i>
    <div style="font-size:12px; font-weight:900; color:#e0f2fe; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; text-shadow:0 1px 2px rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:center; gap:6px;"><i class="ph-bold ph-crown"></i> Upgrade Paket</div>
    <div style="font-size:22px; font-weight:900; color:#fef08a; text-shadow:0 2px 4px rgba(0,0,0,0.4); letter-spacing:-0.5px; line-height:1.2;">Tonton Lebih Banyak,<br>Cuan Tanpa Batas!</div>
  </div>

  <?php if ($flash): ?>
  <div class="cg-card" style="background:#fef2f2; border-radius:16px; border:3px solid #0f172a; box-shadow:0 4px 0 #0f172a; padding:14px; color:#991b1b; font-weight:900; font-size:12px; margin-bottom:16px; display:flex; align-items:center; gap:8px;">
    <i class="ph-fill ph-warning-circle" style="font-size:20px; color:#ef4444;"></i>
    <div><?= htmlspecialchars($flash) ?></div>
  </div>
  <?php endif; ?>

  <?php if ($active_membership): ?>
  <!-- ACTIVE PACKAGE -->
  <div class="cg-card" style="background:#e0f2fe; border-radius:20px; border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; padding:16px; margin-bottom:16px;">
    <div style="font-size:13px; font-weight:900; color:#0369a1; margin-bottom:4px; display:flex; align-items:center; gap:6px;"><i class="ph-fill ph-star" style="color:#0ea5e9;"></i> Paket Aktif Saat Ini</div>
    <div style="font-size:20px; font-weight:900; color:#0f172a; letter-spacing:-0.5px; margin-bottom:8px;"><?= htmlspecialchars($active_membership['name']) ?></div>
    
    <div style="font-size:11px; font-weight:800; color:#0f172a; margin-bottom:16px; background:rgba(255,255,255,0.7); padding:8px 12px; border-radius:12px; border:2.5px solid #0f172a; box-shadow:0 2px 0 #0f172a; display:inline-block;">
      Limit <strong><?= $active_membership['watch_limit'] ?>× /hari</strong> • S/d <?= date('d M Y', strtotime($user['membership_expires_at'])) ?>
    </div>
    
    <?php if ($can_refund): ?>
    <button type="button" onclick="document.getElementById('brutal-refund-confirm').style.display='flex'" style="background:#ef4444; color:#fff; border:3px solid #0f172a; box-shadow:0 4px 0 #0f172a; border-radius:12px; width:100%; padding:12px; font-size:13px; font-weight:900; display:flex; justify-content:center; align-items:center; gap:8px; cursor:pointer; transition:transform 0.1s;" onactive="this.style.transform='translateY(4px)'; this.style.boxShadow='none'">
      <i class="ph-bold ph-arrow-u-up-left" style="font-size:18px"></i> Minta Refund
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- BALANCE GRID (BENTO) -->
  <div class="sh" style="margin-bottom:10px">
    <div class="sh__title">💳 Saldo & Topup</div>
  </div>
  
  <div class="bento-grid" style="margin-bottom:20px;">
    <div class="bento-wide" style="background:linear-gradient(135deg, #fef08a, #fde047); border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; padding:14px; position:relative; overflow:hidden;">
      <i class="ph-fill ph-wallet" style="color:#ca8a04; font-size:36px; margin-right:8px; z-index:2; position:relative;"></i>
      <div class="bento-wide__txt" style="z-index:2; position:relative;">
        <div class="bento-wide__label" style="color:#78350f; font-size:12px; margin-bottom:2px;">Saldo Beli (Khusus Upgrade)</div>
        <div class="bento-wide__sub" style="color:#0f172a; font-size:18px; font-weight:900; letter-spacing:-0.5px;"><?= format_rp((float)$user['balance_dep']) ?></div>
      </div>
      <i class="ph-fill ph-coins" style="position:absolute; bottom:-10px; right:-10px; font-size:60px; color:#facc15; opacity:0.4; z-index:1; transform:rotate(-15deg);"></i>
    </div>
    
    <a href="/deposit" class="bento-sm" style="background:linear-gradient(135deg, #34d399, #10b981); border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a;">
      <i class="ph-bold ph-plus"></i>
      <span class="bento-sm__label">Topup</span>
    </a>
    
    <a href="/checkin" class="bento-sm" style="background:linear-gradient(135deg, #f472b6, #ec4899); border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a;">
      <i class="ph-bold ph-calendar-check"></i>
      <span class="bento-sm__label">Checkin</span>
    </a>
  </div>

  <!-- PACKAGES FORM -->
  <form method="POST" id="upgrade-form">
    <?= csrf_field() ?>
    <input type="hidden" name="membership_id" id="chosen-id" value="">
    <input type="hidden" name="voucher_code" id="applied-voucher-code" value="">
    
    <div class="sh" style="margin-bottom:10px; margin-top:8px;">
      <div class="sh__title">🔥 Pilih Rank Kamu</div>
    </div>

    <div class="bento-grid" style="margin-bottom:24px;">
      <?php 
      // Ambil paket berbayar
      $paid = array_filter($memberships, function($m) { return (float)$m['price'] > 0; });
      // Reset keys agar 0, 1, 2 = Juragan, Sultan, Konglomerat
      $paid = array_values($paid);
      
      $mythic = $paid[2] ?? null; // Konglomerat (Highest)
      $gold   = $paid[1] ?? null; // Sultan (Mid)
      $silver = $paid[0] ?? null; // Juragan (Entry)
      
      // Render Mythic (Konglomerat) as bento-big
      if ($mythic):
        $m = $mythic;
        $can_afford = (float)$user['balance_dep'] >= (float)$m['price'];
      ?>
      <div class="bento-big" style="background:linear-gradient(135deg, #fef08a, #fde047); border:3px solid #0f172a; box-shadow:0 8px 0 #0f172a; position:relative; display:flex; flex-direction:column; justify-content:space-between; cursor:pointer; padding:16px; text-align:left; transition:transform 0.1s;" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)" onactive="this.style.transform='translateY(4px)'; this.style.boxShadow='0 4px 0 #0f172a'">
        <div style="position:absolute; top:-12px; right:-12px; background:linear-gradient(135deg, #ef4444, #b91c1c); color:#fff; font-size:10px; font-weight:900; padding:6px 12px; border-radius:12px; border:2.5px solid #fff; box-shadow:0 4px 0 #7f1d1d; transform:rotate(8deg); z-index:5;">👑 BEST DEAL!</div>
        
        <div style="margin-bottom:12px;">
          <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
            <div style="width:38px; height:38px; background:#fff; border-radius:10px; border:2.5px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow:0 3px 0 #0f172a; flex-shrink:0;"><?= htmlspecialchars($m['icon'] ?: '💎') ?></div>
            <div>
              <div style="font-size:16px; font-weight:900; color:#0f172a; line-height:1.1;"><?= htmlspecialchars($m['name']) ?></div>
              <div style="font-size:11px; font-weight:800; color:#b45309;"><i class="ph-bold ph-hourglass"></i> <?= $m['duration_days'] ?> Hari</div>
            </div>
          </div>
          
          <div style="font-size:10px; font-weight:800; color:#78350f; background:rgba(255,255,255,0.4); border-radius:10px; padding:6px 8px; border: 2px dashed #ca8a04;">
            <div style="display:flex; align-items:center; gap:6px; margin-bottom:4px;"><i class="ph-bold ph-video-camera" style="color:#0ea5e9; font-size:14px;"></i> <?= $m['watch_limit'] ?> Video/hari</div>
            <div style="display:flex; align-items:center; gap:6px;"><i class="ph-bold ph-trend-up" style="color:#10b981; font-size:14px;"></i> Maks WD Bebas</div>
          </div>
        </div>
        
        <div>
          <div style="text-decoration:line-through; font-size:11px; color:#b45309; font-weight:800; text-align:right; margin-bottom:-4px;"><?= format_rp((float)$m['original_price']) ?></div>
          <div style="font-size:22px; font-weight:900; color:#0f172a; text-align:right; margin-bottom:10px; letter-spacing:-1px;"><?= format_rp((float)$m['price']) ?></div>
          <div style="background:#0f172a; color:#fff; text-align:center; padding:10px; border-radius:12px; font-weight:900; font-size:12px; box-shadow:0 4px 0 rgba(0,0,0,0.3);"><?= $can_afford ? 'Gas Upgrade!' : 'Saldo Kurang' ?></div>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- GOLD (SULTAN) as WIDE -->
      <?php 
      if ($gold):
        $m = $gold; 
        $can_afford = (float)$user['balance_dep'] >= (float)$m['price'];
      ?>
      <div class="bento-wide" style="background:#fff; border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; position:relative; cursor:pointer; flex-direction:column; align-items:flex-start; padding:12px; min-height:100px;" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)">
        <div style="position:absolute; top:-10px; right:-8px; background:#f97316; color:#fff; font-size:9px; font-weight:900; padding:4px 8px; border-radius:8px; border:2.5px solid #fff; box-shadow:0 3px 0 #c2410c; transform:rotate(5deg); z-index:5;">🔥 POPULER</div>
        
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
          <div style="width:32px; height:32px; background:#f0f9ff; border-radius:8px; border:2px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:16px; box-shadow:0 2px 0 #0f172a;">
              <?= htmlspecialchars($m['icon'] ?: '⭐') ?>
          </div>
          <div>
            <div class="bento-wide__label" style="font-size:14px; color:#0f172a; line-height:1.1;"><?= htmlspecialchars($m['name']) ?></div>
            <div class="bento-wide__sub" style="font-size:10px; color:#64748b; margin-top:2px;"><i class="ph-bold ph-video-camera"></i> <?= $m['watch_limit'] ?>x • <?= $m['duration_days'] ?> Hr</div>
          </div>
        </div>
        
        <div style="margin-top:auto;">
          <div style="text-decoration:line-through; font-size:10px; color:#94a3b8; font-weight:800; margin-bottom:-2px;"><?= format_rp((float)$m['original_price']) ?></div>
          <div style="font-size:16px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;"><?= format_rp((float)$m['price']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- SILVER (JURAGAN) as WIDE -->
      <?php 
      if ($silver):
        $m = $silver; 
        $can_afford = (float)$user['balance_dep'] >= (float)$m['price'];
      ?>
      <div class="bento-wide" style="background:#f8fafc; border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; position:relative; cursor:pointer; flex-direction:column; align-items:flex-start; padding:12px; min-height:100px;" onclick="openConfirm(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name'], ENT_QUOTES) ?>', <?= (float)$m['price'] ?>, <?= $m['duration_days'] ?>)">
        
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
          <div style="width:32px; height:32px; background:#e2e8f0; border-radius:8px; border:2px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:16px; box-shadow:0 2px 0 #0f172a;">
              <?= htmlspecialchars($m['icon'] ?: '🏅') ?>
          </div>
          <div>
            <div class="bento-wide__label" style="font-size:14px; color:#0f172a; line-height:1.1;"><?= htmlspecialchars($m['name']) ?></div>
            <div class="bento-wide__sub" style="font-size:10px; color:#64748b; margin-top:2px;"><i class="ph-bold ph-video-camera"></i> <?= $m['watch_limit'] ?>x • <?= $m['duration_days'] ?> Hr</div>
          </div>
        </div>
        
        <div style="margin-top:auto;">
          <div style="text-decoration:line-through; font-size:10px; color:#94a3b8; font-weight:800; margin-bottom:-2px;"><?= format_rp((float)$m['original_price']) ?></div>
          <div style="font-size:16px; font-weight:900; color:#0f172a; letter-spacing:-0.5px;"><?= format_rp((float)$m['price']) ?></div>
        </div>
      </div>
      <?php endif; ?>
      
    </div>
  </form>

  <!-- INFO CARDS (CARA KERJA & KEUNTUNGAN & FAQ) -->
  <div class="cg-card" style="background:#fff; border-radius:24px; border:3px solid #0f172a; box-shadow:0 6px 0 #0f172a; padding:16px; margin-bottom:24px;">
    
    <div style="font-size:15px; font-weight:900; color:#0f172a; margin-bottom:16px; display:flex; align-items:center; gap:8px;"><i class="ph-bold ph-lightbulb" style="color:#f59e0b; font-size:20px;"></i> Cara Kerja Upgrade</div>
    
    <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
      <div style="width:28px; height:28px; border-radius:10px; border:2.5px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:900; color:#0f172a; background:#a7f3d0; box-shadow:0 3px 0 #0f172a; flex-shrink:0;">1</div>
      <div style="font-size:12px; font-weight:800; color:#334155; line-height:1.4; padding-top:4px;"><strong>Topup Saldo Beli</strong> khusus untuk membeli paket berlangganan.</div>
    </div>
    
    <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:12px;">
      <div style="width:28px; height:28px; border-radius:10px; border:2.5px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:900; color:#0f172a; background:#fde047; box-shadow:0 3px 0 #0f172a; flex-shrink:0;">2</div>
      <div style="font-size:12px; font-weight:800; color:#334155; line-height:1.4; padding-top:4px;"><strong>Pilih paket Sultan / Konglomerat</strong> dari daftar Bento di atas.</div>
    </div>
    
    <div style="display:flex; align-items:flex-start; gap:12px; margin-bottom:16px;">
      <div style="width:28px; height:28px; border-radius:10px; border:2.5px solid #0f172a; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:900; color:#0f172a; background:#f9a8d4; box-shadow:0 3px 0 #0f172a; flex-shrink:0;">3</div>
      <div style="font-size:12px; font-weight:800; color:#334155; line-height:1.4; padding-top:4px;"><strong>Konfirmasi & Boom!</strong> Limit menonton Anda otomatis meroket drastis!</div>
    </div>
    
    <div style="border-top:2.5px dashed #cbd5e1; padding-top:16px; margin-bottom:16px;">
      <div style="font-size:14px; font-weight:900; color:#0f172a; margin-bottom:12px; display:flex; align-items:center; gap:6px;"><i class="ph-bold ph-check-circle" style="color:#10b981; font-size:18px;"></i> Keuntungan Langsung</div>
      <div style="display:flex; flex-wrap:wrap; gap:8px;">
        <span style="font-size:11px; font-weight:900; color:#0f172a; background:#e0f2fe; padding:8px 12px; border-radius:12px; border:2.5px solid #0f172a; box-shadow:0 3px 0 #0f172a;">📹 Nonton Sepuasnya</span>
        <span style="font-size:11px; font-weight:900; color:#0f172a; background:#dcfce7; padding:8px 12px; border-radius:12px; border:2.5px solid #0f172a; box-shadow:0 3px 0 #0f172a;">💸 Min. WD Rendah</span>
        <span style="font-size:11px; font-weight:900; color:#0f172a; background:#fef08a; padding:8px 12px; border-radius:12px; border:2.5px solid #0f172a; box-shadow:0 3px 0 #0f172a;">📈 Profit Gila-gilaan</span>
        <span style="font-size:11px; font-weight:900; color:#0f172a; background:#fce7f3; padding:8px 12px; border-radius:12px; border:2.5px solid #0f172a; box-shadow:0 3px 0 #0f172a;">💰 Max WD Milyaran</span>
      </div>
    </div>
    
    <div style="background:#f1f5f9; border-radius:16px; padding:14px; border:2.5px solid #0f172a;">
      <div style="font-size:13px; font-weight:900; color:#0f172a; margin-bottom:10px; display:flex; align-items:center; gap:6px;"><i class="ph-bold ph-warning" style="color:#f59e0b; font-size:16px;"></i> Catatan Penting</div>
      <div style="font-size:11px; font-weight:800; color:#475569; display:flex; gap:6px; margin-bottom:8px;"><i class="ph-fill ph-info" style="color:#64748b; font-size:14px; margin-top:2px;"></i> <span>Membeli paket baru akan menimpa paket lama (sisa masa aktif akan hangus/terganti).</span></div>
      <div style="font-size:11px; font-weight:800; color:#475569; display:flex; gap:6px;"><i class="ph-fill ph-info" style="color:#64748b; font-size:14px; margin-top:2px;"></i> <span>Saldo Beli khusus hanya untuk transaksi fitur, tidak dapat di-withdraw kembali.</span></div>
    </div>
    
  </div>

</div>

<!-- Confirmation Modal -->
<div id="upgrade-modal" class="cg-modal">
  <div class="cg-modal-card">
    <div class="cg-mc-hdr">🚀 Konfirmasi Upgrade</div>
    <div class="cg-mc-sub">Pastikan kamu yakin sebelum memproses!</div>
    
    <div class="cg-mc-box">
      <div class="cg-mc-lbl">Paket Dipilih</div>
      <div class="cg-mc-val" id="modal-name">—</div>
      <div class="cg-mc-price" id="price-row">Harga: <span id="modal-price">—</span></div>
      <div class="cg-mc-discount" id="discount-row">Diskon: -<span id="modal-discount">—</span> (<span id="modal-pct">—</span>)</div>
      <div class="cg-mc-total" id="final-price-row">Total Bayar: <span id="modal-final-price">—</span></div>
      <div class="cg-mc-dur">Berlaku <span id="modal-days">—</span> hari setelah aktivasi</div>
    </div>
    
    <!-- Voucher Coupon Section -->
    <div style="margin-bottom:16px;">
      <button type="button" id="toggle-voucher-btn" onclick="toggleVoucherInput()" style="background:none;border:none;color:#0ea5e9;font-weight:900;font-size:12px;cursor:pointer;padding:0;outline:none;display:flex;align-items:center;gap:4px;"><i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?</button>
      <div id="voucher-input-container" style="display:none;margin-top:8px;gap:8px;">
        <input type="text" id="voucher-code-input" placeholder="KODE VOUCHER" style="flex:1;border:2.5px solid #cbd5e1;border-radius:10px;padding:8px 12px;font-weight:900;text-transform:uppercase;font-size:12px;outline:none;">
        <button type="button" onclick="applyVoucher()" style="background:linear-gradient(135deg, #fde047, #f59e0b);color:#78350f;border:2px solid #fff;border-radius:10px;padding:8px 14px;font-weight:900;font-size:12px;cursor:pointer;box-shadow:0 3px 0 #d97706;text-shadow:0 1px 0 rgba(255,255,255,0.5);">Gunakan</button>
      </div>
      <div id="voucher-message" style="font-size:11px;font-weight:800;margin-top:6px;display:none;"></div>
    </div>

    <!-- Balance Warning -->
    <div id="modal-balance-warning" style="display:none;font-size:11px;color:#ef4444;font-weight:800;margin-bottom:16px;background:#fef2f2;border:2px solid #fca5a5;border-radius:10px;padding:10px 12px;"></div>

    <div style="font-size:11px;color:#94a3b8;font-weight:800;margin-bottom:16px;text-align:center;">⚠️ Saldo Beli akan otomatis terpotong. Aksi ini permanen.</div>
    
    <div class="cg-btn-row">
      <button type="button" class="cg-btn cg-btn--cancel" onclick="closeConfirm()">Batal</button>
      <button type="button" id="modal-confirm-btn" class="cg-btn cg-btn--confirm" onclick="submitUpgrade()">✅ YA, GAS!</button>
    </div>
  </div>
</div>

<!-- Neo-brutalism Refund Confirm Modal -->
<div id="brutal-refund-confirm" class="cg-modal">
  <div class="cg-modal-card" style="border-color:#b91c1c;box-shadow:0 8px 0 #b91c1c">
    <div class="cg-mc-hdr" style="color:#ef4444">⚠️ Minta Refund?</div>
    <div class="cg-mc-sub">Kamu yakin ingin refund paket kamu saat ini?</div>
    
    <div style="background:#fef2f2;border:2.5px solid #fca5a5;border-radius:14px;padding:12px;margin-bottom:16px;font-size:11px;font-weight:800;color:#991b1b;line-height:1.4">
      Uang refund akan dikembalikan ke <strong>Saldo Beli</strong> (bukan transfer bank) dengan potongan biaya admin (jika ada). Level kamu akan dibatalkan!
    </div>
    
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="refund_level">
      <div class="cg-btn-row">
        <button type="button" class="cg-btn cg-btn--cancel" onclick="document.getElementById('brutal-refund-confirm').style.display='none'">Gak Jadi</button>
        <button type="submit" class="cg-btn" style="background:linear-gradient(135deg, #ef4444, #dc2626);color:#fff;border-color:#fff;box-shadow:0 4px 0 #991b1b;text-shadow:0 1px 1px rgba(0,0,0,0.3)">Refund Sekarang</button>
      </div>
    </form>
  </div>
</div>

<script>
const userBalance = <?= (float)$user['balance_dep'] ?>;

function checkAffordability(finalPrice) {
  const btn = document.getElementById('modal-confirm-btn');
  const warnEl = document.getElementById('modal-balance-warning');
  if (userBalance < finalPrice) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    btn.style.cursor = 'not-allowed';
    btn.innerText = '💳 Saldo Kurang';
    if (warnEl) {
      warnEl.style.display = 'block';
      warnEl.innerHTML = '⚠️ Saldo Beli tidak cukup (Kurang <strong>Rp ' + (finalPrice - userBalance).toLocaleString('id-ID') + '</strong>). Silakan deposit dulu.';
    }
  } else {
    btn.disabled = false;
    btn.style.opacity = '1';
    btn.style.cursor = 'pointer';
    btn.innerText = '✅ YA, GAS!';
    if (warnEl) warnEl.style.display = 'none';
  }
}

function openConfirm(id, name, price, days) {
  document.getElementById('chosen-id').value = id;
  document.getElementById('modal-name').textContent  = name;
  document.getElementById('modal-price').textContent = 'Rp ' + price.toLocaleString('id-ID');
  document.getElementById('modal-days').textContent  = days;
  
  // Reset voucher states
  document.getElementById('applied-voucher-code').value = '';
  const codeInput = document.getElementById('voucher-code-input');
  if (codeInput) codeInput.value = '';
  const msgEl = document.getElementById('voucher-message');
  if (msgEl) msgEl.style.display = 'none';
  const container = document.getElementById('voucher-input-container');
  if (container) container.style.display = 'none';
  const toggleBtn = document.getElementById('toggle-voucher-btn');
  if (toggleBtn) {
    toggleBtn.style.display = 'flex';
    toggleBtn.innerHTML = '<i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?';
  }
  
  document.getElementById('discount-row').style.display = 'none';
  document.getElementById('final-price-row').style.display = 'none';
  
  // Check if balance is enough for original price
  checkAffordability(price);
  
  const m = document.getElementById('upgrade-modal');
  m.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeConfirm() {
  document.getElementById('upgrade-modal').style.display = 'none';
  document.body.style.overflow = '';
}
function toggleVoucherInput() {
  const container = document.getElementById('voucher-input-container');
  const toggleBtn = document.getElementById('toggle-voucher-btn');
  if (container.style.display === 'none') {
    container.style.display = 'flex';
    toggleBtn.innerHTML = '✖ Tutup Voucher';
  } else {
    container.style.display = 'none';
    toggleBtn.innerHTML = '<i class="ph-bold ph-tag"></i> Pakai Voucher Diskon?';
  }
}
function applyVoucher() {
  const codeInput = document.getElementById('voucher-code-input');
  const code = codeInput.value.toUpperCase().trim();
  const mid = document.getElementById('chosen-id').value;
  const msgEl = document.getElementById('voucher-message');
  
  if (!code) {
    msgEl.style.color = '#ef4444';
    msgEl.innerText = '⚠️ Masukkan kode voucher.';
    msgEl.style.display = 'block';
    return;
  }
  
  msgEl.style.color = '#94a3b8';
  msgEl.innerText = '⏳ Mengecek voucher...';
  msgEl.style.display = 'block';
  
  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=check_voucher&code=' + encodeURIComponent(code) + '&membership_id=' + encodeURIComponent(mid) + '&_csrf=' + encodeURIComponent(document.querySelector('input[name="_csrf"]')?.value || '')
  })
  .then(r => r.json())
  .then(res => {
    if (res.error) {
      msgEl.style.color = '#ef4444';
      msgEl.innerText = '❌ ' + res.error;
      msgEl.style.display = 'block';
      
      document.getElementById('applied-voucher-code').value = '';
      document.getElementById('discount-row').style.display = 'none';
      document.getElementById('final-price-row').style.display = 'none';
      
      const originalPrice = parseFloat(document.getElementById('modal-price').textContent.replace(/[^0-9]/g, ''));
      checkAffordability(originalPrice);
    } else {
      msgEl.style.color = '#10b981';
      msgEl.innerText = '✅ Diskon ' + res.discount_text + ' aktif!';
      msgEl.style.display = 'block';
      
      document.getElementById('applied-voucher-code').value = code;
      
      document.getElementById('modal-discount').textContent = res.discount_amount_formatted;
      document.getElementById('modal-pct').textContent = res.discount_text;
      document.getElementById('modal-final-price').textContent = res.final_price_formatted;
      
      document.getElementById('discount-row').style.display = 'block';
      document.getElementById('final-price-row').style.display = 'block';
      
      document.getElementById('voucher-input-container').style.display = 'none';
      document.getElementById('toggle-voucher-btn').style.display = 'none';
      
      checkAffordability(res.final_price);
    }
  })
  .catch(err => {
    msgEl.style.color = '#ef4444';
    msgEl.innerText = '❌ Gagal mengecek voucher.';
    msgEl.style.display = 'block';
  });
}
function submitUpgrade() {
  const btn = document.getElementById('modal-confirm-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Memproses...';
  document.getElementById('upgrade-form').submit();
}
// Close on backdrop click
document.getElementById('upgrade-modal').addEventListener('click', function(e) {
  if (e.target === this) closeConfirm();
});
document.getElementById('brutal-refund-confirm').addEventListener('click', function(e) {
  if (e.target === this) this.style.display='none';
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
