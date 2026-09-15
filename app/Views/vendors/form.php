<?php
$isEdit = $vendor !== null;
$pageTitle = $isEdit ? 'Ubah Vendor' : 'Tambah Vendor';
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Vendor' : 'Tambah Vendor' ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data vendor ' . e($vendor['name']) . '.' : 'Daftarkan vendor/supplier baru.' ?></p>
</div>

<div class="card card-elevated" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="<?= $isEdit ? url('/vendors/' . $vendor['id']) : url('/vendors') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <?php if ($isEdit): ?>
                <div class="col-12">
                    <label class="form-label">Kode Vendor</label>
                    <input type="text" class="form-control" value="<?= e($vendor['vendor_code']) ?>" readonly>
                </div>
                <?php endif; ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Nama Vendor</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($vendor['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="category">Kategori</label>
                    <input type="text" class="form-control" id="category" name="category" placeholder="mis. Elektronik, Material, Jasa" value="<?= e($vendor['category'] ?? old('category')) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="contact_person">Kontak Person</label>
                    <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= e($vendor['contact_person'] ?? old('contact_person')) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="phone">Telepon</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($vendor['phone'] ?? old('phone')) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($vendor['email'] ?? old('email')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="address">Alamat</label>
                    <textarea class="form-control" id="address" name="address" rows="2"><?= e($vendor['address'] ?? old('address')) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Catatan</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($vendor['notes'] ?? old('notes')) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Vendor' ?></button>
                <a href="<?= url('/vendors') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
