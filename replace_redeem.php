<?php
$orig = file_get_contents(__DIR__ . '/user/redeem.php');
// Normalize line endings to LF just for the split
$normalized = str_replace("\r\n", "\n", $orig);
$parts = explode("require dirname(__DIR__) . '/partials/header.php';\n?>", $normalized, 2);

if (count($parts) < 2) {
    die("Failed to split file.");
}

$newHtml = <<<'EOT'

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
EOT;

$finalContent = $parts[0] . "require dirname(__DIR__) . '/partials/header.php';\n?>" . $newHtml;
// Convert back to CRLF just to be nice
$finalContent = str_replace("\n", "\r\n", $finalContent);
file_put_contents(__DIR__ . '/user/redeem.php', $finalContent);
echo "Redeem page updated successfully (fixed split).";
