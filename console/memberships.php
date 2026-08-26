<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
staff_require('memberships');
csrf_enforce();

$flash = $flashType = '';

// -- Determine what to show inline --
$edit_id   = isset($_GET['edit'])   ? (int)$_GET['edit']   : 0;
$show_add  = isset($_GET['add']);
$show_perf = isset($_GET['perf']);

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
        $is_genjutsu_hilang = isset($_POST['is_genjutsu_hilang']) ? 1 : 0;
        $price_genjutsu  = (float)preg_replace('/[^\d.]/', '', $_POST['price_genjutsu'] ?? '0');
        $sort            = (int)($_POST['sort_order'] ?? 0);
        $min_wd          = (float)preg_replace('/[^\d.]/', '', $_POST['min_wd'] ?? '50000');
        $max_wd          = (float)preg_replace('/[^\d.]/', '', $_POST['max_wd'] ?? '0');

        if (!$name) { $flash = 'Nama paket wajib diisi.'; $flashType = 'error'; }
        else {
            if ($action === 'add') {
                $pdo->prepare("INSERT INTO memberships (name,icon,price,original_price,watch_limit,duration_days,description,is_active,sort_order,min_wd,max_wd,wd_hold,allow_edit_bank,is_genjutsu,is_genjutsu_hilang,price_genjutsu) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$icon,$price,$orig_price,$limit,$days,$desc,$active,$sort,$min_wd,$max_wd,$wd_hold,$allow_edit_bank,$is_genjutsu,$is_genjutsu_hilang,$price_genjutsu]);
                $flash = "Paket <strong>{$name}</strong> berhasil ditambahkan.";
            } else {
                $pdo->prepare("UPDATE memberships SET name=?,icon=?,price=?,original_price=?,watch_limit=?,duration_days=?,description=?,is_active=?,sort_order=?,min_wd=?,max_wd=?,wd_hold=?,allow_edit_bank=?,is_genjutsu=?,is_genjutsu_hilang=?,price_genjutsu=? WHERE id=?")
                    ->execute([$name,$icon,$price,$orig_price,$limit,$days,$desc,$active,$sort,$min_wd,$max_wd,$wd_hold,$allow_edit_bank,$is_genjutsu,$is_genjutsu_hilang,$price_genjutsu,$id]);
                $flash = "Paket <strong>{$name}</strong> berhasil diperbarui.";
            }
            $flashType = 'success';
            header('Location: memberships.php'); exit;
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
            $flash = ($e->getCode() == '23000')
                ? 'Gagal: Masih ada user/riwayat terkait. Gunakan opsi Hapus Paksa.'
                : 'Terjadi kesalahan sistem saat menghapus.';
            $flashType = 'error';
        }
        header('Location: memberships.php'); exit;
    }

    if ($action === 'save_level_perf') {
        foreach ($_POST['perf'] ?? [] as $pid => $data) {
            $pdo->prepare("UPDATE memberships SET perf_avg=?, perf_down_if_own=?, is_wd_disabled=? WHERE id=?")
                ->execute([(float)($data['avg']??99.8), isset($data['down'])?1:0, isset($data['disabled'])?1:0, $pid]);
        }
        $flash = 'Pengaturan kinerja berhasil disimpan!';
        $flashType = 'success';
        header('Location: memberships.php'); exit;
    }
}

$plans = $pdo->query("SELECT * FROM memberships ORDER BY sort_order ASC, price ASC")->fetchAll();
$edit_plan = null;
if ($edit_id) {
    foreach ($plans as $p) { if ((int)$p['id'] === $edit_id) { $edit_plan = $p; break; } }
}

$total_plans   = count($plans);
$active_plans  = count(array_filter($plans, fn($p) => $p['is_active']));
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE membership_id != 1 AND membership_expires_at > NOW()")->fetchColumn();
$total_revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM upgrade_orders WHERE status='confirmed'")->fetchColumn();

$pageTitle  = 'Paket Membership';
$activePage = 'memberships';
require __DIR__ . '/partials/header.php';
?>

<style>
/* ── PAGE ── */
.ms-page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; gap:12px; flex-wrap:wrap; }
.ms-page-title  { font-size:20px; font-weight:800; color:#e0e0f0; display:flex; align-items:center; gap:8px; }
.ms-page-actions{ display:flex; gap:8px; flex-wrap:wrap; }

/* ── STATS ── */
.ms-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:24px; }
.ms-stat  { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:14px 16px; }
.ms-stat__label { font-size:10px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.ms-stat__val   { font-size:22px; font-weight:900; color:#e0e0f0; }
.ms-stat__sub   { font-size:11px; color:#666; margin-top:2px; }

/* ── TAB NAV ── */
.stab-nav  { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:20px; padding:6px; background:rgba(255,255,255,.04); border-radius:12px; border:1px solid rgba(255,255,255,.07); }
.stab-link { display:flex; align-items:center; gap:6px; padding:8px 14px; border-radius:8px; font-size:12px; font-weight:700; color:#aaa; text-decoration:none; transition:all .15s; border:1px solid transparent; cursor:pointer; background:none; }
.stab-link:hover  { background:rgba(255,255,255,.07); color:#fff; }
.stab-link.active { background:var(--brand); color:#fff; box-shadow:0 2px 8px rgba(255,107,53,.35); }
.stab-pane        { display:none; }
.stab-pane.active { display:block; }

/* ── TABLE ── */
.ms-table-wrap { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:14px; overflow:hidden; }
.ms-table { width:100%; border-collapse:collapse; font-size:13px; }
.ms-table thead th { background:rgba(255,255,255,.05); color:#888; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:12px 16px; border-bottom:1px solid rgba(255,255,255,.07); white-space:nowrap; }
.ms-table tbody tr { border-bottom:1px solid rgba(255,255,255,.05); transition:background .15s; }
.ms-table tbody tr:last-child { border-bottom:none; }
.ms-table tbody tr:hover { background:rgba(255,255,255,.02); }
.ms-table td { padding:13px 16px; vertical-align:middle; color:#e0e0f0; }

.ms-plan-badge { display:inline-flex; align-items:center; gap:8px; }
.ms-plan-icon  { width:36px; height:36px; border-radius:10px; background:rgba(255,255,255,.07); display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.ms-plan-name  { font-weight:800; font-size:14px; }
.ms-plan-sort  { font-size:10px; color:#666; margin-top:1px; }

.ms-price-main     { font-weight:900; font-size:15px; color:#fff; }
.ms-price-orig     { font-size:11px; color:#666; text-decoration:line-through; }
.ms-price-genjutsu { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:800; background:rgba(255,193,7,.12); color:#ffc107; border:1px solid rgba(255,193,7,.3); border-radius:6px; padding:2px 8px; margin-top:4px; }

.ms-badge       { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:800; border-radius:20px; padding:3px 10px; }
.ms-badge--on   { background:rgba(76,175,130,.15); color:#4CAF82; border:1px solid rgba(76,175,130,.3); }
.ms-badge--off  { background:rgba(244,78,59,.1);  color:#F44E3B; border:1px solid rgba(244,78,59,.25); }
.ms-badge--hold { background:rgba(255,193,7,.1);  color:#FFC107; border:1px solid rgba(255,193,7,.25); }
.ms-badge--nowd { background:rgba(244,78,59,.1);  color:#ef5350; border:1px solid rgba(244,78,59,.25); }
.ms-user-count  { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#4E9BFF; }

/* ── ACTION LINK BUTTONS ── */
.ms-btn-icon       { width:30px; height:30px; border:1px solid rgba(255,255,255,.12); border-radius:8px; background:rgba(255,255,255,.05); color:#ccc; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; font-size:13px; transition:all .15s; text-decoration:none; }
.ms-btn-icon:hover { background:rgba(255,255,255,.12); color:#fff; border-color:rgba(255,255,255,.25); }
.ms-btn-icon--danger:hover { background:rgba(239,68,68,.15); color:#ef4444; border-color:rgba(239,68,68,.4); }
.ms-btn-icon--active { background:rgba(255,107,53,.15); color:var(--brand); border-color:rgba(255,107,53,.4); }

/* ── INLINE FORM PANEL ── */
.ms-inline-panel {
  background:rgba(255,255,255,.03);
  border:1px solid rgba(255,255,255,.1);
  border-radius:16px;
  padding:28px;
  margin-bottom:24px;
}
.ms-inline-panel--edit {
  border-color:rgba(78,155,255,.3);
  background:rgba(78,155,255,.04);
}
.ms-inline-panel--add {
  border-color:rgba(255,107,53,.3);
  background:rgba(255,107,53,.04);
}
.ms-panel-title {
  font-size:15px; font-weight:800; color:#e0e0f0;
  margin-bottom:22px; display:flex; align-items:center; gap:8px;
  padding-bottom:14px; border-bottom:1px solid rgba(255,255,255,.08);
}
.ms-panel-title a {
  margin-left:auto; font-size:11px; font-weight:700; color:#888;
  text-decoration:none; padding:4px 10px; border-radius:6px;
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
}
.ms-panel-title a:hover { color:#fff; background:rgba(255,255,255,.12); }

/* ── FORM FIELDS ── */
.ms-section-label {
  font-size:10px; font-weight:800; color:var(--brand,#FF6B35);
  text-transform:uppercase; letter-spacing:.6px;
  margin:20px 0 12px; display:flex; align-items:center; gap:6px;
  padding-bottom:8px; border-bottom:1px solid rgba(255,107,53,.2);
}
.ms-section-label:first-child { margin-top:0; }
.ms-field-row      { display:grid; gap:12px; margin-bottom:14px; }
.ms-field-row.col2 { grid-template-columns:1fr 1fr; }
.ms-field-row.col3 { grid-template-columns:1fr 1fr 1fr; }
.ms-field          { display:flex; flex-direction:column; gap:5px; }
.ms-field label    { font-size:11px; font-weight:700; color:#888; }
.ms-field input[type="text"],
.ms-field input[type="number"],
.ms-field textarea  {
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12);
  border-radius:8px; padding:9px 12px; color:#e0e0f0;
  font-size:13px; font-weight:600; outline:none; width:100%;
  transition:border-color .15s, background .15s;
}
.ms-field input:focus, .ms-field textarea:focus {
  border-color:var(--brand,#FF6B35); background:rgba(255,107,53,.06);
}
.ms-field small { font-size:10px; color:#555; }

/* ── TOGGLES ── */
.ms-toggle-group { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
.ms-toggle       { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); cursor:pointer; transition:background .15s; }
.ms-toggle:hover { background:rgba(255,255,255,.07); }
.ms-toggle input[type="checkbox"] { width:16px; height:16px; cursor:pointer; accent-color:var(--brand,#FF6B35); flex-shrink:0; }
.ms-toggle-info strong { font-size:12px; font-weight:800; color:#e0e0f0; display:block; }
.ms-toggle-info small  { font-size:10px; color:#666; }

.ms-genjutsu-box {
  background:rgba(255,193,7,.05); border:1px dashed rgba(255,193,7,.35);
  border-radius:12px; padding:14px 16px; margin-bottom:14px;
}
.ms-genjutsu-label { font-size:11px; font-weight:800; color:#ffc107; margin-bottom:10px; display:flex; align-items:center; gap:6px; }

/* ── FORM ACTIONS ── */
.ms-form-actions { display:flex; gap:10px; padding-top:20px; border-top:1px solid rgba(255,255,255,.08); margin-top:8px; }

/* ── BUTTONS ── */
.ms-btn-primary   { display:inline-flex; align-items:center; gap:6px; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:800; cursor:pointer; background:var(--brand); color:#fff; border:none; transition:all .15s; box-shadow:0 4px 12px rgba(255,107,53,.3); }
.ms-btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(255,107,53,.4); }
.ms-btn-secondary { display:inline-flex; align-items:center; gap:6px; padding:9px 20px; border-radius:10px; font-size:13px; font-weight:800; cursor:pointer; background:rgba(255,255,255,.07); color:#ccc; border:1px solid rgba(255,255,255,.12); text-decoration:none; transition:all .15s; }
.ms-btn-secondary:hover { background:rgba(255,255,255,.12); color:#fff; }
.ms-btn-info      { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:10px; font-size:13px; font-weight:800; cursor:pointer; background:rgba(78,155,255,.12); color:#4E9BFF; border:1px solid rgba(78,155,255,.3); text-decoration:none; transition:all .15s; }
.ms-btn-info:hover { background:rgba(78,155,255,.2); }

/* ── ALERT ── */
.ms-alert { display:flex; align-items:flex-start; gap:10px; padding:12px 16px; border-radius:10px; margin-bottom:20px; font-size:13px; font-weight:600; }
.ms-alert--success { background:rgba(76,175,130,.1); border:1px solid rgba(76,175,130,.3); color:#4CAF82; }
.ms-alert--error   { background:rgba(244,78,59,.1);  border:1px solid rgba(244,78,59,.3);  color:#F44E3B; }

/* ── DELETE INLINE CONFIRM ── */
.ms-delete-confirm {
  background:rgba(239,68,68,.06); border:1px solid rgba(239,68,68,.25);
  border-radius:12px; padding:20px; margin-top:12px; display:none;
}
.ms-delete-confirm.open { display:block; }

/* ── PERF TABLE ── */
.ms-perf-row { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:10px; padding:12px 14px; margin-bottom:10px; }
.ms-perf-name { font-weight:800; font-size:13px; color:#e0e0f0; margin-bottom:10px; }

@media (max-width:600px) {
  .ms-field-row.col2, .ms-field-row.col3 { grid-template-columns:1fr; }
  .ms-stats { grid-template-columns:1fr 1fr; }
}
</style>

<?php /* ── REUSABLE FORM FIELDS FUNCTION ── */ ?>
<?php function render_membership_fields(array $d = []): void {
    $v = fn($k,$def='') => $d[$k] ?? $def;
?>

<div class="ms-section-label">📦 Info Dasar</div>
<div class="ms-field-row col2">
  <div class="ms-field">
    <label>Nama Paket</label>
    <input type="text" name="name" value="<?= htmlspecialchars((string)$v('name')) ?>" placeholder="Contoh: Gold" required>
  </div>
  <div class="ms-field">
    <label>Icon (Emoji)</label>
    <input type="text" name="icon" value="<?= htmlspecialchars((string)$v('icon','⭐')) ?>" required>
  </div>
</div>

<div class="ms-section-label">💰 Harga</div>
<div class="ms-field-row col2">
  <div class="ms-field">
    <label>Harga Jual (Rp)</label>
    <input type="number" name="price" value="<?= (float)$v('price',0) ?>" min="0" step="1">
  </div>
  <div class="ms-field">
    <label>Harga Coret/Asli (Rp)</label>
    <input type="number" name="original_price" value="<?= (float)$v('original_price',0) ?>" min="0" step="1">
  </div>
</div>

<div class="ms-genjutsu-box">
  <div class="ms-genjutsu-label">👁️ Genjutsu Pricing & Rules</div>
  <div class="ms-field-row col3">
    <div class="ms-field">
      <label style="color:#ffc107">Aktifkan Genjutsu Price</label>
      <label class="ms-toggle" style="cursor:pointer">
        <input type="checkbox" name="is_genjutsu" <?= $v('is_genjutsu') ? 'checked' : '' ?>>
        <div class="ms-toggle-info">
          <strong>Genjutsu Mode</strong>
          <small>Harga berubah jika saldo beli user cukup</small>
        </div>
      </label>
    </div>
    <div class="ms-field">
      <label style="color:#ffc107">Aktifkan Genjutsu Hilang</label>
      <label class="ms-toggle" style="cursor:pointer">
        <input type="checkbox" name="is_genjutsu_hilang" <?= $v('is_genjutsu_hilang') ? 'checked' : '' ?>>
        <div class="ms-toggle-info">
          <strong>Genjutsu Hilang</strong>
          <small>Paket hilang jika saldo beli user cukup</small>
        </div>
      </label>
    </div>
    <div class="ms-field">
      <label style="color:#ffc107">Harga Genjutsu (Rp)</label>
      <input type="number" name="price_genjutsu" value="<?= (float)$v('price_genjutsu',0) ?>" min="0" step="1">
    </div>
  </div>
</div>

<div class="ms-section-label">⚙️ Pengaturan Paket</div>
<div class="ms-field-row col3">
  <div class="ms-field">
    <label>Limit Tonton/Hari</label>
    <input type="number" name="watch_limit" value="<?= (int)$v('watch_limit',10) ?>" min="1">
  </div>
  <div class="ms-field">
    <label>Durasi (hari)</label>
    <input type="number" name="duration_days" value="<?= (int)$v('duration_days',30) ?>" min="1">
  </div>
  <div class="ms-field">
    <label>Urutan Tampil</label>
    <input type="number" name="sort_order" value="<?= (int)$v('sort_order',0) ?>">
  </div>
</div>

<div class="ms-section-label">💸 Withdraw</div>
<div class="ms-field-row col2">
  <div class="ms-field">
    <label>Min. Withdraw (Rp)</label>
    <input type="number" name="min_wd" value="<?= (float)$v('min_wd',50000) ?>" min="0" step="1">
  </div>
  <div class="ms-field">
    <label>Max. Withdraw (Rp)</label>
    <input type="number" name="max_wd" value="<?= (float)$v('max_wd',0) ?>" min="0" step="1">
    <small>0 = Tanpa batas maksimum</small>
  </div>
</div>

<div class="ms-section-label">🔘 Opsi Tambahan</div>
<div class="ms-toggle-group">
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="wd_hold" <?= $v('wd_hold') ? 'checked' : '' ?>>
    <div class="ms-toggle-info">
      <strong>⏸ Tahan Withdraw (Auto Hold)</strong>
      <small>WD dari user level ini otomatis di-hold</small>
    </div>
  </label>
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="allow_edit_bank" <?= $v('allow_edit_bank') ? 'checked' : '' ?>>
    <div class="ms-toggle-info">
      <strong>✏️ Izinkan Edit Rekening Bank</strong>
      <small>User level ini bisa ubah data rekening bank</small>
    </div>
  </label>
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="is_active" <?= ($v('is_active',1) || empty($d)) ? 'checked' : '' ?>>
    <div class="ms-toggle-info">
      <strong>✅ Paket Aktif</strong>
      <small>Paket tampil di halaman upgrade user</small>
    </div>
  </label>
</div>

<div class="ms-section-label">📝 Deskripsi</div>
<div class="ms-field">
  <label>Deskripsi Singkat (opsional)</label>
  <textarea name="description" rows="3" placeholder="Keterangan tambahan..."><?= htmlspecialchars((string)$v('description')) ?></textarea>
</div>
<?php } ?>

<!-- PAGE HEADER -->
<div class="ms-page-header">
  <div class="ms-page-title">⭐ Paket Membership</div>
  <div class="ms-page-actions">
    <a href="memberships.php?perf=1" class="ms-btn-info">📊 Setting Kinerja WD</a>
    <a href="memberships.php?add=1" class="ms-btn-primary">＋ Tambah Paket</a>
  </div>
</div>

<!-- FLASH ALERT -->
<?php if ($flash): ?>
<div class="ms-alert ms-alert--<?= $flashType==='error'?'error':'success' ?>">
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
    <div class="ms-stat__sub">semua order confirmed</div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════ -->
<!-- INLINE ADD FORM -->
<!-- ═══════════════════════════════════════════════ -->
<?php if ($show_add): ?>
<div class="ms-inline-panel ms-inline-panel--add">
  <div class="ms-panel-title">
    ＋ Tambah Paket Baru
    <a href="memberships.php">✕ Tutup</a>
  </div>
  <form method="POST">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <?php render_membership_fields(); ?>
    <div class="ms-form-actions">
      <button type="submit" class="ms-btn-primary">💾 Simpan Paket</button>
      <a href="memberships.php" class="ms-btn-secondary">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════ -->
<!-- INLINE EDIT FORM -->
<!-- ═══════════════════════════════════════════════ -->
<?php if ($edit_plan): ?>
<div class="ms-inline-panel ms-inline-panel--edit">
  <div class="ms-panel-title">
    ✏️ Edit Paket: <?= htmlspecialchars($edit_plan['name']) ?>
    <a href="memberships.php">✕ Tutup</a>
  </div>
  <form method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="id" value="<?= $edit_plan['id'] ?>">
    <?php render_membership_fields($edit_plan); ?>
    <div class="ms-form-actions">
      <button type="submit" class="ms-btn-primary">💾 Update Paket</button>
      <a href="memberships.php" class="ms-btn-secondary">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════ -->
<!-- INLINE PERF FORM -->
<!-- ═══════════════════════════════════════════════ -->
<?php if ($show_perf): ?>
<div class="ms-inline-panel">
  <div class="ms-panel-title">
    📊 Setting Kinerja WD Per Level
    <a href="memberships.php">✕ Tutup</a>
  </div>
  <p style="font-size:12px;color:#888;margin-bottom:20px;line-height:1.6">Atur rata-rata keberhasilan/uptime WD yang ditampilkan kepada user. Aktifkan <strong>Down If Own</strong> agar kinerjanya tampak rendah saat diakses pemilik level tersebut.</p>
  <form method="POST">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_level_perf">
    <?php foreach ($plans as $m): ?>
    <div class="ms-perf-row">
      <div class="ms-perf-name"><?= html_entity_decode($m['icon'], ENT_HTML5, 'UTF-8') ?> <?= htmlspecialchars($m['name']) ?></div>
      <div class="ms-field-row col3">
        <div class="ms-field">
          <label>AVG Kinerja (%)</label>
          <input type="number" step="0.01" name="perf[<?= $m['id'] ?>][avg]" value="<?= (float)($m['perf_avg']??99.8) ?>" min="0" max="100">
        </div>
        <label class="ms-toggle" style="cursor:pointer;margin-top:20px">
          <input type="checkbox" name="perf[<?= $m['id'] ?>][down]" value="1" <?= !empty($m['perf_down_if_own'])?'checked':'' ?>>
          <div class="ms-toggle-info"><strong style="color:#ffc107">Down If Own</strong></div>
        </label>
        <label class="ms-toggle" style="cursor:pointer;margin-top:20px">
          <input type="checkbox" name="perf[<?= $m['id'] ?>][disabled]" value="1" <?= !empty($m['is_wd_disabled'])?'checked':'' ?>>
          <div class="ms-toggle-info"><strong style="color:#ef4444">Tutup WD</strong></div>
        </label>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="ms-form-actions">
      <button type="submit" class="ms-btn-primary">💾 Simpan Kinerja</button>
      <a href="memberships.php" class="ms-btn-secondary">Batal</a>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════ -->
<!-- TAB NAV + PLANS TABLE -->
<!-- ═══════════════════════════════════════════════ -->
<nav class="stab-nav">
  <button class="stab-link active" data-tab="pakets">⭐ Daftar Paket</button>
  <button class="stab-link" data-tab="orders">📦 User per Paket</button>
</nav>

<div class="stab-pane active" id="tab-pakets">
  <div class="ms-table-wrap">
    <table class="ms-table">
      <thead>
        <tr>
          <th>Paket</th>
          <th>Harga</th>
          <th>Durasi</th>
          <th>WD Min / Max</th>
          <th>Limit</th>
          <th>Flags</th>
          <th>User Aktif</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($plans as $p):
          $ucount = (int)$pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()")->execute([$p['id']]) ?: 0;
          $cnt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()");
          $cnt->execute([$p['id']]); $ucount = (int)$cnt->fetchColumn();
          $is_editing = ($edit_id === (int)$p['id']);
        ?>
        <tr style="<?= $is_editing ? 'background:rgba(78,155,255,.06)' : '' ?>">
          <td>
            <div class="ms-plan-badge">
              <div class="ms-plan-icon"><?= html_entity_decode($p['icon']?:'⭐', ENT_HTML5, 'UTF-8') ?></div>
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
              <div class="ms-price-genjutsu">👁️ Price: <?= format_rp((float)$p['price_genjutsu']) ?></div>
              <?php endif; ?>
              <?php if ($p['is_genjutsu_hilang']): ?>
              <div class="ms-price-genjutsu" style="background:rgba(244,78,59,.1);color:#f44e3b;border-color:rgba(244,78,59,.25)">👁️ Hilang</div>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="color:#aaa;font-size:12px;font-weight:700"><?= $p['duration_days'] ?> hari</td>
          <td style="font-size:12px">
            <div style="color:#4CAF82;font-weight:700">Min: <?= format_rp((float)$p['min_wd']) ?></div>
            <div style="color:#aaa;font-weight:600">Max: <?= (float)$p['max_wd']>0 ? format_rp((float)$p['max_wd']) : '<em style="color:#555">Bebas</em>' ?></div>
          </td>
          <td style="font-weight:700"><?= $p['watch_limit'] ?>×/hari</td>
          <td>
            <div style="display:flex;flex-wrap:wrap;gap:4px">
              <span class="ms-badge <?= $p['is_active']?'ms-badge--on':'ms-badge--off' ?>"><?= $p['is_active']?'Aktif':'Nonaktif' ?></span>
              <?php if ($p['wd_hold']): ?><span class="ms-badge ms-badge--hold">⏸ Hold</span><?php endif; ?>
              <?php if ($p['is_wd_disabled']): ?><span class="ms-badge ms-badge--nowd">WD Off</span><?php endif; ?>
              <?php if ($p['allow_edit_bank']): ?><span class="ms-badge" style="background:rgba(78,155,255,.12);color:#4E9BFF;border:1px solid rgba(78,155,255,.3)">✏️ Rek</span><?php endif; ?>
            </div>
          </td>
          <td><span class="ms-user-count">👥 <?= $ucount ?></span></td>
          <td style="text-align:right">
            <div style="display:flex;gap:6px;justify-content:flex-end">
              <a href="memberships.php?edit=<?= $p['id'] ?>" class="ms-btn-icon <?= $is_editing?'ms-btn-icon--active':'' ?>" title="Edit">✏️</a>
              <?php if ((float)$p['price'] > 0): ?>
              <button class="ms-btn-icon ms-btn-icon--danger" title="Hapus" onclick="showDelete(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">🗑️</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- INLINE DELETE CONFIRM (below table) -->
  <div class="ms-delete-confirm" id="delete-box">
    <form method="POST" id="delete-form">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="del-id">
      <div style="font-size:15px;font-weight:800;color:#ef4444;margin-bottom:6px">🗑️ Hapus Paket: <span id="del-name" style="color:#fff"></span>?</div>
      <div style="font-size:12px;color:#888;margin-bottom:14px">Aksi ini tidak bisa dibatalkan.</div>
      <label class="ms-toggle" style="cursor:pointer;margin-bottom:14px;border-color:rgba(239,68,68,.25)">
        <input type="checkbox" name="force" id="del-force" value="1">
        <div class="ms-toggle-info">
          <strong style="color:#ffc107">Hapus Paksa</strong>
          <small>Centang jika ada user/riwayat terkait — user dikembalikan ke <?= htmlspecialchars(get_free_tier_name($pdo)) ?></small>
        </div>
      </label>
      <div style="display:flex;gap:10px">
        <button type="submit" style="background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;border-radius:10px;padding:9px 20px;font-size:13px;font-weight:800;cursor:pointer">Ya, Hapus</button>
        <button type="button" class="ms-btn-secondary" onclick="hideDelete()">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- TAB: USER PER PLAN -->
<div class="stab-pane" id="tab-orders">
  <div class="row g-3">
    <?php foreach ($plans as $p):
      if ((float)$p['price'] === 0.0) continue;
      $us = $pdo->prepare("SELECT username, membership_expires_at FROM users WHERE membership_id=? AND membership_expires_at>NOW() ORDER BY membership_expires_at DESC LIMIT 20");
      $us->execute([$p['id']]); $usersInPlan = $us->fetchAll();
      $cc = $pdo->prepare("SELECT COUNT(*) FROM users WHERE membership_id=? AND membership_expires_at>NOW()");
      $cc->execute([$p['id']]); $planUserCount = (int)$cc->fetchColumn();
    ?>
    <div class="col-md-6">
      <div class="c-card">
        <div class="c-card-header" style="display:flex;align-items:center;justify-content:space-between">
          <span class="c-card-title"><?= html_entity_decode($p['icon'], ENT_HTML5, 'UTF-8') ?> <?= htmlspecialchars($p['name']) ?></span>
          <span class="ms-user-count">👥 <?= $planUserCount ?> aktif</span>
        </div>
        <div class="c-card-body" style="padding:12px 16px">
          <?php if (empty($usersInPlan)): ?>
            <p style="font-size:12px;color:#555;margin:0">Belum ada user aktif di paket ini.</p>
          <?php else: ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($usersInPlan as $u): ?>
            <span style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;color:#ccc" title="s/d <?= date('d M Y', strtotime($u['membership_expires_at'])) ?>"><?= htmlspecialchars($u['username']) ?></span>
            <?php endforeach; ?>
            <?php if ($planUserCount > 20): ?><span style="background:rgba(255,107,53,.1);border:1px solid rgba(255,107,53,.3);border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;color:#FF6B35">+<?= $planUserCount-20 ?> lainnya</span><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
/* Tab */
document.querySelectorAll('.stab-link').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.stab-link').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.stab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
  });
});

/* Delete inline confirm */
function showDelete(id, name) {
  document.getElementById('del-id').value = id;
  document.getElementById('del-name').textContent = name;
  document.getElementById('del-force').checked = false;
  const box = document.getElementById('delete-box');
  box.classList.add('open');
  box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
function hideDelete() {
  document.getElementById('delete-box').classList.remove('open');
}

/* Auto-scroll to inline panel */
<?php if ($edit_plan || $show_add || $show_perf): ?>
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('.ms-inline-panel');
  if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php endif; ?>
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
