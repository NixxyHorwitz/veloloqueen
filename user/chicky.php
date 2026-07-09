<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$pageTitle  = 'Chicky Run';
$activePage = 'home'; 

require dirname(__DIR__) . '/partials/header.php';
?>
<style>
/* ══════════════════════════════════════════════
   CHICKY RUN PAGE — CASUAL GAME STYLE
   ══════════════════════════════════════════════ */
html body { background: #f97316 !important; font-family: 'Nunito', sans-serif; }

/* ── BLUE TOP BANNER ── */
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
.wd-top-left { display: flex; align-items: center; gap: 10px; }
.wd-back-btn {
  width: 32px; height: 32px; background: #fde047; border: none; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; color: #ca8a04; font-size: 16px;
  box-shadow: 0 3px 0 #a16207; text-decoration: none; flex-shrink: 0; transition: transform 0.1s;
}
.wd-back-btn:active { transform: translateY(3px); }
.wd-top-title { font-size: 18px; font-weight: 900; color: #fff; text-shadow: 0 2px 0 #0369a1; line-height: 1.1; margin-bottom: 2px; }
.wd-top-sub   { font-size: 11px; font-weight: 800; color: #e0f2fe; }

/* ── ORANGE BODY ── */
.wd-body {
  flex: 1; background: #f97316; padding: 14px 14px 100px; position: relative;
  display: flex; flex-direction: column; align-items: center;
}
.wd-body::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%),
              radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%);
  background-size: 50px 50px; background-position: 0 0, 25px 25px; pointer-events: none;
}

/* ── GAME CONTAINER ── */
.game-wrapper {
  width: 100%; max-width: 500px; 
  background: #fff; border: 4px solid #1e3a8a; border-radius: 20px;
  box-shadow: 0 8px 0 #1e3a8a; padding: 10px;
  position: relative; z-index: 2;
  margin-top: 10px;
}
.game-header {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 10px; padding: 0 5px;
}
.score-box {
  background: #fef3c7; border: 2px solid #f59e0b; border-radius: 12px;
  padding: 6px 12px; font-weight: 900; color: #d97706; font-size: 18px;
  box-shadow: 0 3px 0 #d97706;
}
.hi-score {
  font-size: 12px; font-weight: 800; color: #64748b;
}

canvas {
  width: 100%; height: auto;
  border-radius: 12px; border: 2px solid #cbd5e1;
  background: linear-gradient(180deg, #bae6fd 0%, #e0f2fe 100%);
  display: block; cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

/* OVERLAYS */
.overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.5);
  border-radius: 12px; z-index: 10;
  display: flex; align-items: center; justify-content: center;
}
.overlay.hidden { display: none !important; }
.overlay-card {
  background: rgba(255,255,255,0.95); border: 3px solid #1e3a8a; border-radius: 16px;
  padding: 20px; text-align: center; box-shadow: 0 6px 0 #1e3a8a;
  display: flex; flex-direction: column; gap: 10px;
  max-width: 80%;
}
.overlay-card h2 { font-size: 22px; font-weight: 900; color: #e11d48; margin: 0; text-shadow: 0 2px 0 #9f1239; }
.overlay-card p { font-size: 13px; font-weight: 700; color: #475569; margin: 0; }
.btn-play {
  background: linear-gradient(135deg, #fbbf24, #f59e0b); border: 2px solid #d97706;
  border-radius: 12px; padding: 10px; font-size: 15px; font-weight: 900; color: #7c2d12;
  box-shadow: 0 4px 0 #b45309; cursor: pointer; font-family: 'Nunito', sans-serif;
  margin-top: 5px; transition: transform 0.1s;
}
.btn-play:active { transform: translateY(4px); box-shadow: none; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-inner">
    <div class="wd-top-left">
      <a href="/home" class="wd-back-btn"><i class="ph-bold ph-arrow-left"></i></a>
      <div>
        <div class="wd-top-title">Chicky Run</div>
        <div class="wd-top-sub">Lompat! Hindari rintangan!</div>
      </div>
    </div>
  </div>
</div>

<div class="wd-body">
  <div class="game-wrapper">
    <div class="game-header">
      <div class="hi-score">HI: <span id="hiScoreVal">00000</span></div>
      <div class="score-box">HI: <span id="scoreVal" style="display:none"></span><span id="scoreDisplay">00000</span></div>
    </div>
    
    <div style="position:relative;">
      <canvas id="gameCanvas" width="600" height="300"></canvas>
      <!-- DOM GIF for character running (animates perfectly in all browsers) -->
      <img id="chickyDom" src="/assets/running.gif" style="position:absolute; width:10.666%; height:21.333%; left:8.333%; top:58.666%; z-index:5; pointer-events:none; display:none;" crossorigin="anonymous" />

      
      <!-- Loader Screen -->
      <div id="loaderScreen" class="overlay">
        <div class="overlay-card">
          <h2 style="color:#0891b2; text-shadow:none;"><i class="ph-bold ph-spinner-gap" style="display:inline-block; animation:spin 1s linear infinite;"></i></h2>
          <p>Memuat Aset Game...</p>
        </div>
      </div>

      <!-- Start Screen -->
      <div id="startScreen" class="overlay hidden">
        <div class="overlay-card">
          <h2 style="color:#0284c7; text-shadow:0 2px 0 #0369a1;">CHICKY RUN</h2>
          <p>Ketuk/Spasi untuk lompat</p>
          <button class="btn-play" onclick="startGame()">Mulai Game</button>
        </div>
      </div>

      <!-- Game Over Screen -->
      <div id="gameOverScreen" class="overlay hidden">
        <div class="overlay-card">
          <h2>GAME OVER</h2>
          <p>Skormu: <span id="finalScore">0</span></p>
          <button class="btn-play" onclick="startGame()">Main Lagi</button>
        </div>
      </div>
    </div>
  </div>
</div>



<script>
const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');
const scoreDisplay = document.getElementById('scoreDisplay');
const hiScoreVal = document.getElementById('hiScoreVal');
const startScreen = document.getElementById('startScreen');
const gameOverScreen = document.getElementById('gameOverScreen');
const chickyDom = document.getElementById('chickyDom');

// Game constants
const CANVAS_W = 600;
const CANVAS_H = 300;
const GROUND_Y = 240;

// Variables
let animationId;
let isPlaying = false;
let score = 0;
let hiScore = localStorage.getItem('chickyHiScore') || 0;
hiScoreVal.innerText = String(Math.floor(hiScore)).padStart(5, '0');

let baseSpeed = 6;
let currentSpeed = baseSpeed;
let frameCount = 0;
let obstacles = [];
let clouds = [];

// Chicky Player
const chicky = {
  w: 64, h: 64,
  x: 50, y: GROUND_Y - 64,
  vy: 0, gravity: 0.7, jumpStrength: -13,
  isJumping: false
};

// Frozen frame canvas (for when jumping)
const frozenCanvas = document.createElement('canvas');
frozenCanvas.width = chicky.w;
frozenCanvas.height = chicky.h;
const frozenCtx = frozenCanvas.getContext('2d');
let hasFrozenFrame = false;

// Controls
function jump() {
  if (!isPlaying) return;
  if (!chicky.isJumping) {
    chicky.isJumping = true;
    chicky.vy = chicky.jumpStrength;
    
    // Freeze the GIF at the moment of jump!
    hasFrozenFrame = false; // reset flag so it captures the immediate frame in the game loop
  }
}
window.addEventListener('keydown', e => { if (e.code === 'Space') { e.preventDefault(); jump(); } });
canvas.addEventListener('touchstart', e => { e.preventDefault(); jump(); }, {passive: false});
canvas.addEventListener('mousedown', jump);

function spawnObstacle() {
  const types = [
    { w: 30, h: 50, color: '#ef4444', border: '#b91c1c' }, // Tall red block
    { w: 50, h: 30, color: '#f59e0b', border: '#b45309' }, // Wide orange block
    { w: 40, h: 40, color: '#10b981', border: '#047857' }  // Square green block
  ];
  const t = types[Math.floor(Math.random() * types.length)];
  obstacles.push({
    x: CANVAS_W,
    y: GROUND_Y - t.h,
    w: t.w, h: t.h,
    color: t.color, border: t.border,
    passed: false
  });
}

function spawnCloud() {
  clouds.push({
    x: CANVAS_W,
    y: Math.random() * 100 + 20,
    size: Math.random() * 20 + 20,
    speed: Math.random() * 1 + 0.5
  });
}

function resetGame() {
  score = 0;
  currentSpeed = baseSpeed;
  frameCount = 0;
  obstacles = [];
  clouds = [];
  chicky.y = GROUND_Y - chicky.h;
  chicky.vy = 0;
  chicky.isJumping = false;
  hasFrozenFrame = false;
  scoreDisplay.innerText = '00000';
}

function startGame() {
  startScreen.classList.add('hidden');
  gameOverScreen.classList.add('hidden');
  resetGame();
  isPlaying = true;
  loop();
}

function gameOver() {
  isPlaying = false;
  cancelAnimationFrame(animationId);
  gameOverScreen.classList.remove('hidden');
  document.getElementById('finalScore').innerText = Math.floor(score);
  
  if (score > hiScore) {
    hiScore = score;
    localStorage.setItem('chickyHiScore', hiScore);
    hiScoreVal.innerText = String(Math.floor(hiScore)).padStart(5, '0');
  }
}

function loop() {
  if (!isPlaying) return;
  ctx.clearRect(0, 0, CANVAS_W, CANVAS_H);

  // Update Game Logic
  frameCount++;
  currentSpeed = baseSpeed + (score * 0.005); // Speed increases with score!

  // Score
  score += 0.1;
  scoreDisplay.innerText = String(Math.floor(score)).padStart(5, '0');

  // Spawn entities
  if (frameCount % Math.max(60, Math.floor(120 - currentSpeed*5)) === 0) {
    if (Math.random() > 0.2) spawnObstacle();
  }
  if (frameCount % 100 === 0) {
    spawnCloud();
  }

  // Draw Background (Sky) handled by CSS, just draw clouds
  for (let i = 0; i < clouds.length; i++) {
    let c = clouds[i];
    c.x -= c.speed;
    ctx.fillStyle = 'rgba(255,255,255,0.7)';
    ctx.beginPath();
    ctx.arc(c.x, c.y, c.size, 0, Math.PI * 2);
    ctx.fill();
  }
  clouds = clouds.filter(c => c.x + c.size > 0);

  // Draw Ground
  ctx.fillStyle = '#4ade80';
  ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
  ctx.fillStyle = '#16a34a';
  ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);

  // Update & Draw Obstacles
  for (let i = 0; i < obstacles.length; i++) {
    let obs = obstacles[i];
    obs.x -= currentSpeed;
    
    // Draw bento style obstacle
    ctx.fillStyle = obs.color;
    ctx.beginPath();
    ctx.roundRect(obs.x, obs.y, obs.w, obs.h, 6);
    ctx.fill();
    ctx.lineWidth = 3;
    ctx.strokeStyle = obs.border;
    ctx.stroke();

    // Collision detection (AABB)
    // Reduce hitbox slightly for fairness
    let hitX = 10, hitY = 10; 
    if (
      chicky.x + hitX < obs.x + obs.w &&
      chicky.x + chicky.w - hitX > obs.x &&
      chicky.y + hitY < obs.y + obs.h &&
      chicky.y + chicky.h - hitY > obs.y
    ) {
      gameOver();
      return;
    }
  }
  obstacles = obstacles.filter(o => o.x + o.w > 0);

  // Update Chicky
  if (chicky.isJumping) {
    chicky.vy += chicky.gravity;
    chicky.y += chicky.vy;
    
    if (chicky.y >= GROUND_Y - chicky.h) {
      chicky.y = GROUND_Y - chicky.h;
      chicky.isJumping = false;
      chicky.vy = 0;
      hasFrozenFrame = false; // Reset freeze when touching ground
      chickyDom.style.display = 'block'; // Resume animation
    }
  }

  // Draw Chicky
  if (chicky.isJumping) {
    chickyDom.style.display = 'none'; // Hide DOM element while jumping
    // FREEZE LOGIC: Capture frame once per jump
    if (!hasFrozenFrame && chickyDom.complete) {
      frozenCtx.clearRect(0, 0, chicky.w, chicky.h);
      frozenCtx.drawImage(chickyDom, 0, 0, chicky.w, chicky.h);
      hasFrozenFrame = true;
    }
    if (hasFrozenFrame) {
      ctx.drawImage(frozenCanvas, chicky.x, chicky.y, chicky.w, chicky.h);
    }
  } else {
    // On ground: let the GIF animate naturally via DOM. We don't draw on canvas!
  }

  animationId = requestAnimationFrame(loop);
}

// Initial render
function initAfterLoad() {
  document.getElementById('loaderScreen').classList.add('hidden');
  document.getElementById('startScreen').classList.remove('hidden');
  if (!isPlaying) {
    chickyDom.style.display = 'block'; // Show on ground
    // Draw initial ground
    ctx.fillStyle = '#4ade80';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
    ctx.fillStyle = '#16a34a';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);
  }
}

if (chickyDom.complete && chickyDom.naturalWidth > 0) {
  initAfterLoad();
} else {
  chickyDom.onload = initAfterLoad;
  chickyDom.onerror = initAfterLoad; // fallback
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
