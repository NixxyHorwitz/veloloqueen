<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$flash = $flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'claim';
    
    // -- AJAX Check Endpoint --
    if ($action === 'check') {
        header('Content-Type: application/json');
        $code_input = strtoupper(trim($_POST['code'] ?? ''));
        if (!$code_input) { echo json_encode(['error' => 'Masukkan kode redeem kamu dulu ya!']); exit; }
        
        $stmt = $pdo->prepare("SELECT * FROM redeem_codes WHERE code = ?");
        $stmt->execute([$code_input]);
        $codeData = $stmt->fetch();
        
        if (!$codeData) { echo json_encode(['error' => 'Kode redeem-nya gak ketemu atau udah gak valid nih.']); exit; }
        if ($codeData['expires_at'] && strtotime($codeData['expires_at']) < time()) { echo json_encode(['error' => 'Kode redeem ini udah kedaluwarsa ya.']); exit; }
        if ($codeData['max_claims'] > 0 && $codeData['claims_count'] >= $codeData['max_claims']) { echo json_encode(['error' => 'Kuota klaim buat kode redeem ini udah abis nih.']); exit; }
        if (!empty($codeData['target_users'])) {
            $allowed = array_map('trim', explode(',', $codeData['target_users']));
            if (!in_array($user['username'], $allowed, true) && !in_array($user['email'], $allowed, true)) {
                echo json_encode(['error' => 'Kode redeem ini khusus buat user tertentu aja ya.']);
                exit;
            }
        }
        
        $chk = $pdo->prepare("SELECT id FROM user_redeems WHERE user_id = ? AND code_id = ?");
        $chk->execute([$user['id'], $codeData['id']]);
        if ($chk->fetch()) { echo json_encode(['error' => 'Kamu udah pernah klaim kode ini sebelumnya.']); exit; }
        
        $msg_parts = [];
        if ($codeData['reward_wd'] > 0) $msg_parts[] = 'Saldo Penarikan: ' . format_rp((float)$codeData['reward_wd']);
        if ($codeData['reward_dep'] > 0) $msg_parts[] = 'Saldo Beli: ' . format_rp((float)$codeData['reward_dep']);
        if ($codeData['reward_level_id']) {
            $ls = $pdo->prepare("SELECT name FROM memberships WHERE id = ?");
            $ls->execute([$codeData['reward_level_id']]);
            if ($lname = $ls->fetchColumn()) $msg_parts[] = 'Level: ' . $lname;
        }
        
        echo json_encode(['ok' => true, 'details' => $msg_parts]);
        exit;
    }
    // -- End AJAX Check --

    $code_input = strtoupper(trim($_POST['code'] ?? ''));
    if (!$code_input) {
        $flash = 'Masukkan kode redeem kamu dulu ya!';
        $flashType = 'error';
    } else {
        $pdo->beginTransaction();
        
        // Cek kode exist dan valid
        $stmt = $pdo->prepare("SELECT * FROM redeem_codes WHERE code = ? FOR UPDATE");
        $stmt->execute([$code_input]);
        $codeData = $stmt->fetch();
        
        if (!$codeData) {
            $flash = 'Kode redeem-nya gak ketemu atau udah gak valid nih.';
            $flashType = 'error';
        } else {
            if ($codeData['expires_at'] && strtotime($codeData['expires_at']) < time()) {
                $flash = 'Kode redeem ini udah kedaluwarsa ya.';
                $flashType = 'error';
            } elseif ($codeData['max_claims'] > 0 && $codeData['claims_count'] >= $codeData['max_claims']) {
                $flash = 'Kuota klaim buat kode redeem ini udah abis nih.';
                $flashType = 'error';
            } elseif (!empty($codeData['target_users']) && 
                      !in_array($user['username'], array_map('trim', explode(',', $codeData['target_users'])), true) && 
                      !in_array($user['email'], array_map('trim', explode(',', $codeData['target_users'])), true)) {
                $flash = 'Kode redeem ini khusus buat user tertentu aja ya.';
                $flashType = 'error';
            } else {
                // Cek apakah user sudah pernah klaim
                $chk = $pdo->prepare("SELECT id FROM user_redeems WHERE user_id = ? AND code_id = ?");
                $chk->execute([$user['id'], $codeData['id']]);
                if ($chk->fetch()) {
                    $flash = 'Kamu udah pernah klaim kode ini sebelumnya.';
                    $flashType = 'error';
                } else {
                    // Record klaim
                    $pdo->prepare("INSERT INTO user_redeems (user_id, code_id) VALUES (?, ?)")
                        ->execute([$user['id'], $codeData['id']]);
                        
                    $pdo->prepare("UPDATE redeem_codes SET claims_count = claims_count + 1 WHERE id = ?")
                        ->execute([$codeData['id']]);
                        
                    // Berikan reward ke user
                    $r_wd  = (float)$codeData['reward_wd'];
                    $r_dep = (float)$codeData['reward_dep'];
                    $r_lvl = $codeData['reward_level_id'];
                    
                    $updateSql = "UPDATE users SET balance_wd = balance_wd + ?, balance_dep = balance_dep + ?, total_earned = total_earned + ?";
                    $updateParams = [$r_wd, $r_dep, $r_wd]; // hanya WD yang dihitung total earned (opsional, tergantung logic bisnis)
 
                    $level_name = '';
                    if ($r_lvl) {
                        // Get level duration
                        $ls = $pdo->prepare("SELECT name, duration_days FROM memberships WHERE id = ?");
                        $ls->execute([$r_lvl]);
                        $levelData = $ls->fetch();
                        if ($levelData) {
                            $level_name = $levelData['name'];
                            $days = (int)$levelData['duration_days'];
                            $updateSql .= ", membership_id = ?, membership_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)";
                            $updateParams[] = $r_lvl;
                            $updateParams[] = $days;
                        }
                    }
 
                    $updateSql .= " WHERE id = ?";
                    $updateParams[] = $user['id'];
                    
                    $pdo->prepare($updateSql)->execute($updateParams);
                        
                    // Re-fetch user to reflect changes
                    $usrStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $usrStmt->execute([$user['id']]);
                    $user = $usrStmt->fetch();
                    
                    $pdo->commit();
                    
                    $msg_parts = [];
                    if ($r_wd > 0) $msg_parts[] = 'Saldo Penarikan ' . format_rp($r_wd);
                    if ($r_dep > 0) $msg_parts[] = 'Saldo Beli ' . format_rp($r_dep);
                    if ($level_name) $msg_parts[] = 'Level ' . $level_name;
                    
                    $flash = '🎉 Hore! Kamu dapet ' . implode(', ', $msg_parts) . '. Selamat ya!';
                    $flashType = 'success';
                    goto done_redeem;
                }
            }
        }
        $pdo->rollBack();
    }
}
done_redeem:

$pageTitle  = 'Redeem Code  ';
$activePage = 'redeem';
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="page-title-bar">
  <h1>🎁 Kode Redeem</h1>
  <p>Tukarkan kodemu dan dapatkan reward melimpah!</p>
</div>

<?php if ($flash): ?>
<div class="alert alert--<?= $flashType ?>" style="margin-bottom:16px"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="card card--mint" style="margin-bottom:16px">
  <div class="card__body">
    <div style="font-size:13px;font-weight:800;margin-bottom:8px">🎟️ Masukkan Kode Redeem</div>
    <form id="form-claim" method="POST" onsubmit="checkRedeem(event)">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="claim">
      <div class="form-group" style="margin-bottom:16px">
        <input class="form-control" type="text" name="code" 
               placeholder="Contoh: TONTONVIP" style="text-transform:uppercase;letter-spacing:2px;font-weight:700" required>
      </div>
      <button type="submit" id="btn-check" class="btn btn--primary btn--full">Cek & Klaim</button>
    </form>
  </div>
</div>

<!-- Cara Kerja -->
<div class="card" style="margin-bottom:16px">
  <div class="card__header"><div class="card__title">💡 Informasi Kode Redeem</div></div>
  <div class="card__body" style="font-size:12px;color:#555">
    <ul style="padding-left:16px;margin:0">
      <li style="margin-bottom:4px">Pastikan kode yang dimasukkan sudah benar (huruf besar/kecil otomatis disesuaikan).</li>
      <li style="margin-bottom:4px">Satu akun hanya dapat mengklaim satu kode maksimal 1 (satu) kali.</li>
      <li>Kode redeem dapat memberikan kombinasi reward berupa <strong>Saldo Penarikan, Saldo Beli, maupun Level (Membership)</strong>.</li>
    </ul>
  </div>
</div>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

<!-- Neobrutalism Modal Confirm -->
<div id="brutal-confirm" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);">
  <div class="card card--mint" style="width:100%;max-width:340px;box-shadow:6px 6px 0 var(--ink);border:3px solid var(--ink);border-radius:12px;animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
    <div class="card__header" style="background:var(--brand);border-bottom:3px solid var(--ink);border-radius:9px 9px 0 0;padding:12px 16px;">
      <div class="card__title" style="color:var(--ink);font-weight:900;font-size:16px;">🎁 Konfirmasi Klaim</div>
    </div>
    <div class="card__body" style="padding:16px;background:#fff;border-radius:0 0 9px 9px;">
      <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:#333;">Kode ini berisi reward berikut:</div>
      <ul id="brutal-confirm-list" style="margin:0 0 16px 20px;padding:0;font-size:14px;font-weight:900;color:var(--brand);">
      </ul>
      <div style="font-size:12px;color:#666;margin-bottom:20px;font-weight:600;">Apakah kamu yakin ingin mengklaimnya sekarang?</div>
      <div style="display:flex;gap:12px;">
        <button type="button" onclick="document.getElementById('brutal-confirm').style.display='none'" class="btn" style="flex:1;background:#eee;color:var(--ink);border:2.5px solid var(--ink);font-weight:800;border-radius:8px;">Batal</button>
        <button type="button" onclick="confirmBrutalClaim()" class="btn btn--primary" style="flex:1.5;background:var(--brand);color:var(--ink);border:2.5px solid var(--ink);font-weight:900;border-radius:8px;box-shadow:2px 2px 0 var(--ink);">Klaim Sekarang</button>
      </div>
    </div>
  </div>
</div>
<style>
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
</style>

<script>
let tempForm = null;

function confirmBrutalClaim() {
    if (tempForm) {
        tempForm.onsubmit = null;
        tempForm.submit();
    }
}

function checkRedeem(e) {
    e.preventDefault();
    const form = document.getElementById('form-claim');
    const code = form.querySelector('input[name="code"]').value;
    const btn = document.getElementById('btn-check');
    
    btn.disabled = true;
    btn.innerText = 'Mengecek...';
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check&code=' + encodeURIComponent(code) + '&_csrf=' + encodeURIComponent(form.querySelector('input[name="_csrf"]')?.value || '')
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerText = 'Cek & Klaim';
        if (res.error) {
            if (typeof nToast !== 'undefined') {
                nToast(res.error, 'error');
            } else {
                alert(res.error);
            }
        } else {
            const list = document.getElementById('brutal-confirm-list');
            list.innerHTML = res.details.map(d => `<li style="margin-bottom:6px;">${d}</li>`).join('');
            tempForm = form;
            document.getElementById('brutal-confirm').style.display = 'flex';
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerText = 'Cek & Klaim';
        if (typeof nToast !== 'undefined') {
            nToast('Terjadi kesalahan jaringan.', 'error');
        } else {
            alert('Terjadi kesalahan jaringan.');
        }
    });
}
</script>
require dirname(__DIR__) . '/partials/header.php';
?>
<style>
/* ══════════════════════════════════════════════
   REDEEM PAGE — CASUAL GAME STYLE (ULTRA COMPACT)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; margin: 0; padding: 0; font-family: 'Nunito', sans-serif; }

/* ── BLUE TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 16px 14px 20px; border-bottom: 3px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 20px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 3px 0 #1e3a8a; z-index: 2; margin-bottom: 2px; letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.wd-top-sub { position: relative; font-size: 11px; font-weight: 800; color: #bae6fd; z-index: 2; }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 14px 14px 100px; position: relative; z-index: 2; margin-top: 0; min-height: 80vh; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 40px 40px; background-position: 0 0, 20px 20px; pointer-events: none; z-index: -1; }

/* ── CARDS ── */
.c-group { background: #ffffff; border: 2.5px solid #1e3a8a; border-radius: 12px; box-shadow: 0 3px 0 #1e3a8a; overflow: hidden; margin-bottom: 14px; position: relative; }
.c-hdr { background: linear-gradient(135deg, #fbbf24, #f59e0b); padding: 8px 10px; font-size: 12px; font-weight: 900; color: #78350f; border-bottom: 2.5px solid #d97706; display: flex; align-items: center; gap: 6px; }
.c-hdr i { font-size: 16px; color: #b45309; }
.c-hdr--blue { background: linear-gradient(135deg, #60a5fa, #3b82f6); color: #fff; border-bottom-color: #1d4ed8; text-shadow: 0 2px 0 #1e40af; }
.c-hdr--blue i { color: #bfdbfe; }
.c-body { padding: 12px; background: #fff; }

/* ── FORMS ── */
.f-input { width: 100%; background: #f8fafc; border: 2.5px solid #cbd5e1; border-radius: 10px; padding: 12px 14px; font-size: 16px; font-weight: 900; color: #334155; font-family: 'Nunito', sans-serif; transition: border-color 0.2s; text-transform: uppercase; letter-spacing: 2px; text-align: center; margin-bottom: 12px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05); }
.f-input:focus { outline: none; border-color: #3b82f6; background: #fff; }
.f-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 900; color: #fff; background: linear-gradient(135deg, #34d399, #059669); border: 2.5px solid #047857; box-shadow: 0 4px 0 #047857; cursor: pointer; transition: transform 0.1s; text-shadow: 0 1px 1px rgba(0,0,0,0.3); }
.f-btn:active { transform: translateY(4px); box-shadow: none; }
.f-btn:disabled { opacity: 0.7; pointer-events: none; }

/* ── ALERT ── */
.f-alert { padding: 10px; border-radius: 8px; font-size: 11px; font-weight: 800; margin-bottom: 12px; border: 2px solid; text-align: center; }
.f-alert.success { background: #dcfce7; color: #166534; border-color: #4ade80; }
.f-alert.error { background: #fee2e2; color: #991b1b; border-color: #f87171; }

/* ── LIST INFO ── */
.info-list { padding-left: 20px; margin: 0; font-size: 11px; font-weight: 700; color: #475569; line-height: 1.4; }
.info-list li { margin-bottom: 6px; }

/* ── MODAL BRUTAL ── */
#brutal-confirm { display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.8);z-index:9999;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(4px); }
.m-card { width:100%;max-width:320px;background:#fff;border:3px solid #1e3a8a;border-radius:14px;box-shadow:0 6px 0 #1e3a8a;animation:popIn 0.3s cubic-bezier(0.175,0.885,0.32,1.275); overflow:hidden; }
.m-hdr { background:linear-gradient(135deg,#60a5fa,#3b82f6);border-bottom:3px solid #1e3a8a;padding:12px 16px;font-weight:900;color:#fff;font-size:14px;display:flex;align-items:center;gap:6px;text-shadow:0 2px 0 #1e40af; }
.m-body { padding:16px; }
.m-btn-row { display:flex;gap:10px;margin-top:16px; }
.m-btn { flex:1;padding:10px;border-radius:10px;font-weight:900;font-size:12px;text-align:center;cursor:pointer;transition:transform .1s; }
.m-btn.cancel { background:#f1f5f9;border:2.5px solid #94a3b8;color:#475569;box-shadow:0 3px 0 #94a3b8; }
.m-btn.confirm { background:linear-gradient(135deg,#f59e0b,#d97706);border:2.5px solid #9a3412;color:#fff;box-shadow:0 3px 0 #9a3412;text-shadow:0 1px 1px rgba(0,0,0,0.3); }
.m-btn:active { transform:translateY(3px);box-shadow:none; }
@keyframes popIn { 0% { transform: scale(0.8); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

/* ── GIF CONTAINER ── */
.gif-container { text-align: center; margin-bottom: -10px; position: relative; z-index: 5; }
.gif-container img { width: 120px; height: auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title"><i class="ph-bold ph-gift"></i> Kode Redeem</div>
  <div class="wd-top-sub">Tukarkan kodemu dan panen reward melimpah!</div>
</div>

<div class="wd-body">
  <?php if ($flash): ?>
  <div class="f-alert <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="gif-container">
    <img src="/assets/ccg.gif" alt="Buaya Makan Kado">
  </div>

  <div class="c-group">
    <div class="c-hdr"><i class="ph-bold ph-ticket"></i> Masukkan Kode</div>
    <div class="c-body">
      <form id="form-claim" method="POST" onsubmit="checkRedeem(event)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="claim">
        <input class="f-input" type="text" name="code" placeholder="CONTOH: TONTONVIP" required>
        <button type="submit" id="btn-check" class="f-btn"><i class="ph-bold ph-gift"></i> Cek & Klaim</button>
      </form>
    </div>
  </div>

  <!-- Cara Kerja -->
  <div class="c-group">
    <div class="c-hdr c-hdr--blue"><i class="ph-bold ph-info"></i> Info Kode Redeem</div>
    <div class="c-body">
      <ul class="info-list">
        <li>Pastikan kode yang dimasukkan sudah benar (huruf besar/kecil otomatis disesuaikan).</li>
        <li>Satu akun hanya dapat mengklaim satu kode maksimal 1 (satu) kali.</li>
        <li>Kode redeem dapat memberikan kombinasi reward berupa <strong>Saldo Penarikan, Saldo Beli, maupun Level (Membership)</strong>.</li>
      </ul>
    </div>
  </div>
</div>

<!-- Neobrutalism Modal Confirm (Restyled to Game UI) -->
<div id="brutal-confirm">
  <div class="m-card">
    <div class="m-hdr"><i class="ph-bold ph-gift"></i> Konfirmasi Klaim</div>
    <div class="m-body">
      <div style="font-size:11px;font-weight:800;color:#64748b;margin-bottom:8px;">Kode ini berisi reward berikut:</div>
      <ul id="brutal-confirm-list" style="margin:0 0 16px 20px;padding:0;font-size:13px;font-weight:900;color:#0369a1;line-height:1.4;">
      </ul>
      <div style="font-size:10px;font-weight:800;color:#94a3b8;text-align:center;">Apakah kamu yakin ingin mengklaimnya sekarang?</div>
      
      <div class="m-btn-row">
        <div onclick="document.getElementById('brutal-confirm').style.display='none'" class="m-btn cancel">Batal</div>
        <div onclick="confirmBrutalClaim()" class="m-btn confirm">Klaim Sekarang</div>
      </div>
    </div>
  </div>
</div>

<script>
let tempForm = null;

function confirmBrutalClaim() {
    if (tempForm) {
        tempForm.onsubmit = null;
        tempForm.submit();
    }
}

function checkRedeem(e) {
    e.preventDefault();
    const form = document.getElementById('form-claim');
    const code = form.querySelector('input[name="code"]').value;
    const btn = document.getElementById('btn-check');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Mengecek...';
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=check&code=' + encodeURIComponent(code) + '&_csrf=' + encodeURIComponent(form.querySelector('input[name="_csrf"]')?.value || '')
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-bold ph-gift"></i> Cek & Klaim';
        if (res.error) {
            if (typeof nToast !== 'undefined') {
                nToast(res.error, 'error');
            } else {
                alert(res.error);
            }
        } else {
            const list = document.getElementById('brutal-confirm-list');
            list.innerHTML = res.details.map(d => `<li style="margin-bottom:6px;">${d}</li>`).join('');
            tempForm = form;
            document.getElementById('brutal-confirm').style.display = 'flex';
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.innerHTML = '<i class="ph-bold ph-gift"></i> Cek & Klaim';
        if (typeof nToast !== 'undefined') {
            nToast('Terjadi kesalahan jaringan.', 'error');
        } else {
            alert('Terjadi kesalahan jaringan.');
        }
    });
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>