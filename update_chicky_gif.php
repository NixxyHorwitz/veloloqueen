<?php
$file = 'c:/laragon/www/velostar/user/chicky.php';
$c = file_get_contents($file);

// 1. Move and update the img tag
$oldImg = '<img id="chickyImg" src="/assets/running.gif" style="position:absolute; width:1px; height:1px; opacity:0.01; pointer-events:none; z-index:-1;" crossorigin="anonymous" />';
$c = str_replace("<!-- Hidden GIF for character (must not be display:none so browser animates it) -->\n" . $oldImg, "", $c);
$c = str_replace("<!-- Hidden GIF for character -->\n" . $oldImg, "", $c); // just in case
$c = str_replace($oldImg, "", $c);

// Insert img inside the relative div
$newImg = "\n      <!-- DOM GIF for character running (animates perfectly in all browsers) -->\n      <img id=\"chickyDom\" src=\"/assets/running.gif\" style=\"position:absolute; width:10.666%; height:21.333%; left:8.333%; top:58.666%; z-index:5; pointer-events:none; display:none;\" crossorigin=\"anonymous\" />\n";

$c = str_replace('<canvas id="gameCanvas" width="600" height="300"></canvas>', '<canvas id="gameCanvas" width="600" height="300"></canvas>' . $newImg, $c);

// 2. JS Changes
$oldJs1 = "const chickyImg = document.getElementById('chickyImg');";
$newJs1 = "const chickyDom = document.getElementById('chickyDom');";
$c = str_replace($oldJs1, $newJs1, $c);

// loader check
$oldJs2 = "if (chickyImg.complete && chickyImg.naturalWidth > 0) {";
$newJs2 = "if (chickyDom.complete && chickyDom.naturalWidth > 0) {";
$c = str_replace($oldJs2, $newJs2, $c);
$c = str_replace("chickyImg.onload = initAfterLoad;", "chickyDom.onload = initAfterLoad;", $c);
$c = str_replace("chickyImg.onerror = initAfterLoad;", "chickyDom.onerror = initAfterLoad;", $c);

// initAfterLoad drawImage removal
$oldInit = <<<'OLD'
  if (!isPlaying) {
    ctx.drawImage(chickyImg, chicky.x, chicky.y, chicky.w, chicky.h);
    // Draw initial ground
    ctx.fillStyle = '#4ade80';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
    ctx.fillStyle = '#16a34a';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);
  }
OLD;

$newInit = <<<'NEW'
  if (!isPlaying) {
    chickyDom.style.display = 'block'; // Show on ground
    // Draw initial ground
    ctx.fillStyle = '#4ade80';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, CANVAS_H - GROUND_Y);
    ctx.fillStyle = '#16a34a';
    ctx.fillRect(0, GROUND_Y, CANVAS_W, 6);
  }
NEW;
$c = str_replace($oldInit, $newInit, $c);

// jump logic
$oldJump = <<<'OLD'
    if (chicky.y >= GROUND_Y - chicky.h) {
      chicky.y = GROUND_Y - chicky.h;
      chicky.isJumping = false;
      chicky.vy = 0;
      hasFrozenFrame = false; // Reset freeze when touching ground
    }
OLD;
$newJump = <<<'NEW'
    if (chicky.y >= GROUND_Y - chicky.h) {
      chicky.y = GROUND_Y - chicky.h;
      chicky.isJumping = false;
      chicky.vy = 0;
      hasFrozenFrame = false; // Reset freeze when touching ground
      chickyDom.style.display = 'block'; // Resume animation
    }
NEW;
$c = str_replace($oldJump, $newJump, $c);

// Draw logic
$oldDraw = <<<'OLD'
  // Draw Chicky
  if (chicky.isJumping) {
    // FREEZE LOGIC: Capture frame once per jump
    if (!hasFrozenFrame && chickyImg.complete) {
      frozenCtx.clearRect(0, 0, chicky.w, chicky.h);
      frozenCtx.drawImage(chickyImg, 0, 0, chicky.w, chicky.h);
      hasFrozenFrame = true;
    }
    if (hasFrozenFrame) {
      ctx.drawImage(frozenCanvas, chicky.x, chicky.y, chicky.w, chicky.h);
    } else {
      ctx.drawImage(chickyImg, chicky.x, chicky.y, chicky.w, chicky.h);
    }
  } else {
    // On ground: let the GIF animate naturally
    if (chickyImg.complete) {
      ctx.drawImage(chickyImg, chicky.x, chicky.y, chicky.w, chicky.h);
    }
  }
OLD;

$newDraw = <<<'NEW'
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
NEW;
$c = str_replace($oldDraw, $newDraw, $c);


file_put_contents($file, $c);
echo "Replaced successfully!\n";
