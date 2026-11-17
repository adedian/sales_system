<?php
$isEdit = $targetUser !== null;
$pageTitle = $isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna';
$isSelf = $isEdit && (int) $targetUser['id'] === auth_user()['id'];
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data akun ' . e($targetUser['name']) . '.' : 'Buat akun baru untuk tim internal.' ?></p>
</div>

<div class="card card-elevated" style="max-width:640px;">
    <div class="card-body">
        <?php if ($isSelf): ?>
            <div class="alert alert-warning">Anda sedang mengubah akun sendiri &mdash; role dan status aktif tidak dapat diubah dari sini.</div>
        <?php endif; ?>

        <form method="POST" action="<?= $isEdit ? url('/users/' . $targetUser['id']) : url('/users') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Nama Lengkap</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?= e($targetUser['name'] ?? old('name')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?= e($targetUser['username'] ?? old('username')) ?>" <?= $isEdit ? '' : 'required' ?> <?= $isEdit ? 'readonly' : '' ?>>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?= e($targetUser['email'] ?? old('email')) ?>" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="phone">Telepon</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?= e($targetUser['phone'] ?? old('phone')) ?>">
                </div>

                <?php
                    $currentRoleIds = array_map('intval', (array) ($targetUser['role_ids'] ?? []));
                    $oldRoleIds = old('role_ids');
                    if (is_array($oldRoleIds)) { $currentRoleIds = array_map('intval', $oldRoleIds); }
                ?>
                <div class="col-12">
                    <label class="form-label mb-0">Role</label>
                    <div class="form-text mt-0 mb-2">Satu pengguna boleh memiliki beberapa role. Menu dan hak akses adalah gabungan seluruh role yang dipilih.</div>
                    <?php if (!empty($isSelf)): ?>
                        <div>
                            <?php foreach ((array) ($targetUser['role_names'] ?? []) as $rn): ?><span class="badge-pill me-1"><?= e($rn) ?></span><?php endforeach; ?>
                        </div>
                        <div class="form-text">Role akun sendiri tidak dapat diubah di sini.</div>
                    <?php else: ?>
                    <div class="row g-2">
                        <?php foreach ($roles as $role): ?>
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="role_<?= (int) $role['id'] ?>" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $currentRoleIds, true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="role_<?= (int) $role['id'] ?>"><?= e($role['name']) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($isEdit && empty($isSelf)): ?>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) $targetUser['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Akun aktif</label>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <hr>
                    <label class="form-label" for="password"><?= $isEdit ? 'Reset Password (opsional)' : 'Password' ?></label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" <?= $isEdit ? 'placeholder="Kosongkan jika tidak ingin mengubah"' : 'required' ?>>
                    <div class="form-text">Minimal 8 karakter. <?= $isEdit ? 'Mengisi field ini akan memaksa pengguna mengganti password saat login berikutnya.' : 'Pengguna wajib mengganti password saat login pertama.' ?></div>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Pengguna' ?></button>
                <a href="<?= url('/users') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
