<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
if (auth_user($pdo)) redirect('/home');
csrf_enforce();

$error = '';

// Rate limit
$ip_key   = 'login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
$attempts = (int)($_SESSION[$ip_key . '_att'] ?? 0);
$lock_until = (int)($_SESSION[$ip_key . '_lock'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (time() < $lock_until) {
        $wait  = ceil(($lock_until - time()) / 60);
        $error = "Akun terkunci. Coba lagi dalam {$wait} menit.";
        goto end_login;
    }

    $login = trim($_POST['login'] ?? '');
    $pwd   = $_POST['password'] ?? '';
    $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $s     = $pdo->prepare("SELECT * FROM users WHERE {$field}=? AND is_active=1");
    $s->execute([$login]);
    $user  = $s->fetch();

    if ($user && password_verify($pwd, $user['password_hash'])) {
        unset($_SESSION[$ip_key . '_att'], $_SESSION[$ip_key . '_lock']);
        session_regenerate_id(true);
        set_auth_cookie((int)$user['id']);
        redirect('/home');
    }

    $new_att = $attempts + 1;
    $_SESSION[$ip_key . '_att'] = $new_att;
    if ($new_att >= 5) {
        $_SESSION[$ip_key . '_lock'] = time() + 600;
        $error = 'Terlalu banyak percobaan. Coba lagi dalam 10 menit.';
    } else {
        $left  = 5 - $new_att;
        $error = "Username/email atau password salah. Sisa percobaan: {$left}";
    }
}
end_login:
?>
<?php
// Load SEO settings
$_seo_title  = setting($pdo, 'seo_title', 'Meloton');
$_seo_desc   = setting($pdo, 'seo_description', 'Tonton video dan kumpulkan reward di Meloton!');
$_seo_kw     = setting($pdo, 'seo_keywords', '');
$_seo_og     = setting($pdo, 'seo_og_image', '');
$_seo_robots = setting($pdo, 'seo_robots', 'index,follow');
$_seo_og_type = setting($pdo, 'seo_og_type', 'website');
$_favicon    = setting($pdo, 'favicon_path', '');
$_page_title = 'Masuk — ' . $_seo_title;
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
  background: #fff8f0;
  border: 4px solid #fff;
  border-radius: 36px;
  box-shadow: 0 10px 0 #c2410c, 0 20px 40px rgba(0,0,0,0.3);
  padding: 32px 24px 24px;
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
.auth-title { font-size: 22px; font-weight: 900; color: #0f172a; line-height: 1.2; }
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
.inp-box {
  display: flex; align-items: stretch;
  background: #fff;
  border: 3.5px solid #cbd5e1;
  border-radius: 20px;
  box-shadow: 0 6px 0 #e2e8f0;
  transition: all 0.2s;
  overflow: hidden;
  margin-bottom: 20px;
}
.inp-box:focus-within {
  border-color: #f97316;
  box-shadow: 0 6px 0 #ea580c;
  transform: translateY(-2px);
}
.inp-icon {
  background: #f1f5f9;
  padding: 0 16px;
  display: flex; align-items: center; justify-content: center;
  border-right: 3.5px solid #cbd5e1;
  color: #94a3b8;
  transition: all 0.2s;
}
.inp-box:focus-within .inp-icon {
  background: #fff7ed;
  border-right-color: #f97316;
  color: #f97316;
}
.inp-icon svg { width: 22px; height: 22px; }
.inp-field {
  flex: 1; padding: 14px 16px; display: flex; align-items: center; position: relative;
}
.inp-field input {
  width: 100%; border: none; outline: none; background: none;
  font-size: 15px; font-weight: 800; color: #0f172a; font-family: inherit;
}
.inp-field input::placeholder { color: #94a3b8; font-weight: 700; text-transform: uppercase; font-size: 13px; letter-spacing: 0.5px; }
.inp-field .eye { background: none; border: none; font-size: 18px; cursor: pointer; padding: 0; color: #94a3b8; transition: color 0.2s; margin-left: 10px; }
.inp-field .eye:hover { color: #0f172a; }

/* 3D Buttons */
.btn-3d {
  border: none; border-radius: 20px; padding: 14px;
  font-weight: 900; font-size: 16px; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  cursor: pointer; position: relative; overflow: hidden; text-decoration: none;
  transition: transform 0.1s, box-shadow 0.1s;
  text-shadow: 0 2px 2px rgba(0,0,0,0.15);
}
.btn-3d::after {
  content: ''; position: absolute; top: 4px; left: 10%; right: 10%; height: 35%;
  background: linear-gradient(180deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 100%);
  border-radius: 20px; pointer-events: none;
}
.btn-3d:active { transform: translateY(5px); }

.auth-actions {
  display: flex; gap: 12px; margin-top: 10px;
}

.btn-primary {
  flex: 1;
  background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
  border: 3.5px solid #93c5fd; box-shadow: 0 6px 0 #1d4ed8;
}
.btn-primary:active { box-shadow: 0 1px 0 #1d4ed8; }

.btn-secondary {
  width: 64px; flex-shrink: 0;
  background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #fff;
  border: 3.5px solid #fde68a; box-shadow: 0 6px 0 #d97706;
}
.btn-secondary:active { box-shadow: 0 1px 0 #d97706; }

/* Footer */
.auth-ft { text-align: center; font-size: 13px; color: #64748b; font-weight: 700; margin-top: 24px; }
.auth-ft a { color: #ea580c; font-weight: 900; text-decoration: underline; }
</style>
</head>
<body>

<div class="auth-card">
  <a href="/" class="auth-close">✕</a>
  
  <div class="auth-header">
    <div class="auth-emoji">👋</div>
    <div class="auth-title">Selamat Datang</div>
    <div class="auth-subtitle">Masuk ke akunmu dan mulai nonton!</div>
  </div>

  <?php if ($error): ?>
  <div class="auth-err">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>

    <div class="inp-box">
      <div class="inp-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div class="inp-field">
        <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" placeholder="Username / Email" autofocus autocomplete="username">
      </div>
    </div>

    <div class="inp-box">
      <div class="inp-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
      </div>
      <div class="inp-field">
        <input type="password" id="pwd" name="password" placeholder="Password" autocomplete="current-password">
        <button type="button" class="eye" onclick="let p=document.getElementById('pwd');p.type=p.type==='password'?'text':'password'">👁</button>
      </div>
    </div>

    <div class="auth-actions">
      <a href="/register" class="btn-3d btn-secondary" title="Daftar Baru">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg>
      </a>
      <button type="submit" class="btn-3d btn-primary">MASUK SEKARANG 🚀</button>
    </div>
  </form>
</div>

</body>
</html>
