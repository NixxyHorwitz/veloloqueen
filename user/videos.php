<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$watch_limit = user_watch_limit($pdo, $user);
$watch_today = user_watch_today($pdo, $user);

// Determine Sort Order
$sort_mode = setting($pdo, 'video_sort_mode', 'default');
$order_by = 'v.sort_order ASC, v.id DESC';
if ($sort_mode === 'newest') $order_by = 'v.id DESC';
if ($sort_mode === 'oldest') $order_by = 'v.id ASC';
if ($sort_mode === 'reward_desc') $order_by = 'v.reward_amount DESC, v.id DESC';
if ($sort_mode === 'reward_asc') $order_by = 'v.reward_amount ASC, v.id DESC';
if ($sort_mode === 'duration_asc') $order_by = 'v.watch_duration ASC, v.id DESC';
if ($sort_mode === 'random') $order_by = 'RAND()';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// All active videos with watch status for today
$videos = $pdo->prepare(
    "SELECT v.*,
       (SELECT COUNT(*) FROM watch_history wh
        WHERE wh.user_id=? AND wh.video_id=v.id AND DATE(wh.watched_at)=CURDATE()) AS watched_today
     FROM videos v
     WHERE v.is_active=1
     ORDER BY {$order_by}
     LIMIT {$limit} OFFSET {$offset}"
);
$videos->execute([$user['id']]);
$videos = $videos->fetchAll();

if (isset($_GET['ajax'])) {
    if (empty($videos)) {
        echo '';
        exit;
    }
    foreach ($videos as $v) {
        $done    = (bool)$v['watched_today'];
        $blocked = !$done && ($watch_today >= $watch_limit);
        $href    = ($done || $blocked) ? 'javascript:void(0)' : '/watch?id='.$v['id'];
        ?>
        <a href="<?= $href ?>" class="vcard <?= $done ? 'vcard--done' : '' ?>" <?= ($done||$blocked) ? 'style="pointer-events:none"' : '' ?>>
          <div class="vcard__thumb-wrapper">
            <img src="<?= yt_thumb($v['youtube_id']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" loading="lazy" onerror="this.src='https://img.youtube.com/vi/<?= $v['youtube_id'] ?>/hqdefault.jpg'">
            <div class="vcard__play">
              <?php if ($done): ?>
                <i class="ph-fill ph-check-circle" style="color:#10b981; filter: drop-shadow(0 4px 0 #047857); font-size:42px;"></i>
              <?php else: ?>
                <div class="vcard__play-btn"><i class="ph-fill ph-play"></i></div>
              <?php endif; ?>
            </div>
            <div class="vcard__badge <?= $done ? 'vcard__badge--done' : '' ?>">
              <?= $done ? '✓ Selesai' : '+'.format_rp((float)$v['reward_amount']) ?>
            </div>
          </div>
          <div class="vcard__info">
            <div class="vcard__title"><?= htmlspecialchars($v['title']) ?></div>
            <div class="vcard__meta">
              <span class="vcard__reward <?= $done ? 'vcard__reward--done' : '' ?>">
                <?php if ($done): ?>
                  <i class="ph-bold ph-check"></i> Selesai
                <?php else: ?>
                  <i class="ph-bold ph-coins" style="color:#eab308; font-size:14px"></i> <?= format_rp((float)$v['reward_amount']) ?>
                <?php endif; ?>
              </span>
              <span class="vcard__duration"><i class="ph-bold ph-clock"></i> <?= $v['watch_duration'] ?>s</span>
            </div>
          </div>
        </a>
        <?php
    }
    exit;
}

$pageTitle  = 'Tonton Video  ';
$activePage = 'videos';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
  body { background: #fff8f0 !important; }
</style>

<div style="padding: 16px 14px 100px;">
<div class="cg-card" style="background:#fff; border:3px solid #0f172a; border-radius:22px; box-shadow:0 6px 0 #0f172a; padding:16px; margin-bottom:16px; display:flex; align-items:center; gap:12px; margin-top: 4px;">
  <div style="width:48px;height:48px;background:#c4b5fd;border:3px solid #0f172a;border-radius:16px;box-shadow:0 4px 0 #0f172a;display:flex;align-items:center;justify-content:center;font-size:24px;color:#4c1d95;flex-shrink:0;">
    <i class="ph-bold ph-film-strip"></i>
  </div>
  <div style="flex:1;">
    <h1 style="font-size:18px;font-weight:900;color:#0f172a;margin:0;line-height:1.2;">Tonton Video</h1>
    <p style="font-size:11px;font-weight:800;color:#64748b;margin:0;">Selesaikan misi, kumpulkan reward!</p>
  </div>
</div>

<!-- Progress bar -->
<div class="cg-card" style="background:#0ea5e9; border:3px solid #0f172a; border-radius:22px; box-shadow:0 6px 0 #0f172a; padding:16px; margin-bottom:20px; color:#fff;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
    <span style="font-size:13px;font-weight:900; display:flex; align-items:center; gap:6px; text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
        <i class="ph-fill ph-chart-pie-slice" style="color:#fde047; font-size:20px;"></i> Progres Harian
    </span>
    <span style="font-size:12px;font-weight:900;background:#0369a1; border:2px solid #0f172a; padding:4px 10px; border-radius:12px; box-shadow: 0 3px 0 #0f172a;"><?= $watch_today ?>/<?= $watch_limit ?></span>
  </div>
  <div style="background:#0c4a6e;border-radius:20px;height:14px;overflow:hidden;border:2px solid #0f172a; box-shadow:inset 0 2px 4px rgba(0,0,0,0.5);">
    <?php $pct = $watch_limit > 0 ? min(100, round(($watch_today / $watch_limit) * 100)) : 0; ?>
    <div style="background:<?= $pct >= 100 ? '#10b981' : 'linear-gradient(90deg, #fde047, #f59e0b)' ?>;height:100%;width:<?= $pct ?>%;border-radius:20px;transition:width .5s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: inset 0 -3px 0 rgba(0,0,0,0.1);"></div>
  </div>
  <?php if ($watch_today >= $watch_limit): ?>
  <div style="font-size:11px;color:#fff;margin-top:14px;font-weight:900;background:#ef4444;padding:10px;border-radius:14px;border:3px solid #0f172a; box-shadow: 0 4px 0 #0f172a; display:flex; align-items:center; justify-content:space-between;">
    <div style="display:flex; align-items:center; gap:6px;"><i class="ph-bold ph-warning-circle" style="font-size:18px;"></i> Limit tercapai!</div>
    <a href="/upgrade" style="color:#fff;font-weight:900;text-decoration:none; background:#0f172a; padding:4px 10px; border-radius:10px;">Upgrade →</a>
  </div>
  <?php endif; ?>
</div>

<?php if (empty($videos)): ?>
<div style="background:#fff; border:3px solid #0f172a; border-radius:22px; padding:30px 16px; text-align:center; box-shadow:0 6px 0 #0f172a; margin-bottom:16px">
  <div style="width:64px;height:64px;background:#f1f5f9;border:3px solid #0f172a;box-shadow:0 4px 0 #0f172a;border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#94a3b8;font-size:32px;">
    <i class="ph-fill ph-video-camera-slash"></i>
  </div>
  <h3 style="font-size:16px;font-weight:900;color:#0f172a;margin:0 0 6px;">Wah, Kosong!</h3>
  <p style="font-size:12px;font-weight:700;color:#64748b;margin:0">Saat ini belum ada video baru untuk ditonton.</p>
</div>
<?php else: ?>

<style>
/* Casual Game Grid & Cards (Ultra Fresh) */
.vgrid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
    gap: 14px; 
    margin-bottom: 24px; 
}
.vcard { 
    text-decoration: none; 
    display: block; 
    background: #fff; 
    border: 3px solid #0f172a; 
    border-radius: 22px; 
    box-shadow: 0 6px 0 #0f172a; 
    transition: transform 0.1s, box-shadow 0.1s; 
    position: relative;
    padding: 8px; /* Frame effect */
}
.vcard:active { transform: translateY(4px); box-shadow: 0 2px 0 #0f172a; }
.vcard--done { opacity: 0.7; filter: grayscale(40%); box-shadow: 0 6px 0 #64748b; border-color: #64748b; }
.vcard--done:active { box-shadow: 0 2px 0 #64748b; }

.vcard__thumb-wrapper {
    position: relative; 
    aspect-ratio: 16/9; 
    background: #000; 
    border-radius: 14px; 
    border: 2px solid #0f172a;
    overflow: hidden;
    margin-bottom: 8px;
}
.vcard__thumb-wrapper img { 
    width: 100%; height: 100%; object-fit: cover; opacity: 0.9; transition: transform 0.3s ease;
}
.vcard:hover .vcard__thumb-wrapper img { transform: scale(1.05); }

.vcard__badge { 
    position: absolute; 
    top: 6px; right: 6px; 
    background: #f97316; 
    color: #fff; font-size: 10px; font-weight: 900; 
    padding: 3px 8px; border-radius: 12px; 
    border: 2px solid #0f172a; 
    box-shadow: 0 3px 0 #0f172a; 
    z-index: 2;
}
.vcard__badge--done { background: #10b981; }

.vcard__play { 
    position: absolute; inset: 0; 
    display: flex; align-items: center; justify-content: center; 
    background: rgba(15, 23, 42, 0.3); 
    opacity: 0; transition: opacity 0.2s; z-index: 1;
}
.vcard:hover .vcard__play { opacity: 1; }
.vcard--done:hover .vcard__play { opacity: 1; background: transparent; }

.vcard__play-btn {
    width: 40px; height: 40px;
    background: #eab308;
    border: 3px solid #0f172a;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px;
    box-shadow: 0 4px 0 #0f172a;
    transform: scale(0.8); transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    padding-left: 3px;
}
.vcard:hover .vcard__play-btn { transform: scale(1); }

.vcard__info { padding: 0 4px; }
.vcard__title { 
    font-size: 12px; font-weight: 900; color: #0f172a; 
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; 
    overflow: hidden; line-height: 1.3; height: 32px; 
    text-shadow: 0 1px 0 rgba(255,255,255,1);
}
.vcard__meta { 
    display: flex; align-items: center; justify-content: space-between; 
    font-size: 11px; font-weight: 900; color: #64748b; 
    margin-top: 6px; padding-top: 6px; border-top: 2px dashed #cbd5e1;
}
.vcard__reward { color: #d97706; display: flex; align-items: center; gap: 4px; }
.vcard__reward--done { color: #10b981; }
.vcard__duration { display: flex; align-items: center; gap: 4px; background:#f1f5f9; padding:2px 6px; border-radius:8px; color:#475569; }
</style>

<div class="vgrid" id="vgrid">
<?php foreach ($videos as $v):
  $done    = (bool)$v['watched_today'];
  $blocked = !$done && ($watch_today >= $watch_limit);
  $href    = ($done || $blocked) ? 'javascript:void(0)' : '/watch?id='.$v['id'];
?>
<a href="<?= $href ?>" class="vcard <?= $done ? 'vcard--done' : '' ?>" <?= ($done||$blocked) ? 'style="pointer-events:none"' : '' ?>>
  <div class="vcard__thumb-wrapper">
    <img src="<?= yt_thumb($v['youtube_id']) ?>" alt="<?= htmlspecialchars($v['title']) ?>" loading="lazy" onerror="this.src='https://img.youtube.com/vi/<?= $v['youtube_id'] ?>/hqdefault.jpg'">
    <div class="vcard__play">
      <?php if ($done): ?>
        <i class="ph-fill ph-check-circle" style="color:#10b981; filter: drop-shadow(0 4px 0 #047857); font-size:42px;"></i>
      <?php else: ?>
        <div class="vcard__play-btn"><i class="ph-fill ph-play"></i></div>
      <?php endif; ?>
    </div>
    <div class="vcard__badge <?= $done ? 'vcard__badge--done' : '' ?>">
      <?= $done ? '✓ Selesai' : '+'.format_rp((float)$v['reward_amount']) ?>
    </div>
  </div>
  <div class="vcard__info">
    <div class="vcard__title"><?= htmlspecialchars($v['title']) ?></div>
    <div class="vcard__meta">
      <span class="vcard__reward <?= $done ? 'vcard__reward--done' : '' ?>">
        <?php if ($done): ?>
          <i class="ph-bold ph-check"></i> Selesai
        <?php else: ?>
          <i class="ph-bold ph-coins" style="color:#eab308; font-size:14px"></i> <?= format_rp((float)$v['reward_amount']) ?>
        <?php endif; ?>
      </span>
      <span class="vcard__duration"><i class="ph-bold ph-clock"></i> <?= $v['watch_duration'] ?>s</span>
    </div>
  </div>
</a>
<?php endforeach; ?>
</div>

<div id="loader" style="text-align:center;padding:20px;display:none">
  <div style="background:#e2e8f0; width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto; box-shadow:0 4px 0 #cbd5e1;">
    <i class="ph-bold ph-spinner ph-spin" style="font-size:24px;color:#0ea5e9"></i>
  </div>
  <div style="font-size:12px;font-weight:800;color:#64748b;margin-top:12px">Memuat video...</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  let page = 1;
  let isLoading = false;
  let hasMore = <?= count($videos) === $limit ? 'true' : 'false' ?>;
  const grid = document.getElementById('vgrid');
  const loader = document.getElementById('loader');

  if (!hasMore) return; // No more pages to load initially

  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting && !isLoading && hasMore) {
      loadMore();
    }
  }, { rootMargin: '100px' });

  // Create a sentinel element to observe
  const sentinel = document.createElement('div');
  sentinel.style.height = '1px';
  grid.parentNode.insertBefore(sentinel, grid.nextSibling);
  observer.observe(sentinel);

  async function loadMore() {
    isLoading = true;
    loader.style.display = 'block';
    page++;

    try {
      const res = await fetch(`?ajax=1&page=${page}`);
      const html = await res.text();

      if (html.trim() === '') {
        hasMore = false;
        observer.unobserve(sentinel);
      } else {
        grid.insertAdjacentHTML('beforeend', html);
      }
    } catch (e) {
      console.error('Error loading more videos:', e);
      page--; // revert page count on error
    } finally {
      isLoading = false;
      loader.style.display = 'none';
    }
  }
});
</script>
<?php endif; ?>

</div>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
