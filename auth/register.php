<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (auth_user($pdo)) redirect('/home');
csrf_enforce();

$error = '';
$error_step = 1;
$error_fields = [];

// Rate limiting
$ip_key  = 'reg_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
$attempts = (int)($_SESSION[$ip_key . '_attempts'] ?? 0);
$lock_until = (int)($_SESSION[$ip_key . '_lock'] ?? 0);

// Generate Emoji CAPTCHA
$emojis = [
    // --- BUAH & SAYUR POPULER ---
    '🍎' => 'Apel',
    '🍌' => 'Pisang',
    '🍉' => 'Semangka',
    '🍓' => 'Stroberi',
    '🍇' => 'Anggur',
    '🍊' => 'Jeruk',
    '🍍' => 'Nanas',
    '🥑' => 'Alpukat',
    '🌶️' => 'Cabai',
    '🌽' => 'Jagung',

    // --- MAKANAN UTAMA ---
    '🍕' => 'Pizza',
    '🍔' => 'Burger',
    '🍟' => 'Kentang Goreng',
    '🍗' => 'Ayam Goreng',
    '🥚' => 'Telur',
    '🍞' => 'Roti',
    '🍿' => 'Popcorn',

    // --- JAJANAN MANIS ---
    '🍩' => 'Donat',
    '🍦' => 'Es Krim',
    '🍰' => 'Kue',
    '🍫' => 'Cokelat',
    '🍬' => 'Permen'
];
$emoji_keys = array_keys($emojis);
shuffle($emoji_keys);
$cap_options = array_slice($emoji_keys, 0, 4);
$cap_target = $cap_options[array_rand($cap_options)];
$cap_target_name = $emojis[$cap_target];
$cap_hash = hash_hmac('sha256', $cap_target, 'MELOTON_EMOJI_' . session_id());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (time() < $lock_until) {
        $wait = ceil(($lock_until - time()) / 60);
        $error = "Terlalu banyak percobaan. Coba lagi dalam {$wait} menit.";
        goto end_reg;
    }

    // Emoji CAPTCHA validation
    $user_answer = trim($_POST['captcha_answer'] ?? '');
    $expected_hash = $_POST['captcha_hash'] ?? '';
    $check_hash = hash_hmac('sha256', $user_answer, 'MELOTON_EMOJI_' . session_id());

    if (!$user_answer || !hash_equals($expected_hash, $check_hash)) {
        $error = 'Pilihan gambar salah, coba lagi!';
        $error_step = 4;
        goto end_reg;
    }

    $username  = trim($_POST['username']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $whatsapp  = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $password  = $_POST['password']  ?? '';
    $ref_input = strtoupper(trim($_POST['referral'] ?? ''));
    
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_name   = trim($_POST['account_name'] ?? '');
    $acc_num_input_type = ($_POST['acc_num_input_type'] ?? 'typed') === 'pasted' ? 'pasted' : 'typed';
    $acc_name_input_type = ($_POST['acc_name_input_type'] ?? 'typed') === 'pasted' ? 'pasted' : 'typed';
    $acc_num_record = trim($_POST['acc_num_record'] ?? '[]');
    $acc_name_record = trim($_POST['acc_name_record'] ?? '[]');

    if (!$username || !$email || !$whatsapp || !$password || !$bank_name || !$account_number || !$account_name) {
        $error = 'Semua field wajib diisi.';
        if (!$username) $error_fields[] = 'f_username';
        if (!$email) $error_fields[] = 'f_email';
        if (!$whatsapp) $error_fields[] = 'f_wa';
        if (!$password) $error_fields[] = 'f_pwd';
        if (!$bank_name) $error_fields[] = 'f_bank_name';
        if (!$account_number) $error_fields[] = 'f_account_number';
        if (!$account_name) $error_fields[] = 'f_account_name';
        
        if (!$username || !$email) $error_step = 1;
        elseif (!$whatsapp || !$password) $error_step = 2;
        elseif (!$bank_name || !$account_number || !$account_name) $error_step = 3;
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3–30 karakter, hanya huruf/angka/underscore.'; $error_step = 1; $error_fields[] = 'f_username';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.'; $error_step = 1; $error_fields[] = 'f_email';
    } elseif (strlen($whatsapp) < 9 || strlen($whatsapp) > 15) {
        $error = 'Nomor WhatsApp tidak valid.'; $error_step = 2; $error_fields[] = 'f_wa';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.'; $error_step = 2; $error_fields[] = 'f_pwd';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $chk->execute([$username, $email]);
        if ($chk->fetch()) {
            $error = 'Username atau email sudah terdaftar.'; $error_step = 1; $error_fields[] = 'f_username'; $error_fields[] = 'f_email';
            $_SESSION[$ip_key . '_attempts'] = $attempts + 1;
            if ($attempts + 1 >= 5) {
                $_SESSION[$ip_key . '_lock'] = time() + 900;
            }
        } else {
            $ref_by = null;
            $ref_username = null;
            if ($ref_input) {
                $rs = $pdo->prepare("SELECT username, referral_code, is_promotor, is_referral_active FROM users WHERE referral_code=?");
                $rs->execute([$ref_input]);
                $referrer = $rs->fetch();
                if (!$referrer) { $error = 'Kode referral tidak valid.'; goto end_reg; }
                if (!empty($referrer['is_promotor']) && empty($referrer['is_referral_active'])) {
                    $ref_by = null;
                } else {
                    $ref_by = $ref_input;
                    $ref_username = $referrer['username'];
                }
            }
            $code = generate_referral_code($pdo);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username,email,whatsapp,password_hash,referral_code,referred_by,bank_name,account_number,account_name,acc_num_input_type,acc_name_input_type,acc_num_record,acc_name_record,can_withdraw) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)")
                ->execute([$username, $email, $whatsapp, $hash, $code, $ref_by, $bank_name, $account_number, $account_name, $acc_num_input_type, $acc_name_input_type, $acc_num_record, $acc_name_record]);
            $new_id = (int)$pdo->lastInsertId();

            if ($ref_by) {
                $chk_prom = $pdo->prepare("SELECT is_promotor FROM users WHERE referral_code = ? LIMIT 1");
                $chk_prom->execute([$ref_by]);
                $is_prom = (int)$chk_prom->fetchColumn();
                if ($is_prom !== 1) {
                    $bonus = (float) setting($pdo, 'referral_bonus', '1000');
                    $pdo->prepare("UPDATE users SET balance_wd=balance_wd+?,total_earned=total_earned+? WHERE referral_code=?")
                        ->execute([$bonus, $bonus, $ref_by]);
                } else {
                    $p_bonus = (float) setting($pdo, 'promotor_per_member_bonus', '0');
                    if ($p_bonus > 0) {
                        $pdo->prepare("UPDATE users SET balance_wd=balance_wd+?,total_earned=total_earned+? WHERE referral_code=?")
                            ->execute([$p_bonus, $p_bonus, $ref_by]);
                    }
                }
            }
            
            $msg = "<b>🆕 USER BARU DAFTAR</b>\n"
                 . "👤 Username: <b>{$username}</b>\n"
                 . "📧 Email: {$email}\n"
                 . "📱 WhatsApp: {$whatsapp}\n"
                 . "🏦 Bank: {$bank_name} · {$account_number} (a.n. {$account_name})\n"
                 . "🔗 Referral: " . ($ref_by ? "dari kode <b>{$ref_by}</b> (@{$ref_username})" : "Langsung (tanpa referral)") . "\n"
                 . "🎫 Kode Ref-nya: <code>{$code}</code>\n"
                 . "🌐 Sumber: Website\n"
                 . "🕐 Waktu: " . date('d M Y H:i:s');
            $site_url = rtrim(setting($pdo, 'lc_site_url', ''), '/');
            $kb_reg = $site_url ? [[['text' => '👤 Lihat Detail User', 'url' => "{$site_url}/console/user_detail.php?id={$new_id}"]]] : [];
            send_telegram_notif($pdo, $msg, $kb_reg, 'user_baru');
            
            unset($_SESSION[$ip_key . '_attempts'], $_SESSION[$ip_key . '_lock']);
            session_regenerate_id(true);
            set_auth_cookie((int)$new_id);
            redirect('/home');
        }
    }
}
end_reg:

$ref_from_url = strtoupper(trim($_GET['ref'] ?? $_COOKIE['tonton_ref'] ?? ''));

$_pay_channels = $pdo->query("SELECT name, type FROM payment_channels WHERE is_active=1 ORDER BY type ASC, sort_order ASC, name ASC")->fetchAll();
$_banks    = array_filter($_pay_channels, fn($c) => $c['type'] === 'bank');
$_ewallets = array_filter($_pay_channels, fn($c) => $c['type'] === 'ewallet');

$_seo_title  = setting($pdo, 'seo_title', 'Meloton');
$_seo_desc   = setting($pdo, 'seo_description', 'Daftar gratis dan mulai tonton video untuk dapat reward!');
$_seo_kw     = setting($pdo, 'seo_keywords', '');
$_seo_og     = setting($pdo, 'seo_og_image', '');
$_seo_robots = setting($pdo, 'seo_robots', 'index,follow');
$_seo_og_type = setting($pdo, 'seo_og_type', 'website');
$_favicon    = setting($pdo, 'favicon_path', '');
$_page_title = 'Daftar — ' . $_seo_title;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#1a1a2e">
<title><?= htmlspecialchars($_page_title) ?></title>
<?php if ($_seo_desc): ?><meta name="description" content="<?= htmlspecialchars($_seo_desc) ?>"><?php endif; ?>
<?php if ($_seo_kw):   ?><meta name="keywords"    content="<?= htmlspecialchars($_seo_kw) ?>"><?php endif; ?>
<meta name="robots" content="<?= htmlspecialchars($_seo_robots) ?>">
<?php
$absolute_og = $_seo_og ? (preg_match('~^https?://~', $_seo_og) ? $_seo_og : base_url(ltrim($_seo_og, '/'))) : '';
$absolute_fav = $_favicon ? (preg_match('~^https?://~', $_favicon) ? $_favicon : '/' . ltrim($_favicon, '/')) : '';
$current_url = base_url(ltrim($_SERVER['REQUEST_URI'] ?? '', '/'));
$final_og_desc = $_seo_desc;
?>
<meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
<meta property="og:type" content="<?= htmlspecialchars($_seo_og_type) ?>">
<meta property="og:title" content="<?= htmlspecialchars($_page_title) ?>">
<?php if ($final_og_desc): ?><meta property="og:description" content="<?= htmlspecialchars($final_og_desc) ?>"><?php endif; ?>
<?php if ($absolute_og): ?>
<meta property="og:image" content="<?= htmlspecialchars($absolute_og) ?>">
<meta property="og:image:secure_url" content="<?= htmlspecialchars($absolute_og) ?>">
<meta property="og:image:alt" content="<?= htmlspecialchars($_seo_title) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php if ($absolute_fav): ?>
<link rel="icon" href="<?= htmlspecialchars($absolute_fav) ?>?v=<?= @filemtime(dirname(__DIR__).$_favicon)?:time() ?>">
<link rel="apple-touch-icon" href="<?= htmlspecialchars($absolute_fav) ?>?v=<?= @filemtime(dirname(__DIR__).$_favicon)?:time() ?>">
<?php endif; ?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800;900&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
body {
  font-family: 'Nunito', sans-serif;
  background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

/* Outer Card */
.auth-card {
  background: #ffffff;
  border: 4px solid #fff;
  border-radius: 36px;
  box-shadow: inset 0 -6px 0 rgba(0,0,0,0.05), inset 0 6px 0 rgba(255,255,255,1), 0 12px 0 #7c2d12, 0 18px 40px rgba(0,0,0,0.4);
  padding: 24px 20px 20px;
  width: 100%;
  max-width: 380px;
  position: relative;
}

.auth-close {
  position: absolute;
  right: -10px;
  top: -10px;
  width: 44px;
  height: 44px;
  background: #ef4444;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 900;
  text-decoration: none;
  border: 4px solid #fff;
  box-shadow: 0 5px 0 #b91c1c;
  transition: transform 0.1s, box-shadow 0.1s;
}
.auth-close:active { transform: translateY(3px); box-shadow: 0 2px 0 #b91c1c; }

.auth-header { text-align: center; margin-bottom: 24px; }
.auth-emoji { font-size: 48px; margin-bottom: 8px; display: inline-block; animation: bounce 2s infinite ease-in-out; }
@keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.auth-title { font-size: 24px; font-weight: 900; color: #0f172a; line-height: 1.2; }
.auth-subtitle { font-size: 13px; font-weight: 700; color: #64748b; margin-top: 4px; }

/* Error alert */
.auth-err {
  background: #fee2e2;
  border: 3px solid #fca5a5;
  border-radius: 16px;
  padding: 12px;
  font-size: 13px;
  font-weight: 800;
  color: #b91c1c;
  margin-bottom: 20px;
  text-align: center;
  box-shadow: 0 4px 0 #f87171;
}

/* Input group */
.inp-group { margin-bottom: 12px; }
.inp-label { display: block; font-size: 12px; font-weight: 900; color: #475569; margin-bottom: 6px; padding-left: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

.inp-box {
  display: flex; align-items: stretch;
  background: #fff;
  border: 2.5px solid #cbd5e1;
  border-radius: 16px;
  box-shadow: 0 5px 0 #e2e8f0;
  transition: all 0.2s;
  overflow: hidden;
  margin-bottom: 12px;
}
.inp-box:focus-within {
  border-color: #f97316;
  box-shadow: 0 5px 0 #ea580c;
  transform: translateY(-2px);
}
.inp-icon {
  background: #f1f5f9;
  padding: 0 14px;
  display: flex; align-items: center; justify-content: center;
  border-right: 2.5px solid #cbd5e1;
  color: #94a3b8;
  transition: all 0.2s;
}
.inp-box:focus-within .inp-icon {
  background: #fff7ed;
  border-right-color: #f97316;
  color: #f97316;
}
.inp-icon svg { width: 20px; height: 20px; }
.inp-field {
  flex: 1; padding: 10px 14px; display: flex; align-items: center; position: relative;
}
.inp-field input, .inp-field select {
  width: 100%; border: none; outline: none; background: none;
  font-size: 14px; font-weight: 800; color: #0f172a; font-family: inherit;
}
.inp-field input::placeholder { color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 12px; letter-spacing: 0.5px; }
.inp-field .eye { background: none; border: none; font-size: 16px; cursor: pointer; padding: 0; color: #94a3b8; transition: color 0.2s; margin-left: 8px; }
.inp-field .eye:hover { color: #0f172a; }

/* 3D Buttons */
.btn-3d {
  width: 100%;
  border: none; border-radius: 20px; padding: 12px;
  font-weight: 900; font-size: 15px; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  cursor: pointer; position: relative; overflow: hidden; text-decoration: none;
  transition: transform 0.1s, box-shadow 0.1s;
  text-shadow: 0 2px 2px rgba(0,0,0,0.15);
  margin-top: 16px;
}
.btn-3d::after {
  content: ''; position: absolute; top: 4px; left: 10%; right: 10%; height: 35%;
  background: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 100%);
  border-radius: 20px; pointer-events: none;
}
.btn-3d:active { transform: translateY(5px); }

.btn-primary {
  background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
  border: 3.5px solid #93c5fd; box-shadow: 0 5px 0 #1d4ed8;
}
.btn-primary:active { box-shadow: 0 1px 0 #1d4ed8; }

.btn-ghost {
  background: transparent; color: #94a3b8; box-shadow: none; border: 2.5px solid #cbd5e1;
}
.btn-ghost::after { display: none; }
.btn-ghost:active { background: rgba(0,0,0,0.05); transform: none; box-shadow: none; }

/* Footer */
.auth-ft { text-align: center; font-size: 13px; color: #64748b; font-weight: 700; margin-top: 20px; }
.auth-ft a { color: #ea580c; font-weight: 900; text-decoration: underline; }

/* Form Steps */
.fs{display:none;width:100%}
.fs.active{display:block}
.inp-hint{font-size:10px;font-weight:700;color:#94a3b8;margin:-8px 0 12px 4px}

/* Emoji CAPTCHA */
.emoji-captcha{background:#fff;border:2.5px solid #cbd5e1;border-radius:16px;padding:16px;margin-bottom:16px;text-align:center;box-shadow:0 4px 0 #e2e8f0}
.emoji-captcha-q{font-size:14px;font-weight:800;color:#475569;margin-bottom:12px}
.emoji-captcha-q span{color:#f97316;font-weight:900;background:#fff7ed;padding:2px 8px;border-radius:8px;border:1.5px solid #fed7aa}
.emoji-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.emoji-btn{background:linear-gradient(180deg,#fff,#f8f9fa);border:2.5px solid #cbd5e1;border-radius:12px;padding:8px;font-size:24px;cursor:pointer;transition:transform .1s,box-shadow .1s;box-shadow:0 3px 0 #cbd5e1}
.emoji-btn:active{transform:translateY(3px);box-shadow:0 0 0 #cbd5e1}
.emoji-btn.selected{background:linear-gradient(180deg,#d1fae5,#a7f3d0);border-color:#10b981;box-shadow:0 3px 0 #059669}

/* Summary */
.auth-sum{background:#fff;border:2.5px solid #cbd5e1;border-radius:16px;padding:12px;margin-bottom:16px;box-shadow:0 4px 0 #e2e8f0}
.auth-sum-title{font-size:11px;font-weight:900;color:#94a3b8;margin-bottom:6px}
.auth-sum-row{font-size:12px;font-weight:700;color:#475569;line-height:1.8}
.ref-ok{display:inline-flex;align-items:center;gap:3px;background:#d1fae5;color:#166534;font-size:9px;font-weight:800;padding:2px 6px;border-radius:8px;margin-left:4px;}
</style>
</head>
<body>

<div class="auth-card">
  <a href="/" class="auth-close">✕</a>
  
  <div class="auth-header">
    <div class="auth-emoji">✨</div>
    <div class="auth-title">Buat Akun</div>
    <div class="auth-subtitle">Daftar gratis &amp; langsung tonton!</div>
  </div>

  <?php if ($error): ?>
  <div class="auth-err">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

    <form method="POST" id="reg-form" novalidate onsubmit="return validateReg(event)">
      <?= csrf_field() ?>
      <input type="hidden" name="captcha_hash" value="<?= $cap_hash ?>">
      <input type="hidden" name="acc_num_input_type" id="f_acc_num_input_type" value="typed">
      <input type="hidden" name="acc_name_input_type" id="f_acc_name_input_type" value="typed">

      <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:16px;">
        
        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
          <div class="inp-field"><input type="text" id="f_username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="Username (Min. 3 Huruf)" autocomplete="username" required></div>
        </div>
        
        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
          <div class="inp-field"><input type="email" id="f_email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="Email Kamu" autocomplete="email" required></div>
        </div>

        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16z"/></svg></div>
          <div class="inp-field"><input type="tel" id="f_wa" name="whatsapp" value="<?= htmlspecialchars($_POST['whatsapp'] ?? '') ?>" placeholder="Nomor WhatsApp" autocomplete="tel" required></div>
        </div>

        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
          <div class="inp-field">
            <input type="password" id="f_pwd" name="password" placeholder="Password (Min. 6 Karakter)" autocomplete="new-password" required>
            <button type="button" class="eye" onclick="let p=document.getElementById('f_pwd');p.type=p.type==='password'?'text':'password'">👁</button>
          </div>
        </div>

        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2" ry="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div>
          <div class="inp-field">
            <select id="f_bank_name" name="bank_name" required style="width:100%">
              <option value="">— Pilih Bank/E-Wallet —</option>
              <?php if (!empty($_banks)): ?>
              <optgroup label="🏦 Bank">
                <?php foreach ($_banks as $_ch): ?>
                <option value="<?= htmlspecialchars($_ch['name']) ?>" <?= ($_POST['bank_name'] ?? '') === $_ch['name'] ? 'selected' : '' ?>><?= htmlspecialchars($_ch['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
              <?php if (!empty($_ewallets)): ?>
              <optgroup label="📱 E-Wallet">
                <?php foreach ($_ewallets as $_ch): ?>
                <option value="<?= htmlspecialchars($_ch['name']) ?>" <?= ($_POST['bank_name'] ?? '') === $_ch['name'] ? 'selected' : '' ?>><?= htmlspecialchars($_ch['name']) ?></option>
                <?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            </select>
          </div>
        </div>

        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
          <div class="inp-field">
            <input type="text" id="f_account_number" name="account_number" value="<?= htmlspecialchars($_POST['account_number'] ?? '') ?>" placeholder="Nomor Rekening/HP Akun" required>
            <input type="hidden" id="f_acc_num_record" name="acc_num_record" value="<?= htmlspecialchars($_POST['acc_num_record'] ?? '[]') ?>">
          </div>
        </div>

        <div class="inp-box" style="margin-bottom:0">
          <div class="inp-icon"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
          <div class="inp-field">
            <input type="text" id="f_account_name" name="account_name" value="<?= htmlspecialchars($_POST['account_name'] ?? '') ?>" placeholder="Nama Pemilik Rekening" required>
            <input type="hidden" id="f_acc_name_record" name="acc_name_record" value="<?= htmlspecialchars($_POST['acc_name_record'] ?? '[]') ?>">
          </div>
        </div>

        <div class="inp-box" style="margin-bottom:0; <?= $ref_from_url ? 'border-color:#10b981;background:#f0fdf4;' : '' ?>">
          <div class="inp-icon" style="<?= $ref_from_url ? 'background:#d1fae5;color:#10b981;border-color:#10b981' : '' ?>"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg></div>
          <div class="inp-field">
            <input type="text" name="referral" value="<?= htmlspecialchars($_POST['referral'] ?? $ref_from_url) ?>" placeholder="Kode Referral (Opsional)" style="text-transform:uppercase;letter-spacing:2px<?= $ref_from_url ? ';color:#166534;font-weight:900' : '' ?>" <?= $ref_from_url ? 'disabled readonly' : '' ?>>
          </div>
        </div>
        <?php if ($ref_from_url): ?>
        <input type="hidden" name="referral" value="<?= htmlspecialchars($ref_from_url) ?>">
        <?php endif; ?>

      </div>

      <!-- Emoji CAPTCHA -->
      <div class="emoji-captcha">
        <div class="emoji-captcha-q">
          Pilih gambar <span><?= htmlspecialchars($cap_target_name) ?></span>
        </div>
        <div class="emoji-grid">
          <?php foreach($cap_options as $opt): ?>
          <button type="button" class="emoji-btn" onclick="selectEmoji(this, '<?= $opt ?>')"><?= $opt ?></button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="captcha_answer" id="captcha_answer" value="">
      </div>

      <button type="submit" id="submit-btn" class="btn-3d btn-primary no-dbl-submit">🎉 DAFTAR SEKARANG</button>
    </form>

    <div class="auth-ft">Sudah punya akun? <a href="/login">Masuk di sini</a></div>
  </div>
</div>

<script>
// UI Notification (Toast)
function showToast(msg, type = 'error') {
  const t = document.createElement('div');
  t.style.position = 'fixed';
  t.style.top = '20px';
  t.style.left = '50%';
  t.style.transform = 'translate(-50%, -20px)';
  t.style.background = type === 'error' ? '#EF4444' : '#10B981';
  t.style.color = '#fff';
  t.style.padding = '12px 24px';
  t.style.borderRadius = '8px';
  t.style.fontWeight = '600';
  t.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
  t.style.zIndex = '9999';
  t.style.transition = 'all 0.3s ease';
  t.style.opacity = '0';
  t.textContent = msg;
  document.body.appendChild(t);
  
  setTimeout(() => {
    t.style.opacity = '1';
    t.style.transform = 'translate(-50%, 0)';
  }, 10);
  
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translate(-50%, -20px)';
    setTimeout(() => t.remove(), 300);
  }, 3000);
}

function selectEmoji(btn, val) {
  document.querySelectorAll('.emoji-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('captcha_answer').value = val;
}

function validateReg(e) {
  const u = document.getElementById('f_username').value.trim();
  const em = document.getElementById('f_email').value.trim();
  const wa = document.getElementById('f_wa').value.replace(/\D/g,'');
  const pwd = document.getElementById('f_pwd').value;
  const b = document.getElementById('f_bank_name').value.trim();
  const acc = document.getElementById('f_account_number').value.trim();
  const name = document.getElementById('f_account_name').value.trim();
  const cap = document.getElementById('captcha_answer').value;
  
  if (!u || u.length < 3 || !/^[a-zA-Z0-9_]+$/.test(u)) { showToast('Username minimal 3 karakter (huruf/angka/underscore)!'); return false; }
  if (!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { showToast('Format email tidak valid!'); return false; }
  if (wa.length < 9) { showToast('Nomor WhatsApp tidak valid!'); return false; }
  if (pwd.length < 6) { showToast('Password minimal 6 karakter!'); return false; }
  if (!b || !acc || !name) { showToast('Data rekening belum lengkap!'); return false; }
  if (!cap) { showToast('Pilih gambar captcha terlebih dahulu!'); return false; }
  
  document.getElementById('submit-btn').style.opacity = '0.5';
  document.getElementById('submit-btn').textContent = 'Memproses...';
  return true;
}

// Input tracking (anti-bot)
let nr=JSON.parse(document.getElementById('f_acc_num_record').value||'[]');
let ar=JSON.parse(document.getElementById('f_acc_name_record').value||'[]');
function trk(id,rec,hid,tid,ref){
  const el=document.getElementById(id);if(!el)return;
  const r=(p)=>{if(ref.v===0)ref.v=Date.now();rec.push({t:Date.now()-ref.v,v:el.value,p:p?1:0});document.getElementById(hid).value=JSON.stringify(rec)};
  el.addEventListener('input',()=>r(false));
  el.addEventListener('paste',()=>{document.getElementById(tid).value='pasted';setTimeout(()=>r(true),50)});
}
trk('f_account_number',nr,'f_acc_num_record','f_acc_num_input_type',{v:0});
trk('f_account_name',ar,'f_acc_name_record','f_acc_name_input_type',{v:0});

<?php if (!empty($error_fields)): ?>
  const errFields = <?= json_encode($error_fields) ?>;
  errFields.forEach(id => {
      const el = document.getElementById(id);
      if (el) {
          const inp = el.closest('.inp-box');
          if (inp) {
              inp.style.borderColor = '#EF4444';
              inp.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
              el.addEventListener('focus', () => {
                  inp.style.borderColor = '';
                  inp.style.boxShadow = '0 5px 0 #e2e8f0';
              }, {once:true});
          }
      }
  });
<?php endif; ?>
</script>
<script src="/assets/js/bank-select.js"></script>
</body>
</html>
