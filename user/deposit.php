<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$flash = $flashType = '';
$min_deposit  = (float) setting($pdo, 'min_deposit', '10000');
$bank_enabled = setting($pdo, 'bank_enabled', '1') === '1';
$qris_enabled = setting($pdo, 'qris_enabled', '1') === '1';
$bankName     = setting($pdo, 'bank_name', 'BCA');
$bankAccount  = setting($pdo, 'bank_account', '-');
$bankHolder   = setting($pdo, 'bank_holder', 'Admin');
$qris_raw     = '00020101021126610014COM.GO-JEK.WWW01189360091431528826820210G1528826820303UMI51440014ID.CO.QRIS.WWW0215ID10265193497510303UMI5204899953033605802ID5916PRO PLAN DIGITAL6013JAKARTA UTARA61051411062070703A016304B1F7';

$u_enabled = setting($pdo, 'depo_unique_code_enabled', '0') === '1';
$u_min = (int)setting($pdo, 'depo_unique_code_min', '1');
$u_max = (int)setting($pdo, 'depo_unique_code_max', '999');
$unique_code = $u_enabled ? random_int(min($u_min, $u_max), max($u_min, $u_max)) : 0;

// ── Double-submit prevention ──────────────────────────────────────────────
$_ftk = 'dep_form_token';
if (empty($_SESSION[$_ftk])) $_SESSION[$_ftk] = bin2hex(random_bytes(16));
$_form_token = $_SESSION[$_ftk];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reconnect MySQL in case connection has gone away (error 2006/2013)
    pdo_reconnect($pdo);
    
    $submitted_ftk = $_POST['form_token'] ?? '';
    if (!hash_equals($_SESSION[$_ftk] ?? '', $submitted_ftk)) {
        $flash = '⚠️ Request kamu gagal diproses atau gak valid. Coba refresh halaman dulu ya!';
        $flashType = 'error';
        goto end_dep;
    }
    // Invalidate immediately to prevent double-submit
    unset($_SESSION[$_ftk]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_bank') {
    $amount = (int) preg_replace('/\D/', '', $_POST['amount'] ?? '0');
    $u_code = (int) preg_replace('/\D/', '', $_POST['unique_code'] ?? '0');
    if ($u_enabled && $u_code >= min($u_min, $u_max) && $u_code <= max($u_min, $u_max)) {
        $amount += $u_code;
    }
    if ($amount < $min_deposit) {
        $flash = 'Minimal deposit ' . format_rp($min_deposit) . ' ya.'; $flashType = 'error';
    } elseif (!$bank_enabled) {
        $flash = 'Transfer bank lagi gak tersedia nih.'; $flashType = 'error';
    } else {
        $proof = null;
        if (!empty($_FILES['proof']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $flash = 'Bukti transfer harus format JPG/PNG/WEBP ya.'; $flashType = 'error';
                goto end_dep;
            }
            $dir = dirname(__DIR__) . '/uploads/deposits/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = 'dep_' . $user['id'] . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['proof']['tmp_name'], $dir . $fname);
            $proof = 'deposits/' . $fname;
        }
        $pdo->prepare("INSERT INTO deposits (user_id,amount,method,proof_image) VALUES (?,?,?,?)")
            ->execute([$user['id'], $amount, 'transfer', $proof]);
        $dep_id = $pdo->lastInsertId();
        
        $msg = "<b>📢 DEPOSIT BARU (Transfer)</b>\nUser: {$user['username']}\nAmount: " . format_rp((float)$amount) . "\nStatus: Pending";
        $kb = [
            [['text'=>'✅ Approve', 'callback_data'=>'depo_approve_'.$dep_id], ['text'=>'❌ Reject', 'callback_data'=>'depo_reject_'.$dep_id]],
            [['text'=>'⚡ Acc Expired', 'callback_data'=>'depo_accexp_'.$dep_id], ['text'=>'🔄 Refresh Status', 'callback_data'=>'refresh_depo_'.$dep_id]]
        ];
        send_telegram_notif($pdo, $msg, $kb, 'depo');
        
        // Success — regenerate token for next request
        $_SESSION[$_ftk] = bin2hex(random_bytes(16));
        $flash = '✅ Bukti transfer berhasil dikirim! Admin bakal memproses dalam 1×24 jam ya.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_qris') {
    $amount = (int) preg_replace('/\D/', '', $_POST['amount'] ?? '0');
    $u_code = (int) preg_replace('/\D/', '', $_POST['unique_code'] ?? '0');
    if ($u_enabled && $u_code >= min($u_min, $u_max) && $u_code <= max($u_min, $u_max)) {
        $amount += $u_code;
    }
    if ($amount < $min_deposit) {
        $flash = 'Minimal deposit ' . format_rp($min_deposit) . ' ya.'; $flashType = 'error';
    } elseif (!$qris_enabled || empty($qris_raw)) {
        $flash = 'QRIS lagi gak tersedia nih.'; $flashType = 'error';
    } else {
        $pdo->prepare("INSERT INTO deposits (user_id,amount,method,status) VALUES (?,?,'qris','pending')")
            ->execute([$user['id'], $amount]);
        $dep_id = $pdo->lastInsertId();
        
        $merchant_name = 'Unknown';
        $idx = 0;
        while ($idx < strlen($qris_raw) - 4) {
            $tag = substr($qris_raw, $idx, 2);
            $len = (int)substr($qris_raw, $idx+2, 2);
            if ($tag === '59') {
                $merchant_name = substr($qris_raw, $idx+4, $len);
                break;
            }
            $idx += 4 + $len;
        }

        $fmt_amount = format_rp((float)$amount);
        $msg = "📢 <b>DEPOSIT BARU ({$fmt_amount})</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "👤 <b>User:</b> <code>" . htmlspecialchars($user['username']) . "</code>\n";
        $msg .= "💵 <b>Amount:</b> <code>" . format_rp((float)$amount) . "</code>\n";
        $msg .= "🕒 <b>Time:</b> <code>" . date('d-m-Y H:i:s') . " WIB</code>\n";
        $msg .= "🏪 <b>QRIS:</b> <code>" . htmlspecialchars($merchant_name) . "</code>\n";
        $msg .= "💳 <b>Method:</b> <code>QRIS Otomatis</code>\n";
        $msg .= "⏳ <b>Status:</b> <code>Pending</code>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "<i>Sistem memantau secara otomatis. Status di Telegram ini akan diperbarui ketika sukses terbayar via callback!</i>";
        
        $kb = [
            [['text'=>'✅ Approve', 'callback_data'=>'depo_approve_'.$dep_id], ['text'=>'❌ Reject', 'callback_data'=>'depo_reject_'.$dep_id]],
            [['text'=>'⚡ Acc Expired', 'callback_data'=>'depo_accexp_'.$dep_id], ['text'=>'🔄 Refresh Status', 'callback_data'=>'refresh_depo_'.$dep_id]]
        ];
        
        $tg_msg_id = send_telegram_notif($pdo, $msg, $kb, 'depo');
        if ($tg_msg_id) {
            $pdo->prepare("UPDATE deposits SET tg_msg_id = ? WHERE id = ?")->execute([$tg_msg_id, $dep_id]);
        }

        redirect('/pay?id=' . $dep_id);
    }
}
end_dep:

$deps = $pdo->prepare("SELECT * FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 6");
$deps->execute([$user['id']]); $deps = $deps->fetchAll();

$pageTitle  = 'Isi Saldo — Meloton';
$activePage = 'deposit';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   DEPOSIT PAGE — CASUAL GAME STYLE (V4)
   ══════════════════════════════════════════════ */
body {
  background: #f97316 !important;
  color: #0f172a;
}
.wd-page { padding: 0 0 20px; }

/* ── TOP BANNER ── */
.wd-top {
  position: relative;
  background: linear-gradient(180deg, #3b82f6, #1d4ed8);
  padding: 16px 14px 40px;
  border-bottom: 4px solid #1e3a8a;
  z-index: 10;
}
.wd-top::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px);
  background-size: 30px 20px;
  pointer-events: none;
}
.wd-top-flex {
  position: relative;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  z-index: 2;
}
.wd-back {
  background: rgba(255,255,255,0.2);
  border: 2px solid rgba(255,255,255,0.4);
  color: #fff;
  width: 36px; height: 36px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; text-decoration: none;
}
.wd-notice {
  background: #fef08a;
  border: 2px solid #ca8a04;
  border-radius: 12px;
  padding: 6px 12px;
  box-shadow: 0 4px 0 #ca8a04;
  display: flex; gap: 6px; align-items: center;
  position: relative;
  margin-top: 4px;
}
.wd-notice::after {
  content: '';
  position: absolute;
  right: -8px; top: 12px;
  border-width: 6px;
  border-style: solid;
  border-color: transparent transparent transparent #fef08a;
}
.wd-notice-icon { font-size: 16px; flex-shrink: 0; }
.wd-notice-txt {
  font-size: 11px; font-weight: 800; color: #854d0e; line-height: 1.2;
}
.wd-dog-mascot {
  position: absolute;
  bottom: -4px; right: 14px;
  font-size: 46px; line-height: 1;
  filter: drop-shadow(0 2px 2px rgba(0,0,0,0.2));
  z-index: 5;
}

/* ── BODY ── */
.wd-body {
  flex: 1;
  background: #f97316;
  padding: 16px 14px 100px;
  position: relative;
}
.wd-body::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%),
              radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%);
  background-size: 50px 50px; background-position: 0 0, 25px 25px;
  pointer-events: none;
}

/* ── SALDO ROW / CURRENT BANK ROW ── */
.wd-bank-card { background: linear-gradient(135deg, #1e3a8a, #3b82f6); border: 3px solid #1e40af; box-shadow: 0 5px 0 #1e3a8a; border-radius: 16px; padding: 16px; margin-bottom: 20px; position: relative; z-index: 2; display: flex; align-items: center; gap: 14px; }
.wd-saldo-icon { width: 50px; height: 50px; background: #fffbeb; border: 3px solid #fde047; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: 0 4px 0 #ca8a04; flex-shrink: 0; }
.wd-saldo-lbl { font-size: 11px; font-weight: 900; color: rgba(255,255,255,0.7); margin-bottom: 2px; letter-spacing: 0.5px; text-transform: uppercase; }
.wd-saldo-val { font-size: 24px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #9a3412, 0 4px 6px rgba(0,0,0,0.3); font-style: italic; letter-spacing: -0.5px; display:flex; align-items:center; gap:8px;}

/* ── ALERTS ── */
.wd-alert { padding: 10px 12px; border-radius: 12px; font-size: 11px; font-weight: 800; display: flex; gap: 8px; align-items: center; margin-bottom: 12px; border: 2px solid; line-height: 1.3; position: relative; z-index: 2; }
.wd-alert--err { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
.wd-alert--warn { background: #fffbeb; color: #9a3412; border: 2px solid #c2410c; box-shadow: 0 3px 0 #c2410c; }
.wd-alert--succ { background: #f0fdf4; color: #166534; border-color: #86efac; }
.wd-alert--info { background: #1e3a8a; color: #fff; border: 2px solid #1e40af; box-shadow: 0 3px 0 #1e40af; }
.wd-alert-icon { font-size: 18px; flex-shrink: 0; }

/* ── Segmented Tabs ── */
.dep-tabs { display: flex; background: #c2410c; border-radius: 16px; padding: 4px; margin-bottom: 16px; border: 3px solid #9a3412; position: relative; z-index: 2;}
.dep-tab { flex: 1; text-align: center; padding: 10px; font-size: 12px; font-weight: 900; color: #f97316; cursor: pointer; border-radius: 10px; transition: all 0.2s; display:flex; align-items:center; justify-content:center; gap:6px; }
.dep-tab.active { background: #ffffff; color: #9a3412; box-shadow: 0 4px 0 #9a3412; border: 2px solid #ca8a04; }
.dep-tab.active i { color: #ea580c; }

/* ── Form Card ── */
.dep-form-card { display: none; position: relative; z-index: 2;}
.dep-form-card.active { display: block; animation: fadein 0.3s ease; }
@keyframes fadein { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* ── Bank Account Info ── */
.dep-rek { background: #fffbeb; border: 3px solid #fde047; border-radius: 14px; padding: 14px; margin-bottom: 16px; text-align: center; box-shadow: 0 4px 0 #ca8a04; }
.dep-rek__lbl { font-size: 10px; color: #9a3412; font-weight: 900; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.dep-rek__bank { font-size: 13px; font-weight: 900; color: #ea580c; }
.dep-rek__num { font-size: 20px; font-weight: 900; letter-spacing: 1px; margin: 4px 0; color: #7c2d12; }
.dep-rek__name { font-size: 11px; color: #b45309; font-weight: 800; }
.dep-rek__copy {
  margin-top: 10px; width: 100%;
  background: #fef08a; border: 2px solid #ea580c; border-radius: 10px;
  padding: 8px; font-size: 11px; font-weight: 900; color: #9a3412;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;
  box-shadow: 0 3px 0 #ea580c; transition: transform 0.1s;
}
.dep-rek__copy:active { transform: translateY(3px); box-shadow: 0 0 0 #ea580c; }

/* ── Amount Input (Chunky) ── */
.wd-input-grp { margin-bottom: 14px; position: relative; z-index: 2; }
.wd-input-grp label { display: block; font-size: 11px; font-weight: 900; color: #fff; text-shadow: 0 1px 0 #c2410c; margin-bottom: 6px; padding-left: 4px; }
.dep-amount-inner { display: flex; align-items: center; justify-content: flex-start; gap: 8px; background: #ffffff; border: 3px solid #c2410c; border-radius: 14px; padding: 12px 16px; box-shadow: 0 4px 0 #9a3412; }
.dep-amount-prefix { font-size: 16px; font-weight: 900; color: #c2410c; }
.dep-amount-input {
  width: 100%; background: transparent; border: none;
  font-size: 16px; font-weight: 900; color: #7c2d12;
  font-family: inherit; outline: none; text-align: left;
  padding: 0; margin: 0; box-sizing: border-box;
}
.dep-amount-input::placeholder { color: #f59e0b; opacity: 0.8; }

/* ── Amount Grid (Pills) ── */
.dep-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 16px; }
.dep-amt-btn {
  background: #fff; border: 3px solid #ffedd5; border-radius: 12px;
  padding: 12px 10px; text-align: center; cursor: pointer; transition: all 0.1s; 
  box-shadow: 0 4px 0 rgba(0,0,0,0.05); outline: none;
}
.dep-amt-btn:active { transform: translateY(4px); box-shadow: 0 0 0 #9a3412; }
.dep-amt-btn.active { background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-color: #60a5fa; box-shadow: 0 4px 0 #1e3a8a; }
.dep-amt-btn.active:active { transform: translateY(4px); box-shadow: 0 0 0 #1e3a8a; }
.dep-amt-val { font-size: 13px; font-weight: 900; color: #9a3412; letter-spacing: -0.5px; }
.dep-amt-btn.active .dep-amt-val { color: #fff; }

/* ── File Upload ── */
.dep-file-wrap { margin-bottom: 16px; }
.dep-file-lbl { font-size: 11px; font-weight: 900; color: #fff; text-shadow: 0 1px 0 #c2410c; margin-bottom: 6px; padding-left: 4px; display: block; text-align: left; }
.dep-file {
  width: 100%; background: #fff;
  border: 3px dashed #ffedd5; border-radius: 14px;
  padding: 12px; font-size: 11px; font-weight: 800; color: #c2410c;
  cursor: pointer; box-sizing: border-box; text-align: center;
}
.dep-file::file-selector-button { background: #fde047; border: 2px solid #ca8a04; border-radius: 8px; padding: 6px 10px; margin-right: 10px; font-weight: 900; color: #9a3412; cursor: pointer; transition: background 0.2s; box-shadow: 0 2px 0 #ca8a04; }
.dep-file::file-selector-button:active { transform:translateY(2px); box-shadow:none; }

/* ── Submit Button ── */
.wd-submit-btn {
  width: 100%;
  background: linear-gradient(180deg, #4ade80, #16a34a);
  border: none;
  border-radius: 16px;
  padding: 16px;
  font-size: 18px;
  font-weight: 900;
  color: #fff;
  text-shadow: 0 2px 2px rgba(0,0,0,0.3);
  box-shadow: 0 6px 0 #14532d, inset 0 2px 4px rgba(255,255,255,0.5);
  cursor: pointer;
  transition: transform 0.1s, box-shadow 0.1s;
  position: relative;
  z-index: 2;
}
.wd-submit-btn:active:not(:disabled) {
  transform: translateY(6px);
  box-shadow: 0 0 0 #14532d, inset 0 2px 4px rgba(255,255,255,0.5);
}
.wd-submit-btn.blue-btn { background: linear-gradient(180deg, #3b82f6, #1d4ed8); box-shadow: 0 6px 0 #1e3a8a, inset 0 2px 4px rgba(255,255,255,0.5); }
.wd-submit-btn.blue-btn:active:not(:disabled) { box-shadow: 0 0 0 #1e3a8a, inset 0 2px 4px rgba(255,255,255,0.5); }

/* ── History ── */
.hist-wrap { margin-top: 24px; border-top: 3px dashed rgba(255,255,255,0.2); padding-top: 16px; position:relative; z-index:2; }
.hist-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.hist-head h3 { font-size: 14px; font-weight: 900; color: #fff; margin: 0; display:flex; align-items:center; gap:6px; text-shadow:0 1px 0 #c2410c;}
.hist-head a { font-size: 10px; font-weight: 900; color: #fff; text-decoration: none; background: #ea580c; padding: 6px 10px; border-radius: 8px; border: 2px solid #fff; box-shadow: 0 3px 0 #c2410c; transition: transform 0.1s; text-shadow:none;}
.hist-head a:active { transform: translateY(3px); box-shadow: 0 0 0 #c2410c; }
.hist-list { display: flex; flex-direction: column; gap: 10px; }
.hist-card { background: #fffbeb; border: 3px solid #fde047; border-radius: 14px; padding: 12px; box-shadow: 0 4px 0 #ca8a04; display: flex; align-items: center; gap: 10px; }
.hist-card-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; border:2px solid; }
.hist-card-body { flex: 1; min-width: 0; }
.hist-card-amt { font-size: 14px; font-weight: 900; color: #7c2d12; letter-spacing: -0.5px; margin-bottom: 2px; }
.hist-card-date { font-size: 10px; font-weight: 800; color: #b45309; }
.hist-card-right { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.hist-badge { font-size: 9px; font-weight: 900; padding: 4px 8px; border-radius: 6px; text-transform: uppercase; border:2px solid;}

.dep-badge.pending { background: #fef08a; color: #9a3412; border-color:#fde047; }
.dep-badge.confirmed { background: #bbf7d0; color: #14532d; border-color:#86efac; }
.dep-badge.rejected { background: #fecaca; color: #7f1d1d; border-color:#fca5a5; }
.dep-badge.error { background: #fecaca; color: #7f1d1d; border-color:#fca5a5; }
</style>

<div class="wd-page">
  <!-- TOP BANNER -->
  <div class="wd-top">
    <div class="wd-top-flex">
      <a href="/home" class="wd-back"><i class="ph-bold ph-caret-left"></i></a>
      <div class="wd-notice">
        <div class="wd-notice-icon">💎</div>
        <div class="wd-notice-txt">Isi Saldo</div>
      </div>
    </div>
    <div class="wd-dog-mascot">🐶💰</div>
  </div>

  <div class="wd-body">
    
    <!-- SALDO ROW -->
    <div class="wd-bank-card">
      <div class="wd-saldo-icon">💰</div>
      <div>
        <div class="wd-saldo-lbl">Saldo Beli Saat Ini</div>
        <div class="wd-saldo-val"><?= format_rp((float)$user['balance_dep']) ?></div>
      </div>
    </div>

    <div class="wd-alert wd-alert--warn">
      <div class="wd-alert-icon">💡</div>
      <div style="flex:1">Minimal top up <strong><?= format_rp($min_deposit) ?></strong>.</div>
    </div>

    <?php if ($flash): ?>
    <div class="wd-alert wd-alert--<?= $flashType === 'error' ? 'err' : 'succ' ?>">
      <div class="wd-alert-icon"><?= $flashType === 'error' ? '❌' : '✨' ?></div>
      <div style="flex:1"><?= htmlspecialchars($flash) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!$bank_enabled && (!$qris_enabled || empty($qris_raw))): ?>
    <div class="wd-alert wd-alert--err">
      <div class="wd-alert-icon">⚠️</div>
      <div style="flex:1">Tidak ada metode deposit aktif. Hubungi admin.</div>
    </div>
    <?php else: ?>

    <!-- SEGMENTED TABS -->
    <div class="dep-tabs">
      <?php if ($qris_enabled && !empty($qris_raw)): ?>
      <div class="dep-tab" id="tab-qris" onclick="switchForm('qris')">
        <i class="ph-bold ph-qr-code"></i> QRIS
      </div>
      <?php endif; ?>
      <?php if ($bank_enabled): ?>
      <div class="dep-tab" id="tab-bank" onclick="switchForm('bank')">
        <i class="ph-bold ph-bank"></i> Bank Transfer
      </div>
      <?php endif; ?>
    </div>

    <?php if ($qris_enabled && !empty($qris_raw)): ?>
    <!-- QRIS FORM -->
    <div class="dep-form-card" id="form-qris">
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="form_token" value="<?= htmlspecialchars($_form_token) ?>">
        <input type="hidden" name="action" value="submit_qris">
        
        <div class="wd-input-grp">
          <label>Nominal Top Up</label>
          <div class="dep-amount-inner">
            <span class="dep-amount-prefix">Rp</span>
            <input class="dep-amount-input" id="qris-amount" type="number" name="amount" min="<?= $min_deposit ?>" step="any" placeholder="<?= number_format($min_deposit,0,'','') ?>" required>
          </div>
        </div>
        
        <div class="dep-grid">
          <?php foreach ([10000,25000,50000,100000,200000,500000] as $q): ?>
          <div class="dep-amt-btn" onclick="setAmt('qris',<?= $q ?>, this)">
            <div class="dep-amt-val"><?= format_rp($q) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <?php if ($u_enabled): ?>
        <input type="hidden" name="unique_code" value="<?= $unique_code ?>">
        <?php endif; ?>

        <div class="wd-alert wd-alert--info" style="margin-bottom:16px;">
          <div class="wd-alert-icon"><i class="ph-fill ph-lightning" style="color:#fff"></i></div>
          <div style="flex:1;">Klik Lanjut untuk bayar instan pakai QRIS.</div>
        </div>
        <button type="submit" class="wd-submit-btn blue-btn no-dbl-submit">
          <i class="ph-bold ph-qr-code"></i> Lanjut Bayar QRIS
        </button>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($bank_enabled): ?>
    <!-- BANK FORM -->
    <div class="dep-form-card" id="form-bank">
      <div class="dep-rek">
        <div class="dep-rek__lbl">Rekening Tujuan</div>
        <div class="dep-rek__bank">Bank <?= htmlspecialchars($bankName) ?></div>
        <div class="dep-rek__num" id="rek-num"><?= htmlspecialchars($bankAccount) ?></div>
        <div class="dep-rek__name">a.n. <?= htmlspecialchars($bankHolder) ?></div>
        <button type="button" class="dep-rek__copy" onclick="copyRek()"><i class="ph-bold ph-copy"></i> Salin Nomor Rekening</button>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="form_token" value="<?= htmlspecialchars($_form_token) ?>">
        <input type="hidden" name="action" value="submit_bank">
        
        <div class="wd-input-grp">
          <label>Nominal Top Up</label>
          <div class="dep-amount-inner">
            <span class="dep-amount-prefix">Rp</span>
            <input class="dep-amount-input" id="bank-amount" type="number" name="amount" min="<?= $min_deposit ?>" step="any" placeholder="<?= number_format($min_deposit,0,'','') ?>" required>
          </div>
        </div>
        
        <div class="dep-grid">
          <?php foreach ([10000,25000,50000,100000,200000,500000] as $q): ?>
          <div class="dep-amt-btn" onclick="setAmt('bank',<?= $q ?>, this)">
            <div class="dep-amt-val"><?= format_rp($q) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <?php if ($u_enabled): ?>
        <input type="hidden" name="unique_code" value="<?= $unique_code ?>">
        <?php endif; ?>

        <div class="dep-file-wrap">
          <label class="dep-file-lbl">Upload Bukti Transfer (JPG/PNG)</label>
          <input class="dep-file" type="file" name="proof" accept="image/*" required>
        </div>
        <button type="submit" class="wd-submit-btn no-dbl-submit"><i class="ph-bold ph-paper-plane-tilt"></i> Kirim Bukti</button>
      </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- RIWAYAT (Paling Bawah) -->
    <?php if (!empty($deps)): ?>
    <div class="hist-wrap">
      <div class="hist-head">
        <h3><i class="ph-fill ph-clock-counter-clockwise"></i> Riwayat Top Up</h3>
        <a href="/history">Semua →</a>
      </div>
      <div class="hist-list">
        <?php foreach ($deps as $d): ?>
          <?php
          $st = strtolower($d['status']);
          $bclass = 'error';
          if ($st === 'pending') $bclass = 'pending';
          elseif ($st === 'confirmed' || $st === 'approved') $bclass = 'confirmed';
          elseif ($st === 'rejected') $bclass = 'rejected';
          ?>
          <div class="hist-card">
            <div class="hist-card-icon" style="background:<?= $d['method']==='qris'?'#d1fae5':'#dbeafe' ?>;color:<?= $d['method']==='qris'?'#059669':'#2563eb' ?>;border-color:<?= $d['method']==='qris'?'#86efac':'#93c5fd' ?>;">
              <i class="<?= $d['method']==='qris' ? 'ph-bold ph-qr-code' : 'ph-bold ph-bank' ?>"></i>
            </div>
            <div class="hist-card-body">
              <div class="hist-card-amt"><?= format_rp((float)$d['amount']) ?></div>
              <div class="hist-card-date"><?= strtoupper($d['method']) ?> · <?= date('d M H:i', strtotime($d['created_at'])) ?></div>
            </div>
            <div class="hist-card-right">
              <span class="hist-badge dep-badge <?= $bclass ?>"><?= ucfirst($d['status']) ?></span>
              <?php if ($st === 'pending' && $d['method'] === 'qris'): ?>
              <a href="/pay?id=<?= $d['id'] ?>" style="display:inline-block; margin-top:4px; font-size:10px; font-weight:900; color:#fff; background:#3b82f6; padding:6px 10px; border-radius:8px; text-decoration:none; box-shadow:0 3px 0 #1d4ed8;"><i class="ph-bold ph-arrow-right"></i> Bayar</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script>
function switchForm(id) {
  const tabs = document.querySelectorAll('.dep-tab');
  const forms = document.querySelectorAll('.dep-form-card');
  tabs.forEach(t => t.classList.remove('active'));
  forms.forEach(f => f.classList.remove('active'));
  
  const targetTab = document.getElementById('tab-' + id);
  const targetForm = document.getElementById('form-' + id);
  
  if (targetTab) targetTab.classList.add('active');
  if (targetForm) targetForm.classList.add('active');
}

function setAmt(type, v, btn) {
  if (type === 'bank') {
    document.getElementById('bank-amount').value = v;
    const btns = document.querySelectorAll('#form-bank .dep-amt-btn');
    btns.forEach(b => b.classList.remove('active'));
  }
  if (type === 'qris') {
    document.getElementById('qris-amount').value = v;
    const btns = document.querySelectorAll('#form-qris .dep-amt-btn');
    btns.forEach(b => b.classList.remove('active'));
  }
  if (btn) btn.classList.add('active');
}

function copyRek() {
  const t = document.getElementById('rek-num').textContent.trim();
  nToast.copy ? nToast.copy(t, 'Nomor rekening') : navigator.clipboard.writeText(t);
}

document.addEventListener('DOMContentLoaded', () => {
  const tabQris = document.getElementById('tab-qris');
  const tabBank = document.getElementById('tab-bank');
  if (tabQris) {
    switchForm('qris');
  } else if (tabBank) {
    switchForm('bank');
  }
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
