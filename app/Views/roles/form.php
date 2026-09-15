<?php
$isEdit = $role !== null;
$pageTitle = $isEdit ? 'Ubah Role' : 'Tambah Role';
$isSystem = $isEdit && (int) $role['is_system'] === 1;
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Role' : 'Tambah Role' ?></h2>
    <p class="text-muted">Permission bersifat tetap (didefinisikan di kode) &mdash; yang bisa diatur di sini adalah kombinasi mana saja yang melekat pada role ini.</p>
</div>

<div class="card card-elevated" style="max-width:760px;">
    <div class="card-body">
        <form method="POST" action="<?= $isEdit ? url('/roles/' . $role['id']) : url('/roles') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3 mb-2">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Nama Role</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($role['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="description">Deskripsi</label>
                    <input type="text" class="form-control" id="description" name="description" value="<?= e($role['description'] ?? old('description')) ?>">
                </div>
            </div>

            <hr>
            <label class="form-label d-block mb-2">Permission</label>
            <div class="permission-grid">
                <?php foreach ($permissionGroups as $module => $permissions): ?>
                <div class="permission-group">
                    <div class="permission-group-label"><?= e(ucfirst($module)) ?></div>
                    <?php foreach ($permissions as $slug => $description): ?>
                    <label class="permission-check">
                        <input type="checkbox" name="permissions[]" value="<?= e($slug) ?>" <?= in_array($slug, $assignedSlugs, true) ? 'checked' : '' ?>>
                        <span>
                            <code><?= e($slug) ?></code>
                            <small><?= e($description) ?></small>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Role' ?></button>
                <a href="<?= url('/roles') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
