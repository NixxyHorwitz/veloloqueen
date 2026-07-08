<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';
$flash = $flashType = '';
$active_section = 'main';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        if (strlen($username) < 3) { $flash = 'Username minimal 3 karakter.'; $flashType = 'error'; }
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) { $flash = 'Username hanya boleh huruf, angka, underscore.'; $flashType = 'error'; }
        else {
            $ex = $pdo->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $ex->execute([$username, $user['id']]);
            if ($ex->fetch()) { $flash = 'Username sudah digunakan.'; $flashType = 'error'; }
            else {
                $pdo->prepare("UPDATE users SET username=? WHERE id=?")->execute([$username, $user['id']]);
                $flash = 'Username berhasil diperbarui!';
            }
        }
        $active_section = 'edit';
    }
    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!password_verify($old, $user['password_hash'])) { $flash = 'Password lama salah.'; $flashType = 'error'; }
        elseif (strlen($new) < 6) { $flash = 'Password baru minimal 6 karakter.'; $flashType = 'error'; }
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
            $flash = 'Password berhasil diubah!';
        }
        $active_section = 'password';
    }
    $ru = $pdo->prepare("SELECT * FROM users WHERE id=?"); $ru->execute([$user['id']]); $user = $ru->fetch();
}

$st = $pdo->prepare("SELECT COUNT(*) FROM watch_history WHERE user_id=?"); $st->execute([$user['id']]); $total_watches = (int)$st->fetchColumn();
$refs = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?"); $refs->execute([$user['referral_code']]); $refs = (int)$refs->fetchColumn();

$membership_name = '';
$membership_allow_edit_bank = false;
$is_premium = false;

if ($user['membership_id'] && $user['membership_expires_at'] && strtotime((string)$user['membership_expires_at']) > time()) {
    $ms = $pdo->prepare("SELECT name, allow_edit_bank FROM memberships WHERE id=?"); $ms->execute([$user['membership_id']]);
    $ms = $ms->fetch();
    $membership_name = $ms['name'] ?? '';
    $membership_allow_edit_bank = (bool)($ms['allow_edit_bank'] ?? false);
    $is_premium = true;
}
if (!$membership_name) {
    $ms_free = $pdo->query("SELECT name, allow_edit_bank FROM memberships WHERE price=0 AND is_active=1 ORDER BY sort_order ASC LIMIT 1")->fetch();
    $membership_name = $ms_free['name'] ?? 'Free';
    $membership_allow_edit_bank = (bool)($ms_free['allow_edit_bank'] ?? false);
    $is_premium = false;
}

try {
    $_contact_btns = $pdo->query("SELECT * FROM contact_buttons WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
} catch (\Throwable) { $_contact_btns = []; }

$pageTitle  = 'Profil';
$activePage = 'profile';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   PROFILE PAGE â€” CASUAL GAME UI v2 (COMPACT & MINIMALIST)
   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

/* HERO */
.p-hero {
  background: linear-gradient(160deg, #3b82f6 0%, #2563eb 55%, #1d4ed8 100%);
  padding: 16px 14px 24px;
  position: relative; overflow: hidden;
  border-bottom: 3px solid #1e3a8a;
}
.p-hero::before {
  content: ''; position: absolute; top: -60px; right: -40px;
  width: 180px; height: 180px; background: rgba(255,255,255,0.07);
  border-radius: 50%; pointer-events: none;
}
.p-hero-top { display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; }
.p-avatar {
  width: 54px; height: 54px; background: #fff;
  border: 3px solid #fde68a; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px; font-weight: 900; color: #2563eb;
  box-shadow: 0 4px 0 #1e3a8a; flex-shrink: 0;
}
.p-info { flex: 1; min-width: 0; }
.p-name { font-size: 18px; font-weight: 900; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.2); line-height: 1.2; }
.p-email { font-size: 11px; font-weight: 700; color: #bfdbfe; margin-top: 2px; }
.p-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: rgba(255,255,255,0.2); border: 1.5px solid rgba(255,255,255,0.3);
  padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 900;
  color: #fff; margin-top: 6px;
}

/* CONTENT BODY */
.page-content { display: flex; flex-direction: column; padding-bottom: 0 !important; }
.p-body { background: #fff8f0; padding: 16px 14px calc(var(--nav-h) + 24px); flex: 1; position: relative; }

/* FLASH */
.flash-alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 14px; font-size: 12px; font-weight: 800; display: flex; align-items: center; gap: 8px; border: 2.5px solid; }
.flash-alert--success { background: #ecfdf5; color: #065f46; border-color: #6ee7b7; box-shadow: 0 3px 0 #6ee7b7; }
.flash-alert--error { background: #fef2f2; color: #991b1b; border-color: #fca5a5; box-shadow: 0 3px 0 #fca5a5; }

/* BENTO STATS */
.p-stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 16px; }
.p-stat {
  background: #fff; border: 2.5px solid #0f172a; border-radius: 16px;
  padding: 12px 8px; text-align: center; box-shadow: 0 4px 0 #0f172a;
}
.p-stat__val { font-size: 14px; font-weight: 900; color: #0f172a; line-height: 1.2; }
.p-stat__val--blue { color: #2563eb; }
.p-stat__val--green { color: #059669; }
.p-stat__lbl { font-size: 9px; font-weight: 800; color: #64748b; text-transform: uppercase; margin-top: 2px; }

/* REFERRAL STRIP */
.p-ref {
  display: flex; align-items: center; justify-content: space-between;
  background: #fffbeb; border: 2.5px solid #d97706; border-radius: 16px;
  padding: 10px 14px; box-shadow: 0 4px 0 #b45309; margin-bottom: 16px;
}
.p-ref__lbl { font-size: 10px; font-weight: 800; color: #b45309; text-transform: uppercase; margin-bottom: 2px; }
.p-ref__code { font-size: 16px; font-weight: 900; color: #78350f; letter-spacing: 1px; }
.p-ref__btn {
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border: 2px solid #d97706; border-radius: 12px; padding: 8px 12px;
  font-size: 11px; font-weight: 900; color: #78350f;
  box-shadow: 0 4px 0 #b45309; transition: transform 0.1s;
}
.p-ref__btn:active { transform: translateY(4px); box-shadow: none; }

/* MENUS */
.p-menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.p-menu {
  display: flex; align-items: center; gap: 8px;
  background: #fff; border: 2.5px solid #0f172a; border-radius: 16px;
  padding: 12px 14px; box-shadow: 0 4px 0 #0f172a; text-decoration: none;
  transition: transform 0.1s;
}
.p-menu:active { transform: translateY(3px); box-shadow: none; }
.p-menu i { font-size: 20px; }
.p-menu span { font-size: 12px; font-weight: 900; color: #0f172a; }
.p-menu--rek { border-color: #047857; box-shadow: 0 4px 0 #047857; background: #ecfdf5; }
.p-menu--rek i { color: #059669; }
.p-menu--upg { border-color: #7e22ce; box-shadow: 0 4px 0 #7e22ce; background: #faf5ff; }
.p-menu--upg i { color: #9333ea; }
.p-menu--pan { border-color: #b45309; box-shadow: 0 4px 0 #b45309; background: #fffbeb; }
.p-menu--pan i { color: #d97706; }

/* COMPACT ACCORDION FOR SETTINGS */
.p-group { background: #fff; border: 2.5px solid #0f172a; border-radius: 16px; box-shadow: 0 5px 0 #0f172a; overflow: hidden; margin-bottom: 16px; }
.p-acc { border-bottom: 2px solid #e2e8f0; }
.p-acc:last-child { border-bottom: none; }
.p-acc__hd {
  display: flex; align-items: center; gap: 8px; padding: 14px;
  background: #f8fafc; cursor: pointer; user-select: none;
}
.p-acc.open .p-acc__hd { background: #e0f2fe; border-bottom: 2px solid #bae6fd; }
.p-acc__icon { font-size: 18px; color: #3b82f6; width: 24px; text-align: center; }
.p-acc__title { flex: 1; font-size: 12px; font-weight: 900; color: #0f172a; }
.p-acc__caret { font-size: 14px; color: #94a3b8; transition: transform 0.2s; }
.p-acc.open .p-acc__caret { transform: rotate(180deg); color: #3b82f6; }
.p-acc__bd { display: none; padding: 14px; background: #fff; }
.p-acc.open .p-acc__bd { display: block; }

/* FORMS */
.c-lbl { font-size: 11px; font-weight: 800; color: #475569; margin-bottom: 4px; display: block; }
.c-input {
  width: 100%; background: #fff; border: 2px solid #cbd5e1; border-radius: 10px;
  padding: 10px 12px; font-size: 13px; font-weight: 700; color: #0f172a;
  margin-bottom: 10px; outline: none; font-family: 'Nunito', sans-serif;
}
.c-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px #dbeafe; }
.c-input:disabled { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
.c-btn {
  width: 100%; background: linear-gradient(135deg, #3b82f6, #2563eb);
  border: 2px solid #1d4ed8; border-radius: 12px; padding: 10px;
  font-size: 12px; font-weight: 900; color: #fff;
  box-shadow: 0 4px 0 #1e3a8a; cursor: pointer; transition: transform 0.1s;
  font-family: 'Nunito', sans-serif;
}
.c-btn:active { transform: translateY(4px); box-shadow: none; }

/* CONTACT ROW */
.p-contacts { display: flex; justify-content: center; gap: 10px; margin-bottom: 16px; }
.p-contact {
  width: 46px; height: 46px; border-radius: 14px; display: flex; align-items: center; justify-content: center;
  font-size: 24px; color: #fff; text-decoration: none;
  border: 2.5px solid rgba(0,0,0,0.15); box-shadow: 0 4px 0 rgba(0,0,0,0.25);
  transition: transform 0.1s;
}
.p-contact:active { transform: translateY(4px); box-shadow: none; }

/* LOGOUT */
.p-logout {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  border: 2.5px solid #991b1b; border-radius: 16px; padding: 14px;
  font-size: 14px; font-weight: 900; color: #fff; text-decoration: none;
  box-shadow: 0 5px 0 #7f1d1d; transition: transform 0.1s;
}
.p-logout:active { transform: translateY(5px); box-shadow: none; }

#toast { position: fixed; bottom: 85px; left: 50%; transform: translateX(-50%); background: #22c55e; color: #fff; padding: 8px 16px; border-radius: 12px; font-size: 12px; font-weight: 900; border: 2px solid #14532d; box-shadow: 0 4px 0 #14532d; display: none; z-index: 1000; }
</style>

<!-- FORCE UPDATE 1 --><div class="p-hero">
  <div class="p-hero-top">
    <div class="p-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
    <div class="p-info">
      <div class="p-name"><?= htmlspecialchars($user['username']) ?></div>
      <div class="p-email"><?= htmlspecialchars($user['email']) ?></div>
      <div class="p-badge">
        <i class="ph-fill ph-star" style="font-size:11px;color:#fde047"></i>
        <?= $is_premium ? $membership_name : $membership_name ?>
      </div>
    </div>
  </div>
</div>

<div class="p-body">
  <?php if ($flash): ?>
  <div class="flash-alert flash-alert--<?= $flashType === 'error' ? 'error' : 'success' ?>">
    <i class="ph-bold ph-<?= $flashType === 'error' ? 'warning-circle' : 'check-circle' ?>" style="font-size:18px;"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="p-stats-grid">
    <div class="p-stat">
      <div class="p-stat__val p-stat__val--green"><?= format_rp((float)$user['total_earned']) ?></div>
      <div class="p-stat__lbl">Earned</div>
    </div>
    <div class="p-stat">
      <div class="p-stat__val"><?= number_format($total_watches) ?></div>
      <div class="p-stat__lbl">Ditonton</div>
    </div>
    <div class="p-stat">
      <div class="p-stat__val p-stat__val--blue"><?= $refs ?></div>
      <div class="p-stat__lbl">Referral</div>
    </div>
  </div>

  <!-- REFERRAL -->
  <div class="p-ref">
    <div>
      <div class="p-ref__lbl">Kode Referral</div>
      <div class="p-ref__code" id="ref-code"><?= htmlspecialchars($user['referral_code']) ?></div>
    </div>
    <button class="p-ref__btn" onclick="copyRef()">
      <i class="ph-bold ph-copy"></i> Salin
    </button>
  </div>

  <!-- MENUS -->
  <div class="p-menu-grid">
    <a href="/edit-rekening" class="p-menu p-menu--rek">
      <i class="ph-fill ph-bank"></i>
      <span>Rekening</span>
    </a>
    <a href="/upgrade" class="p-menu p-menu--upg">
      <i class="ph-fill ph-rocket-launch"></i>
      <span>Upgrade</span>
    </a>
    <a href="/history" class="p-menu">
      <i class="ph-fill ph-receipt" style="color:#0ea5e9"></i>
      <span>Riwayat</span>
    </a>
    <a href="/panduan" class="p-menu p-menu--pan">
      <i class="ph-fill ph-book-open"></i>
      <span>Panduan</span>
    </a>
  </div>

  <!-- SETTINGS ACCORDION -->
  <div class="p-group">
    <!-- INFO -->
    <div class="p-acc" id="acc-info">
      <div class="p-acc__hd" onclick="toggleAcc('info')">
        <i class="ph-fill ph-identification-card p-acc__icon"></i>
        <div class="p-acc__title">Info Akun</div>
        <i class="ph-bold ph-caret-down p-acc__caret"></i>
      </div>
      <div class="p-acc__bd">
        <label class="c-lbl">WhatsApp</label>
        <input class="c-input" value="<?= htmlspecialchars(mask_account($user['whatsapp'] ?? '')) ?>" disabled>
        <label class="c-lbl">Bank Terdaftar</label>
        <input class="c-input" value="<?= $user['bank_name'] ? htmlspecialchars($user['bank_name'] . ' - ' . mask_account($user['account_number'] ?? '')) : 'Belum Ada' ?>" disabled>
      </div>
    </div>
    <!-- EDIT USERNAME -->
    <div class="p-acc <?= $active_section === 'edit' ? 'open' : '' ?>" id="acc-edit">
      <div class="p-acc__hd" onclick="toggleAcc('edit')">
        <i class="ph-fill ph-pencil-simple p-acc__icon" style="color:#eab308"></i>
        <div class="p-acc__title">Ubah Username</div>
        <i class="ph-bold ph-caret-down p-acc__caret"></i>
      </div>
      <div class="p-acc__bd">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <label class="c-lbl">Username Baru (Huruf/Angka/_)</label>
          <input class="c-input" type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required minlength="3">
          <button class="c-btn"><i class="ph-bold ph-floppy-disk"></i> Simpan</button>
        </form>
      </div>
    </div>
    <!-- CHANGE PASS -->
    <div class="p-acc <?= $active_section === 'password' ? 'open' : '' ?>" id="acc-pass">
      <div class="p-acc__hd" onclick="toggleAcc('pass')">
        <i class="ph-fill ph-lock-key p-acc__icon" style="color:#ef4444"></i>
        <div class="p-acc__title">Ganti Password</div>
        <i class="ph-bold ph-caret-down p-acc__caret"></i>
      </div>
      <div class="p-acc__bd">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <label class="c-lbl">Password Lama</label>
          <input class="c-input" type="password" name="old_password" required minlength="6">
          <label class="c-lbl">Password Baru</label>
          <input class="c-input" type="password" name="new_password" required minlength="6">
          <button class="c-btn"><i class="ph-bold ph-key"></i> Update</button>
        </form>
      </div>
    </div>
  </div>

  <!-- CONTACTS -->
  <div class="p-contacts">
    <?php foreach ($_contact_btns as $cb):
      $t = strtolower(\$cb['icon_value']);
      $c = match($t) {
        'wa' => 'background:linear-gradient(135deg, #4ade80, #16a34a);',
        'tele' => 'background:linear-gradient(135deg, #60a5fa, #2563eb);',
        'ig' => 'background:linear-gradient(135deg, #f43f5e, #be123c);',
        'fb' => 'background:linear-gradient(135deg, #3b82f6, #1d4ed8);',
        default => 'background:linear-gradient(135deg, #94a3b8, #475569);'
      };
      $i = match($t) { 'wa' => 'ph-whatsapp-logo', 'tele' => 'ph-telegram-logo', 'ig' => 'ph-instagram-logo', 'fb' => 'ph-facebook-logo', default => 'ph-headset' };
    ?>
    <a href="<?= htmlspecialchars($cb['url']) ?>" class="p-contact" target="_blank" style="<?= $c ?>">
      <i class="ph-fill <?= $i ?>"></i>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- LOGOUT -->
  <a href="/logout" class="p-logout">
    <i class="ph-bold ph-sign-out"></i> KELUAR
  </a>
</div>

<div id="toast">âœ… Tersalin!</div>
<script src="/assets/js/toast.js"></script>
<script>
function toggleAcc(id) {
  let acc = document.getElementById('acc-'+id);
  let wasOpen = acc.classList.contains('open');
  document.querySelectorAll('.p-acc').forEach(e => e.classList.remove('open'));
  if(!wasOpen) acc.classList.add('open');
}
function copyRef() {
  let txt = document.getElementById('ref-code').innerText;
  navigator.clipboard.writeText(txt).then(()=>{
    let t = document.getElementById('toast');
    t.style.display='block';
    setTimeout(()=>t.style.display='none', 2000);
  });
}
</script>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
