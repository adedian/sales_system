<?php
$isEdit = $lead !== null;
$val = fn (string $field, $default = '') => $isEdit ? ($lead[$field] ?? $default) : old($field, $default);
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Lead' : 'Tambah Lead' ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data ' . e($lead['customer_name']) . ' (' . e($lead['lead_code']) . ').' : 'Catat lead baru yang masuk.' ?></p>
</div>

<form method="POST" action="<?= $isEdit ? url('/leads/' . $lead['id']) : url('/leads') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="detail-section">
        <h3 class="detail-section-title">Informasi Customer</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="customer_name">Nama Customer</label>
                <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= e($val('customer_name')) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="company_name">Perusahaan</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?= e($val('company_name')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="phone">Telepon</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($val('phone')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($val('email')) ?>">
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Klasifikasi Lead</h3>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="source_id">Sumber Lead</label>
                <select class="form-select" id="source_id" name="source_id">
                    <option value="">Pilih sumber</option>
                    <?php foreach ($sources as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('source_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="category_id">Kategori</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Pilih kategori</option>
                    <?php foreach ($categories as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('category_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="need_type_id">Jenis Kebutuhan</label>
                <select class="form-select" id="need_type_id" name="need_type_id">
                    <option value="">Pilih jenis kebutuhan</option>
                    <?php foreach ($needTypes as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('need_type_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="type_id">Type</label>
                <select class="form-select" id="type_id" name="type_id">
                    <option value="">Pilih tipe</option>
                    <?php foreach ($leadTypes as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('type_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="system_id">System</label>
                <select class="form-select" id="system_id" name="system_id">
                    <option value="">Pilih sistem</option>
                    <?php foreach ($leadSystems as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('system_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="funding_id">Funding</label>
                <select class="form-select" id="funding_id" name="funding_id">
                    <option value="">Pilih funding</option>
                    <?php foreach ($fundingSources as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('funding_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Teknis &amp; Kebutuhan</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="estimated_value">Estimasi Nilai (Rp)</label>
                <input type="number" step="0.01" class="form-control" id="estimated_value" name="estimated_value" value="<?= e($val('estimated_value')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="size_kwp">Size (KWp)</label>
                <input type="number" step="0.01" class="form-control" id="size_kwp" name="size_kwp" value="<?= e($val('size_kwp')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="needs_description">Deskripsi Kebutuhan</label>
                <textarea class="form-control" id="needs_description" name="needs_description" rows="2"><?= e($val('needs_description')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Data Awal (syarat Prelim)</h3>
        <p class="text-muted small mb-2">4 data ini wajib lengkap sebelum Prelim dapat dibuat: ID PLN, Tagihan Listrik, Model System (di atas), dan Lokasi (di bawah).</p>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="pln_id">ID PLN</label>
                <input type="text" class="form-control" id="pln_id" name="pln_id" value="<?= e($val('pln_id')) ?>" placeholder="Nomor ID Pelanggan PLN">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="electricity_bill">Tagihan Listrik (Rp/bulan)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="electricity_bill" name="electricity_bill" value="<?= e($val('electricity_bill')) ?>">
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Instalasi</h3>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="site_location">Site Location (Lokasi)</label>
                <input type="text" class="form-control" id="site_location" name="site_location" value="<?= e($val('site_location')) ?>" placeholder="Contoh: Sidoarjo">
            </div>
            <div class="col-12">
                <label class="form-label" for="address">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="2"><?= e($val('address')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Proses</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="priority">Prioritas</label>
                <select class="form-select" id="priority" name="priority" required>
                    <?php foreach ($priorities as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $val('priority', 'medium') === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="follow_up_date">Tanggal Follow Up</label>
                <input type="date" class="form-control" id="follow_up_date" name="follow_up_date" value="<?= e($val('follow_up_date')) ?>">
            </div>
            <?php if ($showSalesField && count($salesUsers)): ?>
            <div class="col-12">
                <label class="form-label">Sales</label>
                <div class="checkbox-list">
                    <?php foreach ($salesUsers as $row): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sales_<?= (int) $row['id'] ?>" name="sales_ids[]" value="<?= (int) $row['id'] ?>" <?= in_array((int) $row['id'], $assignedSalesIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sales_<?= (int) $row['id'] ?>"><?= e($row['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">Bisa pilih lebih dari satu. Yang teratas dalam daftar (sesuai urutan di atas) jadi Sales utama.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Catatan</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="notes">Catatan 1</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($val('notes')) ?></textarea>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="note2">Catatan 2</label>
                <textarea class="form-control" id="note2" name="note2" rows="2"><?= e($val('note2')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Lead' ?></button>
        <a href="<?= $isEdit ? url('/leads/' . $lead['id']) : url('/leads') ?>" class="btn btn-light">Batal</a>
    </div>
</form>
