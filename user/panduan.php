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
/* ══════════════════════════════════════════════════════════
   PANDUAN PAGE — COMPACT MINIMALIST (CASUAL GAME UI v2)
   ══════════════════════════════════════════════════════════ */
body { background: #f97316 !important; }

/* HERO */
.pan-hero {
  background: linear-gradient(160deg, #a855f7 0%, #9333ea 55%, #7e22ce 100%);
  padding: 16px 14px 24px; position: relative; overflow: hidden;
  border-bottom: 3px solid #581c87;
}
.pan-hero::before {
  content: '📖'; position: absolute; right: -20px; bottom: -30px;
  font-size: 120px; opacity: 0.15; transform: rotate(-15deg); pointer-events: none;
}
.pan-hero-top { display: flex; align-items: center; gap: 12px; position: relative; z-index: 2; }
.pan-icon {
  width: 54px; height: 54px; background: #fff;
  border: 3px solid #fde68a; border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 26px; color: #9333ea; box-shadow: 0 4px 0 #581c87; flex-shrink: 0;
}
.pan-info { flex: 1; min-width: 0; }
.pan-title { font-size: 18px; font-weight: 900; color: #fff; text-shadow: 0 2px 4px rgba(0,0,0,0.2); line-height: 1.2; }
.pan-sub { font-size: 11px; font-weight: 700; color: #e9d5ff; margin-top: 4px; line-height: 1.3; }

/* CONTENT BODY */
.page-content { display: flex; flex-direction: column; padding-bottom: 0 !important; }
.pan-body { background: #fff8f0; padding: 16px 14px calc(var(--nav-h) + 24px); flex: 1; position: relative; }

/* PAN-CARD */
.pan-card {
  background: #fff; border: 2.5px solid #0f172a; border-radius: 16px;
  box-shadow: 0 5px 0 #0f172a; overflow: hidden; margin-bottom: 16px;
}
.pan-card--blue { border-color: #1e3a8a; box-shadow: 0 5px 0 #1e3a8a; }
.pan-card--green { border-color: #064e3b; box-shadow: 0 5px 0 #064e3b; }
.pan-card--red { border-color: #7f1d1d; box-shadow: 0 5px 0 #7f1d1d; }
.pan-card--purple { border-color: #4c1d95; box-shadow: 0 5px 0 #4c1d95; }
.pan-card--yellow { border-color: #78350f; box-shadow: 0 5px 0 #78350f; }

.pan-card__hd {
  padding: 12px 14px; font-weight: 900; font-size: 14px; color: #fff;
  display: flex; align-items: center; gap: 8px; text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}
.pan-card--blue .pan-card__hd { background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-bottom: 2px solid #1e3a8a; }
.pan-card--green .pan-card__hd { background: linear-gradient(135deg, #10b981, #059669); border-bottom: 2px solid #064e3b; }
.pan-card--red .pan-card__hd { background: linear-gradient(135deg, #ef4444, #b91c1c); border-bottom: 2px solid #7f1d1d; }
.pan-card--purple .pan-card__hd { background: linear-gradient(135deg, #a855f7, #7e22ce); border-bottom: 2px solid #4c1d95; }
.pan-card--yellow .pan-card__hd { background: linear-gradient(135deg, #f59e0b, #d97706); border-bottom: 2px solid #78350f; }
.pan-card--gray .pan-card__hd { background: linear-gradient(135deg, #64748b, #475569); border-bottom: 2px solid #334155; }

.pan-card__bd { padding: 14px; }

/* STEPS */
.p-step { display: flex; gap: 12px; margin-bottom: 12px; position: relative; }
.p-step:last-child { margin-bottom: 0; }
.p-step:not(:last-child)::before { content: ''; position: absolute; left: 16px; top: 36px; bottom: -8px; width: 2px; background: #bfdbfe; }
.p-step__num {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  background: linear-gradient(135deg, #fde047, #f59e0b);
  border: 2px solid #d97706; box-shadow: 0 3px 0 #b45309;
  display: flex; align-items: center; justify-content: center;
  font-weight: 900; font-size: 14px; color: #78350f; position: relative; z-index: 1;
}
.p-step__info { flex: 1; min-width: 0; padding-top: 2px; }
.p-step__title { font-weight: 900; font-size: 12px; color: #0f172a; margin-bottom: 2px; }
.p-step__desc { font-size: 11px; color: #475569; font-weight: 700; line-height: 1.4; }

/* TIP BOX */
.p-tip { display: flex; gap: 10px; align-items: flex-start; padding: 10px; border-radius: 12px; border: 2px solid; margin-bottom: 10px; }
.p-tip:last-child { margin-bottom: 0; }
.p-tip__icon { font-size: 20px; flex-shrink: 0; width: 34px; height: 34px; background: rgba(255,255,255,0.6); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 4px rgba(255,255,255,0.8); }
.p-tip__txt { font-size: 11px; font-weight: 700; color: #0f172a; line-height: 1.4; }
.p-tip__txt strong { font-weight: 900; }

/* MEMBERSHIPS */
.m-card { display: flex; flex-direction: column; border: 2.5px solid; border-radius: 14px; overflow: hidden; margin-bottom: 10px; background: #fff; }
.m-card:last-child { margin-bottom: 0; }
.m-card__hd { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; border-bottom: 2px solid; }
.m-card__name { font-weight: 900; font-size: 13px; display: flex; align-items: center; gap: 6px; color: #0f172a; }
.m-card__price { font-weight: 900; font-size: 11px; background: #fff; border: 2px solid; padding: 3px 8px; border-radius: 8px; }
.m-card__bd { padding: 10px 12px; background: rgba(255,255,255,0.5); }
.m-badge-wrap { display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; }
.m-badge { font-size: 9px; font-weight: 900; background: #fff; border: 1.5px solid; border-radius: 8px; padding: 3px 6px; color: #0f172a; }
.m-desc { font-size: 10px; font-weight: 700; color: #334155; line-height: 1.4; display: flex; flex-direction: column; gap: 4px; }
.m-desc div { display: flex; gap: 4px; align-items: flex-start; }

/* FAQ */
.f-item { border: 2px solid #cbd5e1; border-radius: 14px; margin-bottom: 8px; background: #f8fafc; overflow: hidden; transition: all 0.2s; }
.f-item:last-child { margin-bottom: 0; }
.f-item.open { border-color: #3b82f6; background: #eff6ff; box-shadow: 0 3px 0 #bfdbfe; }
.f-q { font-weight: 900; font-size: 12px; padding: 12px 14px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 8px; color: #0f172a; }
.f-q::after { content: '＋'; font-size: 14px; font-weight: 900; flex-shrink: 0; transition: transform .2s; color: #3b82f6; }
.f-item.open .f-q::after { transform: rotate(45deg); color: #2563eb; }
.f-a { font-size: 11px; color: #475569; font-weight: 700; line-height: 1.4; padding: 0 14px 14px; display: none; }
.f-item.open .f-a { display: block; }

/* CTA */
.p-cta {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: linear-gradient(135deg, #10b981, #059669);
  border: 2.5px solid #fff; border-radius: 16px; padding: 14px;
  font-size: 14px; font-weight: 900; color: #fff; text-decoration: none;
  box-shadow: 0 5px 0 #047857; margin-top: 20px; transition: transform 0.1s;
}
.p-cta:active { transform: translateY(4px); box-shadow: none; }
</style>

<div class="pan-hero">
  <div class="pan-hero-top">
    <div class="pan-icon"><i class="ph-fill ph-book-open"></i></div>
    <div class="pan-info">
      <div class="pan-title">Panduan Meloton</div>
      <div class="pan-sub"><?= htmlspecialchars($panduan_intro) ?></div>
    </div>
  </div>
</div>

<div class="pan-body">

  <!-- Cara Kerja -->
  <div class="pan-card pan-card--blue">
    <div class="pan-card__hd"><i class="ph-fill ph-target"></i> Cara Kerja</div>
    <div class="pan-card__bd">
      <div class="p-step">
        <div class="p-step__num">1</div>
        <div class="p-step__info">
          <div class="p-step__title">Daftar &amp; Login</div>
          <div class="p-step__desc"><?= nl2br(htmlspecialchars($panduan_step1)) ?></div>
        </div>
      </div>
      <div class="p-step">
        <div class="p-step__num">2</div>
        <div class="p-step__info">
          <div class="p-step__title">Tonton Video</div>
          <div class="p-step__desc"><?= nl2br(htmlspecialchars($panduan_step2)) ?></div>
        </div>
      </div>
      <div class="p-step">
        <div class="p-step__num">3</div>
        <div class="p-step__info">
          <div class="p-step__title">Kumpulkan Reward</div>
          <div class="p-step__desc"><?= nl2br(htmlspecialchars($panduan_step3)) ?></div>
        </div>
      </div>
      <div class="p-step">
        <div class="p-step__num">4</div>
        <div class="p-step__info">
          <div class="p-step__title">Tarik Saldo</div>
          <div class="p-step__desc"><?= nl2br(htmlspecialchars($panduan_step4)) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Jenis Saldo -->
  <div class="pan-card pan-card--green">
    <div class="pan-card__hd"><i class="ph-fill ph-wallet"></i> Jenis Saldo</div>
    <div class="pan-card__bd">
      <div class="p-tip" style="background:#d1fae5; border-color:#6ee7b7;">
        <div class="p-tip__icon">💸</div>
        <div class="p-tip__txt"><strong>Saldo Penarikan (WD)</strong> — Didapat dari reward tonton video, bonus referral, check-in, dan klaim misi. Bisa ditarik ke rekening/e-wallet.</div>
      </div>
      <div class="p-tip" style="background:#dbeafe; border-color:#93c5fd;">
        <div class="p-tip__icon">💳</div>
        <div class="p-tip__txt"><strong>Saldo Beli</strong> — Diisi via deposit. Digunakan untuk upgrade paket membership agar bisa tonton lebih banyak video &amp; dapat reward besar.</div>
      </div>
    </div>
  </div>

  <!-- Paket Membership -->
  <div class="pan-card pan-card--red">
    <div class="pan-card__hd"><i class="ph-fill ph-crown"></i> Paket Membership</div>
    <div class="pan-card__bd">
      <div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:12px">Upgrade paket untuk meningkatkan limit tonton harian dan akses reward yang lebih besar.</div>
      <?php if (!empty($memberships)): foreach ($memberships as $i => $mem):
        $color = $mem_colors[$i % count($mem_colors)];
        $border = $mem_borders[$i % count($mem_borders)];
        $icon  = $mem_icons[$i % count($mem_icons)];
        $isFree = (float)$mem['price'] === 0.0;
        $descLines = array_filter(array_map('trim', preg_split('/\r?\n/', $mem['description'] ?? '')));
      ?>
      <div class="m-card" style="background:<?= $color ?>; border-color:<?= $border ?>;">
        <div class="m-card__hd" style="border-bottom-color:<?= $border ?>;">
          <div class="m-card__name"><?= $icon ?> <?= htmlspecialchars($mem['name']) ?></div>
          <div class="m-card__price" style="color:<?= $border ?>; border-color:<?= $border ?>;">
            <?= $isFree ? 'GRATIS' : format_rp((float)$mem['price']) ?>
          </div>
        </div>
        <div class="m-card__bd">
          <div class="m-badge-wrap">
            <span class="m-badge" style="border-color:<?= $border ?>; color:<?= $border ?>;">📺 <?= $mem['watch_limit'] ?>× / hari</span>
            <?php if (!$isFree): ?>
            <span class="m-badge" style="border-color:<?= $border ?>; color:<?= $border ?>;">📅 <?= $mem['duration_days'] ?> hari</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($descLines)): ?>
          <div class="m-desc">
            <?php foreach ($descLines as $line): ?>
            <div><span style="color:<?= $border ?>;">•</span><span><?= htmlspecialchars($line) ?></span></div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
      <div style="font-size:11px; color:#64748b; font-weight:800; margin-top:14px; text-align:center;">
        Lihat detail di halaman <a href="/upgrade" style="color:#ef4444; text-decoration:none;">Upgrade →</a>
      </div>
    </div>
  </div>

  <!-- Program Referral -->
  <div class="pan-card pan-card--purple">
    <div class="pan-card__hd"><i class="ph-fill ph-users-three"></i> Program Referral</div>
    <div class="pan-card__bd">
      <div class="p-tip" style="background:#f3e8ff; border-color:#d8b4fe;">
        <div class="p-tip__icon">🎁</div>
        <div class="p-tip__txt">Ajak teman daftar pakai <strong>kode referralmu</strong>. Kamu dapat bonus <strong><?= format_rp($ref_bonus) ?></strong> untuk setiap teman. Komisi berlapis hingga 3 level!</div>
      </div>
      <div style="font-size:11px; color:#64748b; font-weight:800; margin-top:12px; text-align:center;">
        Kode referralmu ada di halaman <a href="/referral" style="color:#a855f7; text-decoration:none;">Squad →</a>
      </div>
    </div>
  </div>

  <!-- Check-in & Misi -->
  <div class="pan-card pan-card--yellow">
    <div class="pan-card__hd"><i class="ph-fill ph-calendar-check"></i> Check-in &amp; Misi</div>
    <div class="pan-card__bd">
      <div class="p-tip" style="background:#fef9c3; border-color:#fde047;">
        <div class="p-tip__icon">📅</div>
        <div class="p-tip__txt"><strong>Check-in Harian</strong> — Login setiap hari untuk dapat bonus reward harian. Makin lama streak, makin besar bonusnya!</div>
      </div>
      <div class="p-tip" style="background:#cffafe; border-color:#67e8f9;">
        <div class="p-tip__icon">🎯</div>
        <div class="p-tip__txt"><strong>Misi</strong> — Selesaikan misi harian &amp; mingguan untuk klaim reward tambahan ke Saldo Tarik.</div>
      </div>
    </div>
  </div>

  <!-- FAQ -->
  <div class="pan-card pan-card--gray">
    <div class="pan-card__hd" style="background: linear-gradient(135deg, #64748b, #475569);"><i class="ph-fill ph-question"></i> FAQ</div>
    <div class="pan-card__bd">
      <div class="f-item">
        <div class="f-q">Kapan reward masuk?</div>
        <div class="f-a">Reward otomatis masuk ke saldo setelah video ditonton sampai selesai (waktu minimum tercapai).</div>
      </div>
      <div class="f-item">
        <div class="f-q">Bisa skip video?</div>
        <div class="f-a">Tidak bisa. Reward hanya diberikan jika kamu menonton sampai durasi minimum.</div>
      </div>
      <div class="f-item">
        <div class="f-q">Apakah daftar bayar?</div>
        <div class="f-a">Gratis! Deposit hanya jika kamu ingin upgrade paket.</div>
      </div>
      <div class="f-item">
        <div class="f-q">Withdraw ke mana saja?</div>
        <div class="f-a">Semua bank &amp; e-wallet (GoPay, DANA, OVO). Minimal tarik <?= format_rp($min_wd) ?>.</div>
      </div>
      <?php if ($panduan_faq): ?>
      <div class="f-item">
        <div class="f-q">Info Tambahan</div>
        <div class="f-a"><?= nl2br(htmlspecialchars($panduan_faq)) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CTA -->
  <a href="<?= htmlspecialchars($panduan_cta_url) ?>" class="p-cta">
    <i class="ph-bold ph-play-circle" style="font-size:22px;"></i> <?= htmlspecialchars($panduan_cta_text) ?>
  </a>

</div>

<script>
document.querySelectorAll('.f-q').forEach(q => {
  q.addEventListener('click', () => {
    const item = q.closest('.f-item');
    const wasOpen = item.classList.contains('open');
    document.querySelectorAll('.f-item').forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
