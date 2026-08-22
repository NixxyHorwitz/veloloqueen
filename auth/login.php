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
$_seo_title  = setting($pdo, 'seo_title', 'TontonCuan');
$_seo_desc   = setting($pdo, 'seo_description', 'Tonton video dan kumpulkan reward di TontonCuan!');
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
<meta name="theme-color" content="#9a5aff">
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
<meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
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
  background: #eaf7ec; /* Light pastel mint background */
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

/* Container Wrapper simulating phone shell / card */
.login-container {
  background: #eaf7ec;
  border: 4px solid #181818;
  border-radius: 40px;
  box-shadow: 8px 8px 0 #181818;
  width: 100%;
  max-width: 395px;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

/* Header Banner - Purple with Curve */
.login-header {
  background: #9a5aff; /* Bright purple */
  border-bottom: 4px solid #181818;
  border-bottom-left-radius: 200px 30px;
  border-bottom-right-radius: 200px 80px;
  padding: 30px 24px 44px 24px;
  position: relative;
  color: #fff;
}

.header-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

/* White Key Icon Box */
.icon-box {
  background: #fff;
  border: 3px solid #181818;
  border-radius: 18px;
  box-shadow: 3px 3px 0 #181818;
  width: 54px;
  height: 54px;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Portal Version Badge */
.version-badge {
  background: #facc15; /* Yellow */
  border: 2px solid #181818;
  border-radius: 9999px;
  padding: 4px 12px;
  color: #181818;
  font-size: 10px;
  font-weight: 900;
  display: flex;
  align-items: center;
  gap: 6px;
  box-shadow: 1.5px 1.5px 0 #181818;
}
.version-badge .dot {
  width: 6px;
  height: 6px;
  background: #181818;
  border-radius: 50%;
}

/* Titles */
.welcome-txt {
  font-size: 18px;
  font-weight: 800;
  color: rgba(255, 255, 255, 0.95);
  margin-top: 24px;
  line-height: 1;
}
.signin-txt {
  font-size: 38px;
  font-weight: 900;
  color: #fff;
  margin-top: 4px;
  line-height: 1;
  letter-spacing: -0.5px;
}

/* Body / Form Section */
.login-body {
  padding: 32px 24px 28px 24px;
}

/* Error Alert */
.auth-err {
  background: #fee2e2;
  border: 3px solid #181818;
  border-radius: 20px;
  padding: 12px;
  font-size: 13px;
  font-weight: 800;
  color: #b91c1c;
  margin-bottom: 24px;
  text-align: center;
  box-shadow: 3px 3px 0 #181818;
}

/* Neo-brutalist Input Box Group */
.inp-group {
  position: relative;
  margin-bottom: 28px;
}

/* Input Floating Label Badge */
.inp-label {
  position: absolute;
  top: -12px;
  left: 20px;
  background: #fff;
  border: 2px solid #181818;
  border-radius: 8px;
  padding: 1px 10px;
  font-size: 10px;
  font-weight: 900;
  color: #181818;
  z-index: 10;
  letter-spacing: 0.2px;
  text-transform: uppercase;
}

/* Main Input wrapper */
.inp-wrapper {
  background: #fff;
  border: 4px solid #181818;
  border-radius: 28px;
  box-shadow: 4px 4px 0 #181818;
  display: flex;
  align-items: center;
  height: 58px;
  padding: 0 16px;
  position: relative;
  transition: transform 0.1s, box-shadow 0.1s;
}
.inp-wrapper:focus-within {
  transform: translate(-1px, -1px);
  box-shadow: 5px 5px 0 #181818;
}

/* Yellow prefix +62 badge */
.prefix-badge {
  background: #ffcf00; /* Yellow prefix */
  border: 2.5px solid #181818;
  border-radius: 14px;
  padding: 4px 10px;
  font-weight: 900;
  font-size: 13px;
  color: #181818;
  margin-right: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 1.5px 1.5px 0 #181818;
  user-select: none;
}

.inp-wrapper input {
  flex: 1;
  border: none;
  outline: none;
  background: none;
  font-family: inherit;
  font-size: 15px;
  font-weight: 800;
  color: #181818;
  width: 100%;
}
.inp-wrapper input::placeholder {
  color: #a1a1aa;
  font-weight: 700;
}

/* Visibility eye button */
.eye-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 6px;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.1s;
}
.eye-btn:hover {
  color: #181818;
}
.eye-btn svg {
  width: 20px;
  height: 20px;
  stroke-width: 2.5;
}

/* Otentikasi Button */
.submit-btn {
  width: 100%;
  height: 56px;
  border: 3px solid #181818;
  border-radius: 28px;
  background: #3b82f6; /* Blue button */
  color: #fff;
  font-size: 15px;
  font-weight: 900;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 4px 4px 0 #181818;
  cursor: pointer;
  transition: transform 0.1s, box-shadow 0.1s;
  margin-top: 32px;
}
.submit-btn:active {
  transform: translate(3px, 3px);
  box-shadow: 1px 1px 0 #181818;
}

/* Footer / Links */
.footer-divider {
  border: none;
  border-top: 2px dashed #cbd5e1;
  margin: 28px 0 20px 0;
}

.footer-links {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.footer-label {
  font-size: 9px;
  font-weight: 900;
  color: #64748b;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.footer-link {
  font-size: 13px;
  font-weight: 900;
  color: #181818;
  text-decoration: underline;
  transition: color 0.1s;
}
.footer-link:hover {
  color: #3b82f6;
}
</style>
</head>
<body>

<div class="login-container">
  <!-- Header Banner -->
  <div class="login-header">
    <div class="header-top">
      <div class="icon-box">
        <!-- SVG Key Icon -->
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#181818" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
        </svg>
      </div>
      <div class="version-badge">
        <span class="dot"></span>[PORTAL-OTENTIKASI-V1.9]
      </div>
    </div>
    <div class="welcome-txt">Welcome,</div>
    <div class="signin-txt">Sign In!</div>
  </div>

  <!-- Body Content -->
  <div class="login-body">
    <?php if ($error): ?>
      <div class="auth-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>

      <!-- Phone / login Input -->
      <div class="inp-group">
        <div class="inp-label">Telepon Genggam (HP)</div>
        <div class="inp-wrapper">
          <div class="prefix-badge">+62</div>
          <input type="text" name="login" value="<?= htmlspecialchars($_POST['login'] ?? '') ?>" placeholder="81234567890" autofocus autocomplete="username" required>
        </div>
      </div>

      <!-- Password Input -->
      <div class="inp-group">
        <div class="inp-label">Kata Sandi (Password)</div>
        <div class="inp-wrapper">
          <input type="password" id="pwd" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required>
          <button type="button" class="eye-btn" onclick="let p=document.getElementById('pwd');p.type=p.type==='password'?'text':'password'" title="Lihat password">
            <!-- Eye icon -->
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Submit Button -->
      <button type="submit" class="submit-btn">
        <span>OTENTIKASI MASUK</span>
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
          <polyline points="12 5 19 12 12 19"></polyline>
        </svg>
      </button>
    </form>

    <hr class="footer-divider">

    <!-- Footer Links -->
    <div class="footer-links">
      <div class="footer-item">
        <span class="footer-label">Belum punya akun?</span>
        <a href="/register" class="footer-link">Daftar Baru</a>
      </div>
      <div class="footer-item" style="text-align: right;">
        <span class="footer-label">Butuh bantuan?</span>
        <a href="https://wa.me/6281234567890" target="_blank" class="footer-link">Hubungi CS</a>
      </div>
    </div>
  </div>
</div>

</body>
</html>
