<?php
$file = 'c:/laragon/www/velostar/user/chicky.php';
$c = file_get_contents($file);

// Replace the static config variables with dynamic fetch
$old = <<<'OLD'
let baseSpeed = 2.5;
let currentSpeed = baseSpeed;
let frameCount = 0;
let obstacles = [];
let clouds = [];

// Chicky Player
const chicky = {
  w: 64, h: 64,
  x: 50, y: GROUND_Y - 64,
  vy: 0, gravity: 0.45, jumpStrength: -10.5,
  isJumping: false
};
OLD;

$new = <<<'NEW'
// Config (overridden by server via /api/game_config)
let CFG = {
  base_speed: 2.5,
  speed_multiplier: 0.003,
  gravity: 0.45,
  jump_strength: -10.5,
  obstacle_interval: 80,
};

let baseSpeed = CFG.base_speed;
let currentSpeed = baseSpeed;
let frameCount = 0;
let obstacles = [];
let clouds = [];

// Chicky Player
const chicky = {
  w: 64, h: 64,
  x: 50, y: GROUND_Y - 64,
  vy: 0, gravity: CFG.gravity, jumpStrength: CFG.jump_strength,
  isJumping: false
};
NEW;

$c = str_replace($old, $new, $c);

// Replace acceleration formula
$c = str_replace(
    "currentSpeed = baseSpeed + (score * 0.003);",
    "currentSpeed = baseSpeed + (score * CFG.speed_multiplier);",
    $c
);

// Replace obstacle interval
$c = str_replace(
    "if (frameCount % Math.max(60, Math.floor(120 - currentSpeed*5)) === 0) {",
    "if (frameCount % Math.max(30, CFG.obstacle_interval - Math.floor(currentSpeed * 3)) === 0) {",
    $c
);

// Inject fetch call BEFORE initAfterLoad function
$old2 = <<<'OLD'
// Initial render
function initAfterLoad() {
OLD;
$new2 = <<<'NEW'
// Load server game config then init
fetch('/api/game_config')
  .then(r => r.json())
  .then(data => {
    CFG.base_speed          = parseFloat(data.base_speed)        || CFG.base_speed;
    CFG.speed_multiplier    = parseFloat(data.speed_multiplier)  || CFG.speed_multiplier;
    CFG.gravity             = parseFloat(data.gravity)           || CFG.gravity;
    CFG.jump_strength       = parseFloat(data.jump_strength)     || CFG.jump_strength;
    CFG.obstacle_interval   = parseInt(data.obstacle_interval)   || CFG.obstacle_interval;
    baseSpeed               = CFG.base_speed;
    chicky.gravity          = CFG.gravity;
    chicky.jumpStrength     = CFG.jump_strength;
  })
  .catch(() => {}); // Silent fallback to defaults

// Initial render
function initAfterLoad() {
NEW;

$c = str_replace($old2, $new2, $c);

file_put_contents($file, $c);
echo "Replaced successfully!\n";
