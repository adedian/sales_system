<?php
$isEdit = $product !== null;
$pageTitle = $isEdit ? 'Ubah Produk' : 'Tambah Produk';
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Produk' : 'Tambah Produk' ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data produk ' . e($product['name']) . '.' : 'Tambahkan produk baru ke katalog.' ?></p>
</div>

<div class="card card-elevated" style="max-width:640px;">
    <div class="card-body">
        <form method="POST" action="<?= $isEdit ? url('/products/' . $product['id']) : url('/products') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <?php if ($isEdit): ?>
                <div class="col-12">
                    <label class="form-label">Kode Produk</label>
                    <input type="text" class="form-control" value="<?= e($product['product_code']) ?>" readonly>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label" for="name">Nama Produk</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($product['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="category_id">Kategori</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">- Pilih Kategori -</option>
                        <?php foreach ($categories as $code => $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (int) ($product['category_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="unit_id">Satuan</label>
                    <select class="form-select" id="unit_id" name="unit_id">
                        <option value="">- Pilih Satuan -</option>
                        <?php foreach ($units as $code => $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (int) ($product['unit_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="default_price">Harga Default (Rp)</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="default_price" name="default_price" value="<?= e($product['default_price'] ?? old('default_price')) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi / Spesifikasi</label>
                    <textarea class="form-control" id="description" name="description" rows="2"><?= e($product['description'] ?? old('description')) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Produk' ?></button>
                <a href="<?= url('/products') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
