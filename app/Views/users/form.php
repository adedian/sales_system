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

                <?php if (!$isSelf): ?>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="role_id">Role</label>
                    <select class="form-select" id="role_id" name="role_id" required>
                        <option value="">Pilih role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['id'] ?>" <?= (int) ($targetUser['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isEdit): ?>
                <div class="col-12 col-md-6 d-flex align-items-end">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) $targetUser['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Akun aktif</label>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <div class="col-12">
                    <hr>
                    <label class="form-label" for="password"><?= $isEdit ? 'Reset Password (opsional)' : 'Password' ?></label>
                    <input type="password" class="form-control" id="password" name="password" minlength="8" <?= $isEdit ? 'placeholder="Kosongkan jika tidak ingin mengubah"' : 'required' ?>>
                    <div class="form-text">Minimal 8 karakter. <?= $isEdit ? 'Mengisi field ini akan memaksa pengguna mengganti password saat login berikutnya.' : 'Pengguna wajib mengganti password saat login pertama.' ?></div>
                </div>

                <div class="col-12">
                    <hr>
                    <label class="form-label mb-0">Fungsi Operasional</label>
                    <div class="form-text mt-0 mb-2">Fungsi tambahan di luar role login — independen dari Role, dan satu pengguna boleh punya lebih dari satu fungsi.</div>
                    <?php
                        $flagOptions = [
                            'is_sales' => 'Sales',
                            'is_estimator' => 'Estimator',
                            'is_surveyor' => 'Surveyor',
                            'is_engineer' => 'Engineer',
                            'is_sales_engineer' => 'Sales Engineer',
                            'is_director' => 'Direktur / Price Validator',
                        ];
                    ?>
                    <div class="row g-2">
                        <?php foreach ($flagOptions as $flag => $label): ?>
                        <div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="<?= e($flag) ?>" name="<?= e($flag) ?>" value="1" <?= !empty($targetUser[$flag]) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= e($flag) ?>"><?= e($label) ?></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Pengguna' ?></button>
                <a href="<?= url('/users') ?>" class="btn btn-light">Batal</a>
            </div>
        </form>
    </div>
</div>
