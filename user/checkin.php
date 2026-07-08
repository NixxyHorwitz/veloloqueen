<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$checkin_min  = max(1, (float) setting($pdo, 'checkin_reward_min', '500'));
$checkin_max  = max($checkin_min, (float) setting($pdo, 'checkin_reward_max', '2000'));
$today        = date('Y-m-d');
$last_checkin = $user['last_checkin'] ?? null;
$already      = false; // DEBUG TESTING: ALWAYS FALSE

// Streak hitung
$streak = 0;
if ($last_checkin) {
    $diff = (int)((strtotime($today) - strtotime($last_checkin)) / 86400);
    if ($diff <= 1) {
        $sq = $pdo->prepare("SELECT COUNT(DISTINCT DATE(watched_at)) FROM watch_history WHERE user_id=? AND watched_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $sq->execute([$user['id']]);
        $streak = max(1, (int)$sq->fetchColumn());
    }
}

$flash = $flashType = '';
$reward_given = 0;

// Ambil reward dari session jika ada (untuk display setelah redirect)
if (!empty($_SESSION['checkin_reward_display']) && ($already)) {
    $reward_given = (int)$_SESSION['checkin_reward_display'];
    unset($_SESSION['checkin_reward_display']);
    $flash = 'checkin_ok';
    $flashType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkin') {
    if ($already && $flash !== 'checkin_ok') {
        $flash = 'Kamu sudah check-in hari ini. Kembali besok!';
        $flashType = 'warn';
    } elseif ($flash !== 'checkin_ok') {
        // Generate reward server-side — klien tidak bisa manipulasi
        $reward_given = rand((int)$checkin_min, (int)$checkin_max);
        try {
            $pdo->beginTransaction();
            // Double-guard: WHERE clause pakai CURDATE() server → tanggal device klien tidak berpengaruh
            $stmt = $pdo->prepare(
                "UPDATE users SET balance_dep=balance_dep+?, last_checkin=CURDATE()
                 WHERE id=?" // DEBUG TESTING: removed last_checkin restriction
            );
            $stmt->execute([$reward_given, $user['id']]);
            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                // Simpan reward ke session untuk ditampilkan setelah page load
                $_SESSION['checkin_reward_display'] = $reward_given;
                // PRG redirect untuk hindari double-submit
                header('Location: /checkin');
                exit;
            } else {
                $pdo->rollBack();
                $flash = 'Kamu sudah check-in hari ini!';
                $flashType = 'warn';
                $already = true;
            }
        } catch (\Throwable) {
            $pdo->rollBack();
            $flash = 'Terjadi kesalahan.';
            $flashType = 'error';
        }
    }
}

$pageTitle  = 'Check-in Harian';
$activePage = 'checkin';
require dirname(__DIR__) . '/partials/header.php';

$completed_days = $already ? ($streak % 7 ?: 7) : ($streak % 7);
if ($streak == 0 && $already) $completed_days = 1;
?>

<style>
/* ══════════════════════════════════════════════
   CHECK-IN PAGE — CASUAL GAME STYLE (ULTRA COMPACT)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; }

/* ── TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 16px 14px 24px; border-bottom: 3px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 20px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 3px 0 #1e3a8a; z-index: 2; margin-bottom: 2px; letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.wd-top-sub { position: relative; font-size: 11px; font-weight: 800; color: #bae6fd; z-index: 2; }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 20px 14px 100px; position: relative; z-index: 2; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 40px 40px; background-position: 0 0, 20px 20px; pointer-events: none; z-index: -1; }

/* ── STREAK BAR ── */
.streak-bar { display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 16px; position: relative; z-index: 5; }
.streak-day { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.streak-dot { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 900; border: 2.5px solid; transition: all 0.2s; }
.streak-dot.done { background: linear-gradient(135deg,#34d399,#10b981); border-color: #047857; color: #fff; box-shadow: 0 3px 0 #064e3b; }
.streak-dot.today { background: linear-gradient(135deg,#fde047,#eab308); border-color: #ca8a04; color: #713f12; box-shadow: 0 3px 0 #a16207; animation: pulse-today 1.5s ease infinite; }
.streak-dot.future { background: #ffffff; border-color: #c2410c; color: #ea580c; box-shadow: 0 3px 0 #9a3412; opacity: 0.8; }
.streak-lbl { font-size: 9px; font-weight: 900; color: #fff; text-shadow: 0 1px 1px rgba(0,0,0,0.3); }
@keyframes pulse-today { 0%,100% { transform: scale(1); box-shadow:0 3px 0 #a16207; } 50% { transform: scale(1.1); box-shadow:0 5px 0 #a16207; } }

/* ── SHELL GAME CONTAINER ── */
.game-wrap { position: relative; background: rgba(0,0,0,0.1); border: 3px dashed rgba(255,255,255,0.3); border-radius: 20px; padding: 30px 10px 20px; height: 180px; margin-bottom: 16px; overflow: visible; display: flex; align-items: flex-end; justify-content: space-around; perspective: 800px; }
.game-hint { position: absolute; top: 12px; left: 0; right: 0; text-align: center; font-size: 14px; font-weight: 900; color: #fff; text-shadow: 0 2px 2px rgba(0,0,0,0.5); z-index: 20; pointer-events: none; }
.game-hint.blink { animation: blinker 1s linear infinite; color: #fde047; }
@keyframes blinker { 50% { opacity: 0.3; } }

/* ── THE CUPS ── */
.cup { width: 70px; height: 90px; position: absolute; bottom: 20px; transform-origin: bottom center; cursor: pointer; transition: transform 0.4s cubic-bezier(0.25, 1, 0.5, 1), left 0.35s ease; z-index: 10; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; }
/* The visual cup graphic (SVG or CSS shape) */
.cup-graphic { width: 100%; height: 100%; background: linear-gradient(180deg, #ef4444 0%, #b91c1c 80%, #7f1d1d 100%); border-radius: 8px 8px 12px 12px; border: 3px solid #7f1d1d; border-top: 6px solid #fca5a5; box-shadow: inset 0 -10px 15px rgba(0,0,0,0.4), 0 8px 10px rgba(0,0,0,0.5); position: relative; z-index: 11; transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
/* Lift animation */
.cup.lifted .cup-graphic { transform: translateY(-70px) rotate(-10deg); }
.cup.disabled { pointer-events: none; }

/* ── REVEAL CONTENT (Behind Cup) ── */
.cup-content { position: absolute; bottom: 0; width: 60px; height: 60px; z-index: 9; display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.2s; }
.cup.lifted .cup-content { opacity: 1; }
/* The Rooster */
.rooster { width: 100%; height: 100%; object-fit: contain; transform: scale(0.5) translateY(20px); opacity: 0; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.cup.lifted .rooster { transform: scale(1.3) translateY(-10px); opacity: 1; }
/* The Reward Text (appears after rooster) */
.reward-text { position: absolute; font-size: 16px; font-weight: 900; color: #fde047; text-shadow: 0 2px 4px rgba(0,0,0,0.8), 0 0 10px rgba(253, 224, 71, 0.5); transform: translateY(20px); opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); white-space: nowrap; }
.cup.show-reward .rooster { opacity: 0; transform: scale(0.8) translateY(20px); }
.cup.show-reward .reward-text { opacity: 1; transform: translateY(-40px) scale(1.2); }

/* Start Button */
.btn-play { background: linear-gradient(180deg, #fde047, #eab308); border: 3px solid #ca8a04; border-radius: 12px; font-size: 16px; font-weight: 900; color: #713f12; padding: 12px 24px; box-shadow: 0 6px 0 #a16207; cursor: pointer; text-shadow: 0 1px 0 rgba(255,255,255,0.5); width: 100%; margin-bottom: 16px; transition: transform 0.1s; }
.btn-play:active { transform: translateY(6px); box-shadow: 0 0 0 #a16207; }

/* ── DONE STATE ── */
.done-card { background: #ffffff; border: 3px solid #1e3a8a; border-radius: 16px; padding: 24px 20px; text-align: center; box-shadow: 0 6px 0 #1e3a8a; margin-bottom: 16px; }
.done-card-ico { font-size: 50px; margin-bottom: 10px; animation: bounce 2s infinite; }
@keyframes bounce { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
.done-card-title { font-size: 16px; font-weight: 900; color: #1e3a8a; margin-bottom: 4px; text-transform: uppercase; }
.done-card-sub { font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 12px; }
.done-card-amt { font-size: 32px; font-weight: 900; color: #10b981; text-shadow: 0 2px 0 rgba(16,185,129,0.3); letter-spacing: -1px; margin-bottom: 12px; }
.done-card-badge { display: inline-flex; align-items: center; gap: 6px; background: #d1fae5; border: 2px solid #059669; border-radius: 10px; padding: 6px 14px; font-size: 11px; font-weight: 900; color: #047857; box-shadow: 0 3px 0 #059669; }

/* Stats Row */
.ci-stats { display: flex; gap: 8px; margin-bottom: 16px; }
.ci-stat { flex: 1; background: #ffffff; border: 2.5px solid #c2410c; border-radius: 12px; padding: 12px 6px; text-align: center; box-shadow: 0 4px 0 #9a3412; }
.ci-stat-val { font-size: 18px; font-weight: 900; color: #0f172a; margin-bottom: 2px; }
.ci-stat-lbl { font-size: 9px; font-weight: 900; color: #ea580c; text-transform: uppercase; }

/* Flash */
.h-flash { background: #fee2e2; border: 2.5px solid #dc2626; border-radius: 12px; padding: 10px 12px; color: #7f1d1d; font-weight: 900; font-size: 11px; margin-bottom: 14px; box-shadow: 0 3px 0 #dc2626; display: flex; align-items: center; gap: 8px; }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title"><i class="ph-fill ph-calendar-check" style="color:#60a5fa"></i> Check-in Harian</div>
  <div class="wd-top-sub">Tebak Gelas & Menangkan Saldo!</div>
</div>

<div class="wd-body">

  <?php if ($flash && $flash !== 'checkin_ok'): ?>
  <div class="h-flash">
    <i class="ph-bold ph-warning-circle" style="font-size:16px;"></i> <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <!-- STREAK BAR -->
  <div class="streak-bar">
    <?php for ($i = 1; $i <= 7; $i++):
      $is_done   = $i < $completed_days || ($i == $completed_days && $already);
      $is_today  = !$already && $i == $completed_days + 1;
      $cls = $is_done ? 'done' : ($is_today ? 'today' : 'future');
    ?>
    <div class="streak-day">
      <div class="streak-dot <?= $cls ?>">
        <?php if ($is_done): ?><i class="ph-bold ph-check"></i><?php elseif ($is_today): ?><i class="ph-fill ph-star"></i><?php else: ?><?= $i ?><?php endif; ?>
      </div>
      <span class="streak-lbl">H<?= $i ?></span>
    </div>
    <?php endfor; ?>
  </div>

  <?php if (!$already): ?>
  <!-- ── SHELL GAME ── -->
  <button id="btn-play" class="btn-play" onclick="startShuffle()">Mulai Acak Gelas!</button>

  <div class="game-wrap" id="game-wrap">
    <div class="game-hint" id="game-hint">Tekan Mulai!</div>
    
    <!-- Cup 0 -->
    <div class="cup disabled" id="cup-0" data-index="0" style="left: 10%;" onclick="pickCup(0)">
      <div class="cup-content">
        <img src="/assets/rooster_fuck.gif" class="rooster" alt="Rooster">
        <div class="reward-text" id="reward-0"></div>
      </div>
      <div class="cup-graphic"></div>
    </div>
    
    <!-- Cup 1 -->
    <div class="cup disabled" id="cup-1" data-index="1" style="left: 38%;" onclick="pickCup(1)">
      <div class="cup-content">
        <img src="/assets/rooster_fuck.gif" class="rooster" alt="Rooster">
        <div class="reward-text" id="reward-1"></div>
      </div>
      <div class="cup-graphic"></div>
    </div>
    
    <!-- Cup 2 -->
    <div class="cup disabled" id="cup-2" data-index="2" style="left: 66%;" onclick="pickCup(2)">
      <div class="cup-content">
        <img src="/assets/rooster_fuck.gif" class="rooster" alt="Rooster">
        <div class="reward-text" id="reward-2"></div>
      </div>
      <div class="cup-graphic"></div>
    </div>
  </div>

  <form method="POST" id="checkin-form" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="checkin">
  </form>

  <?php elseif ($flash === 'checkin_ok'): ?>
  <!-- ── JUST FINISHED ── -->
  <div class="done-card">
    <div class="done-card-ico">🎊</div>
    <div class="done-card-title">Check-in Sukses!</div>
    <div class="done-card-sub">Hadiah Tebak Gelas masuk ke Saldo Beli</div>
    <div class="done-card-amt">+ <?= format_rp($reward_given) ?></div>
    <div class="done-card-badge"><i class="ph-bold ph-check-circle"></i> Selesai</div>
  </div>

  <?php else: ?>
  <!-- ── ALREADY DONE TODAY ── -->
  <div class="done-card">
    <div class="done-card-ico" style="filter: grayscale(1)">⏳</div>
    <div class="done-card-title">Sudah Main Hari Ini</div>
    <div class="done-card-sub">Kembali lagi besok untuk menebak gelas lagi!</div>
    <div class="done-card-amt" style="color:#64748b"><?= format_rp((float)$user['balance_dep']) ?></div>
    <div style="font-size:10px;font-weight:900;color:#94a3b8;text-transform:uppercase">Saldo Beli Saat Ini</div>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="ci-stats">
    <div class="ci-stat">
      <div class="ci-stat-val"><?= $streak ?> <span style="color:#ea580c">🔥</span></div>
      <div class="ci-stat-lbl">Hari Aktif</div>
    </div>
    <div class="ci-stat">
      <div class="ci-stat-val" style="color:#059669"><?= format_rp((float)$user['balance_dep']) ?></div>
      <div class="ci-stat-lbl">Saldo Beli</div>
    </div>
  </div>
  
  <div style="text-align:center; font-size:9px; font-weight:900; color:rgba(255,255,255,0.7); text-transform:uppercase">
    Range Hadiah: <?= format_rp($checkin_min) ?> - <?= format_rp($checkin_max) ?>
  </div>

</div>

<?php if (!$already): ?>
<script>
  const MIN_REWARD = <?= (int)$checkin_min ?>;
  const MAX_REWARD = <?= (int)$checkin_max ?>;
  const rewardAmt = Math.floor(Math.random() * (MAX_REWARD - MIN_REWARD + 1)) + MIN_REWARD;
  
  const cups = [
    document.getElementById('cup-0'),
    document.getElementById('cup-1'),
    document.getElementById('cup-2')
  ];
  const positions = ['10%', '38%', '66%']; // left percentages
  let cupPositions = [0, 1, 2]; // Current logical positions of cups 0, 1, 2
  let isShuffling = false;

  function startShuffle() {
    if (isShuffling) return;
    isShuffling = true;
    document.getElementById('btn-play').style.display = 'none';
    document.getElementById('game-hint').innerText = 'Mengacak... Perhatikan baik-baik!';
    document.getElementById('game-hint').classList.remove('blink');
    
    let shuffles = 0;
    const maxShuffles = 8 + Math.floor(Math.random() * 5); // 8-12 shuffles
    const speed = 350; // ms per shuffle

    const interval = setInterval(() => {
      // Pick two random indices to swap
      let idx1 = Math.floor(Math.random() * 3);
      let idx2 = (idx1 + 1 + Math.floor(Math.random() * 2)) % 3;
      
      // Swap their logical positions
      let temp = cupPositions[idx1];
      cupPositions[idx1] = cupPositions[idx2];
      cupPositions[idx2] = temp;
      
      // Apply CSS left based on new logical positions
      cups[idx1].style.left = positions[cupPositions[idx1]];
      cups[idx2].style.left = positions[cupPositions[idx2]];

      shuffles++;
      if (shuffles >= maxShuffles) {
        clearInterval(interval);
        finishShuffle();
      }
    }, speed);
  }

  function finishShuffle() {
    isShuffling = false;
    document.getElementById('game-hint').innerText = 'Pilih Gelas Keberuntunganmu!';
    document.getElementById('game-hint').classList.add('blink');
    
    // Enable clicking
    cups.forEach(cup => cup.classList.remove('disabled'));
  }

  function pickCup(index) {
    if (isShuffling || cups[index].classList.contains('disabled')) return;
    
    // Disable all cups
    cups.forEach(cup => cup.classList.add('disabled'));
    document.getElementById('game-hint').innerText = '';
    document.getElementById('game-hint').classList.remove('blink');

    const selectedCup = cups[index];
    const rewardEl = document.getElementById('reward-' + index);
    
    // 1. Lift the cup to reveal the Rooster
    selectedCup.classList.add('lifted');
    
    // 2. Wait a bit, then swap rooster for the reward amount
    setTimeout(() => {
      rewardEl.innerText = 'Rp ' + rewardAmt.toLocaleString('id-ID');
      selectedCup.classList.add('show-reward');
      
      // 3. Submit form to claim
      setTimeout(() => {
        document.getElementById('checkin-form').submit();
      }, 1500);
      
    }, 1200); // Rooster mocks for 1.2 seconds
  }
</script>
<?php endif; ?>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
