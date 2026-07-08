<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

// Referral stats
$s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
$s->execute([$user['referral_code']]);
$ref_count = (int)$s->fetchColumn();

$e = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM referral_commissions WHERE user_id=?");
$e->execute([$user['id']]);
$ref_earned = (float)$e->fetchColumn();

// Referral history
$hist = $pdo->prepare(
  "SELECT rc.amount, rc.created_at, u.username
   FROM referral_commissions rc
   JOIN users u ON u.id = rc.from_user_id
   WHERE rc.user_id = ?
   ORDER BY rc.created_at DESC LIMIT 20"
);
$hist->execute([$user['id']]);
$history = $hist->fetchAll();

// Referred users list
$refs = $pdo->prepare(
  "SELECT u.username, u.created_at, 
          COALESCE(m.name, '" . get_free_tier_name($pdo) . "') as membership_name,
          COALESCE((SELECT SUM(amount) FROM deposits WHERE user_id = u.id AND status = 'confirmed'), 0) as total_deposit,
          COALESCE((SELECT SUM(amount) FROM referral_commissions WHERE user_id = ? AND from_user_id = u.id), 0) as commission_earned
   FROM users u
   LEFT JOIN memberships m ON m.id = u.membership_id
   WHERE u.referred_by = ?
   ORDER BY u.created_at DESC"
);
$refs->execute([$user['id'], $user['referral_code']]);
$referreds = $refs->fetchAll();

$ref_url = base_url('register/' . $user['referral_code']);

$pageTitle  = 'Referral — Meloton';
$activePage = 'referral';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   REFERRAL PAGE — CASUAL GAME STYLE (HIGH CONTRAST)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; }

/* ── TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 24px 14px 50px; border-bottom: 4px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 30px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 26px; font-weight: 900; color: #fff; text-shadow: 0 4px 0 #1e3a8a; z-index: 2; margin-bottom: 6px; letter-spacing: -0.5px; }
.wd-top-sub { position: relative; font-size: 13px; font-weight: 800; color: #bae6fd; z-index: 2; }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 20px 14px 100px; position: relative; z-index: 2; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 50px 50px; background-position: 0 0, 25px 25px; pointer-events: none; z-index: -1; }

/* ── STATS ROW ── */
.stat-row { display: flex; gap: 8px; margin-bottom: 20px; position: relative; z-index: 5; }
.stat-box { flex: 1; background: #ffffff; border: 3px solid #1e3a8a; border-radius: 16px; padding: 14px 6px; text-align: center; box-shadow: 0 5px 0 #1e3a8a; }
.stat-val { font-size: 16px; font-weight: 900; line-height: 1.2; }
.stat-val.blue { color: #0284c7; }
.stat-val.green { color: #16a34a; }
.stat-val.orange { color: #ea580c; }
.stat-lbl { font-size: 10px; font-weight: 900; color: #64748b; margin-top: 4px; text-transform: uppercase; }

/* ── PROMOTOR ALERT ── */
.promo-alert { background: linear-gradient(135deg, #10b981, #34d399); border: 3px solid #059669; border-radius: 16px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 4px 0 #047857; margin-bottom: 20px; position: relative; z-index: 5; }
.promo-alert div { font-size: 13px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.promo-btn { background: #fde047; border: 2.5px solid #ca8a04; border-radius: 10px; font-size: 11px; font-weight: 900; color: #9a3412; padding: 8px 12px; box-shadow: 0 3px 0 #ca8a04; text-decoration: none; }
.promo-btn:active { transform: translateY(3px); box-shadow: 0 0 0 #ca8a04; }

/* ── SECTION TITLE ── */
.sec-title { font-size: 14px; font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 12px; margin-top: 24px; display: flex; align-items: center; gap: 8px; text-shadow: 0 2px 2px rgba(0,0,0,0.3); }
.sec-title i { color: #fde047; font-size: 20px; }

/* ── SHARE STRIP (DIRECT ON BODY) ── */
.share-strip { display: flex; align-items: center; justify-content: space-between; background: #fffbeb; border: 3px solid #c2410c; border-radius: 14px; padding: 10px 12px; box-shadow: 0 4px 0 #9a3412; margin-bottom: 12px; }
.share-lbl { font-size: 11px; font-weight: 900; color: #ea580c; text-transform: uppercase; margin-bottom: 2px; }
.share-val { font-size: 15px; font-weight: 900; color: #7c2d12; letter-spacing: 0.5px; }
.share-btn-copy { background: linear-gradient(180deg, #fde047, #eab308); border: 2.5px solid #ca8a04; border-radius: 10px; font-size: 12px; font-weight: 900; color: #713f12; padding: 10px 16px; box-shadow: 0 4px 0 #a16207; cursor: pointer; flex-shrink: 0; text-shadow: 0 1px 0 rgba(255,255,255,0.5); }
.share-btn-copy:active { transform: translateY(4px); box-shadow: 0 0 0 #a16207; }

.share-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 24px; }
.s-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 14px; border-radius: 16px; font-size: 13px; font-weight: 900; color: #fff; text-decoration: none; border: 3px solid rgba(0,0,0,0.2); box-shadow: 0 5px 0 rgba(0,0,0,0.3); transition: transform 0.1s; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
.s-btn:active { transform: translateY(5px); box-shadow: none; }
.s-btn.wa { background: linear-gradient(135deg, #4ade80, #16a34a); border-color: #15803d; box-shadow: 0 5px 0 #14532d; }
.s-btn.wa:active { box-shadow: 0 0 0 #14532d; }
.s-btn.tg { background: linear-gradient(135deg, #60a5fa, #2563eb); border-color: #1d4ed8; box-shadow: 0 5px 0 #1e3a8a; }
.s-btn.tg:active { box-shadow: 0 0 0 #1e3a8a; }

/* ── CARA KERJA (GLASSMORPHISM LIST) ── */
.step-list { background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); border-radius: 16px; padding: 16px; backdrop-filter: blur(8px); display: flex; flex-direction: column; gap: 12px; margin-bottom: 24px; }
.step-item { display: flex; align-items: flex-start; gap: 12px; }
.step-num { width: 32px; height: 32px; border-radius: 10px; background: linear-gradient(180deg, #fde047, #eab308); border: 2.5px solid #ca8a04; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 900; color: #713f12; flex-shrink: 0; box-shadow: 0 3px 0 #a16207; }
.step-txt { font-size: 13px; font-weight: 800; color: #fff; line-height: 1.4; padding-top: 4px; text-shadow: 0 1px 1px rgba(0,0,0,0.2); }

/* ── COMPACT LISTS (DIRECT ON BODY) ── */
.c-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.c-item { display: flex; align-items: center; gap: 12px; background: #ffffff; border: 3px solid #c2410c; border-radius: 16px; padding: 12px 14px; box-shadow: 0 4px 0 #9a3412; }
.c-ico { width: 44px; height: 44px; border-radius: 14px; border: 2.5px solid #c2410c; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; box-shadow: 0 3px 0 #9a3412; }
.c-ico.blue { background: #e0f2fe; color: #0284c7; border-color: #0369a1; box-shadow: 0 3px 0 #075985; }
.c-ico.yellow { background: linear-gradient(180deg, #fef08a, #facc15); color: #b45309; border-color: #a16207; box-shadow: 0 3px 0 #713f12; }
.c-body { flex: 1; min-width: 0; }
.c-title { font-size: 14px; font-weight: 900; color: #9a3412; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.c-sub { font-size: 11px; font-weight: 800; color: #ea580c; display: flex; align-items: center; gap: 4px; }
.c-right { text-align: right; }
.c-badge { font-size: 9px; font-weight: 900; padding: 3px 6px; border-radius: 6px; border: 1.5px solid; text-transform: uppercase; display: inline-block; margin-bottom: 4px; }
.c-badge.free { background: #e0f2fe; color: #0284c7; border-color: #0ea5e9; }
.c-badge.prem { background: #fdf4ff; color: #c026d3; border-color: #d946ef; }
.c-amt { font-size: 14px; font-weight: 900; color: #16a34a; letter-spacing: -0.5px; }
.c-amt.gray { color: #94a3b8; font-size: 11px; margin-top: 2px; }

/* Empty & Pagination */
.ref-empty { text-align: center; padding: 24px; border: 3px dashed rgba(255,255,255,0.4); border-radius: 16px; background: rgba(0,0,0,0.05); }
.ref-empty-ico { font-size: 40px; margin-bottom: 8px; opacity: 0.8; }
.ref-empty-txt { font-size: 13px; font-weight: 800; color: #fff; }
.ref-pg { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; }
.ref-pg-btn { padding: 8px 16px; background: #ffffff; border: 2.5px solid #c2410c; border-radius: 12px; font-size: 12px; font-weight: 900; color: #9a3412; box-shadow: 0 4px 0 #9a3412; cursor: pointer; transition: transform 0.1s; }
.ref-pg-btn:active { transform: translateY(4px); box-shadow: none; }
.ref-pg-info { font-size: 13px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title">Misi Referral</div>
  <div class="wd-top-sub">Ajak Teman & Panen Komisi Tiap Hari!</div>
</div>

<div class="wd-body">
  <?php if ((int)$user['is_promotor'] === 1): ?>
  <!-- PROMOTOR -->
  <div class="promo-alert">
    <div>🚀 Promotor Aktif</div>
    <a href="/user/promotor.php" class="promo-btn">Dashboard</a>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-val blue"><?= $ref_count ?></div>
      <div class="stat-lbl">Teman</div>
    </div>
    <div class="stat-box">
      <div class="stat-val green"><?= format_rp($ref_earned) ?></div>
      <div class="stat-lbl">Komisi</div>
    </div>
    <div class="stat-box">
      <div class="stat-val orange" style="font-family:monospace;letter-spacing:0px"><?= $user['referral_code'] ?></div>
      <div class="stat-lbl">Kode Unik</div>
    </div>
  </div>

  <!-- SHARE STRIP -->
  <div class="sec-title"><i class="ph-bold ph-share-network"></i> Bagikan Link</div>
  <div class="share-strip">
    <div>
      <div class="share-lbl">Kode Referral</div>
      <div class="share-val" id="ref-code"><?= htmlspecialchars($user['referral_code']) ?></div>
    </div>
    <button onclick="copyRef()" class="share-btn-copy" id="copy-btn">📋 Salin</button>
  </div>
  
  <div class="share-grid">
    <a href="https://wa.me/?text=<?= urlencode('Yuk gabung Meloton! Daftar pakai link ku: ' . $ref_url) ?>" target="_blank" class="s-btn wa">
      <i class="ph-bold ph-whatsapp-logo"></i> WhatsApp
    </a>
    <a href="https://t.me/share/url?url=<?= urlencode($ref_url) ?>&text=<?= urlencode('Gabung Meloton, dapat reward tiap nonton video!') ?>" target="_blank" class="s-btn tg">
      <i class="ph-bold ph-telegram-logo"></i> Telegram
    </a>
  </div>

  <!-- CARA KERJA -->
  <div class="sec-title"><i class="ph-bold ph-lightbulb"></i> Cara Kerja</div>
  <div class="step-list">
    <div class="step-item">
      <div class="step-num">1</div>
      <div class="step-txt">Bagikan link referral ke teman-temanmu.</div>
    </div>
    <div class="step-item">
      <div class="step-num">2</div>
      <div class="step-txt">Teman mendaftar melalui link tersebut.</div>
    </div>
    <div class="step-item">
      <div class="step-num">3</div>
      <div class="step-txt">Dapatkan komisi dari setiap transaksi mereka!</div>
    </div>
  </div>

  <!-- TEMAN BERGABUNG -->
  <div class="sec-title"><i class="ph-bold ph-users"></i> Teman Bergabung</div>
  <?php if (empty($referreds)): ?>
  <div class="ref-empty">
    <div class="ref-empty-ico">👥</div>
    <div class="ref-empty-txt">Belum ada teman yang bergabung.<br>Ayo bagikan link referral kamu!</div>
  </div>
  <?php else: ?>
  <div class="c-list">
    <?php foreach ($referreds as $idx => $r): 
      $isFree = (stripos((string)$r['membership_name'], 'Free') !== false || (string)$r['membership_name'] === '');
      $badgeCls = $isFree ? 'free' : 'prem';
    ?>
    <div class="c-item ref-item-row" data-index="<?= $idx ?>" style="<?= $idx >= 5 ? 'display:none' : '' ?>">
      <div class="c-ico blue"><i class="ph-fill ph-user-circle"></i></div>
      <div class="c-body">
        <div class="c-title"><?= htmlspecialchars($r['username']) ?></div>
        <div class="c-sub"><i class="ph-bold ph-calendar-blank"></i> <?= date('d M y', strtotime($r['created_at'])) ?></div>
      </div>
      <div class="c-right">
        <div class="c-badge <?= $badgeCls ?>"><?= htmlspecialchars($r['membership_name'] ?: 'Free') ?></div>
        <div class="c-amt">+<?= format_rp((float)$r['commission_earned']) ?></div>
        <div class="c-amt gray">Depo: <?= format_rp((float)$r['total_deposit']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($referreds) > 5): ?>
  <div class="ref-pg">
    <button onclick="refPrev()" id="ref-btn-prev" class="ref-pg-btn" style="pointer-events:none;opacity:.5">← Prev</button>
    <span id="ref-page-info" class="ref-pg-info">1/<?= ceil(count($referreds) / 5) ?></span>
    <button onclick="refNext()" id="ref-btn-next" class="ref-pg-btn">Next →</button>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- RIWAYAT KOMISI -->
  <?php if (!empty($history)): ?>
  <div class="sec-title"><i class="ph-bold ph-coins"></i> Riwayat Komisi</div>
  <div class="c-list">
    <?php foreach ($history as $h): ?>
    <div class="c-item">
      <div class="c-ico yellow"><i class="ph-fill ph-gift"></i></div>
      <div class="c-body">
        <div class="c-title">Dari <?= htmlspecialchars($h['username']) ?></div>
        <div class="c-sub"><i class="ph-bold ph-clock"></i> <?= date('d M y H:i', strtotime($h['created_at'])) ?></div>
      </div>
      <div class="c-right">
        <div class="c-amt">+<?= format_rp((float)$h['amount']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<script>
function copyRef() {
  const input = document.createElement('input');
  input.value = "<?= htmlspecialchars($ref_url) ?>";
  document.body.appendChild(input);
  input.select();
  document.execCommand('copy');
  document.body.removeChild(input);
  
  const btn = document.getElementById('copy-btn');
  btn.textContent = '✅ Salin';
  setTimeout(() => btn.textContent = '📋 Salin', 2000);
}

let refCurrentPage = 1;
const refLimit = 5;
const refTotal = <?= count($referreds) ?>;
const refTotalPages = Math.max(1, Math.ceil(refTotal / refLimit));

function updateRefPagination() {
  const items = document.querySelectorAll('.ref-item-row');
  items.forEach((item, idx) => {
    if (idx >= (refCurrentPage - 1) * refLimit && idx < refCurrentPage * refLimit) {
      item.style.display = 'flex';
    } else {
      item.style.display = 'none';
    }
  });
  
  const info = document.getElementById('ref-page-info');
  if (info) info.textContent = refCurrentPage + '/' + refTotalPages;
  
  const prevBtn = document.getElementById('ref-btn-prev');
  if (prevBtn) {
    prevBtn.style.opacity = refCurrentPage <= 1 ? '0.5' : '1';
    prevBtn.style.pointerEvents = refCurrentPage <= 1 ? 'none' : 'auto';
  }
  const nextBtn = document.getElementById('ref-btn-next');
  if (nextBtn) {
    nextBtn.style.opacity = refCurrentPage >= refTotalPages ? '0.5' : '1';
    nextBtn.style.pointerEvents = refCurrentPage >= refTotalPages ? 'none' : 'auto';
  }
}

function refPrev() {
  if (refCurrentPage > 1) {
    refCurrentPage--;
    updateRefPagination();
  }
}

function refNext() {
  if (refCurrentPage < refTotalPages) {
    refCurrentPage++;
    updateRefPagination();
  }
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
