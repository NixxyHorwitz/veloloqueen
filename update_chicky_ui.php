<?php
$file = 'c:/laragon/www/velostar/user/chicky.php';
$c = file_get_contents($file);

// 1. Replace CSS
$oldCss = <<<'OLD'
/* OVERLAYS */
.overlay {
  position: absolute; top: 70px; left: 50%; transform: translateX(-50%);
  background: rgba(255,255,255,0.9); border: 3px solid #1e3a8a; border-radius: 16px;
  padding: 20px; text-align: center; box-shadow: 0 6px 0 #1e3a8a;
  z-index: 10; display: flex; flex-direction: column; gap: 10px;
}
.overlay.hidden { display: none; }
.overlay h2 { font-size: 24px; font-weight: 900; color: #e11d48; margin: 0; text-shadow: 0 2px 0 #9f1239; }
.overlay p { font-size: 14px; font-weight: 700; color: #475569; margin: 0; }
.btn-play {
  background: linear-gradient(135deg, #fbbf24, #f59e0b); border: 2px solid #d97706;
  border-radius: 12px; padding: 10px; font-size: 16px; font-weight: 900; color: #7c2d12;
  box-shadow: 0 4px 0 #b45309; cursor: pointer; font-family: 'Nunito', sans-serif;
  margin-top: 5px; transition: transform 0.1s;
}
.btn-play:active { transform: translateY(4px); box-shadow: none; }
OLD;

$newCss = <<<'NEW'
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
NEW;

$c = str_replace($oldCss, $newCss, $c);


// 2. Replace HTML
$oldHtml = <<<'OLD'
      <!-- Start Screen -->
      <div id="startScreen" class="overlay">
        <h2 style="color:#0284c7; text-shadow:0 2px 0 #0369a1;">CHICKY RUN</h2>
        <p>Ketuk atau Spasi untuk lompat</p>
        <button class="btn-play" onclick="startGame()">Mulai Game</button>
      </div>

      <!-- Game Over Screen -->
      <div id="gameOverScreen" class="overlay hidden">
        <h2>GAME OVER</h2>
        <p>Skormu: <span id="finalScore">0</span></p>
        <button class="btn-play" onclick="startGame()">Main Lagi</button>
      </div>
OLD;

$newHtml = <<<'NEW'
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
NEW;

$c = str_replace($oldHtml, $newHtml, $c);


// 3. Replace JS Loader
$oldJs = <<<'OLD'
// Initial render
chickyImg.onload = () => {
  if (!isPlaying) {
    ctx.drawImage(chickyImg, chicky.x, chicky.y, chicky.w, chicky.h);
    // Draw initial ground
    ctx.fillStyle = '#4ade80';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
    ctx.fillStyle = '#16a34a';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);
  }
};
OLD;

$newJs = <<<'NEW'
// Initial render
function initAfterLoad() {
  document.getElementById('loaderScreen').classList.add('hidden');
  document.getElementById('startScreen').classList.remove('hidden');
  if (!isPlaying) {
    ctx.drawImage(chickyImg, chicky.x, chicky.y, chicky.w, chicky.h);
    // Draw initial ground
    ctx.fillStyle = '#4ade80';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
    ctx.fillStyle = '#16a34a';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);
  }
}

if (chickyImg.complete && chickyImg.naturalWidth > 0) {
  initAfterLoad();
} else {
  chickyImg.onload = initAfterLoad;
  chickyImg.onerror = initAfterLoad; // fallback
}
NEW;

$c = str_replace($oldJs, $newJs, $c);

file_put_contents($file, $c);
echo "Replaced successfully!\n";
