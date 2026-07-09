<?php
// Read file and grab the PHP logic part only (lines 1-173)
$lines = file('c:/laragon/www/velostar/user/missions.php');
$phpPart = implode('', array_slice($lines, 0, 173)); // up to and including the line with require header.php + ?>

$newHtml = <<<'ENDDOC'
<style>
/* ══════════════════════════════════════════════
   MISSION PAGE - CASUAL GAME STYLE (SETEMA WD)
   ══════════════════════════════════════════════ */
html body { background: #f97316 !important; font-family: 'Nunito', sans-serif; }

/* BLUE TOP BANNER */
.wd-top {
  background: #38bdf8;
  background-image:
    linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px);
  background-size: 40px 20px;
  background-position: 0 0, 20px 10px;
  position: relative;
  padding: 16px 14px 20px;
  border-bottom: 3px solid #0284c7;
}
.wd-top-inner { display: flex; align-items: center; justify-content: space-between; }
.wd-top-left  { display: flex; align-items: center; gap: 10px; }
.wd-back-btn {
  width: 32px; height: 32px;
  background: #fde047; border: none; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: #ca8a04; font-size: 16px;
  box-shadow: 0 3px 0 #a16207;
  text-decoration: none; flex-shrink: 0; transition: transform 0.1s;
}
.wd-back-btn:active { transform: translateY(3px); }
.wd-top-title { font-size: 18px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #0369a1; margin-bottom: 2px; }
.wd-top-sub   { font-size: 11px; font-weight: 800; color: #e0f2fe; }

.ms-hero-badge {
  background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.4);
  border-radius: 14px; padding: 6px 12px; text-align: center;
}
.ms-hero-badge__val { font-size: 22px; font-weight: 900; color: #fff; line-height: 1; }
.ms-hero-badge__lbl { font-size: 9px; font-weight: 900; color: #bae6fd; text-transform: uppercase; }

/* ORANGE BODY */
.wd-body {
  flex: 1; background: #f97316; padding: 14px 14px 100px; position: relative;
}
.wd-body::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%),
              radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%);
  background-size: 50px 50px; background-position: 0 0, 25px 25px; pointer-events: none;
}

/* TABS */
.ms-tabs {
  display: flex; background: rgba(255,255,255,0.2);
  border: 2px solid rgba(255,255,255,0.3); border-radius: 10px;
  padding: 4px; gap: 4px; margin-bottom: 14px; position: relative; z-index: 2;
}
.ms-tab {
  flex: 1; padding: 8px 4px; text-align: center; font-size: 11px; font-weight: 900;
  color: #fff; background: transparent; border: none; border-radius: 8px;
  transition: all 0.2s; cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.2);
  font-family: 'Nunito', sans-serif; -webkit-tap-highlight-color: transparent;
}
.ms-tab.active { background: #fff; color: #c2410c; text-shadow: none; box-shadow: 0 2px 0 rgba(0,0,0,0.1); }

/* SECTION HEADER */
.ms-section-hdr {
  font-size: 11px; font-weight: 900; color: #fff; text-transform: uppercase;
  letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center;
  gap: 6px; text-shadow: 0 1px 2px rgba(0,0,0,0.25); position: relative; z-index: 2;
}
.ms-section-hdr i { color: #fde047; font-size: 16px; }

/* MISSION CARD - bento style */
.ms-card {
  background: #fff; border: 2.5px solid #1e3a8a; border-radius: 14px;
  box-shadow: 0 4px 0 #1e3a8a; margin-bottom: 10px; overflow: hidden;
  transition: opacity 0.3s; position: relative; z-index: 2;
}
.ms-card--done    { background: #f0fdf4; border-color: #16a34a; box-shadow: 0 4px 0 #15803d; }
.ms-card--claimed { background: #f8fafc; border-color: #cbd5e1; box-shadow: 0 4px 0 #94a3b8; opacity: 0.75; }

.ms-card__head { display: flex; align-items: center; gap: 10px; padding: 10px 12px 8px; }
.ms-card__icon {
  width: 40px; height: 40px; flex-shrink: 0;
  background: linear-gradient(135deg, #fbbf24, #f59e0b);
  border: 2px solid #d97706; border-radius: 12px; box-shadow: 0 3px 0 #b45309;
  display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff;
}
.ms-card--done    .ms-card__icon { background: linear-gradient(135deg, #34d399, #059669); border-color: #047857; box-shadow: 0 3px 0 #065f46; }
.ms-card--claimed .ms-card__icon { background: linear-gradient(135deg, #94a3b8, #64748b); border-color: #475569; box-shadow: 0 2px 0 #334155; }

.ms-card__info  { flex: 1; min-width: 0; }
.ms-card__title { font-size: 13px; font-weight: 900; color: #1e3a8a; line-height: 1.2; margin-bottom: 2px; }
.ms-card--done    .ms-card__title { color: #065f46; }
.ms-card--claimed .ms-card__title { color: #64748b; }
.ms-card__desc  { font-size: 10px; font-weight: 700; color: #64748b; line-height: 1.3; }

.ms-card__reward {
  background: #fef3c7; border: 1.5px solid #f59e0b; border-radius: 8px;
  padding: 3px 7px; font-size: 10px; font-weight: 900; color: #d97706;
  flex-shrink: 0; box-shadow: 0 2px 0 #d97706;
}
.ms-card--claimed .ms-card__reward { background: #f1f5f9; border-color: #cbd5e1; color: #94a3b8; box-shadow: none; }

/* PROGRESS */
.ms-prog { padding: 0 12px 10px; }
.ms-prog-bar-wrap {
  height: 10px; background: #dbeafe; border-radius: 6px; overflow: hidden;
  box-shadow: inset 0 2px 3px rgba(0,0,0,0.08);
}
.ms-card--done    .ms-prog-bar-wrap { background: #dcfce7; }
.ms-card--claimed .ms-prog-bar-wrap { background: #f1f5f9; box-shadow: none; }
.ms-prog-bar {
  height: 100%; background: linear-gradient(90deg, #60a5fa, #2563eb);
  border-radius: 6px; transition: width 0.5s ease;
}
.ms-card--done    .ms-prog-bar { background: linear-gradient(90deg, #34d399, #059669); }
.ms-card--claimed .ms-prog-bar { background: #cbd5e1; }
.ms-prog-meta { display: flex; justify-content: space-between; font-size: 9px; font-weight: 800; color: #64748b; margin-top: 3px; }

/* BUTTONS */
.ms-btn {
  width: 100%; margin-top: 8px; padding: 9px; border-radius: 10px;
  font-size: 12px; font-weight: 900;
  display: flex; align-items: center; justify-content: center; gap: 6px;
  cursor: pointer; transition: transform 0.1s, box-shadow 0.1s; font-family: 'Nunito', sans-serif;
}
.ms-btn:active { transform: translateY(3px); box-shadow: none !important; }
.ms-btn--locked  { background: #f1f5f9; border: 2px solid #e2e8f0; color: #94a3b8; cursor: not-allowed; }
.ms-btn--ready   { background: linear-gradient(135deg, #fbbf24, #f59e0b); border: 2px solid #d97706; color: #7c2d12; box-shadow: 0 3px 0 #b45309; }
.ms-btn--claimed { background: #dcfce7; border: 2px solid #86efac; color: #16a34a; cursor: default; }
.ms-btn--claimed:active { transform: none; }

/* PANELS */
.ms-panel { display: none; }
.ms-panel.active { display: block; animation: fade-in 0.25s; }
@keyframes fade-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }
@keyframes spin    { to { transform: rotate(360deg); } }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-inner">
    <div class="wd-top-left">
      <a href="/home" class="wd-back-btn"><i class="ph-bold ph-arrow-left"></i></a>
      <div>
        <div class="wd-top-title">Misi</div>
        <div class="wd-top-sub">Selesaikan misi, klaim reward gratis!</div>
      </div>
    </div>
    <div class="ms-hero-badge">
      <div class="ms-hero-badge__val"><?= $claimed_today ?></div>
      <div class="ms-hero-badge__lbl">Diklaim</div>
    </div>
  </div>
</div>

<div class="wd-body">
  <!-- TABS -->
  <div class="ms-tabs" role="tablist">
    <button class="ms-tab active" id="tab-daily"    onclick="switchTab('daily')">Harian</button>
    <button class="ms-tab"        id="tab-weekly"   onclick="switchTab('weekly')">Mingguan</button>
    <button class="ms-tab"        id="tab-lifetime" onclick="switchTab('lifetime')">Pencapaian</button>
  </div>

  <!-- DAILY -->
  <div class="ms-panel active" id="panel-daily">
    <div class="ms-section-hdr"><i class="ph-fill ph-sun"></i> Misi Harian - Reset tiap hari</div>
    <?php foreach ($daily as $m):
      $pct = $m['target'] > 0 ? min(100, round($m['progress'] / $m['target'] * 100)) : 0;
      $cardClass = $m['claimed'] ? 'ms-card--claimed' : ($m['done'] ? 'ms-card--done' : '');
    ?>
    <div class="ms-card <?= $cardClass ?>" id="mc-<?= htmlspecialchars($m['slug']) ?>">
      <div class="ms-card__head">
        <div class="ms-card__icon"><i class="ph-fill <?= htmlspecialchars($m['icon']) ?>"></i></div>
        <div class="ms-card__info">
          <div class="ms-card__title"><?= htmlspecialchars($m['title']) ?></div>
          <div class="ms-card__desc"><?= htmlspecialchars($m['desc']) ?></div>
        </div>
        <div class="ms-card__reward">+Rp<?= number_format($m['reward'],0,',','.') ?></div>
      </div>
      <div class="ms-prog">
        <div class="ms-prog-bar-wrap"><div class="ms-prog-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="ms-prog-meta"><span><?= $m['progress'] ?> / <?= $m['target'] ?></span><span><?= $pct ?>%</span></div>
        <?php if ($m['claimed']): ?>
          <button class="ms-btn ms-btn--claimed" disabled><i class="ph-bold ph-check-circle"></i> Selesai</button>
        <?php elseif ($m['done']): ?>
          <button class="ms-btn ms-btn--ready" onclick="claimMission('<?= $m['slug'] ?>', this)"><i class="ph-bold ph-gift"></i> Klaim Reward!</button>
        <?php else: ?>
          <button class="ms-btn ms-btn--locked" disabled><i class="ph-bold ph-lock"></i> Belum Selesai</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- WEEKLY -->
  <div class="ms-panel" id="panel-weekly">
    <div class="ms-section-hdr"><i class="ph-fill ph-calendar"></i> Misi Mingguan - Reset tiap Senin</div>
    <?php foreach ($weekly as $m):
      $pct = $m['target'] > 0 ? min(100, round($m['progress'] / $m['target'] * 100)) : 0;
      $cardClass = $m['claimed'] ? 'ms-card--claimed' : ($m['done'] ? 'ms-card--done' : '');
    ?>
    <div class="ms-card <?= $cardClass ?>" id="mc-<?= htmlspecialchars($m['slug']) ?>">
      <div class="ms-card__head">
        <div class="ms-card__icon"><i class="ph-fill <?= htmlspecialchars($m['icon']) ?>"></i></div>
        <div class="ms-card__info">
          <div class="ms-card__title"><?= htmlspecialchars($m['title']) ?></div>
          <div class="ms-card__desc"><?= htmlspecialchars($m['desc']) ?></div>
        </div>
        <div class="ms-card__reward">+Rp<?= number_format($m['reward'],0,',','.') ?></div>
      </div>
      <div class="ms-prog">
        <div class="ms-prog-bar-wrap"><div class="ms-prog-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="ms-prog-meta"><span><?= $m['progress'] ?> / <?= $m['target'] ?></span><span><?= $pct ?>%</span></div>
        <?php if ($m['claimed']): ?>
          <button class="ms-btn ms-btn--claimed" disabled><i class="ph-bold ph-check-circle"></i> Selesai</button>
        <?php elseif ($m['done']): ?>
          <button class="ms-btn ms-btn--ready" onclick="claimMission('<?= $m['slug'] ?>', this)"><i class="ph-bold ph-gift"></i> Klaim Reward!</button>
        <?php else: ?>
          <button class="ms-btn ms-btn--locked" disabled><i class="ph-bold ph-lock"></i> Belum Selesai</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- LIFETIME -->
  <div class="ms-panel" id="panel-lifetime">
    <div class="ms-section-hdr"><i class="ph-fill ph-trophy"></i> Pencapaian - Klaim sekali selamanya</div>
    <?php foreach ($lifetime as $m):
      $pct = $m['target'] > 0 ? min(100, round($m['progress'] / $m['target'] * 100)) : 0;
      $cardClass = $m['claimed'] ? 'ms-card--claimed' : ($m['done'] ? 'ms-card--done' : '');
    ?>
    <div class="ms-card <?= $cardClass ?>" id="mc-<?= htmlspecialchars($m['slug']) ?>">
      <div class="ms-card__head">
        <div class="ms-card__icon"><i class="ph-fill <?= htmlspecialchars($m['icon']) ?>"></i></div>
        <div class="ms-card__info">
          <div class="ms-card__title"><?= htmlspecialchars($m['title']) ?></div>
          <div class="ms-card__desc"><?= htmlspecialchars($m['desc']) ?></div>
        </div>
        <div class="ms-card__reward">+Rp<?= number_format($m['reward'],0,',','.') ?></div>
      </div>
      <div class="ms-prog">
        <div class="ms-prog-bar-wrap"><div class="ms-prog-bar" style="width:<?= $pct ?>%"></div></div>
        <div class="ms-prog-meta"><span><?= $m['progress'] ?> / <?= $m['target'] ?></span><span><?= $pct ?>%</span></div>
        <?php if ($m['claimed']): ?>
          <button class="ms-btn ms-btn--claimed" disabled><i class="ph-bold ph-check-circle"></i> Selesai</button>
        <?php elseif ($m['done']): ?>
          <button class="ms-btn ms-btn--ready" onclick="claimMission('<?= $m['slug'] ?>', this)"><i class="ph-bold ph-gift"></i> Klaim Reward!</button>
        <?php else: ?>
          <button class="ms-btn ms-btn--locked" disabled><i class="ph-bold ph-lock"></i> Belum Selesai</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const _csrf = '<?= csrf_token() ?>';

function switchTab(cat) {
  document.querySelectorAll('.ms-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.ms-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + cat).classList.add('active');
  document.getElementById('panel-' + cat).classList.add('active');
}

function claimMission(slug, btn) {
  btn.disabled = true;
  btn.innerHTML = '<i class="ph-bold ph-spinner-gap" style="animation:spin 0.8s linear infinite"></i> Mengklaim...';
  const fd = new FormData();
  fd.append('action', 'claim_mission');
  fd.append('slug', slug);
  fd.append('_csrf', _csrf);
  fetch(location.href, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        if (data.tickets_added && data.tickets_added > 0) {
          const tc = document.getElementById('spin-tickets-count');
          if (tc) tc.innerText = parseInt(tc.innerText) + data.tickets_added;
        }
        const card = document.getElementById('mc-' + slug);
        if (card) { card.classList.remove('ms-card--done'); card.classList.add('ms-card--claimed'); }
        btn.className = 'ms-btn ms-btn--claimed';
        btn.innerHTML = '<i class="ph-bold ph-check-circle"></i> Selesai';
        btn.disabled = true;
        try {
          const AudioCtx = window.AudioContext || window.webkitAudioContext;
          if (AudioCtx) {
            const actx = new AudioCtx(), osc = actx.createOscillator(), gain = actx.createGain();
            osc.connect(gain); gain.connect(actx.destination);
            osc.frequency.value = 1046.5;
            gain.gain.setValueAtTime(0, actx.currentTime);
            gain.gain.linearRampToValueAtTime(0.2, actx.currentTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.01, actx.currentTime + 0.3);
            osc.start(); osc.stop(actx.currentTime + 0.3);
          }
        } catch(e) {}
        if (typeof nToast !== 'undefined') nToast(data.msg, 'success');
      } else {
        btn.disabled = false;
        btn.className = 'ms-btn ms-btn--ready';
        btn.innerHTML = '<i class="ph-bold ph-gift"></i> Klaim Reward!';
        if (typeof nToast !== 'undefined') nToast(data.msg, 'error');
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.className = 'ms-btn ms-btn--ready';
      btn.innerHTML = '<i class="ph-bold ph-gift"></i> Klaim Reward!';
      if (typeof nToast !== 'undefined') nToast('Koneksi terputus.', 'error');
    });
}
</script>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
ENDDOC;

file_put_contents('c:/laragon/www/velostar/user/missions.php', $phpPart . $newHtml);
echo "Done!";
