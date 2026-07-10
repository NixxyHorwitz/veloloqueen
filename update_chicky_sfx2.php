<?php
$file = 'c:/laragon/www/velostar/user/chicky.php';
$c = file_get_contents($file);

// 1. Jump Physics
$c = str_replace(
    "vy: 0, gravity: 0.7, jumpStrength: -13,", 
    "vy: 0, gravity: 0.45, jumpStrength: -10.5,", 
    $c
);

// 2. SFX Refinement
$oldSfx = <<<'OLD'
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
OLD;

$newSfx = <<<'NEW'
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
      osc.type = 'sine';
      osc.frequency.setValueAtTime(400, t);
      osc.frequency.exponentialRampToValueAtTime(600, t + 0.15);
      gain.gain.setValueAtTime(0.1, t);
      gain.gain.exponentialRampToValueAtTime(0.01, t + 0.15);
      osc.start(t); osc.stop(t + 0.15);
    } else if (type === 'point') {
      osc.type = 'sine';
      osc.frequency.setValueAtTime(1000, t);
      osc.frequency.setValueAtTime(1400, t + 0.05);
      gain.gain.setValueAtTime(0.05, t);
      gain.gain.linearRampToValueAtTime(0, t + 0.1);
      osc.start(t); osc.stop(t + 0.1);
    } else if (type === 'die') {
      osc.type = 'sine';
      osc.frequency.setValueAtTime(300, t);
      osc.frequency.exponentialRampToValueAtTime(100, t + 0.4);
      gain.gain.setValueAtTime(0.2, t);
      gain.gain.linearRampToValueAtTime(0, t + 0.4);
      osc.start(t); osc.stop(t + 0.4);
    }
  } catch(e) {}
}
NEW;

$c = str_replace($oldSfx, $newSfx, $c);
file_put_contents($file, $c);
echo "Replaced successfully!\n";
