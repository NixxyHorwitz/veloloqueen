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
   REFERRAL PAGE — COMPACT GAME STYLE
   ══════════════════════════════════════════════ */
.ref-page { padding: 12px 14px 24px; position: relative; z-index: 2;}

/* ── QUEST BANNER ── */
.quest-banner {
  background: linear-gradient(135deg, #0284c7, #0ea5e9);
  border: 3px solid #0369a1; border-radius: 16px;
  padding: 10px 12px; display: flex; align-items: center; gap: 12px;
  box-shadow: 0 4px 0 #075985; margin-bottom: 12px;
}
.quest-icon { width: 42px; height: 42px; border-radius: 12px; background: #fde047; border: 2.5px solid #ca8a04; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 3px 0 #ca8a04; flex-shrink: 0; }
.quest-info { flex: 1; }
.quest-title { font-size: 14px; font-weight: 900; color: #fff; margin-bottom: 2px; text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.quest-sub { font-size: 10px; font-weight: 800; color: #e0f2fe; }

/* ── PROMOTOR BANNER ── */
.promo-alert { background: linear-gradient(135deg, #10b981, #34d399); border: 2.5px solid #059669; border-radius: 12px; padding: 10px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 3px 0 #047857; margin-bottom: 12px; }
.promo-alert div { font-size: 11px; font-weight: 900; color: #fff; }
.promo-btn { background: #fde047; border: 2px solid #ca8a04; border-radius: 8px; font-size: 10px; font-weight: 900; color: #9a3412; padding: 6px 10px; box-shadow: 0 2px 0 #ca8a04; text-decoration: none; }
.promo-btn:active { transform: translateY(2px); box-shadow: 0 0 0 #ca8a04; }

/* ── STATS ROW ── */
.stat-row { display: flex; gap: 8px; margin-bottom: 12px; }
.stat-box { flex: 1; background: #ffffff; border: 2.5px solid #c2410c; border-radius: 12px; padding: 10px 6px; text-align: center; box-shadow: 0 3px 0 #9a3412; }
.stat-val { font-size: 13px; font-weight: 900; line-height: 1.2; }
.stat-val.blue { color: #0284c7; }
.stat-val.green { color: #16a34a; }
.stat-val.orange { color: #ea580c; }
.stat-lbl { font-size: 9px; font-weight: 900; color: #c2410c; margin-top: 2px; text-transform: uppercase; }

/* ── SHARE STRIP ── */
.share-strip { display: flex; align-items: center; gap: 8px; background: #fffbeb; border: 2.5px solid #c2410c; border-radius: 12px; padding: 8px; box-shadow: 0 3px 0 #9a3412; margin-bottom: 8px; }
.share-input { flex: 1; background: #ffffff; border: 2px solid #fb923c; border-radius: 8px; padding: 8px; font-size: 12px; font-weight: 900; color: #9a3412; outline: none; box-sizing: border-box; }
.share-input:focus { border-color: #ea580c; box-shadow: 0 0 0 3px rgba(234,88,12,0.2); }
.share-btn-copy { background: #fde047; border: 2px solid #ca8a04; border-radius: 8px; font-size: 11px; font-weight: 900; color: #9a3412; padding: 8px 12px; box-shadow: 0 3px 0 #ca8a04; cursor: pointer; flex-shrink: 0; }
.share-btn-copy:active { transform: translateY(3px); box-shadow: 0 0 0 #ca8a04; }

.share-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
.s-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border-radius: 12px; font-size: 12px; font-weight: 900; color: #fff; text-decoration: none; border: 2.5px solid rgba(0,0,0,0.2); box-shadow: 0 3px 0 rgba(0,0,0,0.3); transition: transform 0.1s; }
.s-btn:active { transform: translateY(3px); box-shadow: none; }
.s-btn.wa { background: linear-gradient(135deg, #4ade80, #22c55e); }
.s-btn.tg { background: linear-gradient(135deg, #60a5fa, #3b82f6); }

/* ── CARA KERJA ── */
.sec-title { font-size: 13px; font-weight: 900; color: #fff; text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
.sec-title i { color: #fde047; font-size: 16px; }

.step-list { background: #ffffff; border: 2.5px solid #c2410c; border-radius: 14px; box-shadow: 0 4px 0 #9a3412; padding: 12px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 10px; }
.step-item { display: flex; align-items: center; gap: 10px; }
.step-num { width: 28px; height: 28px; border-radius: 8px; background: #fb923c; border: 2px solid #ea580c; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900; color: #fff; flex-shrink: 0; }
.step-txt { font-size: 11px; font-weight: 800; color: #7c2d12; line-height: 1.3; }

/* ── COMPACT LISTS ── */
.c-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.c-item { display: flex; align-items: center; gap: 10px; background: #ffffff; border: 2.5px solid #c2410c; border-radius: 12px; padding: 10px; box-shadow: 0 3px 0 #9a3412; }
.c-ico { width: 36px; height: 36px; border-radius: 10px; border: 2.5px solid #c2410c; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.c-ico.blue { background: #e0f2fe; color: #0284c7; border-color: #0284c7; }
.c-ico.yellow { background: #fef08a; color: #d97706; border-color: #ca8a04; }
.c-body { flex: 1; min-width: 0; }
.c-title { font-size: 12px; font-weight: 900; color: #9a3412; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.c-sub { font-size: 9px; font-weight: 800; color: #ea580c; display: flex; align-items: center; gap: 4px; }
.c-right { text-align: right; }
.c-badge { font-size: 9px; font-weight: 900; padding: 2px 6px; border-radius: 6px; border: 1.5px solid; text-transform: uppercase; display: inline-block; margin-bottom: 2px; }
.c-badge.free { background: #e0f2fe; color: #0284c7; border-color: #0ea5e9; }
.c-badge.prem { background: #fdf4ff; color: #c026d3; border-color: #d946ef; }
.c-amt { font-size: 12px; font-weight: 900; color: #16a34a; }
.c-amt.gray { color: #9ca3af; font-size: 10px; }

/* Empty & Pagination */
.ref-empty { text-align: center; padding: 20px; border: 2.5px dashed #fb923c; border-radius: 14px; background: #fffbeb; margin-bottom: 16px; }
.ref-empty-ico { font-size: 32px; margin-bottom: 6px; opacity: 0.5; }
.ref-empty-txt { font-size: 11px; font-weight: 800; color: #ea580c; }
.ref-pg { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.ref-pg-btn { padding: 6px 12px; background: #fffbeb; border: 2.5px solid #fb923c; border-radius: 10px; font-size: 11px; font-weight: 900; color: #ea580c; box-shadow: 0 3px 0 #c2410c; cursor: pointer; transition: transform 0.1s; }
.ref-pg-btn:active { transform: translateY(3px); box-shadow: none; }
.ref-pg-info { font-size: 11px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
</style>

<div class="ref-page">

  <!-- QUEST BANNER -->
  <div class="quest-banner">
    <div class="quest-icon">🎯</div>
    <div class="quest-info">
      <div class="quest-title">Misi Referral</div>
      <div class="quest-sub">Ajak Teman, Panen Komisi</div>
    </div>
  </div>

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

  <!-- SHARE -->
  <div class="sec-title"><i class="ph-bold ph-share-network"></i> Bagikan Link</div>
  <div class="share-strip">
    <input id="ref-link-input" class="share-input" type="text" value="<?= htmlspecialchars($ref_url) ?>" readonly>
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
  const input = document.getElementById('ref-link-input');
  input.select();
  input.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(input.value).then(() => {
    const btn = document.getElementById('copy-btn');
    btn.textContent = '✅ Salin';
    setTimeout(() => btn.textContent = '📋 Salin', 2000);
  }).catch(() => {
    document.execCommand('copy');
  });
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
