<?php $pageTitle = 'Profil Saya'; ?>
<div class="page-header">
    <h2>Profil Saya</h2>
    <p class="text-muted">Informasi akun dan preferensi login Anda.</p>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card card-elevated">
            <div class="card-header"><h3>Data Diri</h3></div>
            <div class="card-body">
                <form method="POST" action="<?= url('/profile') ?>" novalidate>
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">Nama Lengkap</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= e($user['username']) ?>" disabled>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="phone">Telepon</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= e($user['phone']) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= e($user['role_name'] ?? '-') ?>" disabled>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Login Terakhir</label>
                            <input type="text" class="form-control" value="<?= e(format_datetime($user['last_login_at'])) ?>" disabled>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card card-elevated">
            <div class="card-header"><h3>Keamanan</h3></div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13.5px;">Perbarui password secara berkala. Anda akan diminta login ulang di perangkat lain setelah menggantinya.</p>
                <a href="<?= url('/change-password') ?>" class="btn btn-light"><i class="bi bi-key me-2"></i>Ganti Password</a>
            </div>
        </div>
    </div>
</div>
