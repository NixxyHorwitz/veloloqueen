<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$campaign_enabled = setting($pdo, 'threads_campaign_enabled', '1') === '1';
if (!$campaign_enabled) {
    $_SESSION['flash_home_err'] = 'Kampanye Threads saat ini sedang tidak aktif.';
    redirect('/home');
}

$reward_amount = (float)setting($pdo, 'threads_campaign_reward', '5000');
$instructions = setting($pdo, 'threads_campaign_instructions', "Promosikan TontonCuan di Threads, dapatkan cuan tambahan Rp 5.000! \n\nCaranya:\n1. Posting tentang TontonCuan di akun Threads kamu.\n2. Sertakan link referral atau testimoni positif.\n3. Screenshot postingan tersebut.\n4. Upload bukti screenshot di bawah ini untuk diverifikasi admin.");

// Check if user already has a pending threads campaign request
$stmtPending = $pdo->prepare("SELECT * FROM admin_requests WHERE user_id=? AND type='threads_campaign' AND status='pending' LIMIT 1");
$stmtPending->execute([$user['id']]);
$pendingRequest = $stmtPending->fetch();

// Check if user has an approved request (they might have completed it)
$stmtApproved = $pdo->prepare("SELECT * FROM admin_requests WHERE user_id=? AND type='threads_campaign' AND status='approved' LIMIT 1");
$stmtApproved->execute([$user['id']]);
$approvedRequest = $stmtApproved->fetch();

$flash = $flashType = '';
if ($_SESSION['flash_threads_msg'] ?? null) {
    $flash = $_SESSION['flash_threads_msg'];
    $flashType = $_SESSION['flash_threads_type'] ?? 'success';
    unset($_SESSION['flash_threads_msg'], $_SESSION['flash_threads_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$pendingRequest) {
    csrf_enforce();
    
    if (empty($_FILES['proof']['tmp_name'])) {
        $flash = 'Silakan pilih file bukti screenshot postingan kamu!'; $flashType = 'error';
    } else {
        $file = $_FILES['proof'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $flash = 'Format file tidak didukung! Harus JPG, PNG, atau WEBP.'; $flashType = 'error';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $flash = 'Ukuran file terlalu besar! Maksimal 5MB.'; $flashType = 'error';
        } else {
            // Upload directory
            $dir = dirname(__DIR__) . '/uploads/threads/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $filename = 'threads_' . $user['id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $proof_path = 'uploads/threads/' . $filename;
                
                // Save to admin_requests
                $payload = json_encode([
                    'proof_image' => $proof_path,
                    'reward_amount' => $reward_amount
                ]);
                
                $pdo->prepare("INSERT INTO admin_requests (user_id, type, status, payload, created_at, updated_at) VALUES (?, 'threads_campaign', 'pending', ?, NOW(), NOW())")
                    ->execute([$user['id'], $payload]);
                $request_id = $pdo->lastInsertId();
                
                // Dispatch to Telegram with approve/reject inline keyboard buttons
                $tgMsg = "🌀 <b>KLAIM PROMOSI THREADS</b>\n";
                $tgMsg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
                $tgMsg .= "👤 <b>User:</b> <code>{$user['username']}</code> (ID: {$user['id']})\n";
                $tgMsg .= "💵 <b>Reward:</b> <code>" . format_rp($reward_amount) . "</code>\n";
                $tgMsg .= "📅 <b>Tanggal:</b> " . date('d M Y H:i') . "\n";
                $tgMsg .= "━━━━━━━━━━━━━━━━━━━━━━\n";
                $tgMsg .= "<i>Silakan verifikasi bukti postingan Threads di atas.</i>";
                
                $kb = [
                    [
                        ['text' => '✅ Setujui', 'callback_data' => 'req_approve_' . $request_id],
                        ['text' => '❌ Tolak', 'callback_data' => 'req_reject_' . $request_id]
                    ]
                ];
                
                // Send photo directly to Telegram
                send_telegram_photo($pdo, $dir . $filename, $tgMsg, $kb, 'misi');
                
                $_SESSION['flash_threads_msg'] = 'Bukti postingan berhasil di-upload! Admin akan segera memverifikasi klaim kamu.';
                $_SESSION['flash_threads_type'] = 'success';
                redirect('/threads');
            } else {
                $flash = 'Gagal menyimpan file upload. Coba lagi.'; $flashType = 'error';
            }
        }
    }
}

$pageTitle = 'Event Promosi Threads';
$activePage = 'home';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* THREADS THEMED BRIGHT PAGE DESIGN */
html body { background: #f97316 !important; background-image: none !important; color: #0f172a !important; }
.app-shell { background: #f97316 !important; }
.page-content { background: #f97316 !important; }

/* Pattern like withdraw page */
.threads-container {
  padding: 20px 14px calc(var(--nav-h) + 24px);
  max-width: 480px;
  margin: 0 auto;
  font-family: 'Nunito', sans-serif;
  position: relative;
  z-index: 2;
}

.threads-card {
  background: #ffffff;
  border: 2.5px solid #1e3a8a;
  border-radius: 22px;
  box-shadow: 0 5px 0 #1e3a8a;
  padding: 20px;
  margin-bottom: 20px;
  position: relative;
  overflow: hidden;
}
.threads-card::before {
  content: '🌀';
  position: absolute;
  top: -20px;
  right: -20px;
  font-size: 110px;
  opacity: 0.05;
  pointer-events: none;
  transform: rotate(20deg);
}

.threads-title {
  font-size: 20px;
  font-weight: 900;
  color: #1e3a8a;
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.threads-subtitle {
  font-size: 11px;
  color: #475569;
  font-weight: 700;
  margin-bottom: 16px;
}

.threads-reward-badge {
  background: rgba(34, 197, 94, 0.1);
  border: 2px solid #22c55e;
  border-radius: 16px;
  padding: 10px 14px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  box-shadow: 0 3px 0 #22c55e;
}
.threads-reward-lbl {
  font-size: 11px;
  font-weight: 800;
  color: #15803d;
}
.threads-reward-val {
  font-size: 18px;
  font-weight: 900;
  color: #16a34a;
}

.threads-instructions {
  background: #f8fafc;
  border: 2px solid #cbd5e1;
  border-radius: 16px;
  padding: 14px;
  font-size: 12px;
  line-height: 1.5;
  color: #334155;
  font-weight: 700;
  white-space: pre-line;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 16px;
}
.form-label {
  font-size: 12px;
  font-weight: 800;
  color: #1e293b;
  margin-bottom: 6px;
  display: block;
}

.threads-file-upload {
  border: 2.5px dashed #cbd5e1;
  border-radius: 16px;
  padding: 24px 16px;
  text-align: center;
  cursor: pointer;
  background: #f8fafc;
  transition: border-color 0.2s, background 0.2s;
  position: relative;
}
.threads-file-upload:hover {
  border-color: #64748b;
  background: #f1f5f9;
}
.threads-file-upload input[type="file"] {
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  opacity: 0;
  cursor: pointer;
}
.threads-file-icon {
  font-size: 32px;
  color: #64748b;
  margin-bottom: 8px;
}
.threads-file-text {
  font-size: 11px;
  font-weight: 800;
  color: #64748b;
}

.btn-threads-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  background: linear-gradient(135deg, #db2777, #7c3aed);
  color: #ffffff;
  border: 2.5px solid #701a75;
  border-radius: 16px;
  padding: 14px;
  font-size: 14px;
  font-weight: 900;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  box-shadow: 0 4px 0 #701a75;
  transition: transform 0.1s, box-shadow 0.1s;
}
.btn-threads-submit:active {
  transform: translateY(3px);
  box-shadow: none;
}

.threads-status-box {
  background: #f8fafc;
  border: 2px solid #cbd5e1;
  border-radius: 20px;
  padding: 16px;
  text-align: center;
  margin-bottom: 20px;
}
.threads-status-icon {
  font-size: 40px;
  margin-bottom: 10px;
}
.threads-status-title {
  font-size: 15px;
  font-weight: 900;
  color: #0f172a;
  margin-bottom: 4px;
}
.threads-status-desc {
  font-size: 11px;
  color: #475569;
  font-weight: 700;
  line-height: 1.4;
}

.threads-preview-img {
  max-width: 100%;
  max-height: 250px;
  border-radius: 12px;
  border: 2px solid #cbd5e1;
  margin-top: 12px;
  object-fit: contain;
}

.btn-back-home {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
  font-size: 12px;
  font-weight: 900;
  color: #ffffff;
  margin-bottom: 16px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.15);
  transition: opacity 0.2s;
}
.btn-back-home:hover {
  opacity: 0.9;
}

.flash-alert {
  margin-bottom: 16px;
  padding: 12px 14px;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
  border: 2.5px solid;
}
.flash-alert--success {
  background: #d1fae5;
  color: #065f46;
  border-color: #6ee7b7;
}
.flash-alert--err {
  background: #fef2f2;
  color: #991b1b;
  border-color: #fca5a5;
}
</style>

<div class="threads-container">
  
  <a href="/home" class="btn-back-home">
    <i class="ph-bold ph-arrow-left"></i> Kembali ke Beranda
  </a>

  <?php if ($flash): ?>
  <div class="flash-alert flash-alert--<?= $flashType==='error'?'err':'success' ?>">
    <i class="ph-bold <?= $flashType==='error'?'ph-warning-circle':'ph-check-circle' ?>"></i>
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <div class="threads-card">
    <div class="threads-title">
      🌀 Promosi Threads
    </div>
    <div class="threads-subtitle">Bagikan link referensimu dan raih keuntungan berlipat!</div>

    <div class="threads-reward-badge">
      <span class="threads-reward-lbl">💰 REWARD SALDO TARIK</span>
      <span class="threads-reward-val"><?= format_rp($reward_amount) ?></span>
    </div>

    <!-- Status logic -->
    <?php if ($pendingRequest): ?>
      <?php $pData = json_decode($pendingRequest['payload'], true) ?: []; ?>
      <div class="threads-status-box">
        <div class="threads-status-icon">⏳</div>
        <div class="threads-status-title">Klaim Sedang Diproses</div>
        <div class="threads-status-desc">Postingan kamu sedang diverifikasi oleh admin. Reward akan otomatis masuk jika bukti terbukti valid.</div>
        
        <?php if (!empty($pData['proof_image'])): ?>
          <img src="/<?= htmlspecialchars($pData['proof_image']) ?>" class="threads-preview-img" alt="Bukti Screenshot">
        <?php endif; ?>
      </div>
    <?php elseif ($approvedRequest): ?>
      <div class="threads-status-box" style="border-color: #22c55e;">
        <div class="threads-status-icon">🎉</div>
        <div class="threads-status-title" style="color: #22c55e;">Klaim Berhasil Disetujui!</div>
        <div class="threads-status-desc">Terima kasih telah mempromosikan TontonCuan di Threads! Reward <?= format_rp($reward_amount) ?> sudah masuk ke saldo tarik kamu.</div>
      </div>
    <?php else: ?>
      <div class="threads-instructions">
        <?= htmlspecialchars($instructions) ?>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        
        <div class="form-group">
          <label class="form-label">Upload Bukti Screenshot</label>
          <div class="threads-file-upload" id="upload-zone">
            <i class="ph-bold ph-image threads-file-icon" id="upload-icon"></i>
            <div class="threads-file-text" id="upload-text">Klik atau seret file gambar ke sini (Format: JPG/PNG, Maks: 5MB)</div>
            <input type="file" name="proof" id="file-input" accept="image/*" required>
          </div>
        </div>

        <button type="submit" class="btn-threads-submit">
          <i class="ph-bold ph-paper-plane-tilt" style="font-size: 16px;"></i> Kirim Bukti Postingan
        </button>
      </form>
    <?php endif; ?>

  </div>

</div>

<script>
const fileInput = document.getElementById('file-input');
const uploadZone = document.getElementById('upload-zone');
const uploadText = document.getElementById('upload-text');
const uploadIcon = document.getElementById('upload-icon');

if (fileInput) {
  fileInput.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
      const file = this.files[0];
      uploadText.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
      uploadText.style.color = '#4ade80';
      uploadIcon.style.color = '#4ade80';
      uploadZone.style.borderColor = '#22c55e';
    }
  });
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
