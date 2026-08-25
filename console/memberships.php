<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
staff_require('memberships');
csrf_enforce();

$flash = $flashType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'add' || $action === 'edit') {
        $name            = trim($_POST['name'] ?? '');
        $icon            = trim($_POST['icon'] ?? '⭐');
        $price           = (float)preg_replace('/[^\d.]/', '', $_POST['price'] ?? '0');
        $orig_price      = (float)preg_replace('/[^\d.]/', '', $_POST['original_price'] ?? '0');
        $limit           = (int)($_POST['watch_limit'] ?? 10);
        $days            = (int)($_POST['duration_days'] ?? 30);
        $desc            = trim($_POST['description'] ?? '');
        $active          = isset($_POST['is_active']) ? 1 : 0;
        $wd_hold         = isset($_POST['wd_hold']) ? 1 : 0;
        $allow_edit_bank = isset($_POST['allow_edit_bank']) ? 1 : 0;
        $is_genjutsu     = isset($_POST['is_genjutsu']) ? 1 : 0;
        $price_genjutsu  = (float)preg_replace('/[^\d.]/', '', $_POST['price_genjutsu'] ?? '0');
        $sort            = (int)($_POST['sort_order'] ?? 0);
        $min_wd          = (float)preg_replace('/[^\d.]/', '', $_POST['min_wd'] ?? '50000');
        $max_wd          = (float)preg_replace('/[^\d.]/', '', $_POST['max_wd'] ?? '0');

        if (!$name) { $flash = 'Nama paket wajib diisi.'; $flashType = 'error'; }
        else {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO memberships (name,icon,price,original_price,watch_limit,duration_days,description,is_active,sort_order,min_wd,max_wd,wd_hold,allow_edit_bank,is_genjutsu,price_genjutsu) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$name, $icon, $price, $orig_price, $limit, $days, $desc, $active, $sort, $min_wd, $max_wd, $wd_hold, $allow_edit_bank, $is_genjutsu, $price_genjutsu]);
                $flash = "Paket <strong>{$name}</strong> berhasil ditambahkan.";
            } else {
                $pdo->prepare("UPDATE memberships SET name=?,icon=?,price=?,original_price=?,watch_limit=?,duration_days=?,description=?,is_active=?,sort_order=?,min_wd=?,max_wd=?,wd_hold=?,allow_edit_bank=?,is_genjutsu=?,price_genjutsu=? WHERE id=?")
                    ->execute([$name, $icon, $price, $orig_price, $limit, $days, $desc, $active, $sort, $min_wd, $max_wd, $wd_hold, $allow_edit_bank, $is_genjutsu, $price_genjutsu, $id]);
                $flash = "Paket <strong>{$name}</strong> berhasil diperbarui.";
            }
            $flashType = 'success';
        }
    }

    if ($action === 'delete' && $id) {
        $force = !empty($_POST['force']);
        try {
            if ($force) {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM upgrade_orders WHERE membership_id=?")->execute([$id]);
                $pdo->prepare("UPDATE users SET membership_id=1, membership_expires_at=NULL WHERE membership_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM memberships WHERE id=? AND price>0")->execute([$id]);
                $pdo->commit();
                $flash = 'Paket beserta seluruh riwayat order berhasil dihapus.';
            } else {
                $pdo->prepare("DELETE FROM memberships WHERE id=? AND price>0")->execute([$id]);
                $flash = 'Paket berhasil dihapus.';
            }
            $flashType = 'success';
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() == '23000') {
                $flash = 'Gagal: Masih ada user atau riwayat terkait. Gunakan opsi Hapus Paksa.';
            } else {
                $flash = 'Terjadi kesalahan sistem saat menghapus.';
            }
            $flashType = 'error';
        }
    }

    if ($action === 'save_level_perf') {
        $perfs = $_POST['perf'] ?? [];
        foreach ($perfs as $pid => $data) {
            $avg      = (float)($data['avg'] ?? 99.8);
            $down     = isset($data['down']) ? 1 : 0;
            $disabled = isset($data['disabled']) ? 1 : 0;
            $pdo->prepare("UPDATE memberships SET perf_avg=?, perf_down_if_own=?, is_wd_disabled=? WHERE id=?")->execute([$avg, $down, $disabled, $pid]);
        }
        $flash = 'Pengaturan kinerja level berhasil disimpan!';
        $flashType = 'success';
    }
}

$plans = $pdo->query("SELECT * FROM memberships ORDER BY sort_order ASC, price ASC")->fetchAll();

$pageTitle  = 'Paket Membership';
$activePage = 'memberships';
require __DIR__ . '/partials/header.php';
?>

<style>
/* ── PAGE HEADER ── */
.ms-page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 24px; gap: 12px; flex-wrap: wrap;
}
.ms-page-title { font-size: 20px; font-weight: 800; color: #e0e0f0; display: flex; align-items: center; gap: 8px; }
.ms-page-actions { display: flex; gap: 8px; }

/* ── STAT SUMMARY STRIP ── */
.ms-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.ms-stat { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 14px 16px; }
.ms-stat__label { font-size: 10px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.ms-stat__val { font-size: 22px; font-weight: 900; color: #e0e0f0; }
.ms-stat__sub { font-size: 11px; color: #666; margin-top: 2px; }

/* ── TAB NAV ── */
.stab-nav { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; padding: 6px; background: rgba(255,255,255,.04); border-radius: 12px; border: 1px solid rgba(255,255,255,.07); }
.stab-link { display: flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #aaa; text-decoration: none; transition: all .15s; border: 1px solid transparent; white-space: nowrap; cursor: pointer; background: none; }
.stab-link:hover { background: rgba(255,255,255,.07); color: #fff; }
.stab-link.active { background: var(--brand); color: #fff; border-color: rgba(255,255,255,.2); box-shadow: 0 2px 8px rgba(255,107,53,.35); }
.stab-pane { display: none; }
.stab-pane.active { display: block; }

/* ── PLAN TABLE ── */
.ms-table-wrap { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 14px; overflow: hidden; }
.ms-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.ms-table thead th { background: rgba(255,255,255,.05); color: #888; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,.07); white-space: nowrap; }
.ms-table tbody tr { border-bottom: 1px solid rgba(255,255,255,.05); transition: background .15s; }
.ms-table tbody tr:last-child { border-bottom: none; }
.ms-table tbody tr:hover { background: rgba(255,255,255,.03); }
.ms-table td { padding: 14px 16px; vertical-align: middle; color: #e0e0f0; }

/* Plan icon badge */
.ms-plan-badge { display: inline-flex; align-items: center; gap: 8px; }
.ms-plan-icon { width: 36px; height: 36px; border-radius: 10px; background: rgba(255,255,255,.07); display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ms-plan-name { font-weight: 800; font-size: 14px; }
.ms-plan-sort { font-size: 10px; color: #666; margin-top: 1px; }

/* Price display */
.ms-price-main { font-weight: 900; font-size: 15px; color: #fff; }
.ms-price-orig { font-size: 11px; color: #666; text-decoration: line-through; }
.ms-price-genjutsu { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 800; background: rgba(255,193,7,.12); color: #ffc107; border: 1px solid rgba(255,193,7,.3); border-radius: 6px; padding: 2px 8px; margin-top: 4px; }

/* Status badge */
.ms-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 800; border-radius: 20px; padding: 3px 10px; }
.ms-badge--on  { background: rgba(76,175,130,.15); color: #4CAF82; border: 1px solid rgba(76,175,130,.3); }
.ms-badge--off { background: rgba(244,78,59,.1);  color: #F44E3B; border: 1px solid rgba(244,78,59,.25); }
.ms-badge--hold { background: rgba(255,193,7,.1); color: #FFC107; border: 1px solid rgba(255,193,7,.25); }
.ms-badge--nowd { background: rgba(244,78,59,.1); color: #ef5350; border: 1px solid rgba(244,78,59,.25); }

/* Action buttons in table */
.ms-btn-icon { width: 30px; height: 30px; border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: rgba(255,255,255,.05); color: #ccc; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .15s; }
.ms-btn-icon:hover { background: rgba(255,255,255,.12); color: #fff; border-color: rgba(255,255,255,.25); }
.ms-btn-icon--danger:hover { background: rgba(239,68,68,.15); color: #ef4444; border-color: rgba(239,68,68,.4); }

/* Active user count */
.ms-user-count { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; color: #4E9BFF; }

/* ── DRAWER (SLIDE-IN PANEL) ── */
.ms-drawer-overlay {
  position: fixed; inset: 0; z-index: 1050;
  background: rgba(0,0,0,.65); backdrop-filter: blur(4px);
  opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s;
}
.ms-drawer-overlay.open { opacity: 1; visibility: visible; }

.ms-drawer {
  position: fixed; top: 0; right: 0; height: 100vh; z-index: 1051;
  width: 480px; max-width: 96vw;
  background: #131520; border-left: 1px solid rgba(255,255,255,.1);
  box-shadow: -8px 0 40px rgba(0,0,0,.6);
  display: flex; flex-direction: column;
  transform: translateX(100%); transition: transform .3s cubic-bezier(.4,0,.2,1);
  overflow: hidden;
}
.ms-drawer.open { transform: translateX(0); }

.ms-drawer-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px 18px; border-bottom: 1px solid rgba(255,255,255,.08);
  flex-shrink: 0;
}
.ms-drawer-title { font-size: 16px; font-weight: 800; color: #e0e0f0; display: flex; align-items: center; gap: 8px; }
.ms-drawer-close { width: 32px; height: 32px; border: 1px solid rgba(255,255,255,.12); border-radius: 8px; background: rgba(255,255,255,.05); color: #aaa; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; transition: all .15s; }
.ms-drawer-close:hover { background: rgba(239,68,68,.15); color: #ef4444; border-color: rgba(239,68,68,.4); }

.ms-drawer-body { flex: 1; overflow-y: auto; padding: 24px; }
.ms-drawer-body::-webkit-scrollbar { width: 5px; }
.ms-drawer-body::-webkit-scrollbar-track { background: transparent; }
.ms-drawer-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }

.ms-drawer-footer {
  display: flex; gap: 10px; justify-content: flex-end;
  padding: 16px 24px; border-top: 1px solid rgba(255,255,255,.08);
  background: rgba(0,0,0,.2); flex-shrink: 0;
}

/* ── FORM STYLES ── */
.ms-section-label {
  font-size: 10px; font-weight: 800; color: var(--brand,#FF6B35);
  text-transform: uppercase; letter-spacing: .6px;
  margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
  padding-bottom: 8px; border-bottom: 1px solid rgba(255,107,53,.2);
}
.ms-field-row { display: grid; gap: 12px; margin-bottom: 14px; }
.ms-field-row.col2 { grid-template-columns: 1fr 1fr; }
.ms-field-row.col3 { grid-template-columns: 1fr 1fr 1fr; }

.ms-field { display: flex; flex-direction: column; gap: 5px; }
.ms-field label { font-size: 11px; font-weight: 700; color: #888; }
.ms-field input[type="text"],
.ms-field input[type="number"],
.ms-field textarea,
.ms-field select { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: 8px; padding: 9px 12px; color: #e0e0f0; font-size: 13px; font-weight: 600; outline: none; transition: border-color .15s, background .15s; width: 100%; }
.ms-field input:focus, .ms-field textarea:focus { border-color: var(--brand,#FF6B35); background: rgba(255,107,53,.06); }
.ms-field small { font-size: 10px; color: #555; }

/* Toggle switch */
.ms-toggle-group { display: flex; flex-direction: column; gap: 8px; }
.ms-toggle { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); cursor: pointer; transition: background .15s; }
.ms-toggle:hover { background: rgba(255,255,255,.07); }
.ms-toggle input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: var(--brand,#FF6B35); }
.ms-toggle-info { flex: 1; }
.ms-toggle-info strong { font-size: 12px; font-weight: 800; color: #e0e0f0; display: block; }
.ms-toggle-info small { font-size: 10px; color: #666; }

/* Genjutsu section */
.ms-genjutsu-box {
  background: rgba(255,193,7,.05); border: 1px dashed rgba(255,193,7,.35);
  border-radius: 12px; padding: 14px 16px; margin-bottom: 14px;
}
.ms-genjutsu-label { font-size: 11px; font-weight: 800; color: #ffc107; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }

/* ── DELETE CONFIRM DRAWER (smaller) ── */
.ms-confirm-overlay {
  position: fixed; inset: 0; z-index: 1060;
  background: rgba(0,0,0,.7); backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; padding: 20px;
}
.ms-confirm-overlay.open { display: flex; }
.ms-confirm-card {
  background: #131520; border: 1px solid rgba(239,68,68,.3);
  border-radius: 16px; width: 100%; max-width: 380px;
  padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,.6);
  animation: confirmPop .25s ease;
}
@keyframes confirmPop { from { transform: scale(.92); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.ms-confirm-icon { font-size: 36px; margin-bottom: 12px; }
.ms-confirm-title { font-size: 17px; font-weight: 800; color: #ef4444; margin-bottom: 6px; }
.ms-confirm-sub { font-size: 12px; color: #888; margin-bottom: 18px; line-height: 1.5; }
.ms-confirm-actions { display: flex; gap: 10px; }
.ms-confirm-actions button { flex: 1; padding: 10px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; border: 1px solid transparent; transition: all .15s; }
.ms-btn-cancel { background: rgba(255,255,255,.07); color: #aaa; border-color: rgba(255,255,255,.12) !important; }
.ms-btn-cancel:hover { background: rgba(255,255,255,.12); color: #fff; }
.ms-btn-danger { background: linear-gradient(135deg,#ef4444,#dc2626); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,.35); }
.ms-btn-danger:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(239,68,68,.4); }

/* Perf modal */
.ms-perf-row { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.07); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
.ms-perf-name { font-weight: 800; font-size: 13px; color: #e0e0f0; margin-bottom: 10px; }

/* Alert */
.ms-alert { display: flex; align-items: flex-start; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 600; }
.ms-alert--success { background: rgba(76,175,130,.1); border: 1px solid rgba(76,175,130,.3); color: #4CAF82; }
.ms-alert--error   { background: rgba(244,78,59,.1);  border: 1px solid rgba(244,78,59,.3);  color: #F44E3B; }
.ms-alert i { font-size: 16px; flex-shrink: 0; margin-top: 1px; }

/* Primary button */
.ms-btn-primary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; background: var(--brand,#FF6B35); color: #fff; border: none; transition: all .15s; box-shadow: 0 4px 12px rgba(255,107,53,.3); }
.ms-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(255,107,53,.4); }
.ms-btn-secondary { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; background: rgba(255,255,255,.07); color: #ccc; border: 1px solid rgba(255,255,255,.12); transition: all .15s; }
.ms-btn-secondary:hover { background: rgba(255,255,255,.12); color: #fff; }
.ms-btn-info { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; background: rgba(78,155,255,.12); color: #4E9BFF; border: 1px solid rgba(78,155,255,.3); transition: all .15s; }
.ms-btn-info:hover { background: rgba(78,155,255,.2); }

@media (max-width: 600px) {
  .ms-drawer { width: 100vw; }
  .ms-field-row.col2, .ms-field-row.col3 { grid-template-columns: 1fr; }
  .ms-stats { grid-template-columns: 1fr 1fr; }
}
</style>

<?php
$total_plans   = count($plans);
$active_plans  = count(array_filter($plans, fn($p) => $p['is_active']));
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE membership_id != 1 AND membership_expires_at > NOW()")->fetchColumn();
$total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM upgrade_orders WHERE status='confirmed'")->fetchColumn();
?>

<!-- PAGE HEADER -->
<div class="ms-page-header">
  <div class="ms-page-title">⭐ Paket Membership</div>
  <div class="ms-page-actions">
    <button type="button" class="ms-btn-info" onclick="openPerfDrawer()">
      <i class="ph-bold ph-chart-bar"></i> Setting Kinerja WD
    </button>
    <button type="button" class="ms-btn-primary" onclick="openAddDrawer()">
      <i class="ph-bold ph-plus"></i> Tambah Paket
    </button>
  </div>
</div>

<!-- FLASH ALERT -->
<?php if ($flash): ?>
<div class="ms-alert ms-alert--<?= $flashType === 'error' ? 'error' : 'success' ?>">
  <i class="ph-fill ph-<?= $flashType === 'error' ? 'warning-circle' : 'check-circle' ?>"></i>
  <span><?= $flash ?></span>
</div>
<?php endif; ?>

<!-- STAT STRIP -->
<div class="ms-stats">
  <div class="ms-stat">
    <div class="ms-stat__label">Total Paket</div>
    <div class="ms-stat__val"><?= $total_plans ?></div>
    <div class="ms-stat__sub"><?= $active_plans ?> aktif</div>
  </div>
  <div class="ms-stat">
    <div class="ms-stat__label">Member Aktif</div>
    <div class="ms-stat__val"><?= number_format($total_members) ?></div>
    <div class="ms-stat__sub">sedang berlangsung</div>
  </div>
  <div class="ms-stat">
    <div class="ms-stat__label">Total Revenue Upgrade</div>
    <div class="ms-stat__val" style="font-size:17px"><?= format_rp($total_revenue) ?></div>
    <div class="ms-stat__sub">dari semua order disetujui</div>
  </div>
</div>

<!-- TABS -->
<nav class="stab-nav">
  <button class="stab-link active" data-tab="pakets">⭐ Daftar Paket</button>
  <button class="stab-link" data-tab="orders">📦 Ringkasan User per Paket</button>
</nav>

<!-- TAB: PLANS TABLE -->
<div class="stab-pane active" id="tab-pakets">
  <div class="ms-table-wrap">
    <table class="ms-table" id="ms-plans-table">
      <thead>
        <tr>
          <th>Paket</th>
          <th>Harga</th>
          <th>Durasi</th>
          <th>WD Min / Max</th>
          <th>Limit Tonton</th>
          <th>Flags</th>
          <th>User Aktif</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $p):
          $activeUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()");
          $activeUsersStmt->execute([$p['id']]);
          $ucount = (int)$activeUsersStmt->fetchColumn();
        ?>
        <tr>
          <td>
            <div class="ms-plan-badge">
              <div class="ms-plan-icon"><?= html_entity_decode($p['icon'] ?: '⭐', ENT_HTML5, 'UTF-8') ?></div>
              <div>
                <div class="ms-plan-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="ms-plan-sort">Urutan: <?= $p['sort_order'] ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php if ((float)$p['price'] === 0.0): ?>
              <span class="ms-badge ms-badge--on">GRATIS</span>
            <?php else: ?>
              <div class="ms-price-main"><?= format_rp((float)$p['price']) ?></div>
              <?php if ((float)$p['original_price'] > 0): ?>
              <div class="ms-price-orig"><?= format_rp((float)$p['original_price']) ?></div>
              <?php endif; ?>
              <?php if ($p['is_genjutsu']): ?>
              <div class="ms-price-genjutsu">👁️ Genjutsu: <?= format_rp((float)$p['price_genjutsu']) ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="color:#aaa;font-size:12px;font-weight:700"><?= $p['duration_days'] ?> hari</td>
          <td style="font-size:12px">
            <div style="color:#4CAF82;font-weight:700">Min: <?= format_rp((float)$p['min_wd']) ?></div>
            <div style="color:#aaa;font-weight:600">Max: <?= (float)$p['max_wd'] > 0 ? format_rp((float)$p['max_wd']) : '<em style="color:#555">Bebas</em>' ?></div>
          </td>
          <td style="font-weight:700;color:#e0e0f0"><?= $p['watch_limit'] ?>x /hari</td>
          <td>
            <div style="display:flex;flex-wrap:wrap;gap:4px;">
              <span class="ms-badge <?= $p['is_active'] ? 'ms-badge--on' : 'ms-badge--off' ?>"><?= $p['is_active'] ? 'Aktif' : 'Nonaktif' ?></span>
              <?php if ($p['wd_hold']): ?><span class="ms-badge ms-badge--hold">⏸ Hold</span><?php endif; ?>
              <?php if ($p['is_wd_disabled']): ?><span class="ms-badge ms-badge--nowd">WD Off</span><?php endif; ?>
              <?php if ($p['allow_edit_bank']): ?><span class="ms-badge" style="background:rgba(78,155,255,.12);color:#4E9BFF;border:1px solid rgba(78,155,255,.3)">✏️ Edit Rek</span><?php endif; ?>
            </div>
          </td>
          <td>
            <span class="ms-user-count">
              <i class="ph-fill ph-users"></i> <?= $ucount ?>
            </span>
          </td>
          <td style="text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <button class="ms-btn-icon" title="Edit" data-plan="<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>" onclick="openEditDrawer(JSON.parse(this.dataset.plan))">
                ✏️
              </button>
              <?php if ((float)$p['price'] > 0): ?>
              <button class="ms-btn-icon ms-btn-icon--danger" title="Hapus" onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">
                🗑️
              </button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- TAB: USER SUMMARY PER PLAN -->
<div class="stab-pane" id="tab-orders">
  <div class="row g-3">
    <?php foreach ($plans as $p):
      if ((float)$p['price'] === 0.0) continue;
      $usersStmt = $pdo->prepare("SELECT username, membership_expires_at FROM users WHERE membership_id=? AND membership_expires_at>NOW() ORDER BY membership_expires_at DESC LIMIT 20");
      $usersStmt->execute([$p['id']]);
      $usersInPlan = $usersStmt->fetchAll();
      $totalInPlan = (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()")->execute([$p['id']]);
      $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()");
      $countStmt->execute([$p['id']]);
      $planUserCount = (int)$countStmt->fetchColumn();
    ?>
    <div class="col-md-6">
      <div class="c-card">
        <div class="c-card-header" style="display:flex;align-items:center;justify-content:space-between">
          <span class="c-card-title"><?= html_entity_decode($p['icon'], ENT_HTML5, 'UTF-8') ?> <?= htmlspecialchars($p['name']) ?></span>
          <span class="ms-user-count"><i class="ph-fill ph-users"></i> <?= $planUserCount ?> aktif</span>
        </div>
        <div class="c-card-body" style="padding:12px 16px;">
          <?php if (empty($usersInPlan)): ?>
            <p style="font-size:12px;color:#555;margin:0">Belum ada user aktif di paket ini.</p>
          <?php else: ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($usersInPlan as $u): ?>
            <span style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;color:#ccc" title="Hingga <?= date('d M Y', strtotime($u['membership_expires_at'])) ?>">
              <?= htmlspecialchars($u['username']) ?>
            </span>
            <?php endforeach; ?>
            <?php if ($planUserCount > 20): ?>
            <span style="background:rgba(255,107,53,.1);border:1px solid rgba(255,107,53,.3);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;color:#FF6B35">+<?= $planUserCount - 20 ?> lainnya</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- ADD DRAWER -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="ms-drawer-overlay" id="add-overlay" onclick="closeAddDrawer()"></div>
<div class="ms-drawer" id="add-drawer">
  <div class="ms-drawer-header">
    <div class="ms-drawer-title"><i class="ph-bold ph-plus-circle" style="color:var(--brand)"></i> Tambah Paket Baru</div>
    <button class="ms-drawer-close" onclick="closeAddDrawer()">✕</button>
  </div>
  <form method="POST" id="add-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="ms-drawer-body">
      <?php include __DIR__ . '/partials/membership_form.php'; ?>
    </div>
    <div class="ms-drawer-footer">
      <button type="button" class="ms-btn-secondary" onclick="closeAddDrawer()">Batal</button>
      <button type="submit" class="ms-btn-primary"><i class="ph-bold ph-floppy-disk"></i> Simpan Paket</button>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- EDIT DRAWER -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="ms-drawer-overlay" id="edit-overlay" onclick="closeEditDrawer()"></div>
<div class="ms-drawer" id="edit-drawer">
  <div class="ms-drawer-header">
    <div class="ms-drawer-title"><i class="ph-bold ph-pencil-simple" style="color:#4E9BFF"></i> Edit Paket</div>
    <button class="ms-drawer-close" onclick="closeEditDrawer()">✕</button>
  </div>
  <form method="POST" id="edit-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="edit-id">
    <div class="ms-drawer-body" id="edit-drawer-body">
      <!-- populated via JS -->
    </div>
    <div class="ms-drawer-footer">
      <button type="button" class="ms-btn-secondary" onclick="closeEditDrawer()">Batal</button>
      <button type="submit" class="ms-btn-primary"><i class="ph-bold ph-floppy-disk"></i> Update Paket</button>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- DELETE CONFIRM -->
<!-- ══════════════════════════════════════════════════════════ -->
<div class="ms-confirm-overlay" id="delete-confirm">
  <form method="POST" id="delete-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete-id">
    <div class="ms-confirm-card">
      <div class="ms-confirm-icon">🗑️</div>
      <div class="ms-confirm-title">Hapus Paket?</div>
      <div class="ms-confirm-sub">Paket <strong id="delete-name" style="color:#fff"></strong> akan dihapus permanen. Aksi ini tidak bisa dibatalkan.</div>
      <div class="ms-toggle" style="margin-bottom:16px;border-color:rgba(239,68,68,.25);">
        <input type="checkbox" name="force" id="delete-force" value="1">
        <div class="ms-toggle-info">
          <strong style="color:#ffc107">Hapus Paksa</strong>
          <small>Centang ini jika gagal karena ada user/riwayat terkait. User akan dikembalikan ke paket <?= htmlspecialchars(get_free_tier_name($pdo)) ?>.</small>
        </div>
      </div>
      <div class="ms-confirm-actions">
        <button type="button" class="ms-btn-cancel" onclick="closeDeleteConfirm()">Batal</button>
        <button type="submit" class="ms-btn-danger">Ya, Hapus</button>
      </div>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════ -->
<!-- PERFORMANCE DRAWER -->
<!-- ══════════════════════════════════════════════════════════ -->
<?php $all_memberships = $pdo->query("SELECT * FROM memberships ORDER BY sort_order ASC, price ASC")->fetchAll(); ?>
<div class="ms-drawer-overlay" id="perf-overlay" onclick="closePerfDrawer()"></div>
<div class="ms-drawer" id="perf-drawer">
  <div class="ms-drawer-header">
    <div class="ms-drawer-title"><i class="ph-bold ph-chart-bar" style="color:#4E9BFF"></i> Setting Kinerja WD</div>
    <button class="ms-drawer-close" onclick="closePerfDrawer()">✕</button>
  </div>
  <form method="POST" id="perf-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_level_perf">
    <div class="ms-drawer-body">
      <p style="font-size:12px;color:#888;margin-bottom:16px;line-height:1.6">Atur rata-rata keberhasilan/uptime WD yang ditampilkan kepada user. Aktifkan <strong>Down If Own</strong> agar kinerjanya tampak rendah saat diakses pemilik level tersebut.</p>
      <?php foreach ($all_memberships as $m): ?>
      <div class="ms-perf-row">
        <div class="ms-perf-name"><?= htmlspecialchars($m['icon'].' '.$m['name']) ?></div>
        <div class="ms-field-row col2">
          <div class="ms-field">
            <label>AVG Kinerja (%)</label>
            <input type="number" step="0.01" name="perf[<?= $m['id'] ?>][avg]" value="<?= (float)($m['perf_avg'] ?? 99.8) ?>" min="0" max="100">
          </div>
          <div class="ms-field" style="justify-content:flex-end;gap:8px;padding-top:20px">
            <label class="ms-toggle" style="margin:0;cursor:pointer">
              <input type="checkbox" name="perf[<?= $m['id'] ?>][down]" value="1" <?= !empty($m['perf_down_if_own']) ? 'checked' : '' ?>>
              <div class="ms-toggle-info"><strong style="color:#ffc107">Down If Own</strong></div>
            </label>
            <label class="ms-toggle" style="margin:0;cursor:pointer">
              <input type="checkbox" name="perf[<?= $m['id'] ?>][disabled]" value="1" <?= !empty($m['is_wd_disabled']) ? 'checked' : '' ?>>
              <div class="ms-toggle-info"><strong style="color:#ef4444">Tutup WD</strong></div>
            </label>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="ms-drawer-footer">
      <button type="button" class="ms-btn-secondary" onclick="closePerfDrawer()">Batal</button>
      <button type="submit" class="ms-btn-primary"><i class="ph-bold ph-floppy-disk"></i> Simpan Kinerja</button>
    </div>
  </form>
</div>

<script>
/* ─── TAB SYSTEM ─────────────────────────── */
document.querySelectorAll('.stab-link').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.stab-link').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const tab = btn.dataset.tab;
    document.getElementById('tab-' + tab)?.classList.add('active');
  });
});

/* ─── DRAWER HELPERS ──────────────────────── */
function openDrawer(overlayId, drawerId) {
  document.getElementById(overlayId).classList.add('open');
  document.getElementById(drawerId).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDrawer(overlayId, drawerId) {
  document.getElementById(overlayId).classList.remove('open');
  document.getElementById(drawerId).classList.remove('open');
  document.body.style.overflow = '';
}

function openAddDrawer()   { openDrawer('add-overlay',  'add-drawer'); }
function closeAddDrawer()  { closeDrawer('add-overlay', 'add-drawer'); }
function openPerfDrawer()  { openDrawer('perf-overlay', 'perf-drawer'); }
function closePerfDrawer() { closeDrawer('perf-overlay','perf-drawer'); }

function openEditDrawer(p) {
  document.getElementById('edit-id').value = p.id;
  document.getElementById('edit-drawer-body').innerHTML = buildEditForm(p);
  openDrawer('edit-overlay', 'edit-drawer');
}
function closeEditDrawer() { closeDrawer('edit-overlay','edit-drawer'); }

/* ─── DELETE CONFIRM ──────────────────────── */
function confirmDelete(id, name) {
  document.getElementById('delete-id').value = id;
  document.getElementById('delete-name').textContent = name;
  document.getElementById('delete-force').checked = false;
  document.getElementById('delete-confirm').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDeleteConfirm() {
  document.getElementById('delete-confirm').classList.remove('open');
  document.body.style.overflow = '';
}

/* ─── EDIT FORM BUILDER ───────────────────── */
function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function buildEditForm(p) {
  return `
  <div class="ms-section-label"><i class="ph-bold ph-tag"></i> Info Dasar</div>
  <div class="ms-field-row col2" style="margin-bottom:14px">
    <div class="ms-field">
      <label>Nama Paket</label>
      <input type="text" name="name" value="${esc(p.name)}" required>
    </div>
    <div class="ms-field">
      <label>Icon (Emoji)</label>
      <input type="text" name="icon" value="${esc(p.icon||'⭐')}" required>
    </div>
  </div>

  <div class="ms-section-label"><i class="ph-bold ph-currency-circle-dollar"></i> Harga</div>
  <div class="ms-field-row col2" style="margin-bottom:14px">
    <div class="ms-field">
      <label>Harga Jual (Rp)</label>
      <input type="number" name="price" value="${p.price}" min="0" step="1">
    </div>
    <div class="ms-field">
      <label>Harga Asli/Coret (Rp)</label>
      <input type="number" name="original_price" value="${p.original_price}" min="0" step="1">
    </div>
  </div>

  <div class="ms-genjutsu-box">
    <div class="ms-genjutsu-label">👁️ Genjutsu Pricing</div>
    <div class="ms-field-row col2">
      <div class="ms-field">
        <label style="color:#ffc107">Aktifkan Genjutsu</label>
        <label class="ms-toggle" style="cursor:pointer">
          <input type="checkbox" name="is_genjutsu" ${p.is_genjutsu==1?'checked':''}>
          <div class="ms-toggle-info">
            <strong>Genjutsu Mode</strong>
            <small>Harga berubah jika saldo beli user cukup</small>
          </div>
        </label>
      </div>
      <div class="ms-field">
        <label style="color:#ffc107">Harga Genjutsu (Rp)</label>
        <input type="number" name="price_genjutsu" value="${p.price_genjutsu||0}" min="0" step="1">
      </div>
    </div>
  </div>

  <div class="ms-section-label"><i class="ph-bold ph-sliders"></i> Pengaturan Paket</div>
  <div class="ms-field-row col3" style="margin-bottom:14px">
    <div class="ms-field">
      <label>Limit Tonton/Hari</label>
      <input type="number" name="watch_limit" value="${p.watch_limit}" min="1">
    </div>
    <div class="ms-field">
      <label>Durasi (hari)</label>
      <input type="number" name="duration_days" value="${p.duration_days}" min="1">
    </div>
    <div class="ms-field">
      <label>Urutan Tampil</label>
      <input type="number" name="sort_order" value="${p.sort_order}">
    </div>
  </div>

  <div class="ms-section-label"><i class="ph-bold ph-arrows-out-line-horizontal"></i> Withdraw</div>
  <div class="ms-field-row col2" style="margin-bottom:14px">
    <div class="ms-field">
      <label>Min. Withdraw (Rp)</label>
      <input type="number" name="min_wd" value="${p.min_wd}" min="0" step="1">
    </div>
    <div class="ms-field">
      <label>Max. Withdraw (Rp)</label>
      <input type="number" name="max_wd" value="${p.max_wd}" min="0" step="1">
      <small>0 = Tanpa batas maksimum</small>
    </div>
  </div>

  <div class="ms-section-label"><i class="ph-bold ph-toggle-right"></i> Opsi Tambahan</div>
  <div class="ms-toggle-group" style="margin-bottom:14px">
    <label class="ms-toggle" style="cursor:pointer">
      <input type="checkbox" name="wd_hold" ${p.wd_hold==1?'checked':''}>
      <div class="ms-toggle-info">
        <strong>⏸ Tahan Withdraw (Auto Hold)</strong>
        <small>WD dari user level ini otomatis di-hold</small>
      </div>
    </label>
    <label class="ms-toggle" style="cursor:pointer">
      <input type="checkbox" name="allow_edit_bank" ${p.allow_edit_bank==1?'checked':''}>
      <div class="ms-toggle-info">
        <strong>✏️ Izinkan Edit Rekening Bank</strong>
        <small>User level ini bisa ubah data rekening bank</small>
      </div>
    </label>
    <label class="ms-toggle" style="cursor:pointer">
      <input type="checkbox" name="is_active" ${p.is_active==1?'checked':''}>
      <div class="ms-toggle-info">
        <strong>✅ Paket Aktif</strong>
        <small>Paket tampil di halaman upgrade user</small>
      </div>
    </label>
  </div>

  <div class="ms-section-label"><i class="ph-bold ph-note"></i> Deskripsi</div>
  <div class="ms-field" style="margin-bottom:4px">
    <label>Deskripsi Singkat (opsional)</label>
    <textarea name="description" rows="3">${esc(p.description||'')}</textarea>
  </div>`;
}

/* ─── ESC KEY CLOSE ───────────────────────── */
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeAddDrawer(); closeEditDrawer(); closePerfDrawer(); closeDeleteConfirm();
  }
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
