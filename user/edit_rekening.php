<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

// Fetch membership info including allow_edit_bank
$user_mem = null;
$membership_active = $user['membership_id']
    && $user['membership_expires_at']
    && strtotime((string)$user['membership_expires_at']) > time();

if ($membership_active) {
    $stmt = $pdo->prepare("SELECT name, allow_edit_bank FROM memberships WHERE id=? AND is_active=1");
    $stmt->execute([$user['membership_id']]);
    $user_mem = $stmt->fetch() ?: null;
}
if (!$user_mem) {
    $stmt = $pdo->prepare("SELECT name, allow_edit_bank FROM memberships WHERE price=0 AND is_active=1 ORDER BY sort_order ASC LIMIT 1");
    $stmt->execute();
    $user_mem = $stmt->fetch() ?: null;
}

$can_edit_bank     = (bool)($user_mem['allow_edit_bank'] ?? 0);
$level_name        = $user_mem['name'] ?? 'Free';

// Promotor bypass: skip semua pembatasan level
$is_promotor = ((int)($user['is_promotor'] ?? 0) === 1);
if ($is_promotor) {
    $can_edit_bank   = true;
}

$min_saldo_edit = (float)($user['edit_bank_deposit_min'] ?? 0);
if ($min_saldo_edit <= 0) {
    $min_saldo_edit = 50000;
}
$has_enough_balance = ((float)$user['balance_dep'] >= $min_saldo_edit);

$flash = $flashType = '';

// Cek apakah ada request ganti rekening yang masih pending
$stmtPending = $pdo->prepare("SELECT id FROM admin_requests WHERE user_id=? AND type='change_bank' AND status='pending'");
$stmtPending->execute([$user['id']]);
$has_pending_bank = (bool)$stmtPending->fetchColumn();

// ── POST handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($has_pending_bank) {
        $flash = '❌ Data rekening kamu sebelumnya masih dalam verifikasi.'; $flashType = 'error';
    } elseif (!$can_edit_bank) {
        $flash = '❌ Level kamu belum memiliki izin untuk mengubah rekening.'; $flashType = 'error';
    } elseif (!$has_enough_balance) {
        $flash = '❌ Kamu harus memiliki Saldo Beli minimal ' . format_rp($min_saldo_edit) . ' yang mengendap untuk mengubah rekening.'; $flashType = 'error';
    } else {
            $new_bank    = trim($_POST['bank_name']      ?? '');
            $new_accnum  = trim($_POST['account_number'] ?? '');
            $new_accname = trim($_POST['account_name']   ?? '');

            if (!$new_bank || !$new_accnum || !$new_accname) {
                $flash = '⚠️ Semua field wajib diisi.'; $flashType = 'error';
            } else {
            $payload = json_encode(['bank_name' => $new_bank, 'account_number' => $new_accnum, 'account_name' => $new_accname]);
            $pdo->prepare("INSERT INTO admin_requests (user_id, type, payload) VALUES (?, 'change_bank', ?)")
                ->execute([$user['id'], $payload]);
            $req_id = $pdo->lastInsertId();
            
            $msg  = "🏦 <b>REQUEST GANTI REKENING</b>\n\n";
            $msg .= "👤 User: <code>{$user['username']}</code>\n";
            $msg .= "💳 Rekening Baru:\n";
            $msg .= "- Bank: <b>{$new_bank}</b>\n";
            $msg .= "- No. Rek: <code>{$new_accnum}</code>\n";
            $msg .= "- A.N: <b>{$new_accname}</b>\n";
            $kb = [
                [['text'=>'✅ Approve', 'callback_data'=>'req_approve_'.$req_id], ['text'=>'❌ Reject', 'callback_data'=>'req_reject_'.$req_id]]
            ];
            send_telegram_notif($pdo, $msg, $kb, 'permintaan');
            
            $flash = '✅ Pengajuan rekening baru berhasil dikirim!';
            $has_pending_bank = true;
        }
    }
}

$has_bank = !empty($user['bank_name']) && !empty($user['account_number']) && !empty($user['account_name']);

// Load available payment channels
$channels = $pdo->query("SELECT name, type, logo FROM payment_channels WHERE is_active=1 ORDER BY type ASC, sort_order ASC, name ASC")->fetchAll();
$channel_logos = [];
foreach ($channels as $c) {
    if (!empty($c['logo'])) $channel_logos[strtolower($c['name'])] = $c['logo'];
}
$banks    = array_filter($channels, fn($c) => $c['type'] === 'bank');
$ewallets = array_filter($channels, fn($c) => $c['type'] === 'ewallet');

$pageTitle  = 'Edit Rekening  ';
$activePage = 'profile';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   EDIT REKENING PAGE — CASUAL GAME STYLE
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
.wd-bank-card { background: rgba(0, 0, 0, 0.15); border: 2px dashed rgba(255, 255, 255, 0.3); border-radius: 16px; padding: 16px; margin-bottom: 20px; position: relative; z-index: 2; display: flex; align-items: center; gap: 14px; }
.wd-saldo-icon { width: 50px; height: 50px; background: #fffbeb; border: 3px solid #fde047; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 26px; box-shadow: 0 4px 0 #ca8a04; flex-shrink: 0; }
.wd-saldo-lbl { font-size: 11px; font-weight: 900; color: rgba(255,255,255,0.7); margin-bottom: 2px; letter-spacing: 0.5px; text-transform: uppercase; }
.wd-saldo-val { font-size: 22px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #9a3412, 0 4px 6px rgba(0,0,0,0.3); font-style: italic; letter-spacing: -0.5px; display:flex; align-items:center; gap:8px;}

/* ── ALERTS ── */
.wd-alert { padding: 10px 12px; border-radius: 12px; font-size: 11px; font-weight: 800; display: flex; gap: 8px; align-items: center; margin-bottom: 12px; border: 2px solid; line-height: 1.3; position: relative; z-index: 2; }
.wd-alert--err { background: #fef2f2; color: #991b1b; border-color: #fca5a5; }
.wd-alert--warn { background: #fffbeb; color: #b45309; border-color: #fcd34d; }
.wd-alert--succ { background: #f0fdf4; color: #166534; border-color: #86efac; }
.wd-alert-icon { font-size: 18px; flex-shrink: 0; }
.wd-alert-btn { background: #fbbf24; color: #92400e; border: 2px solid #fff; border-radius: 8px; font-size: 9px; font-weight: 900; padding: 4px 10px; text-decoration: none; box-shadow: 0 2px 0 rgba(0,0,0,0.1); flex-shrink: 0; }

/* ── FORM ── */
.wd-input-grp { margin-bottom: 14px; position: relative; z-index: 2; }
.wd-input-grp label { display: block; font-size: 11px; font-weight: 900; color: #fff; text-shadow: 0 1px 0 #c2410c; margin-bottom: 6px; padding-left: 4px; }
.wd-input-grp input, .custom-select-trigger {
  width: 100%;
  background: #fffbeb;
  border: 3px solid #ea580c;
  border-radius: 14px;
  padding: 14px 16px;
  font-size: 14px;
  font-weight: 900;
  color: #7c2d12;
  box-shadow: 0 4px 0 #ca8a04;
  outline: none;
  font-family: inherit;
}
.wd-input-grp input::placeholder { color: #d97706; opacity: 0.6; }
.wd-input-grp input:focus, .custom-select-trigger.open { border-color: #c2410c; background: #fff; }

/* Custom Select Specific */
.custom-select-wrap { position: relative; width: 100%; }
.custom-select-trigger { display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
.sel-val { display: flex; align-items: center; gap: 8px; }
.sel-val img, .custom-option img { height: 18px; width: auto; border-radius: 4px; object-fit: contain; }
.custom-select-options {
  position: absolute; top: calc(100% + 4px); left: 0; width: 100%;
  background: #fff; border: 3px solid #ea580c; border-radius: 14px;
  box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 100;
  max-height: 200px; overflow-y: auto; display: none; padding: 6px;
}
.custom-select-options.open { display: block; }
.custom-optgroup { font-size: 11px; font-weight: 900; color: #ea580c; padding: 8px 8px 4px; text-transform: uppercase; border-bottom: 2px dashed #ffedd5; margin-bottom: 4px;}
.custom-option {
  padding: 10px 12px; font-size: 13px; font-weight: 800; color: #7c2d12;
  border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px;
}
.custom-option:hover { background: #ffedd5; color: #9a3412; }

/* ── SUBMIT BUTTON ── */
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
  margin-top: 10px;
}
.wd-submit-btn:active:not(:disabled) {
  transform: translateY(6px);
  box-shadow: 0 0 0 #14532d, inset 0 2px 4px rgba(255,255,255,0.5);
}
.wd-submit-btn:disabled {
  background: #cbd5e1; border-color:#94a3b8; color:#334155; box-shadow:none; transform:none; text-shadow:none;
}

/* ── Modal ── */
#cg-modal { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.7); z-index:99999; align-items:center; justify-content:center; padding:16px; backdrop-filter:blur(4px); }
.cg-modal-box { background: #fffbeb; width:100%; max-width:320px; border-radius:24px; border:4px solid #ea580c; box-shadow:0 8px 0 #9a3412; animation: popIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); overflow:hidden; }
.cg-modal-hdr { background: linear-gradient(135deg, #fde047, #f59e0b); padding: 16px; text-align: center; color: #7c2d12; font-weight: 900; font-size: 16px; border-bottom: 3px solid rgba(255,255,255,0.5); }
.cg-modal-bd { padding: 20px; text-align: left; }
.cg-modal-actions { display:flex; gap:12px; padding:0 20px 20px; }
.cg-btn-cancel { flex:1; padding:12px; background:#fef08a; border:2px solid #ca8a04; border-radius:14px; font-weight:900; color:#9a3412; font-size:14px; box-shadow:0 4px 0 #ca8a04; cursor:pointer;}
.cg-btn-cancel:active { transform:translateY(4px); box-shadow:none; }
.cg-btn-confirm { flex:1.5; padding:12px; background:linear-gradient(180deg, #4ade80, #16a34a); border:none; border-radius:14px; font-weight:900; color:#fff; box-shadow:0 4px 0 #14532d; font-size:14px; cursor:pointer;}
.cg-btn-confirm:active { transform:translateY(4px); box-shadow:none; }
@keyframes popIn { from{transform:scale(0.8);opacity:0;} to{transform:scale(1);opacity:1;} }
</style>

<div class="wd-page">
  <!-- TOP BANNER -->
  <div class="wd-top">
    <div class="wd-top-flex">
      <a href="/profile" class="wd-back"><i class="ph-bold ph-caret-left"></i></a>
      <div class="wd-notice">
        <div class="wd-notice-icon">🏦</div>
        <div class="wd-notice-txt">Edit Rekening</div>
      </div>
    </div>
    <div class="wd-dog-mascot">🐶💳</div>
  </div>

  <div class="wd-body">
    
    <!-- CURRENT BANK / SALDO ROW -->
    <div class="wd-bank-card">
      <div class="wd-saldo-icon">💳</div>
      <div>
        <div class="wd-saldo-lbl">Rekening Aktif</div>
        <?php if ($has_bank): ?>
          <div class="wd-saldo-val">
            <?php $user_wl = $channel_logos[strtolower($user['bank_name'] ?? '')] ?? null; ?>
            <?php if ($user_wl): ?>
              <img src="/assets/banks/<?= htmlspecialchars($user_wl) ?>" style="height:24px;border-radius:4px;object-fit:contain;background:#fff;padding:2px">
            <?php endif; ?>
            <?= htmlspecialchars(mask_account($user['account_number'])) ?>
          </div>
          <div style="font-size:12px;font-weight:900;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,0.3);margin-top:2px;">
            <?= htmlspecialchars($user['account_name']) ?>
          </div>
        <?php else: ?>
          <div class="wd-saldo-val" style="font-size:20px;opacity:0.8;">Belum Ada Rekening</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- FLASH ALERTS -->
    <?php if ($flash): ?>
    <div class="wd-alert wd-alert--<?= $flashType === 'error' ? 'err' : 'succ' ?>">
      <div class="wd-alert-icon"><?= $flashType === 'error' ? '❌' : '✨' ?></div>
      <div style="flex:1"><?= htmlspecialchars($flash) ?></div>
    </div>
    <?php endif; ?>

    <!-- NOTICES & CONDITIONS -->
    <?php if ($has_pending_bank): ?>
      <div class="wd-alert wd-alert--warn">
        <div class="wd-alert-icon">⏳</div>
        <div style="flex:1">Data rekening baru sedang diverifikasi otomatis. Mohon tunggu.</div>
      </div>
    <?php elseif (!$can_edit_bank): ?>
      <div class="wd-alert wd-alert--err">
        <div class="wd-alert-icon">🔒</div>
        <div style="flex:1">
           <div style="margin-bottom:2px"><strong>Akses Terkunci!</strong></div>
           <div style="font-size:10px">Level <?= htmlspecialchars($level_name) ?> belum memiliki izin untuk mengubah data rekening.</div>
        </div>
        <a href="/upgrade" class="wd-alert-btn">Upgrade</a>
      </div>
    <?php else: ?>
      
      <!-- SALDO MENGENDAP ALERT -->
      <?php if (!$has_enough_balance): ?>
        <?php
           $pct = $min_saldo_edit > 0 ? ((float)$user['balance_dep'] / $min_saldo_edit) * 100 : 0;
           if ($pct > 100) $pct = 100;
           if ($pct < 0) $pct = 0;
        ?>
        <div class="wd-alert wd-alert--err" style="display:block; padding-bottom:16px;">
          <div style="display:flex; align-items:flex-start; gap:12px;">
              <div class="wd-alert-icon" style="flex-shrink:0;">💰</div>
              <div style="flex:1">
                 <div style="margin-bottom:2px"><strong>Saldo Mengendap Kurang!</strong></div>
                 <div style="font-size:10px; line-height:1.4;">Syarat ganti rekening: Harus ada Saldo Beli minimal <?= format_rp($min_saldo_edit) ?> di akun kamu.</div>
              </div>
              <a href="/deposit" class="wd-alert-btn" style="background:#3b82f6;border-color:#60a5fa;box-shadow:0 3px 0 #2563eb;flex-shrink:0;">Deposit</a>
          </div>
          
          <!-- Progress Bar -->
          <div style="background:rgba(0,0,0,0.06); border-radius:10px; height:14px; overflow:hidden; border: 1px solid rgba(0,0,0,0.05); margin-top:14px; position:relative;">
            <div style="background:linear-gradient(90deg, #ef4444, #f59e0b); height:100%; width:<?= $pct ?>%; transition:width 0.5s;"></div>
          </div>
          <div style="display:flex; justify-content:space-between; font-size:10px; font-weight:900; color:#ef4444; margin-top:6px; padding:0 2px;">
             <span>Terkumpul: <?= format_rp((float)$user['balance_dep']) ?></span>
             <span>Target: <?= format_rp($min_saldo_edit) ?></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- FORM UBAH REKENING -->
      <div style="position:relative; z-index:2;">
        <?php if (!$has_enough_balance): ?>
        <!-- Lock Overlay -->
        <div style="position:absolute; inset:0; background:rgba(255,255,255,0.7); backdrop-filter:blur(3px); z-index:20; border-radius:16px; display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center;">
            <div style="font-size:38px; filter:drop-shadow(0 4px 6px rgba(0,0,0,0.1)); animation: popIn 0.4s ease-out;">🔒</div>
            <div style="font-size:15px; font-weight:900; color:#0f172a; margin-top:8px;">Form Terkunci</div>
            <div style="font-size:11px; font-weight:700; color:#64748b; margin-top:4px; max-width:80%;">Penuhi target Saldo Mengendap untuk membuka.</div>
        </div>
        <?php endif; ?>

        <form method="POST" id="edit-rek-form">
          <?= csrf_field() ?>
          
          <div class="wd-input-grp">
            <label>Bank / E-Wallet Baru</label>
            <select class="custom-logo-select" name="bank_name" required>
              <option value="" data-logo="">— Pilih Tujuan —</option>
              <?php if (!empty($banks)): ?>
                <optgroup label="Transfer Bank">
                  <?php foreach($banks as $b): ?>
                    <option value="<?= htmlspecialchars($b['name']) ?>" data-logo="<?= htmlspecialchars($b['logo']) ?>">
                      <?= htmlspecialchars($b['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
              <?php if (!empty($ewallets)): ?>
                <optgroup label="E-Wallet">
                  <?php foreach($ewallets as $e): ?>
                    <option value="<?= htmlspecialchars($e['name']) ?>" data-logo="<?= htmlspecialchars($e['logo']) ?>">
                      <?= htmlspecialchars($e['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>
          </div>

          <div class="wd-input-grp">
            <label>Nomor Rekening / No HP</label>
            <input type="text" name="account_number" placeholder="Contoh: 08xxxx / 512xxxx" required>
          </div>
          
          <div class="wd-input-grp">
            <label>Nama Pemilik (Sesuai Rekening)</label>
            <input type="text" name="account_name" placeholder="Contoh: Budi Santoso" required>
          </div>

          <?php if ($has_pending_bank): ?>
             <button type="button" class="wd-submit-btn" disabled>Sedang Diverifikasi</button>
          <?php elseif (!$can_edit_bank): ?>
             <button type="button" class="wd-submit-btn" disabled>Akses Terkunci</button>
          <?php elseif (!$has_enough_balance): ?>
             <button type="button" class="wd-submit-btn" disabled>Saldo Kurang</button>
          <?php else: ?>
             <button type="button" class="wd-submit-btn" onclick="showConfirm()">Ajukan Perubahan</button>
          <?php endif; ?>

        </form>
      </div>

    <?php endif; ?>
  </div>
</div>

<!-- CONFIRM MODAL -->
<div id="cg-modal" class="cg-modal">
  <div class="cg-modal-box">
    <div class="cg-modal-hdr">Konfirmasi Perubahan</div>
    <div class="cg-modal-bd">
      <div style="font-size:13px;font-weight:800;color:#9a3412;margin-bottom:12px;line-height:1.4">
        Apakah data rekening baru sudah benar?
      </div>
      <div style="font-size:11px;font-weight:700;color:#7c2d12;background:#ffedd5;padding:12px;border-radius:12px;border:1px dashed #fdba74;">
        Pengajuan yang sudah dikirim akan dicek admin dan tidak bisa diubah sementara waktu.
      </div>
    </div>
    <div class="cg-modal-actions">
      <button class="cg-btn-cancel" onclick="closeConfirm()">Batal</button>
      <button class="cg-btn-confirm" onclick="submitForm()">Ya, Kirim!</button>
    </div>
  </div>
</div>

<script>
// Custom Select Logic
document.addEventListener('DOMContentLoaded', () => {
    const selects = document.querySelectorAll('.custom-logo-select');
    selects.forEach(select => {
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select-wrap';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.style.display = 'none';

        const trigger = document.createElement('div');
        trigger.className = 'custom-select-trigger';
        trigger.innerHTML = '<div class="sel-val">— Pilih Tujuan —</div><i class="ph-bold ph-caret-down"></i>';
        
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'custom-select-options';

        wrapper.appendChild(trigger);
        wrapper.appendChild(optionsContainer);

        Array.from(select.children).forEach(child => {
            if (child.tagName === 'OPTGROUP') {
                const groupLabel = document.createElement('div');
                groupLabel.className = 'custom-optgroup';
                groupLabel.textContent = child.label;
                optionsContainer.appendChild(groupLabel);
                
                Array.from(child.children).forEach(opt => {
                    const optDiv = document.createElement('div');
                    optDiv.className = 'custom-option';
                    optDiv.dataset.value = opt.value;
                    const logo = opt.dataset.logo;
                    if (logo) {
                        optDiv.innerHTML = `<img src="/assets/banks/${logo}" alt="${opt.value}"> ${opt.value}`;
                    } else {
                        optDiv.innerHTML = opt.value;
                    }
                    optDiv.addEventListener('click', () => {
                        select.value = opt.value;
                        trigger.querySelector('.sel-val').innerHTML = optDiv.innerHTML;
                        optionsContainer.classList.remove('open');
                        trigger.classList.remove('open');
                    });
                    optionsContainer.appendChild(optDiv);
                });
            } else {
                if(child.value === '') return;
                const optDiv = document.createElement('div');
                optDiv.className = 'custom-option';
                optDiv.dataset.value = child.value;
                const logo = child.dataset.logo;
                if (logo) {
                    optDiv.innerHTML = `<img src="/assets/banks/${logo}" alt="${child.value}"> ${child.value}`;
                } else {
                    optDiv.innerHTML = child.value;
                }
                optDiv.addEventListener('click', () => {
                    select.value = child.value;
                    trigger.querySelector('.sel-val').innerHTML = optDiv.innerHTML;
                    optionsContainer.classList.remove('open');
                    trigger.classList.remove('open');
                });
                optionsContainer.appendChild(optDiv);
            }
        });

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = optionsContainer.classList.contains('open');
            document.querySelectorAll('.custom-select-options').forEach(o => o.classList.remove('open'));
            document.querySelectorAll('.custom-select-trigger').forEach(t => t.classList.remove('open'));
            if (!isOpen) {
                optionsContainer.classList.add('open');
                trigger.classList.add('open');
            }
        });
    });

    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-select-options').forEach(o => o.classList.remove('open'));
        document.querySelectorAll('.custom-select-trigger').forEach(t => t.classList.remove('open'));
    });
});

function showConfirm() {
    const f = document.getElementById('edit-rek-form');
    if (!f.reportValidity()) return;
    document.getElementById('cg-modal').style.display = 'flex';
}
function closeConfirm() {
    document.getElementById('cg-modal').style.display = 'none';
}
function submitForm() {
    document.getElementById('edit-rek-form').submit();
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
