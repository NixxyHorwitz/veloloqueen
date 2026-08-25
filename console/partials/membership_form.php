<?php /* console/partials/membership_form.php — used in Add drawer */ ?>

<div class="ms-section-label"><i class="ph-bold ph-tag"></i> Info Dasar</div>
<div class="ms-field-row col2" style="margin-bottom:14px">
  <div class="ms-field">
    <label>Nama Paket</label>
    <input type="text" name="name" placeholder="Contoh: Gold" required>
  </div>
  <div class="ms-field">
    <label>Icon (Emoji)</label>
    <input type="text" name="icon" value="⭐" required>
  </div>
</div>

<div class="ms-section-label"><i class="ph-bold ph-currency-circle-dollar"></i> Harga</div>
<div class="ms-field-row col2" style="margin-bottom:14px">
  <div class="ms-field">
    <label>Harga Jual (Rp)</label>
    <input type="number" name="price" value="0" min="0" step="1">
  </div>
  <div class="ms-field">
    <label>Harga Asli/Coret (Rp)</label>
    <input type="number" name="original_price" value="0" min="0" step="1">
  </div>
</div>

<div class="ms-genjutsu-box">
  <div class="ms-genjutsu-label">👁️ Genjutsu Pricing</div>
  <div class="ms-field-row col2">
    <div class="ms-field">
      <label style="color:#ffc107">Aktifkan Genjutsu</label>
      <label class="ms-toggle" style="cursor:pointer">
        <input type="checkbox" name="is_genjutsu">
        <div class="ms-toggle-info">
          <strong>Genjutsu Mode</strong>
          <small>Harga berubah jika saldo beli user cukup</small>
        </div>
      </label>
    </div>
    <div class="ms-field">
      <label style="color:#ffc107">Harga Genjutsu (Rp)</label>
      <input type="number" name="price_genjutsu" value="0" min="0" step="1">
    </div>
  </div>
</div>

<div class="ms-section-label"><i class="ph-bold ph-sliders"></i> Pengaturan Paket</div>
<div class="ms-field-row col3" style="margin-bottom:14px">
  <div class="ms-field">
    <label>Limit Tonton/Hari</label>
    <input type="number" name="watch_limit" value="10" min="1">
  </div>
  <div class="ms-field">
    <label>Durasi (hari)</label>
    <input type="number" name="duration_days" value="30" min="1">
  </div>
  <div class="ms-field">
    <label>Urutan Tampil</label>
    <input type="number" name="sort_order" value="0">
  </div>
</div>

<div class="ms-section-label"><i class="ph-bold ph-arrows-out-line-horizontal"></i> Withdraw</div>
<div class="ms-field-row col2" style="margin-bottom:14px">
  <div class="ms-field">
    <label>Min. Withdraw (Rp)</label>
    <input type="number" name="min_wd" value="50000" min="0" step="1">
  </div>
  <div class="ms-field">
    <label>Max. Withdraw (Rp)</label>
    <input type="number" name="max_wd" value="0" min="0" step="1">
    <small>0 = Tanpa batas maksimum</small>
  </div>
</div>

<div class="ms-section-label"><i class="ph-bold ph-toggle-right"></i> Opsi Tambahan</div>
<div class="ms-toggle-group" style="margin-bottom:14px">
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="wd_hold">
    <div class="ms-toggle-info">
      <strong>⏸ Tahan Withdraw (Auto Hold)</strong>
      <small>WD dari user level ini otomatis di-hold</small>
    </div>
  </label>
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="allow_edit_bank">
    <div class="ms-toggle-info">
      <strong>✏️ Izinkan Edit Rekening Bank</strong>
      <small>User level ini bisa ubah data rekening bank</small>
    </div>
  </label>
  <label class="ms-toggle" style="cursor:pointer">
    <input type="checkbox" name="is_active" checked>
    <div class="ms-toggle-info">
      <strong>✅ Paket Aktif</strong>
      <small>Paket tampil di halaman upgrade user</small>
    </div>
  </label>
</div>

<div class="ms-section-label"><i class="ph-bold ph-note"></i> Deskripsi</div>
<div class="ms-field" style="margin-bottom:4px">
  <label>Deskripsi Singkat (opsional)</label>
  <textarea name="description" rows="3" placeholder="Keterangan tambahan tentang paket ini..."></textarea>
</div>
