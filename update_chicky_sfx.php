<?php
$file = 'c:/laragon/www/velostar/user/chicky.php';
$c = file_get_contents($file);

// 1. Add finalHiScore HTML
$oldHtml = <<<'OLD'
      <!-- Game Over Screen -->
      <div id="gameOverScreen" class="overlay hidden">
        <div class="overlay-card">
          <h2>GAME OVER</h2>
          <p>Skormu: <span id="finalScore">0</span></p>
          <button class="btn-play" onclick="startGame()">Main Lagi</button>
        </div>
      </div>
OLD;
$newHtml = <<<'NEW'
      <!-- Game Over Screen -->
      <div id="gameOverScreen" class="overlay hidden">
        <div class="overlay-card">
          <h2>GAME OVER</h2>
          <p>Skormu: <span id="finalScore">0</span></p>
          <p style="font-size:11px; margin-top:-5px;">Tertinggi: <span id="finalHiScore">0</span></p>
          <button class="btn-play" onclick="startGame()">Main Lagi</button>
        </div>
      </div>
NEW;
$c = str_replace($oldHtml, $newHtml, $c);

// 2. Base Speed
$c = str_replace("let baseSpeed = 4;", "let baseSpeed = 2.5;", $c);

// 3. Acceleration
$c = str_replace("currentSpeed = baseSpeed + (score * 0.005);", "currentSpeed = baseSpeed + (score * 0.003);", $c);

// 4. SFX Function & Jump update
$oldJump = <<<'OLD'
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
OLD;
$newJump = <<<'NEW'
// Web Audio SFX
const AudioCtx = window.AudioContext || window.webkitAudioContext;
let actx;
function playSfx(type) {
  try {
    if (!actx) actx = new AudioCtx();
    if (actx.state === 'suspended') actx.resume();
    const osc = actx.createOscillator();
    const gain = actx.createGain();
    osc.connect(gain); gain.connect(actx.destination);
    const t = actx.currentTime;
    if (type === 'jump') {
      osc.type = 'square';
      osc.frequency.setValueAtTime(150, t);
      osc.frequency.exponentialRampToValueAtTime(300, t + 0.1);
      gain.gain.setValueAtTime(0.05, t);
      gain.gain.exponentialRampToValueAtTime(0.01, t + 0.1);
      osc.start(t); osc.stop(t + 0.1);
    } else if (type === 'point') {
      osc.type = 'sine';
      osc.frequency.setValueAtTime(600, t);
      osc.frequency.setValueAtTime(800, t + 0.05);
      gain.gain.setValueAtTime(0.05, t);
      gain.gain.linearRampToValueAtTime(0, t + 0.1);
      osc.start(t); osc.stop(t + 0.1);
    } else if (type === 'die') {
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(200, t);
      osc.frequency.exponentialRampToValueAtTime(50, t + 0.3);
      gain.gain.setValueAtTime(0.1, t);
      gain.gain.linearRampToValueAtTime(0, t + 0.3);
      osc.start(t); osc.stop(t + 0.3);
    }
  } catch(e) {}
}

// Controls
function jump() {
  if (!isPlaying) return;
  if (!chicky.isJumping) {
    chicky.isJumping = true;
    chicky.vy = chicky.jumpStrength;
    hasFrozenFrame = false; 
    playSfx('jump');
  }
}
NEW;
$c = str_replace($oldJump, $newJump, $c);

// 5. Game Over update
$oldGameOver = <<<'OLD'
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
OLD;
$newGameOver = <<<'NEW'
function gameOver() {
  isPlaying = false;
  cancelAnimationFrame(animationId);
  playSfx('die');
  gameOverScreen.classList.remove('hidden');
  document.getElementById('finalScore').innerText = Math.floor(score);
  
  if (score > hiScore) {
    hiScore = score;
    localStorage.setItem('chickyHiScore', hiScore);
    hiScoreVal.innerText = String(Math.floor(hiScore)).padStart(5, '0');
  }
  let finalHiScoreEl = document.getElementById('finalHiScore');
  if(finalHiScoreEl) finalHiScoreEl.innerText = Math.floor(hiScore);
}
NEW;
$c = str_replace($oldGameOver, $newGameOver, $c);

// 6. Point SFX in loop
$oldLoopObs = <<<'OLD'
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
OLD;
$newLoopObs = <<<'NEW'
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
    
    // Check if passed for SFX
    if (!obs.passed && obs.x + obs.w < chicky.x) {
      obs.passed = true;
      playSfx('point');
    }
  }
  obstacles = obstacles.filter(o => o.x + o.w > 0);
NEW;
$c = str_replace($oldLoopObs, $newLoopObs, $c);

file_put_contents($file, $c);
echo "Replaced successfully!\n";
