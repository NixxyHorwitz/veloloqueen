<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

// Handle AJAX Play
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'play') {
    header('Content-Type: application/json');
    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'message' => 'CSRF tidak valid.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Get user's current spin tickets with lock
        $stmt = $pdo->prepare("SELECT spin_tickets FROM users WHERE id=? FOR UPDATE");
        $stmt->execute([$user['id']]);
        $tickets = (int)$stmt->fetchColumn();

        if ($tickets <= 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Tiket Spin kamu habis! Selesaikan misi untuk mendapatkannya.']);
            exit;
        }

        // Deduct 1 spin ticket
        $stmt = $pdo->prepare("UPDATE users SET spin_tickets = spin_tickets - 1 WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Prizes Definition (Small nominals)
        $prizes_pool = [
            ['val' => 0,     'weight' => 45],
            ['val' => 1000,  'weight' => 30],
            ['val' => 2500,  'weight' => 15],
            ['val' => 5000,  'weight' => 6],
            ['val' => 10000, 'weight' => 3],
            ['val' => 20000, 'weight' => 1],
        ];

        $totalWeight = array_sum(array_column($prizes_pool, 'weight'));
        $rand = random_int(1, $totalWeight);
        $prize_val = 0;
        $current = 0;
        foreach ($prizes_pool as $p) {
            $current += $p['weight'];
            if ($rand <= $current) {
                $prize_val = $p['val'];
                break;
            }
        }

        // Generate other 5 fake prizes for display
        $other_prizes = [];
        for ($i = 0; $i < 5; $i++) {
            $other_prizes[] = $prizes_pool[array_rand($prizes_pool)]['val'];
        }

        // Insert minigame log
        $stmt = $pdo->prepare("INSERT INTO minigame_logs (user_id, game_type, score, reward) VALUES (?, 'lucky_card', 0, ?)");
        $stmt->execute([$user['id'], $prize_val]);

        // Update user balance (balance_wd)
        if ($prize_val > 0) {
            $stmt = $pdo->prepare("UPDATE users SET balance_wd = balance_wd + ? WHERE id = ?");
            $stmt->execute([$prize_val, $user['id']]);
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'prize' => $prize_val,
            'others' => $other_prizes,
            'remaining_tickets' => $tickets - 1
        ]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'SysErr: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch user's current spin tickets for UI
$stmt = $pdo->prepare("SELECT spin_tickets FROM users WHERE id=?");
$stmt->execute([$user['id']]);
$spin_tickets = (int)$stmt->fetchColumn();

$pageTitle = 'Lucky Card';
$activePage = 'missions';
require dirname(__DIR__) . '/partials/header.php';
?>

<div class="wd-body">

<div class="section-header" style="margin-bottom:20px; background: #fff; padding: 14px 16px; border: 3px solid #c084fc; border-radius: 20px; box-shadow: 0 6px 0 #a855f7; display:flex; align-items:center; justify-content:space-between;">
  <div>
      <div class="section-title" style="display:flex;align-items:center;gap:8px;font-size:18px; color: #4c1d95; font-weight: 900;">
        <div style="background:#e9d5ff; width:36px; height:36px; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#9333ea; font-size:20px;">
            <i class="ph-fill ph-cards"></i>
        </div>
        Lucky Card
      </div>
      <p style="font-size:12px;font-weight:700;color:#6b21a8;margin:6px 0 0">Bayar pake 1 tiket spin untuk tebak kartu!</p>
  </div>
  <a href="/missions" style="background:#e9d5ff; padding:8px; border-radius:12px; color:#9333ea;"><i class="ph-bold ph-x"></i></a>
</div>

<!-- Ticket Display Panel -->
<div class="ticket-display">
  <div class="ticket-icon-wrap">
    <i class="ph-fill ph-ticket"></i>
  </div>
  <div class="ticket-text">
    <span class="ticket-title">Tiket Spin Tersisa</span>
    <span class="ticket-val"><span id="ticket-count"><?= $spin_tickets ?></span> Tiket</span>
  </div>
</div>

<div class="game-container">
    <?php if ($spin_tickets <= 0): ?>
        <div class="played-state" id="no-tickets-state">
            <div class="played-icon">🎟️</div>
            <h3 style="font-size:18px;font-weight:900;color:#334155;margin:0 0 8px;">Tiket Spin Habis</h3>
            <p style="font-size:13px;font-weight:700;color:#64748b;margin:0;line-height:1.5;max-width:320px;text-align:center;">Kamu tidak memiliki tiket spin untuk membuka kartu. Selesaikan misi harian atau mingguan untuk mendapatkan tiket gratis!</p>
            <a href="/missions" class="btn-back">Lihat Misi</a>
        </div>
    <?php else: ?>
        <div id="game-active-panel">
            <div class="cards-grid">
                <?php for($i = 0; $i < 6; $i++): ?>
                <div class="card-scene" onclick="flipCard(this, <?= $i ?>)">
                    <div class="card-obj" id="card-<?= $i ?>">
                        <div class="card-face card-front">
                            <i class="ph-bold ph-question"></i>
                        </div>
                        <div class="card-face card-back">
                            <div class="prize-amt" id="prize-<?= $i ?>">Rp 0</div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div id="result-overlay" style="display:none;">
        <div class="result-box">
            <div id="reward-loading" style="display:block;">
                <i class="ph-bold ph-spinner ph-spin" style="font-size:32px;color:#9333ea;"></i>
                <p style="font-size:14px; margin-top:12px; font-weight:700; color:#555;">Memproses Hadiah...</p>
            </div>
            
            <div id="reward-success" style="display:none;">
                <h2 id="result-title" style="color:#d97706; font-size:24px; font-weight:900; margin:0 0 8px;">SELAMAT!</h2>
                <div style="background:#f5f3ff; border:2px dashed #8b5cf6; padding:16px; border-radius:16px; margin-bottom:16px;">
                    <p style="font-size:12px; color:#6d28d9; font-weight:700; margin:0 0 4px;">Kamu mendapatkan:</p>
                    <h1 style="font-size:28px; font-weight:900; color:#7c3aed; margin:0;"><span id="reward-amount">Rp 0</span></h1>
                </div>
                <div class="result-actions" style="display: flex; gap: 12px; width: 100%; margin-top: 10px;">
                    <button id="btn-play-again" onclick="playAgain()" class="btn-action btn-primary" style="flex: 1; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-weight: 800; font-size: 14px; padding: 12px 14px; border-radius: 16px; border: none; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3); cursor:pointer; transition: transform 0.2s;">Main Lagi</button>
                    <button onclick="window.location.href='/missions'" class="btn-action btn-secondary" style="flex: 1; background: #f1f5f9; color: #475569; font-weight: 800; font-size: 14px; padding: 12px 14px; border-radius: 16px; border: none; cursor:pointer; transition: transform 0.2s;">Kembali</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.wd-body {
    flex: 1;
    background: #f97316;
    padding: 14px 14px 100px;
    position: relative;
    z-index: 2;
    min-height: 80vh;
}
.wd-body::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%);
    background-size: 50px 50px; background-position: 0 0, 25px 25px; pointer-events: none;
    z-index: -1;
}

.ticket-display {
    background: linear-gradient(135deg, #a855f7, #7e22ce);
    border: 2px solid #9333ea;
    border-radius: 20px;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 6px 0 #6b21a8;
    margin-bottom: 24px;
}
.ticket-icon-wrap {
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 24px;
}
.ticket-text {
    display: flex;
    flex-direction: column;
}
.ticket-title {
    font-size: 11px;
    font-weight: 800;
    color: #f3e8ff;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.ticket-val {
    font-size: 20px;
    font-weight: 900;
    color: #fff;
}

.game-container {
    background: #fff; border: 3px solid #e2e8f0; border-radius: 24px;
    box-shadow: 0 8px 0 #cbd5e1; position: relative; overflow: hidden;
    min-height: 380px; padding: 20px; display: flex; flex-direction: column;
}

#game-active-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.played-state {
    padding: 40px 20px; text-align: center; flex: 1; display: flex;
    flex-direction: column; align-items: center; justify-content: center;
}
.played-icon { font-size: 64px; margin-bottom: 16px; animation: float 3s ease-in-out infinite; }

.cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    align-content: center;
    padding: 10px 0;
}
@media(max-width: 360px) {
    .cards-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
}

.card-scene {
    width: 100%;
    aspect-ratio: 3/4;
    perspective: 800px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: transform 0.2s ease-in-out;
}
.card-scene:hover {
    transform: translateY(-4px) scale(1.02);
}
.card-obj {
    width: 100%;
    height: 100%;
    position: relative;
    transition: transform 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-style: preserve-3d;
}
.card-obj.is-flipped {
    transform: rotateY(180deg);
}

.card-face {
    position: absolute; width: 100%; height: 100%;
    -webkit-backface-visibility: hidden; backface-visibility: hidden;
    border-radius: 20px; display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 16px rgba(124, 58, 237, 0.12); border: 3px solid #fff;
}
.card-front {
    background: linear-gradient(135deg, #4f46e5, #7c3aed, #c084fc);
    background-size: 200% 200%;
    animation: gradientShift 4s ease infinite;
    position: relative;
    overflow: hidden;
}
.card-front::after {
    content: '';
    position: absolute;
    inset: 4px;
    border: 1px dashed rgba(255, 255, 255, 0.3);
    border-radius: 16px;
}
.card-front i {
    font-size: 40px; color: #fff; text-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
}

.card-back {
    background: linear-gradient(135deg, #fef9c3, #fde047);
    transform: rotateY(180deg);
    flex-direction: column;
    border: 2px solid #ca8a04;
    box-shadow: 0 8px 16px rgba(234, 179, 8, 0.15);
}
.card-back.zonk {
    background: linear-gradient(135deg, #f1f5f9, #cbd5e1);
    border-color: #94a3b8;
    box-shadow: none;
}

.prize-amt {
    font-size: 18px; font-weight: 900; color: #854d0e;
    text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
    font-family: 'Outfit', 'Inter', sans-serif;
}
.card-back.zonk .prize-amt {
    color: #64748b;
}

#result-overlay {
    position: absolute; inset: 0; background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(8px); z-index: 30; display: flex;
    align-items: center; justify-content: center; padding: 20px;
}
.result-box {
    background: #fff; width: 100%; max-width: 320px; border-radius: 28px;
    padding: 32px 24px; text-align: center; border: none;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    overflow: hidden;
}

.btn-back {
    display: inline-block; background: #8b5cf6; color: #fff; font-weight: 800; font-size: 14px;
    padding: 12px 24px; border-radius: 100px; text-decoration: none; border: 2px solid #fff;
    box-shadow: 0 4px 0 #7c3aed; margin-top: 16px; transition: transform 0.1s; cursor:pointer;
}
.btn-back:active { transform: translateY(4px); box-shadow: 0 0 0 #7c3aed; }

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
@keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
@keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
</style>

<script>
const _csrf = '<?= csrf_token() ?>';
let isPlaying = false;
let currentTickets = <?= $spin_tickets ?>;

async function flipCard(el, idx) {
    if (isPlaying) return;
    isPlaying = true;
    
    // Animate click squish
    el.style.transform = 'scale(0.9)';
    setTimeout(() => {
        el.style.transform = 'none';
    }, 150);

    const overlay = document.getElementById('result-overlay');
    
    try {
        const formData = new FormData();
        formData.append('action', 'play');
        formData.append('_csrf', _csrf);
        
        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (!data.success) {
            if (typeof nToast !== 'undefined') {
                nToast(data.message, 'error');
            } else {
                alert(data.message);
            }
            setTimeout(() => window.location.reload(), 1500);
            return;
        }
        
        const prize = data.prize;
        const others = data.others;
        currentTickets = data.remaining_tickets;
        
        // Update ticket balance in DOM
        document.getElementById('ticket-count').innerText = currentTickets;
        
        // Flip selected card
        const cardObj = document.getElementById('card-' + idx);
        const prizeEl = document.getElementById('prize-' + idx);
        
        prizeEl.innerText = prize > 0 ? 'Rp ' + prize.toLocaleString('id-ID') : 'Zonk';
        if (prize === 0) cardObj.querySelector('.card-back').classList.add('zonk');
        
        cardObj.classList.add('is-flipped');
        
        // play flip sound or play a short beep sound
        try {
            const actx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = actx.createOscillator(), gain = actx.createGain();
            osc.connect(gain); gain.connect(actx.destination);
            osc.frequency.value = 523.25; // C5
            gain.gain.setValueAtTime(0, actx.currentTime);
            gain.gain.linearRampToValueAtTime(0.1, actx.currentTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.01, actx.currentTime + 0.15);
            osc.start(); osc.stop(actx.currentTime + 0.15);
        } catch(e) {}
        
        // 0.8 second later, flip others
        setTimeout(() => {
            let otherIdx = 0;
            for(let i=0; i<6; i++) {
                if (i !== idx) {
                    const cObj = document.getElementById('card-' + i);
                    const pEl = document.getElementById('prize-' + i);
                    const pVal = others[otherIdx++];
                    pEl.innerText = pVal > 0 ? 'Rp ' + pVal.toLocaleString('id-ID') : 'Zonk';
                    if (pVal === 0) cObj.querySelector('.card-back').classList.add('zonk');
                    cObj.classList.add('is-flipped');
                }
            }
            
            // Show result modal
            setTimeout(() => {
                overlay.style.display = 'flex';
                document.getElementById('reward-loading').style.display = 'none';
                
                const title = document.getElementById('result-title');
                if (prize === 0) {
                    title.innerText = 'YAHH ZONK!';
                    title.style.color = '#64748b';
                } else {
                    title.innerText = 'SELAMAT!';
                    title.style.color = '#d97706';
                    
                    // play win sound
                    try {
                        const actx = new (window.AudioContext || window.webkitAudioContext)();
                        const osc = actx.createOscillator(), gain = actx.createGain();
                        osc.connect(gain); gain.connect(actx.destination);
                        osc.frequency.setValueAtTime(523.25, actx.currentTime); // C5
                        osc.frequency.setValueAtTime(659.25, actx.currentTime + 0.1); // E5
                        osc.frequency.setValueAtTime(783.99, actx.currentTime + 0.2); // G5
                        gain.gain.setValueAtTime(0.15, actx.currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, actx.currentTime + 0.45);
                        osc.start(); osc.stop(actx.currentTime + 0.45);
                    } catch(e) {}
                }
                
                document.getElementById('reward-amount').innerText = prize > 0 ? 'Rp ' + prize.toLocaleString('id-ID') : 'Zonk';
                document.getElementById('reward-success').style.display = 'block';
                
                // Show or hide play again button depending on remaining tickets
                const playAgainBtn = document.getElementById('btn-play-again');
                if (currentTickets <= 0) {
                    playAgainBtn.style.display = 'none';
                } else {
                    playAgainBtn.style.display = 'block';
                }
                
            }, 1000);
            
        }, 800);
        
    } catch (err) {
        if (typeof nToast !== 'undefined') {
            nToast("Terjadi kesalahan jaringan.", "error");
        } else {
            alert("Terjadi kesalahan jaringan.");
        }
        isPlaying = false;
    }
}

function playAgain() {
    // Hide overlay
    document.getElementById('result-overlay').style.display = 'none';
    document.getElementById('reward-success').style.display = 'none';
    document.getElementById('reward-loading').style.display = 'block';

    // Flip all cards back cascadingly
    const cards = document.querySelectorAll('.card-obj');
    cards.forEach((card, i) => {
        setTimeout(() => {
            card.classList.remove('is-flipped');
            setTimeout(() => {
                card.querySelector('.card-back').classList.remove('zonk');
            }, 400); // Wait until flip back hides the back face
        }, i * 80);
    });

    // Re-enable playing
    setTimeout(() => {
        if (currentTickets <= 0) {
            window.location.reload(); // Hard refresh to show PHP no-tickets block
        } else {
            isPlaying = false;
        }
    }, cards.length * 80 + 600);
}
</script>

</div>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
