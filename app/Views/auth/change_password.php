<?php $pageTitle = 'Ganti Password'; ?>
<div class="page-header">
    <h2>Ganti Password</h2>
    <p class="text-muted">
        <?= !empty($forced)
            ? 'Anda wajib mengganti password default sebelum melanjutkan.'
            : 'Perbarui password akun Anda secara berkala demi keamanan.' ?>
    </p>
</div>

<div class="card card-elevated" style="max-width: 480px;">
    <div class="card-body">
        <form method="POST" action="<?= url('/change-password') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="current_password" class="form-label">Password Saat Ini</label>
                <input type="password" class="form-control" id="current_password" name="current_password" required>
            </div>

            <div class="mb-3">
                <label for="new_password" class="form-label">Password Baru</label>
                <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                <div class="form-text">Minimal 8 karakter.</div>
            </div>

            <div class="mb-3">
                <label for="new_password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary">Simpan Password</button>
            <?php if (empty($forced)): ?>
                <a href="<?= url('/dashboard') ?>" class="btn btn-light">Batal</a>
            <?php endif; ?>
        </form>
    </div>
</div>
