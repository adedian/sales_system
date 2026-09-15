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
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card card-elevated h-100">
                <div class="card-header"><h3>Informasi Customer</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="customer_name">Nama Customer</label>
                            <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= e($val('customer_name')) ?>" required>
                        </div>
                        <div class="col-12">
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
                        <div class="col-12">
                            <label class="form-label" for="address">Alamat</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= e($val('address')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <div class="card card-elevated h-100">
                <div class="card-header"><h3>Detail Lead</h3></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="source_id">Sumber Lead</label>
                            <select class="form-select" id="source_id" name="source_id">
                                <option value="">Pilih sumber</option>
                                <?php foreach ($sources as $code => $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= (string) $val('source_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="category_id">Kategori</label>
                            <select class="form-select" id="category_id" name="category_id">
                                <option value="">Pilih kategori</option>
                                <?php foreach ($categories as $code => $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= (string) $val('category_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="need_type_id">Jenis Kebutuhan</label>
                            <select class="form-select" id="need_type_id" name="need_type_id">
                                <option value="">Pilih jenis kebutuhan</option>
                                <?php foreach ($needTypes as $code => $row): ?>
                                    <option value="<?= (int) $row['id'] ?>" <?= (string) $val('need_type_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="estimated_value">Estimasi Nilai (Rp)</label>
                            <input type="number" step="0.01" class="form-control" id="estimated_value" name="estimated_value" value="<?= e($val('estimated_value')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="needs_description">Deskripsi Kebutuhan</label>
                            <textarea class="form-control" id="needs_description" name="needs_description" rows="2"><?= e($val('needs_description')) ?></textarea>
                        </div>
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
                        <?php if (!$isEdit && $showSalesField && count($salesUsers)): ?>
                        <div class="col-12">
                            <label class="form-label" for="sales_id">Tugaskan ke Sales</label>
                            <select class="form-select" id="sales_id" name="sales_id">
                                <option value="">Belum ditugaskan</option>
                                <?php foreach ($salesUsers as $row): ?>
                                    <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label" for="notes">Catatan</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($val('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Lead' ?></button>
        <a href="<?= $isEdit ? url('/leads/' . $lead['id']) : url('/leads') ?>" class="btn btn-light">Batal</a>
    </div>
</form>
