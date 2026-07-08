<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/auth/guard.php';

$flash = $flashType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'refund_wd_hold') {
    $wd_id = (int)($_POST['wd_id'] ?? 0);
    $stmtPending = $pdo->prepare("SELECT id FROM admin_requests WHERE user_id=? AND type='refund_wd_hold' AND status='pending' AND payload LIKE ?");
    $stmtPending->execute([$user['id'], '%"withdrawal_id":'.$wd_id.'%']);
    if ($stmtPending->fetchColumn()) {
        $flash = '❌ Permintaan pengembalian untuk WD ini sudah diajukan.'; $flashType = 'error';
    } else {
        $w = $pdo->prepare("SELECT * FROM withdrawals WHERE id=? AND user_id=? AND status='hold'");
        $w->execute([$wd_id, $user['id']]);
        $wData = $w->fetch();
        if (!$wData) {
            $flash = '❌ Withdraw tidak ditemukan atau bukan berstatus Hold.'; $flashType = 'error';
        } else {
            $payload = json_encode(['withdrawal_id' => $wd_id]);
            $pdo->prepare("INSERT INTO admin_requests (user_id, type, payload) VALUES (?, 'refund_wd_hold', ?)")
                ->execute([$user['id'], $payload]);
            $req_id = $pdo->lastInsertId();
            
            $msg  = "💰 <b>REQUEST PENGEMBALIAN WD HOLD</b>\n\n";
            $msg .= "👤 User: <code>{$user['username']}</code>\n";
            $msg .= "💸 Jumlah WD: <b>" . format_rp((float)$wData['amount']) . "</b>\n";
            $msg .= "🏦 Tujuan Awal: {$wData['bank_name']} - {$wData['account_number']}\n\n";
            $msg .= "⚠️ <i>Refund ini akan mengembalikan saldo WD yang ditahan (Hold) ke Saldo Tarik user secara utuh.</i>\n";
            $kb = [
                [['text'=>'✅ Approve Refund', 'callback_data'=>'req_approve_'.$req_id], ['text'=>'❌ Reject', 'callback_data'=>'req_reject_'.$req_id]]
            ];
            send_telegram_notif($pdo, $msg, $kb, 'permintaan');
            
            $flash = '✅ Permintaan pengembalian dana telah masuk dan sedang diverifikasi admin.';
            $flashType = 'success';
        }
    }
    $_GET['tab'] = 'withdraw'; // force stay on withdraw tab
}

$tab = $_GET['tab'] ?? 'reward';

// Reward / watch history
$rewards = $pdo->prepare(
    "SELECT wh.*, v.title as video_title FROM watch_history wh
     LEFT JOIN videos v ON v.id=wh.video_id
     WHERE wh.user_id=? ORDER BY wh.watched_at DESC LIMIT 30"
);
$rewards->execute([$user['id']]); $rewards = $rewards->fetchAll();

// Deposits
$deposits = $pdo->prepare("SELECT * FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$deposits->execute([$user['id']]); $deposits = $deposits->fetchAll();

// Withdrawals
$wds = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$wds->execute([$user['id']]); $wds = $wds->fetchAll();

// Pending Refund Requests
$pending_refunds = $pdo->prepare("SELECT payload FROM admin_requests WHERE user_id=? AND type='refund_wd_hold' AND status='pending'");
$pending_refunds->execute([$user['id']]);
$requested_wds = [];
foreach ($pending_refunds->fetchAll() as $pr) {
    $p = json_decode($pr['payload'], true);
    if (isset($p['withdrawal_id'])) {
        $requested_wds[] = (int)$p['withdrawal_id'];
    }
}

// Payment Channels Logos
$channels = $pdo->query("SELECT name, logo FROM payment_channels WHERE logo IS NOT NULL AND logo != ''")->fetchAll();
$channel_logos = [];
foreach ($channels as $c) {
    $channel_logos[strtolower($c['name'])] = $c['logo'];
}

// Totals
$total_earned = (float)$user['total_earned'];
$total_dep    = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM deposits WHERE user_id=? AND status='confirmed'");
$total_dep->execute([$user['id']]); $total_dep = (float)$total_dep->fetchColumn();
$total_wd     = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='approved'");
$total_wd->execute([$user['id']]); $total_wd = (float)$total_wd->fetchColumn();

$pageTitle  = 'Riwayat  ';
$activePage = 'history';
require dirname(__DIR__) . '/partials/header.php';
?>

<style>
/* ══════════════════════════════════════════════
   HISTORY PAGE — CASUAL GAME STYLE (ULTRA COMPACT)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; }

/* ── TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 16px 14px 24px; border-bottom: 3px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 20px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 3px 0 #1e3a8a; z-index: 2; margin-bottom: 2px; letter-spacing: -0.5px; }
.wd-top-sub { position: relative; font-size: 11px; font-weight: 800; color: #bae6fd; z-index: 2; }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 20px 14px 100px; position: relative; z-index: 2; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 40px 40px; background-position: 0 0, 20px 20px; pointer-events: none; z-index: -1; }

/* ── STATS ROW ── */
.stat-row { display: flex; gap: 6px; margin-bottom: 16px; position: relative; z-index: 5; }
.stat-box { flex: 1; background: #ffffff; border: 2.5px solid #1e3a8a; border-radius: 12px; padding: 10px 4px; text-align: center; box-shadow: 0 3px 0 #1e3a8a; }
.stat-val { font-size: 13px; font-weight: 900; line-height: 1.2; }
.stat-val.blue { color: #0284c7; }
.stat-val.green { color: #16a34a; }
.stat-val.orange { color: #ea580c; }
.stat-lbl { font-size: 9px; font-weight: 900; color: #64748b; margin-top: 2px; text-transform: uppercase; }

/* ── TABS ── */
.h-tabs { display: flex; gap: 6px; margin-bottom: 16px; background: rgba(255,255,255,0.2); padding: 6px; border-radius: 14px; border: 2px solid rgba(255,255,255,0.3); box-shadow: 0 3px 0 rgba(0,0,0,0.1); backdrop-filter: blur(4px); }
.h-tab { flex: 1; text-align: center; padding: 10px 4px; font-size: 11px; font-weight: 900; text-decoration: none; color: #fff; background: transparent; border-radius: 10px; transition: all 0.15s; display: flex; align-items: center; justify-content: center; gap: 4px; text-shadow: 0 1px 1px rgba(0,0,0,0.2); }
.h-tab--active { background: #ffffff; color: #9a3412; border: 2px solid #ea580c; box-shadow: 0 3px 0 #c2410c; transform: translateY(-2px); text-shadow: none; }
.h-tab:not(.h-tab--active):active { background: rgba(255,255,255,0.3); }

/* ── COMPACT LISTS (DIRECT ON BODY) ── */
.c-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.c-item { display: flex; align-items: center; gap: 10px; background: #ffffff; border: 2.5px solid #c2410c; border-radius: 12px; padding: 10px 12px; box-shadow: 0 3px 0 #9a3412; }
.c-ico { width: 36px; height: 36px; border-radius: 10px; border: 2px solid #c2410c; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 0 #9a3412; overflow: hidden; }
.c-ico.blue { background: #e0f2fe; color: #0284c7; border-color: #0369a1; box-shadow: 0 2px 0 #075985; }
.c-ico.green { background: #d1fae5; color: #059669; border-color: #047857; box-shadow: 0 2px 0 #064e3b; }
.c-ico.orange { background: #ffedd5; color: #ea580c; border-color: #c2410c; box-shadow: 0 2px 0 #9a3412; }
.c-ico.img-wrap { background: #fff; border-color: #94a3b8; box-shadow: 0 2px 0 #64748b; padding: 4px; }
.c-body { flex: 1; min-width: 0; }
.c-title { font-size: 12px; font-weight: 900; color: #9a3412; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 2px; }
.c-sub { font-size: 10px; font-weight: 800; color: #ea580c; display: flex; align-items: center; gap: 4px; }
.c-note { font-size: 9px; color: #991b1b; font-weight: 900; margin-top: 4px; display: inline-flex; align-items: center; gap: 4px; background: #fef2f2; padding: 3px 6px; border-radius: 6px; border: 1.5px dashed #fca5a5; }
.c-right { text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; }
.c-badge { font-size: 8px; font-weight: 900; padding: 2px 4px; border-radius: 5px; border: 1.5px solid; text-transform: uppercase; margin-bottom: 4px; }
.c-badge.succ { background: #d1fae5; color: #065f46; border-color: #34d399; }
.c-badge.warn { background: #fef3c7; color: #92400e; border-color: #fbbf24; }
.c-badge.err { background: #fee2e2; color: #991b1b; border-color: #f87171; }
.c-badge.info { background: #e0f2fe; color: #075985; border-color: #38bdf8; }
.c-amt { font-size: 13px; font-weight: 900; letter-spacing: -0.5px; }

/* Empty & Actions */
.ref-empty { text-align: center; padding: 20px; border: 2.5px dashed rgba(255,255,255,0.4); border-radius: 12px; background: rgba(0,0,0,0.05); margin-top: 10px; }
.ref-empty-ico { font-size: 32px; margin-bottom: 6px; opacity: 0.8; }
.ref-empty-txt { font-size: 11px; font-weight: 800; color: #fff; }
.action-btn { font-size: 9px; font-weight: 900; padding: 4px 8px; border-radius: 6px; border: 1.5px solid; text-transform: uppercase; cursor: pointer; text-decoration: none; margin-top: 4px; transition: transform 0.1s; }
.action-btn:active { transform: translateY(2px); box-shadow: none !important; }
.action-btn.pay { background: #fde047; color: #a16207; border-color: #ca8a04; box-shadow: 0 2px 0 #ca8a04; display: inline-flex; align-items: center; gap: 2px; }
.action-btn.refund { background: #e0f2fe; color: #075985; border-color: #0284c7; box-shadow: 0 2px 0 #0284c7; display: inline-flex; align-items: center; gap: 2px; }
.action-btn.disabled { background: #f1f5f9; color: #64748b; border-color: #cbd5e1; box-shadow: 0 2px 0 #cbd5e1; cursor: not-allowed; }

/* Flash */
.h-flash { background: #d1fae5; border: 2.5px solid #059669; border-radius: 12px; padding: 10px 12px; color: #064e3b; font-weight: 900; font-size: 11px; margin-bottom: 14px; box-shadow: 0 3px 0 #059669; }
.h-flash.error { background: #fee2e2; border-color: #dc2626; color: #7f1d1d; box-shadow: 0 3px 0 #dc2626; }

/* ── MODAL ── */
.cg-modal { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.6); align-items:center; justify-content:center; backdrop-filter:blur(4px); padding:20px; }
.cg-modal-card { background:#fff; border-radius:20px; border:3px solid #1e3a8a; width:100%; max-width:320px; box-shadow:0 6px 0 #1e3a8a; animation:popIn .3s cubic-bezier(.175,.885,.32,1.275); padding:20px; text-align:center; }
@keyframes popIn { 0% { transform:scale(0.8); opacity:0; } 100% { transform:scale(1); opacity:1; } }
.cg-mc-hdr { font-size: 16px; font-weight: 900; color: #1e3a8a; margin-bottom: 6px; display:flex; align-items:center; justify-content:center; gap:6px; }
.cg-mc-sub { font-size: 11px; font-weight: 800; color: #64748b; margin-bottom: 14px; }
.cg-btn-row { display: flex; gap: 8px; }
.cg-btn { flex: 1; border: 2.5px solid #1e3a8a; border-radius: 12px; font-size: 12px; font-weight: 900; padding: 10px; box-shadow: 0 3px 0 #1e3a8a; cursor: pointer; text-align:center; transition: transform 0.1s; }
.cg-btn:active { transform: translateY(3px); box-shadow: 0 0 0 #1e3a8a; }
.cg-btn--cancel { background: #f1f5f9; color: #475569; border-color: #94a3b8; box-shadow: 0 3px 0 #94a3b8; }
.cg-btn--submit { background: linear-gradient(180deg, #38bdf8, #0ea5e9); color: #fff; }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title">Riwayat Aktivitas</div>
  <div class="wd-top-sub">Pantau Saldo & Transaksi Kamu</div>
</div>

<div class="wd-body">
  <?php if ($flash): ?>
  <div class="h-flash <?= $flashType==='error'?'error':'' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <!-- SUMMARY STATS -->
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-val blue"><?= format_rp($total_earned) ?></div>
      <div class="stat-lbl">Reward</div>
    </div>
    <div class="stat-box">
      <div class="stat-val green"><?= format_rp($total_dep) ?></div>
      <div class="stat-lbl">Top Up</div>
    </div>
    <div class="stat-box">
      <div class="stat-val orange"><?= format_rp($total_wd) ?></div>
      <div class="stat-lbl">Tarik</div>
    </div>
  </div>

  <!-- TABS -->
  <div class="h-tabs">
    <a href="?tab=reward" class="h-tab <?= $tab==='reward'?'h-tab--active':'' ?>"><i class="ph-bold ph-gift"></i> Reward</a>
    <a href="?tab=deposit" class="h-tab <?= $tab==='deposit'?'h-tab--active':'' ?>"><i class="ph-bold ph-wallet"></i> Top Up</a>
    <a href="?tab=withdraw" class="h-tab <?= $tab==='withdraw'?'h-tab--active':'' ?>"><i class="ph-bold ph-paper-plane-right"></i> Tarik</a>
  </div>

  <!-- LISTS -->
  <div class="c-list">
    
    <!-- REWARD TAB -->
    <?php if ($tab === 'reward'): ?>
      <?php if (empty($rewards)): ?>
      <div class="ref-empty">
        <div class="ref-empty-ico">🎁</div>
        <div class="ref-empty-txt">Belum ada riwayat reward.</div>
      </div>
      <?php else: ?>
        <?php foreach ($rewards as $r): ?>
        <div class="c-item">
          <div class="c-ico blue"><i class="ph-fill ph-play-circle"></i></div>
          <div class="c-body">
            <div class="c-title"><?= htmlspecialchars($r['video_title'] ?? 'Video #'.$r['video_id']) ?></div>
            <div class="c-sub"><i class="ph-bold ph-clock"></i> <?= date('d M Y H:i', strtotime($r['watched_at'])) ?></div>
          </div>
          <div class="c-right">
            <div class="c-amt" style="color:#0284c7">+<?= format_rp((float)$r['reward_given']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <!-- DEPOSIT TAB -->
    <?php elseif ($tab === 'deposit'): ?>
      <?php if (empty($deposits)): ?>
      <div class="ref-empty">
        <div class="ref-empty-ico">💳</div>
        <div class="ref-empty-txt">Belum ada riwayat top up.</div>
      </div>
      <?php else: ?>
        <?php foreach ($deposits as $d): ?>
        <?php $dl = $channel_logos[strtolower($d['method'])] ?? null; ?>
        <div class="c-item">
          <?php if ($dl): ?>
          <div class="c-ico img-wrap"><img src="/assets/banks/<?= htmlspecialchars($dl) ?>" style="width:100%;height:100%;object-fit:contain"></div>
          <?php else: ?>
          <div class="c-ico green"><i class="<?= $d['method']==='qris' ? 'ph-bold ph-qr-code' : 'ph-bold ph-bank' ?>"></i></div>
          <?php endif; ?>
          
          <div class="c-body">
            <div class="c-title"><?= format_rp((float)$d['amount']) ?></div>
            <div class="c-sub"><?= strtoupper($d['method']) ?> &bull; <?= date('d M y H:i', strtotime($d['created_at'])) ?></div>
            <?php if ($d['admin_note']): ?>
            <div class="c-note"><i class="ph-bold ph-warning-circle"></i> <?= htmlspecialchars($d['admin_note']) ?></div>
            <?php endif; ?>
          </div>
          <div class="c-right">
            <span class="c-badge <?= match($d['status']){'confirmed'=>'succ','pending'=>'warn','rejected'=>'err',default=>'err'} ?>">
              <?= match($d['status']){'confirmed'=>'Sukses', 'pending'=>'Menunggu', 'rejected'=>'Ditolak', default=>ucfirst($d['status'])} ?>
            </span>
            <?php if ($d['status']==='pending' && $d['method']==='qris'): ?>
            <a href="/pay?id=<?= $d['id'] ?>" class="action-btn pay"><i class="ph-bold ph-arrow-right"></i> Bayar</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <!-- WITHDRAW TAB -->
    <?php elseif ($tab === 'withdraw'): ?>
      <?php if (empty($wds)): ?>
      <div class="ref-empty">
        <div class="ref-empty-ico">💸</div>
        <div class="ref-empty-txt">Belum ada riwayat penarikan.</div>
      </div>
      <?php else: ?>
        <?php foreach ($wds as $w): ?>
        <?php $wl = $channel_logos[strtolower($w['bank_name'])] ?? null; ?>
        <div class="c-item">
          <?php if ($wl): ?>
          <div class="c-ico img-wrap"><img src="/assets/banks/<?= htmlspecialchars($wl) ?>" style="width:100%;height:100%;object-fit:contain"></div>
          <?php else: ?>
          <div class="c-ico orange"><i class="ph-bold ph-bank"></i></div>
          <?php endif; ?>
          
          <div class="c-body">
            <div class="c-title"><?= format_rp((float)$w['amount']) ?></div>
            <div class="c-sub"><?= htmlspecialchars($w['bank_name']) ?> &bull; <?= date('d M y H:i', strtotime($w['created_at'])) ?></div>
            <?php if ($w['admin_note']): ?>
            <div class="c-note"><i class="ph-bold ph-warning-circle"></i> <?= htmlspecialchars($w['admin_note']) ?></div>
            <?php endif; ?>
          </div>
          
          <div class="c-right">
            <span class="c-badge <?= match($w['status']){'approved'=>'succ','pending'=>'warn','hold'=>'warn','rejected'=>'err','refunded'=>'info',default=>'err'} ?>">
              <?= match($w['status']){'approved'=>'Sukses', 'pending'=>'Menunggu', 'hold'=>'Ditahan', 'rejected'=>'Ditolak', 'refunded'=>'Dikembalikan', default=>ucfirst($w['status'])} ?>
            </span>
            <?php if ($w['status'] === 'hold'): ?>
              <?php if (in_array($w['id'], $requested_wds)): ?>
              <button class="action-btn disabled" disabled>Diajukan</button>
              <?php else: ?>
              <button class="action-btn refund" onclick="openRefundModal(<?= $w['id'] ?>)"><i class="ph-bold ph-arrow-u-up-left"></i> Refund</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<!-- Refund Modal -->
<div id="refund-modal" class="cg-modal">
  <div class="cg-modal-card">
    <div class="cg-mc-hdr"><i class="ph-bold ph-warning-circle"></i> Ajukan Refund?</div>
    <div class="cg-mc-sub">Kamu yakin ingin mengembalikan saldo dari penarikan yang ditahan ini?</div>
    <div style="background:#f0f9ff;border:2.5px solid #bae6fd;border-radius:10px;padding:8px;margin-bottom:14px;font-size:10px;font-weight:800;color:#0369a1;line-height:1.3;text-align:left;">
      Saldo akan dikembalikan utuh ke <strong>Saldo Tarik</strong> setelah admin menyetujui.
    </div>
    <form method="POST" id="refund-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="refund_wd_hold">
      <input type="hidden" name="wd_id" id="refund-wd-id" value="">
      <div class="cg-btn-row">
        <button type="button" class="cg-btn cg-btn--cancel" onclick="closeRefundModal()">Batal</button>
        <button type="submit" class="cg-btn cg-btn--submit" onclick="this.disabled=true;this.textContent='Proses...';document.getElementById('refund-form').submit();">Ajukan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRefundModal(wdId) {
  document.getElementById('refund-wd-id').value = wdId;
  document.getElementById('refund-modal').style.display = 'flex';
}
function closeRefundModal() {
  document.getElementById('refund-modal').style.display = 'none';
}
document.getElementById('refund-modal').addEventListener('click', function(e) {
  if (e.target === this) closeRefundModal();
});
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
