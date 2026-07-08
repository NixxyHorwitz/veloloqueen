<?php
// We will replace everything from <style> onwards in promotor.php with this file's contents.
?>
<style>
/* ══════════════════════════════════════════════
   PROMOTOR PAGE — CASUAL GAME STYLE (ULTRA COMPACT)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; margin: 0; padding: 0; }

/* ── BLUE TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 16px 14px 20px; border-bottom: 3px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 20px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 3px 0 #1e3a8a; z-index: 2; margin-bottom: 2px; letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.wd-top-sub { position: relative; font-size: 11px; font-weight: 800; color: #bae6fd; z-index: 2; }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 14px 14px 100px; position: relative; z-index: 2; margin-top: 0; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 40px 40px; background-position: 0 0, 20px 20px; pointer-events: none; z-index: -1; }

/* ── STATS ROW ── */
.stat-row { display: flex; gap: 6px; margin-bottom: 14px; position: relative; z-index: 5; }
.stat-box { flex: 1; background: #ffffff; border: 2.5px solid #1e3a8a; border-radius: 12px; padding: 10px 4px; text-align: center; box-shadow: 0 3px 0 #1e3a8a; }
.stat-val { font-size: 13px; font-weight: 900; line-height: 1.2; }
.stat-val.blue { color: #0284c7; }
.stat-val.green { color: #16a34a; }
.stat-val.orange { color: #ea580c; }
.stat-val.pink { color: #be185d; }
.stat-lbl { font-size: 9px; font-weight: 900; color: #64748b; margin-top: 2px; text-transform: uppercase; }

/* ── SECTION TITLE ── */
.sec-title { font-size: 12px; font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 10px; margin-top: 18px; display: flex; align-items: center; gap: 6px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
.sec-title i { color: #fde047; font-size: 16px; }

/* ── INCOME BOX ── */
.inc-box { background: linear-gradient(135deg, #fef3c7, #fde68a); border: 2.5px solid #f59e0b; border-radius: 12px; padding: 14px; text-align: center; box-shadow: 0 3px 0 #d97706; margin-bottom: 14px; }
.inc-lbl { font-size: 10px; font-weight: 900; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.inc-val { font-size: 26px; font-weight: 900; color: #b45309; line-height: 1; letter-spacing: -1px; text-shadow: 0 1px 0 rgba(255,255,255,0.6); margin-bottom: 10px; }
.inc-split { border-top: 2px dashed #fcd34d; padding-top: 10px; display: flex; align-items: center; justify-content: center; gap: 12px; }
.inc-split-item { text-align: center; }
.inc-split-lbl { font-size: 9px; font-weight: 900; color: #92400e; text-transform: uppercase; margin-bottom: 2px; }
.inc-split-val { font-size: 14px; font-weight: 900; color: #78350f; }
.inc-split-val span { font-size: 9px; color: #b45309; font-weight: 800; }

/* ── TABS ── */
.p-tabs { display: flex; background: rgba(255,255,255,0.2); border-radius: 10px; padding: 4px; gap: 4px; margin-bottom: 14px; border: 2px solid rgba(255,255,255,0.3); }
.p-tab { flex: 1; text-align: center; padding: 8px 0; font-size: 11px; font-weight: 900; color: #fff; cursor: pointer; border-radius: 8px; transition: all 0.2s; text-shadow: 0 1px 1px rgba(0,0,0,0.2); }
.p-tab.active { background: #fff; color: #c2410c; text-shadow: none; box-shadow: 0 2px 0 rgba(0,0,0,0.1); }
.p-content { display: none; }
.p-content.active { display: block; }

/* ── COMPACT LISTS ── */
.c-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
.c-item { display: flex; align-items: center; gap: 10px; background: #ffffff; border: 2.5px solid #1e3a8a; border-radius: 12px; padding: 10px 12px; box-shadow: 0 3px 0 #1e3a8a; }
.c-item.yellow { border-color: #c2410c; box-shadow: 0 3px 0 #9a3412; }
.c-ico { width: 36px; height: 36px; border-radius: 10px; border: 2px solid; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 0; }
.c-ico.blue { background: #e0f2fe; color: #0284c7; border-color: #0369a1; box-shadow: 0 2px 0 #075985; }
.c-ico.yellow { background: linear-gradient(180deg, #fef08a, #facc15); color: #b45309; border-color: #a16207; box-shadow: 0 2px 0 #713f12; }
.c-ico.pink { background: #fce7f3; color: #be185d; border-color: #9d174d; box-shadow: 0 2px 0 #831843; }
.c-ico.green { background: #dcfce7; color: #047857; border-color: #064e3b; box-shadow: 0 2px 0 #064e3b; }
.c-body { flex: 1; min-width: 0; }
.c-title { font-size: 12px; font-weight: 900; color: #1e3a8a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.c-item.yellow .c-title { color: #9a3412; }
.c-sub { font-size: 10px; font-weight: 800; color: #64748b; display: flex; align-items: center; gap: 4px; }
.c-right { text-align: right; }
.c-badge { font-size: 8px; font-weight: 900; padding: 2px 4px; border-radius: 5px; border: 1px solid; text-transform: uppercase; display: inline-block; margin-bottom: 4px; }
.c-badge.success { background: #dcfce7; color: #16a34a; border-color: #22c55e; }
.c-badge.warn { background: #fef08a; color: #ca8a04; border-color: #eab308; }
.c-badge.free { background: #e0f2fe; color: #0284c7; border-color: #0ea5e9; }
.c-amt { font-size: 12px; font-weight: 900; color: #16a34a; letter-spacing: -0.5px; }

/* ── CHARTS ── */
.chart-box { background: #fff; border: 2.5px solid #1e3a8a; border-radius: 12px; padding: 12px; box-shadow: 0 3px 0 #1e3a8a; margin-bottom: 14px; }
.chart-box canvas { width: 100% !important; height: 160px !important; }

/* ── FORMS ── */
.f-group { margin-bottom: 10px; }
.f-label { display: block; font-size: 10px; font-weight: 900; color: #1e3a8a; margin-bottom: 4px; text-transform: uppercase; }
.f-input { width: 100%; background: #f8fafc; border: 2px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; font-weight: 800; color: #334155; font-family: 'Nunito', sans-serif; transition: border-color 0.2s; }
.f-input:focus { outline: none; border-color: #3b82f6; background: #fff; }
.f-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 900; color: #fff; background: linear-gradient(135deg, #3b82f6, #2563eb); border: 2.5px solid #1e40af; box-shadow: 0 4px 0 #1e3a8a; cursor: pointer; transition: transform 0.1s; text-shadow: 0 1px 1px rgba(0,0,0,0.3); }
.f-btn:active { transform: translateY(4px); box-shadow: none; }
.f-alert { padding: 10px; border-radius: 8px; font-size: 11px; font-weight: 800; margin-bottom: 12px; border: 2px solid; }
.f-alert.success { background: #dcfce7; color: #166534; border-color: #4ade80; }
.f-alert.error { background: #fee2e2; color: #991b1b; border-color: #f87171; }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title"><i class="ph-bold ph-megaphone"></i> Promotor Hub</div>
  <div class="wd-top-sub">Analisis traffic, target harian & manajemen WD</div>
</div>

<div class="wd-body">
  <!-- PENDAPATAN HARI INI (NO DEPOSIT TARGET) -->
  <div class="inc-box">
    <div class="inc-lbl">Pendapatan Hari Ini</div>
    <div class="inc-val"><?= format_rp((float)$today_target['salary_rate']) ?></div>
    <div class="inc-split">
      <div class="inc-split-item">
        <div class="inc-split-lbl">🧑‍🤝‍🧑 Member Baru</div>
        <div class="inc-split-val">
          <?= number_format((int)$today_target['actual_regs']) ?> 
          <span>(Target: <?= number_format((int)$today_target['target_regs']) ?>)</span>
        </div>
      </div>
    </div>
  </div>

  <!-- CLICK STATS -->
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-val blue"><?= number_format($today_clicks) ?></div>
      <div class="stat-lbl">Clicks (Hari Ini)</div>
    </div>
    <div class="stat-box">
      <div class="stat-val green"><?= number_format($total_clicks) ?></div>
      <div class="stat-lbl">Total Clicks</div>
    </div>
    <div class="stat-box">
      <div class="stat-val pink"><?= number_format($avg_percentage, 1) ?>%</div>
      <div class="stat-lbl">Rata-rata Target</div>
    </div>
  </div>

  <!-- TAB NAVIGATION -->
  <div class="p-tabs">
    <div class="p-tab active" onclick="switchTab('tab-stats')">Riwayat</div>
    <div class="p-tab" onclick="switchTab('tab-chart')">Grafik</div>
    <div class="p-tab" onclick="switchTab('tab-dl')">Downline</div>
    <div class="p-tab" onclick="switchTab('tab-fwd')">Buat WD Fake</div>
  </div>

  <!-- TAB 1: RIWAYAT TARGET -->
  <div id="tab-stats" class="p-content active">
    <?php if (empty($history_logs)): ?>
      <div style="text-align:center; padding:20px; background:rgba(255,255,255,0.2); border-radius:12px; border:2.5px dashed rgba(255,255,255,0.4);">
        <i class="ph-bold ph-calendar-blank" style="font-size:32px; color:#fff; opacity:0.8;"></i>
        <div style="font-size:11px; font-weight:800; color:#fff; margin-top:8px;">Belum ada riwayat harian.</div>
      </div>
    <?php else: ?>
      <div class="c-list">
        <?php foreach ($history_logs as $log): ?>
        <div class="c-item">
          <div class="c-ico blue"><i class="ph-bold ph-calendar-check"></i></div>
          <div class="c-body">
            <div class="c-title"><?= date('d M Y', strtotime($log['date'])) ?></div>
            <div class="c-sub">
              Target: <?= number_format((int)$log['actual_regs']) ?>/<?= number_format((int)$log['target_regs']) ?> Regs
            </div>
          </div>
          <div class="c-right">
            <div class="c-amt"><?= format_rp((float)$log['salary_rate']) ?></div>
            <div class="c-badge <?= $log['is_paid'] ? 'success' : 'warn' ?>"><?= $log['is_paid'] ? 'DIBAYAR' : 'PROSES' ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <a href="?page=<?= max(1, $page-1) ?>" style="padding:6px 12px; background:#fff; border:2px solid #1e3a8a; border-radius:8px; font-size:10px; font-weight:900; color:#1e3a8a; text-decoration:none; <?= $page<=1 ? 'opacity:0.5;pointer-events:none;' : '' ?>">← Prev</a>
        <span style="font-size:11px; font-weight:900; color:#fff;">Hal <?= $page ?>/<?= $total_pages ?></span>
        <a href="?page=<?= min($total_pages, $page+1) ?>" style="padding:6px 12px; background:#fff; border:2px solid #1e3a8a; border-radius:8px; font-size:10px; font-weight:900; color:#1e3a8a; text-decoration:none; <?= $page>=$total_pages ? 'opacity:0.5;pointer-events:none;' : '' ?>">Next →</a>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- TAB 2: GRAFIK -->
  <div id="tab-chart" class="p-content">
    <div class="chart-box">
      <div style="font-size:11px; font-weight:900; color:#1e3a8a; margin-bottom:8px; text-transform:uppercase;">Volume Clicks (7 Hari)</div>
      <canvas id="clicksChart"></canvas>
    </div>
    <div class="chart-box">
      <div style="font-size:11px; font-weight:900; color:#1e3a8a; margin-bottom:8px; text-transform:uppercase;">Registrasi Baru (7 Hari)</div>
      <canvas id="regsChart"></canvas>
    </div>
  </div>

  <!-- TAB 3: DOWNLINE -->
  <div id="tab-dl" class="p-content">
    <?php if (empty($downlines)): ?>
      <div style="text-align:center; padding:20px; background:rgba(255,255,255,0.2); border-radius:12px; border:2.5px dashed rgba(255,255,255,0.4);">
        <i class="ph-bold ph-users" style="font-size:32px; color:#fff; opacity:0.8;"></i>
        <div style="font-size:11px; font-weight:800; color:#fff; margin-top:8px;">Belum ada member yang mendaftar.</div>
      </div>
    <?php else: ?>
      <div class="c-list">
        <?php foreach ($downlines as $idx => $dl): ?>
        <div class="c-item dl-item-row" style="<?= $idx >= 5 ? 'display:none' : '' ?>">
          <div class="c-ico pink"><i class="ph-bold ph-user"></i></div>
          <div class="c-body">
            <div class="c-title"><?= htmlspecialchars($dl['username']) ?></div>
            <div class="c-sub">Join: <?= date('d M Y', strtotime($dl['created_at'])) ?></div>
          </div>
          <div class="c-right">
            <div class="c-amt" style="color:#0284c7; font-size:11px;"><?= (int)$dl['dep_count'] ?>x Depo</div>
            <div class="c-badge free" style="margin-top:2px;"><?= htmlspecialchars($dl['membership_name'] ?: 'Free') ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php if (count($downlines) > 5): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <button onclick="dlPrev()" id="dl-btn-prev" style="padding:6px 12px; background:#fff; border:2px solid #1e3a8a; border-radius:8px; font-size:10px; font-weight:900; color:#1e3a8a; opacity:0.5; pointer-events:none; cursor:pointer;">← Prev</button>
        <span id="dl-page-info" style="font-size:11px; font-weight:900; color:#fff;">1/<?= ceil(count($downlines) / 5) ?></span>
        <button onclick="dlNext()" id="dl-btn-next" style="padding:6px 12px; background:#fff; border:2px solid #1e3a8a; border-radius:8px; font-size:10px; font-weight:900; color:#1e3a8a; cursor:pointer;">Next →</button>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- TAB 4: FAKE WD -->
  <div id="tab-fwd" class="p-content">
    <?php if ($fwd_flash): ?>
    <div class="f-alert <?= $fwd_flashType ?>"><?= htmlspecialchars($fwd_flash) ?></div>
    <?php endif; ?>

    <!-- Form Input -->
    <div class="c-item yellow" style="display:block; padding:14px; margin-bottom:14px;">
      <form method="POST" id="fwd-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="fake_wd">

        <div style="background:#fffbeb; border:2px solid #fcd34d; border-radius:8px; padding:8px 10px; margin-bottom:12px;">
          <div style="font-size:9px; font-weight:900; color:#b45309; text-transform:uppercase; margin-bottom:2px;">Rekening Pencairan</div>
          <?php if (!empty($user['bank_name'])): ?>
          <div style="font-size:11px; font-weight:800; color:#78350f;">
            <?= htmlspecialchars($user['bank_name']) ?> · <?= htmlspecialchars(mask_account($user['account_number'] ?? '')) ?><br>
            a/n <?= htmlspecialchars($user['account_name']) ?>
          </div>
          <?php else: ?>
          <div style="font-size:10px; font-weight:800; color:#ea580c;">⚠️ Belum ada rekening. <a href="/edit-rekening" style="color:#c2410c;">Isi sekarang</a>.</div>
          <?php endif; ?>
        </div>

        <div class="f-group">
          <label class="f-label" style="color:#9a3412;">Jumlah WD (Rp)</label>
          <input class="f-input" type="number" name="fwd_amount" placeholder="Contoh: 250000" required style="border-color:#fcd34d;">
        </div>

        <div class="f-group">
          <label class="f-label" style="color:#9a3412;">Tanggal</label>
          <input class="f-input" type="date" name="fwd_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required style="border-color:#fcd34d;">
        </div>

        <div class="f-group">
          <label class="f-label" style="color:#9a3412;">Status</label>
          <select class="f-input" name="fwd_status" style="border-color:#fcd34d;">
            <option value="approved">✅ Approved</option>
            <option value="pending">⏳ Pending</option>
          </select>
        </div>

        <button type="submit" class="f-btn" style="background:linear-gradient(135deg,#f97316,#ea580c);border-color:#c2410c;box-shadow:0 4px 0 #9a3412;">
          <i class="ph-bold ph-floppy-disk"></i> Simpan Data WD
        </button>
      </form>
    </div>

    <!-- Fake WD List -->
    <?php if (!empty($fake_wds)): ?>
    <div class="sec-title"><i class="ph-bold ph-clock-counter-clockwise"></i> Riwayat WD Fake</div>
    <div class="c-list">
      <?php foreach ($fake_wds as $fw): ?>
      <?php $wl = $channel_logos[strtolower($fw['bank_name'])] ?? null; ?>
      <div class="c-item yellow">
        <?php if ($wl): ?>
        <div class="c-ico" style="border:none; box-shadow:none;"><img src="/assets/banks/<?= htmlspecialchars($wl) ?>" style="width:100%;height:100%;object-fit:contain;border-radius:6px;"></div>
        <?php else: ?>
        <div class="c-ico yellow"><i class="ph-bold ph-money"></i></div>
        <?php endif; ?>
        <div class="c-body">
          <div class="c-title" style="color:#9a3412;"><?= format_rp((float)$fw['amount']) ?></div>
          <div class="c-sub"><?= htmlspecialchars($fw['bank_name']) ?> · <?= date('d M H:i', strtotime($fw['created_at'])) ?></div>
        </div>
        <div class="c-right">
          <div class="c-badge <?= $fw['status'] === 'approved' ? 'success' : 'warn' ?>"><?= ucfirst($fw['status']) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Tabs
function switchTab(tabId) {
  document.querySelectorAll('.p-tab').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.p-content').forEach(c => c.classList.remove('active'));
  document.querySelector(`.p-tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
  document.getElementById(tabId).classList.add('active');
}

// Downline Pagination
let dlCurrentPage = 1;
const dlLimit = 5;
const dlTotal = <?= count($downlines) ?>;
const dlTotalPages = Math.max(1, Math.ceil(dlTotal / dlLimit));

function updateDlPagination() {
  const items = document.querySelectorAll('.dl-item-row');
  items.forEach((item, idx) => {
    item.style.display = (idx >= (dlCurrentPage - 1) * dlLimit && idx < dlCurrentPage * dlLimit) ? 'flex' : 'none';
  });
  
  const info = document.getElementById('dl-page-info');
  if (info) info.textContent = dlCurrentPage + '/' + dlTotalPages;
  
  const prevBtn = document.getElementById('dl-btn-prev');
  if (prevBtn) {
    prevBtn.style.opacity = dlCurrentPage <= 1 ? '0.5' : '1';
    prevBtn.style.pointerEvents = dlCurrentPage <= 1 ? 'none' : 'auto';
  }
  const nextBtn = document.getElementById('dl-btn-next');
  if (nextBtn) {
    nextBtn.style.opacity = dlCurrentPage >= dlTotalPages ? '0.5' : '1';
    nextBtn.style.pointerEvents = dlCurrentPage >= dlTotalPages ? 'none' : 'auto';
  }
}
function dlPrev() { if (dlCurrentPage > 1) { dlCurrentPage--; updateDlPagination(); } }
function dlNext() { if (dlCurrentPage < dlTotalPages) { dlCurrentPage++; updateDlPagination(); } }

// Charts
document.addEventListener('DOMContentLoaded', () => {
  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { font: { size: 9, family: 'Nunito' } } },
      x: { ticks: { font: { size: 9, family: 'Nunito' } } }
    }
  };

  const ctxClicks = document.getElementById('clicksChart');
  if (ctxClicks) {
    new Chart(ctxClicks.getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
          data: <?= json_encode($chart_data) ?>,
          backgroundColor: '#3b82f6',
          borderRadius: 4
        }]
      },
      options: commonOptions
    });
  }

  const ctxRegs = document.getElementById('regsChart');
  if (ctxRegs) {
    new Chart(ctxRegs.getContext('2d'), {
      type: 'line',
      data: {
        labels: <?= json_encode($chart_reg_labels) ?>,
        datasets: [{
          data: <?= json_encode($chart_reg_data) ?>,
          borderColor: '#10b981',
          backgroundColor: 'rgba(16,185,129,0.2)',
          fill: true,
          tension: 0.3,
          borderWidth: 2,
          pointRadius: 3
        }]
      },
      options: commonOptions
    });
  }
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
