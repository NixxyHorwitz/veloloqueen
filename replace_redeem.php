<?php
$orig = file_get_contents(__DIR__ . '/user/redeem.php');
$normalized = str_replace("\r\n", "\n", $orig);
$parts = explode("require dirname(__DIR__) . '/partials/header.php';\n?>", $normalized, 2);

if (count($parts) < 2) {
    die("Failed to split: header marker not found");
}

$newHtml = <<<'EOT'

<style>
/* ══════════════════════════════════════════════
   REDEEM — MOBILE CASUAL GAME BENTO STYLE
   ══════════════════════════════════════════════ */
@import url('https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900;1000&display=swap');

* { box-sizing: border-box; }
body { background: #f97316 !important; font-family: 'Nunito', sans-serif !important; margin: 0; padding: 0; }

/* ─── HERO BANNER (gridded blue) ─── */
.rdm-hero {
  position: relative;
  background: linear-gradient(180deg, #4b9ef5 0%, #3b82f6 60%, #1d4ed8 100%);
  padding: 14px 16px 0;
  overflow: hidden;
  text-align: center;
  min-height: 150px;
  display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
}
.rdm-hero::before {
  content: '';
  position: absolute; inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.12) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.12) 1px, transparent 1px);
  background-size: 24px 24px;
  pointer-events: none;
}
.rdm-hero-gif {
  position: relative; z-index: 2;
  width: 150px;
  filter: drop-shadow(0 6px 16px rgba(0,0,0,0.35));
  display: block;
  margin: 0 auto;
  /* sits on the edge of hero so it "floats" on the orange */
  margin-bottom: -18px;
}

/* ─── ORANGE BODY ─── */
.rdm-body {
  background: #f97316;
  padding: 30px 14px 100px;
  position: relative;
  min-height: 60vh;
}
.rdm-body::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.07) 10%, transparent 10%),
              radial-gradient(circle, rgba(255,255,255,0.07) 10%, transparent 10%);
  background-size: 36px 36px;
  background-position: 0 0, 18px 18px;
  pointer-events: none; z-index: 0;
}

/* ─── FLASH ALERT ─── */
.rdm-alert {
  position: relative; z-index: 2;
  padding: 12px 14px; border-radius: 16px;
  font-size: 12px; font-weight: 800;
  margin-bottom: 14px; border: 2px solid;
  text-align: center;
}
.rdm-alert.success { background: #dcfce7; color: #166534; border-color: #4ade80; }
.rdm-alert.error   { background: #fee2e2; color: #991b1b; border-color: #f87171; }

/* ─── SECTION LABEL ─── */
.rdm-label {
  position: relative; z-index: 2;
  font-size: 15px; font-weight: 900;
  color: #fff; margin-bottom: 10px;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
  display: flex; align-items: center; gap: 6px;
}

/* ─── INPUT BENTO ─── */
.rdm-input-wrap {
  position: relative; z-index: 2;
  background: #fff9f0;
  border-radius: 24px;
  padding: 16px 16px 14px;
  box-shadow: 0 6px 0 rgba(0,0,0,0.12);
  margin-bottom: 14px;
}
.rdm-input {
  width: 100%;
  background: #fff;
  border: 3px solid #fed7aa;
  border-radius: 16px;
  padding: 14px;
  font-size: 18px; font-weight: 900;
  color: #431407;
  font-family: 'Nunito', sans-serif;
  text-transform: uppercase;
  letter-spacing: 4px;
  text-align: center;
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.06);
}
.rdm-input:focus {
  border-color: #f97316;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.06), 0 0 0 3px rgba(249,115,22,0.25);
}
.rdm-input::placeholder { color: #fdba74; font-weight: 800; letter-spacing: 2px; }

/* ─── SUBMIT BUTTON (pill style like screenshot) ─── */
.rdm-submit {
  width: 100%;
  padding: 16px;
  border-radius: 50px; /* pill */
  font-size: 18px; font-weight: 900;
  color: #78350f;
  background: linear-gradient(180deg, #ffffff 0%, #f0fdf4 50%, #dcfce7 100%);
  border: 3px solid #bbf7d0;
  box-shadow: 0 6px 0 rgba(0,0,0,0.15), inset 0 2px 0 rgba(255,255,255,0.8);
  cursor: pointer;
  transition: transform 0.12s, box-shadow 0.12s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-family: 'Nunito', sans-serif;
  letter-spacing: 0.5px;
}
.rdm-submit:active { transform: translateY(6px); box-shadow: 0 1px 0 rgba(0,0,0,0.15); }
.rdm-submit:disabled { opacity: 0.6; pointer-events: none; }
.rdm-submit i { font-size: 22px; color: #16a34a; }

/* ─── INFO BENTO ─── */
.rdm-info-bento {
  position: relative; z-index: 2;
  background: #fff9f0;
  border-radius: 24px;
  padding: 16px;
  box-shadow: 0 6px 0 rgba(0,0,0,0.1);
  margin-bottom: 14px;
}
.rdm-info-bento-title {
  font-size: 13px; font-weight: 900;
  color: #c2410c;
  margin-bottom: 10px;
  display: flex; align-items: center; gap: 6px;
}
.rdm-info-bento-title i { font-size: 18px; }
.rdm-info-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.rdm-info-list li {
  display: flex; align-items: flex-start; gap: 8px;
  font-size: 11px; font-weight: 800;
  color: #78350f;
  line-height: 1.5;
  background: rgba(249,115,22,0.08);
  border-radius: 12px;
  padding: 8px 10px;
}
.rdm-info-list li::before {
  content: '🎯'; font-size: 14px; flex-shrink: 0; margin-top: -1px;
}

/* ─── MODAL ─── */
#brutal-confirm {
  display: none; position: fixed;
  top:0; left:0; width:100%; height:100%;
  background: rgba(15,23,42,0.75);
  z-index: 9999;
  align-items: center; justify-content: center;
  padding: 20px;
  backdrop-filter: blur(6px);
}
.m-card {
  width: 100%; max-width: 320px;
  background: #fff9f0;
  border-radius: 28px;
  box-shadow: 0 12px 0 rgba(0,0,0,0.15);
  overflow: hidden;
  animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.m-hdr {
  background: linear-gradient(135deg, #fbbf24, #f97316);
  padding: 14px 16px;
  font-size: 16px; font-weight: 900;
  color: #fff;
  text-shadow: 0 2px 4px rgba(0,0,0,0.2);
  display: flex; align-items: center; gap: 8px;
  text-align: center; justify-content: center;
}
.m-body { padding: 16px; }
.m-list {
  margin: 0 0 14px; padding: 0;
  list-style: none;
  display: flex; flex-direction: column; gap: 6px;
}
.m-list li {
  background: #fef3c7;
  border-radius: 12px;
  padding: 8px 12px;
  font-size: 13px; font-weight: 900;
  color: #92400e;
  border: 2px solid #fde68a;
}
.m-confirm-txt {
  font-size: 11px; font-weight: 800;
  color: #92400e;
  text-align: center; margin-bottom: 14px;
}
.m-btn-row { display: flex; gap: 10px; }
.m-btn {
  flex: 1; padding: 12px;
  border-radius: 50px;
  font-weight: 900; font-size: 13px;
  text-align: center; cursor: pointer;
  transition: transform 0.12s, box-shadow 0.12s;
  font-family: 'Nunito', sans-serif;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-btn.cancel {
  background: #f1f5f9;
  box-shadow: 0 4px 0 #94a3b8;
  color: #475569;
}
.m-btn.confirm {
  background: linear-gradient(180deg, #4ade80, #16a34a);
  box-shadow: 0 4px 0 #15803d;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.m-btn:active { transform: translateY(4px); box-shadow: none; }

@keyframes popIn {
  0%   { transform: scale(0.7) translateY(40px); opacity: 0; }
  100% { transform: scale(1) translateY(0); opacity: 1; }
}
</style>

<!-- HERO BANNER -->
<div class="rdm-hero">
  <img class="rdm-hero-gif" src="/assets/ccg.gif" alt="Buaya Makan Kado">
</div>

<div class="rdm-body">
  <?php if ($flash): ?>
  <div class="rdm-alert <?= $flashType ?>"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <!-- LABEL -->
  <div class="rdm-label">🎁 Tukar Kode Redeem</div>

  <!-- INPUT BENTO -->
  <div class="rdm-input-wrap">
    <form id="form-claim" method="POST" onsubmit="checkRedeem(event)">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="claim">
      <input class="rdm-input" type="text" name="code" placeholder="KODE HADIAH" autocomplete="off" required>
      <button type="submit" id="btn-check" class="rdm-submit" style="margin-top:10px;">
        <i class="ph-fill ph-gift"></i> Cek &amp; Klaim
      </button>
    </form>
  </div>

  <!-- INFO BENTO -->
  <div class="rdm-info-bento">
    <div class="rdm-info-bento-title"><i class="ph-fill ph-lightbulb"></i> Cara Klaim</div>
    <ul class="rdm-info-list">
      <li>Kode tidak case-sensitive (huruf besar/kecil otomatis disesuaikan).</li>
      <li>Satu akun hanya bisa klaim setiap kode sebanyak 1x saja.</li>
      <li>Reward bisa berupa <strong>Saldo WD, Saldo Beli, atau Level Membership</strong>.</li>
    </ul>
  </div>
</div>

<!-- MODAL KONFIRMASI -->
<div id="brutal-confirm">
  <div class="m-card">
    <div class="m-hdr">🎉 Konfirmasi Klaim</div>
    <div class="m-body">
      <div class="m-confirm-txt">Kode ini berisi reward berikut:</div>
      <ul class="m-list" id="brutal-confirm-list"></ul>
      <div class="m-confirm-txt">Apakah kamu yakin ingin klaim sekarang?</div>
      <div class="m-btn-row">
        <div onclick="document.getElementById('brutal-confirm').style.display='none'" class="m-btn cancel">
          <i class="ph-bold ph-x"></i> Batal
        </div>
        <div onclick="confirmBrutalClaim()" class="m-btn confirm">
          <i class="ph-bold ph-check"></i> Klaim!
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let tempForm = null;

function confirmBrutalClaim() {
  if (tempForm) { tempForm.onsubmit = null; tempForm.submit(); }
}

function checkRedeem(e) {
  e.preventDefault();
  const form = document.getElementById('form-claim');
  const code = form.querySelector('input[name="code"]').value;
  const btn  = document.getElementById('btn-check');
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-bold ph-circle-notch" style="animation:spin .8s linear infinite;display:inline-block;"></i> Mengecek...';

  fetch('', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=check&code=' + encodeURIComponent(code) + '&_csrf=' + encodeURIComponent(form.querySelector('input[name="_csrf"]')?.value || '')
  })
  .then(r => r.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = '<i class="ph-fill ph-gift"></i> Cek &amp; Klaim';
    if (res.error) {
      typeof nToast !== 'undefined' ? nToast(res.error, 'error') : alert(res.error);
    } else {
      const list = document.getElementById('brutal-confirm-list');
      list.innerHTML = res.details.map(d => `<li>${d}</li>`).join('');
      tempForm = form;
      document.getElementById('brutal-confirm').style.display = 'flex';
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="ph-fill ph-gift"></i> Cek &amp; Klaim';
    typeof nToast !== 'undefined' ? nToast('Terjadi kesalahan jaringan.', 'error') : alert('Terjadi kesalahan jaringan.');
  });
}
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
EOT;

$finalContent = $parts[0]
    . "require dirname(__DIR__) . '/partials/header.php';\n?>"
    . $newHtml;

$finalContent = str_replace("\n", "\r\n", $finalContent);
file_put_contents(__DIR__ . '/user/redeem.php', $finalContent);
echo "Redeem page BENTO style applied!";
