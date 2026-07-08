<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$pageTitle  = 'Panduan';
$activePage = 'panduan';

$free_limit  = (int)   setting($pdo, 'free_watch_limit', '5');
$min_wd      = (float) setting($pdo, 'min_withdraw', '50000');
$ref_bonus   = (float) setting($pdo, 'referral_bonus', '1000');

$panduan_intro    = setting($pdo, 'panduan_intro',      'Cara kerja platform reward video Meloton');
$panduan_step1    = setting($pdo, 'panduan_step1',      'Buat akun gratis, tidak perlu verifikasi ribet. Langsung bisa mulai tonton.');
$panduan_step2    = setting($pdo, 'panduan_step2',      'Setiap video yang ditonton hingga selesai akan otomatis memberikan reward ke Saldo Penarikan kamu.');
$panduan_step3    = setting($pdo, 'panduan_step3',      'Reward terkumpul di Saldo Penarikan. Cek progresmu di halaman Beranda kapan saja.');
$panduan_step4    = setting($pdo, 'panduan_step4',      'Minimal withdraw ' . format_rp($min_wd) . '. Proses cepat ke rekening/e-wallet pilihanmu.');
$panduan_faq      = setting($pdo, 'panduan_faq_custom', '');
$panduan_cta_text = setting($pdo, 'panduan_cta_text',   'Mulai Tonton Sekarang');
$panduan_cta_url  = setting($pdo, 'panduan_cta_url',    '/videos');

try {
    $memberships = $pdo->query("SELECT name, price, watch_limit, duration_days, description FROM memberships WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();
} catch (\Throwable) { $memberships = []; }

$mem_colors = ['#e0f2fe','#d1fae5','#fef08a','#fce7f3','#ede9fe'];
$mem_borders = ['#3b82f6','#059669','#d97706','#db2777','#7c3aed'];
$mem_icons  = ['🆓','🌱','⚡','🗡️','💎'];

require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* PANDUAN PAGE - ULTRA COMPACT CASUAL GAME UI */
.page-content { display: flex; flex-direction: column; padding-bottom: 0 !important; }
.pan-body { background: #fff8f0; padding: 14px 12px calc(var(--nav-h) + 24px); flex: 1; position: relative; }

/* HERO */
.pan-hero { background: linear-gradient(135deg, #a855f7, #7e22ce); padding: 14px 12px; border-bottom: 3px solid #581c87; position: relative; overflow: hidden; }
.pan-hero::after { content: '📖'; position: absolute; right: -10px; bottom: -20px; font-size: 80px; opacity: 0.2; transform: rotate(-15deg); }
.pan-hero-title { display: flex; align-items: center; gap: 8px; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #4c1d95; margin-bottom: 4px; position: relative; z-index: 2; }
.pan-hero-title i { background: #fff; color: #7e22ce; width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 18px; border: 2px solid #fde047; box-shadow: 0 3px 0 #581c87; }
.pan-hero-desc { font-size: 11px; font-weight: 800; color: #e9d5ff; position: relative; z-index: 2; line-height: 1.3; }

/* BENTO BOXES */
.pan-section { background: #fff; border: 2.5px solid #cbd5e1; border-radius: 14px; padding: 12px; margin-bottom: 14px; box-shadow: 0 4px 0 #cbd5e1; }
.pan-section--blue { border-color: #60a5fa; box-shadow: 0 4px 0 #60a5fa; }
.pan-section--green { border-color: #34d399; box-shadow: 0 4px 0 #34d399; }
.pan-section--orange { border-color: #fb923c; box-shadow: 0 4px 0 #fb923c; }
.pan-section--red { border-color: #f87171; box-shadow: 0 4px 0 #f87171; }

.pan-sec-title { display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 900; color: #0f172a; margin-bottom: 10px; }
.pan-sec-title i { font-size: 16px; }
.pan-section--blue .pan-sec-title { color: #1e40af; }
.pan-section--green .pan-sec-title { color: #065f46; }
.pan-section--orange .pan-sec-title { color: #9a3412; }
.pan-section--red .pan-sec-title { color: #991b1b; }

/* STEPS */
.pan-step { display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; }
.pan-step:last-child { margin-bottom: 0; }
.pan-step-num { width: 24px; height: 24px; border-radius: 8px; background: #3b82f6; color: #fff; font-size: 12px; font-weight: 900; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid #1e3a8a; box-shadow: 0 2px 0 #1e3a8a; }
.pan-step-text { font-size: 11px; font-weight: 700; color: #475569; line-height: 1.4; padding-top: 2px; }

/* TIPS */
.pan-tip { display: flex; gap: 8px; background: #f8fafc; border: 2px solid #e2e8f0; padding: 8px; border-radius: 10px; margin-bottom: 8px; align-items: center; }
.pan-tip:last-child { margin-bottom: 0; }
.pan-tip-icon { font-size: 18px; flex-shrink: 0; }
.pan-tip-text { font-size: 11px; font-weight: 700; color: #334155; line-height: 1.3; }
.pan-tip-text strong { color: #0f172a; font-weight: 900; }

/* MEMBERSHIP COMPACT */
.mem-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px; }
.mem-c { background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 8px; display: flex; flex-direction: column; gap: 4px; }
.mem-c-head { display: flex; justify-content: space-between; align-items: center; }
.mem-c-name { font-size: 11px; font-weight: 900; display: flex; align-items: center; gap: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mem-c-price { font-size: 10px; font-weight: 900; color: #fff; background: #ef4444; padding: 2px 6px; border-radius: 6px; border: 1.5px solid #991b1b; box-shadow: 0 2px 0 #991b1b; }
.mem-c-price.free { background: #10b981; border-color: #047857; box-shadow: 0 2px 0 #047857; }
.mem-c-stat { font-size: 10px; font-weight: 800; color: #475569; display: flex; align-items: center; gap: 4px; }

/* FAQ COMPACT */
.faq-i { border: 2px solid #cbd5e1; border-radius: 10px; margin-bottom: 8px; background: #fff; overflow: hidden; }
.faq-i:last-child { margin-bottom: 0; }
.faq-q { padding: 10px; font-size: 11px; font-weight: 900; color: #1e293b; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
.faq-q::after { content: '\25BC'; font-size: 9px; color: #64748b; transition: transform 0.2s; }
.faq-a { padding: 0 10px; font-size: 11px; font-weight: 700; color: #475569; line-height: 1.4; max-height: 0; opacity: 0; transition: all 0.2s; }
.faq-i.open .faq-q::after { transform: rotate(180deg); }
.faq-i.open .faq-q { border-bottom: 2px dashed #e2e8f0; }
.faq-i.open .faq-a { padding: 10px; max-height: 500px; opacity: 1; }

/* CTA */
.pan-cta { background: linear-gradient(135deg, #10b981, #059669); border: 2.5px solid #fff; border-radius: 14px; box-shadow: 0 5px 0 #047857; color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; font-size: 14px; font-weight: 900; text-decoration: none; margin-top: 20px; transition: transform 0.1s; }
.pan-cta:active { transform: translateY(4px); box-shadow: 0 1px 0 #047857; }
</style>

<div class="pan-hero">
  <div class="pan-hero-title"><i class="ph-bold ph-book-open"></i> Panduan</div>
  <div class="pan-hero-desc"><?= htmlspecialchars($panduan_intro) ?></div>
</div>

<div class="pan-body">

  <!-- CARA KERJA -->
  <div class="pan-section pan-section--blue">
    <div class="pan-sec-title"><i class="ph-fill ph-info"></i> Cara Kerja</div>
    <div class="pan-step">
      <div class="pan-step-num">1</div>
      <div class="pan-step-text"><?= htmlspecialchars($panduan_step1) ?></div>
    </div>
    <div class="pan-step">
      <div class="pan-step-num">2</div>
      <div class="pan-step-text"><?= htmlspecialchars($panduan_step2) ?></div>
    </div>
    <div class="pan-step">
      <div class="pan-step-num">3</div>
      <div class="pan-step-text"><?= htmlspecialchars($panduan_step3) ?></div>
    </div>
    <div class="pan-step">
      <div class="pan-step-num">4</div>
      <div class="pan-step-text"><?= htmlspecialchars($panduan_step4) ?></div>
    </div>
  </div>

  <!-- TENTANG SALDO -->
  <div class="pan-section pan-section--green">
    <div class="pan-sec-title"><i class="ph-fill ph-wallet"></i> Tentang Saldo</div>
    <div class="pan-tip">
      <div class="pan-tip-icon">💰</div>
      <div class="pan-tip-text"><strong>Saldo Tarik (WD):</strong> Berasal dari reward video & misi. Bisa dicairkan ke rekening/e-wallet.</div>
    </div>
    <div class="pan-tip">
      <div class="pan-tip-icon">💳</div>
      <div class="pan-tip-text"><strong>Saldo Beli:</strong> Diisi via deposit. Hanya untuk upgrade paket membership (tidak bisa ditarik).</div>
    </div>
  </div>

  <!-- MEMBERSHIP -->
  <div class="pan-section pan-section--red">
    <div class="pan-sec-title"><i class="ph-fill ph-crown"></i> Paket Membership</div>
    <div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:10px">Upgrade paket untuk limit tonton lebih tinggi!</div>
    
    <?php if (!empty($memberships)): ?>
    <div class="mem-grid">
      <?php foreach ($memberships as $i => $mem):
        $color = $mem_colors[$i % count($mem_colors)];
        $border = $mem_borders[$i % count($mem_borders)];
        $icon  = $mem_icons[$i % count($mem_icons)];
        $isFree = (float)$mem['price'] === 0.0;
      ?>
      <div class="mem-c" style="border-color: <?= $border ?>;">
        <div class="mem-c-head">
          <div class="mem-c-name" style="color: <?= $border ?>;"><?= $icon ?> <?= htmlspecialchars($mem['name']) ?></div>
          <div class="mem-c-price <?= $isFree ? 'free' : '' ?>"><?= $isFree ? 'GRATIS' : format_rp((float)$mem['price']) ?></div>
        </div>
        <div class="mem-c-stat">📺 <?= $mem['watch_limit'] ?> video/hari</div>
        <?php if (!$isFree): ?>
        <div class="mem-c-stat">📅 Aktif <?= $mem['duration_days'] ?> hari</div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a href="/upgrade" style="display:block; text-align:center; font-size:11px; font-weight:900; color:#dc2626; margin-top:10px; text-decoration:none;">Beli Paket Sekarang <i class="ph-bold ph-arrow-right"></i></a>
  </div>

  <!-- REFERRAL -->
  <div class="pan-section pan-section--orange">
    <div class="pan-sec-title"><i class="ph-fill ph-users-three"></i> Program Referral</div>
    <div class="pan-tip">
      <div class="pan-tip-icon">🎁</div>
      <div class="pan-tip-text">Ajak teman pakai kode referralmu dan dapatkan <strong><?= format_rp($ref_bonus) ?></strong> per teman. Plus nikmati komisi berlapis hingga 3 level!</div>
    </div>
    <a href="/referral" style="display:block; text-align:center; font-size:11px; font-weight:900; color:#ea580c; margin-top:8px; text-decoration:none;">Bagikan Kodemu <i class="ph-bold ph-arrow-right"></i></a>
  </div>

  <!-- FAQ -->
  <div class="pan-section">
    <div class="pan-sec-title"><i class="ph-fill ph-question"></i> Pertanyaan Umum</div>
    <div class="faq-i">
      <div class="faq-q">Kapan reward masuk ke saldo?</div>
      <div class="faq-a">Otomatis masuk setelah video selesai ditonton sesuai durasi minimum.</div>
    </div>
    <div class="faq-i">
      <div class="faq-q">Apakah bisa skip video?</div>
      <div class="faq-a">Tidak bisa. Reward hanya diberikan jika ditonton tanpa skip.</div>
    </div>
    <div class="faq-i">
      <div class="faq-q">Apakah daftar gratis?</div>
      <div class="faq-a">Ya! Daftar gratis. Deposit hanya diperlukan jika ingin upgrade paket.</div>
    </div>
    <div class="faq-i">
      <div class="faq-q">Withdraw ke mana saja?</div>
      <div class="faq-a">Ke semua bank & e-wallet Indonesia. Min penarikan <?= format_rp($min_wd) ?>.</div>
    </div>
    <?php if ($panduan_faq): ?>
    <div class="faq-i">
      <div class="faq-q">Informasi tambahan</div>
      <div class="faq-a"><?= nl2br(htmlspecialchars($panduan_faq)) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <a href="<?= htmlspecialchars($panduan_cta_url) ?>" class="pan-cta">
    <i class="ph-fill ph-play-circle" style="font-size:22px;"></i> <?= htmlspecialchars($panduan_cta_text) ?>
  </a>

</div>

<script>
document.querySelectorAll('.faq-q').forEach(q => {
  q.addEventListener('click', () => {
    const item = q.closest('.faq-i');
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-i').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
