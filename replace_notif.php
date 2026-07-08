<?php
$orig = file_get_contents(__DIR__ . '/user/notifications.php');
$parts = explode('<style>', $orig, 2);

$newHtml = <<<'EOT'
<style>
/* ══════════════════════════════════════════════
   NOTIFICATIONS PAGE — CASUAL GAME STYLE (ULTRA COMPACT)
   ══════════════════════════════════════════════ */
body { background: #f97316 !important; color: #0f172a; margin: 0; padding: 0; font-family: 'Nunito', sans-serif; }

/* ── BLUE TOP BANNER ── */
.wd-top { position: relative; background: linear-gradient(180deg, #3b82f6, #1d4ed8); padding: 16px 14px 20px; border-bottom: 3px solid #1e3a8a; z-index: 10; text-align: center; }
.wd-top::before { content: ''; position: absolute; inset: 0; background-image: linear-gradient(rgba(255, 255, 255, 0.1) 2px, transparent 2px), linear-gradient(90deg, rgba(255, 255, 255, 0.1) 2px, transparent 2px); background-size: 20px 20px; pointer-events: none; }
.wd-top-title { position: relative; font-size: 20px; font-weight: 900; color: #fff; text-shadow: 0 3px 0 #1e3a8a; z-index: 2; margin-bottom: 2px; letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 6px; }
.wd-top-sub { position: relative; font-size: 11px; font-weight: 800; color: #bae6fd; z-index: 2; }
.wd-top-action { position: relative; z-index: 2; margin-top: 10px; display: inline-block; background: rgba(255,255,255,0.2); border: 2px solid #fff; border-radius: 8px; padding: 6px 10px; font-size: 10px; font-weight: 900; color: #fff; text-transform: uppercase; cursor: pointer; backdrop-filter: blur(4px); box-shadow: 0 2px 0 rgba(0,0,0,0.15); transition: transform 0.1s; text-decoration: none; }
.wd-top-action:active { transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,0.15); }

/* ── BODY ── */
.wd-body { flex: 1; background: #f97316; padding: 14px 14px 100px; position: relative; z-index: 2; margin-top: 0; min-height: 80vh; }
.wd-body::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%), radial-gradient(circle, rgba(255,255,255,0.08) 10%, transparent 10%); background-size: 40px 40px; background-position: 0 0, 20px 20px; pointer-events: none; z-index: -1; }

/* ── COMPACT LISTS ── */
.c-list { display: flex; flex-direction: column; gap: 10px; }
.c-item { display: flex; gap: 10px; background: #ffffff; border: 2.5px solid #1e3a8a; border-radius: 12px; padding: 10px; box-shadow: 0 3px 0 #1e3a8a; align-items: flex-start; transition: opacity .3s, transform .1s; }
.c-item:active { transform: scale(0.98); }
.c-item.read { opacity: 0.65; border-color: #cbd5e1; box-shadow: 0 3px 0 #cbd5e1; background: #f8fafc; filter: grayscale(0.5); }
.c-ico { width: 34px; height: 34px; border-radius: 10px; border: 2px solid; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 0; }
.c-item.read .c-ico { border-color: #94a3b8 !important; box-shadow: 0 2px 0 #94a3b8 !important; background: #e2e8f0 !important; }
.c-body { flex: 1; min-width: 0; }
.c-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
.c-badge { font-size: 9px; font-weight: 900; padding: 2px 5px; border-radius: 6px; border: 1.5px solid; text-transform: uppercase; letter-spacing: 0.5px; }
.c-title { font-weight: 900; font-size: 12px; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.c-msg { font-size: 11px; color: #475569; font-weight: 700; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.c-time { font-size: 9px; color: #94a3b8; font-weight: 800; display: block; margin-top: 4px; }
.c-unread-dot { width: 8px; height: 8px; background: #ef4444; border-radius: 50%; flex-shrink: 0; border: 1px solid #fff; box-shadow: 0 1px 2px rgba(0,0,0,0.2); animation: pulse 2s infinite; }

.c-btn-action { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; font-size: 10px; font-weight: 900; color: #0369a1; text-decoration: none; border: 2px solid #bae6fd; border-radius: 8px; padding: 4px 10px; background: #e0f2fe; text-transform: uppercase; letter-spacing: 0.5px; }
.c-btn-read { background: #f8fafc; border: 2px solid #cbd5e1; color: #64748b; border-radius: 8px; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 900; cursor: pointer; box-shadow: 0 2px 0 #cbd5e1; transition: all .1s; flex-shrink: 0; }
.c-btn-read:active { transform: translateY(2px); box-shadow: 0 0 0 #cbd5e1; }

@keyframes pulse { 0% { transform:scale(0.95); box-shadow:0 0 0 0 rgba(239,68,68,0.7); } 70% { transform:scale(1); box-shadow:0 0 0 4px rgba(239,68,68,0); } 100% { transform:scale(0.95); box-shadow:0 0 0 0 rgba(239,68,68,0); } }

/* ── EMPTY STATE ── */
.ref-empty { text-align: center; padding: 20px; border: 2.5px dashed rgba(255,255,255,0.4); border-radius: 12px; background: rgba(0,0,0,0.05); }
.ref-empty-ico { font-size: 32px; margin-bottom: 6px; opacity: 0.8; color: #fff; }
.ref-empty-txt { font-size: 11px; font-weight: 800; color: #fff; }
</style>

<!-- TOP BANNER -->
<div class="wd-top">
  <div class="wd-top-title"><i class="ph-bold ph-bell-ringing"></i> Notifikasi</div>
  <div class="wd-top-sub">
    <?php if ($unread_count > 0): ?>
      Kamu punya <?= $unread_count ?> pesan baru
    <?php else: ?>
      Semua pesan sudah dibaca <i class="ph-bold ph-check"></i>
    <?php endif; ?>
  </div>
  <?php if ($unread_count > 0): ?>
    <button class="wd-top-action" onclick="markAllRead()"><i class="ph-bold ph-checks"></i> Baca Semua</button>
  <?php endif; ?>
</div>

<div class="wd-body">
  <?php if (empty($notifications)): ?>
  <div class="ref-empty">
    <div class="ref-empty-ico"><i class="ph-fill ph-mailbox"></i></div>
    <div class="ref-empty-txt">Belum ada notifikasi.<br><span style="font-size:9px;color:rgba(255,255,255,0.6);">Pesan penting akan muncul di sini.</span></div>
  </div>
  <?php else: ?>
  <div class="c-list" id="notif-list">
    <?php foreach ($notifications as $n):
      $cfg = $type_cfg[$n['type']] ?? $type_cfg['info'];
      $icon = $n['icon'] ?: $cfg['icon'];
      $is_read = (bool)$n['is_read'];
    ?>
    <div class="c-item <?= $is_read ? 'read' : '' ?>" data-id="<?= $n['id'] ?>" style="<?= !$is_read ? 'border-color: '.$cfg['border'].'; box-shadow: 0 3px 0 '.$cfg['border'].';' : '' ?>">
      <div class="c-ico" style="background: <?= $cfg['bg'] ?>; border-color: <?= $cfg['border'] ?>; box-shadow: 0 2px 0 <?= $cfg['border'] ?>;">
        <?= $icon ?>
      </div>
      <div class="c-body">
        <div class="c-header">
          <?php if (!$is_read): ?><span class="c-unread-dot"></span><?php endif; ?>
          <span class="c-badge" style="color: <?= $cfg['color'] ?>; border-color: <?= $cfg['border'] ?>; background: <?= $cfg['bg'] ?>;"><?= $cfg['label'] ?></span>
          <div class="c-title"><?= htmlspecialchars($n['title']) ?></div>
        </div>
        <div class="c-msg"><?= nl2br(htmlspecialchars($n['message'])) ?></div>
        <?php if ($n['action_url'] && $n['action_text']): ?>
        <a href="<?= htmlspecialchars($n['action_url']) ?>" class="c-btn-action">
          <?= htmlspecialchars($n['action_text']) ?> <i class="ph-bold ph-arrow-right"></i>
        </a>
        <?php endif; ?>
        <span class="c-time"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></span>
      </div>
      <?php if (!$is_read): ?>
      <button class="c-btn-read" onclick="markRead(<?= $n['id'] ?>, this)" title="Tandai dibaca">
        <i class="ph-bold ph-check"></i>
      </button>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
const CSRF = '<?= csrf_token() ?>';

async function markRead(id, btn) {
  const item = btn.closest('.c-item');
  const fd = new FormData();
  fd.append('action', 'mark_read');
  fd.append('id', id);
  fd.append('_csrf', CSRF);
  const res = await fetch('/notif_action', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    item.classList.add('read');
    item.style.borderColor = '#cbd5e1';
    item.style.boxShadow = '0 3px 0 #cbd5e1';
    
    // Remove the unread dot
    const dot = item.querySelector('.c-unread-dot');
    if (dot) dot.remove();
    
    btn.remove();
  }
}

async function markAllRead() {
  const fd = new FormData();
  fd.append('action', 'mark_all_read');
  fd.append('_csrf', CSRF);
  const res = await fetch('/notif_action', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) {
    location.reload();
  }
}
</script>

<?php require dirname(__DIR__) . '/partials/footer.php'; ?>
EOT;

file_put_contents(__DIR__ . '/user/notifications.php', $parts[0] . $newHtml);
echo "Notifications updated successfully.";
