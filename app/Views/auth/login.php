<div class="auth-wrapper">
    <div class="auth-split">
        <div class="auth-brand-panel">
            <div class="auth-brand-mark"><img src="<?= asset('img/logo.png') ?>" alt="HME Sales"></div>
            <h1>Sistem Internal Sales</h1>
            <p>Platform manajemen sales internal &mdash; dari lead masuk hingga ditangani tim Engineer Sales.</p>
            <ul class="auth-feature-list">
                <li><i class="bi bi-shield-check"></i>Akses berbasis role &amp; permission</li>
                <li><i class="bi bi-clock-history"></i>Audit trail untuk setiap aktivitas</li>
                <li><i class="bi bi-broadcast"></i>Data terupdate secara realtime</li>
            </ul>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-inner">
                <div class="auth-brand-mobile">
                    <span class="auth-brand-mark"><img src="<?= asset('img/logo.png') ?>" alt="HME Sales"></span>
                    <div>
                        <strong>Sistem Internal Sales</strong>
                        <small>Masuk ke akun Anda</small>
                    </div>
                </div>

                <h2 class="auth-form-title">Selamat Datang Kembali</h2>
                <p class="auth-form-sub">Masukkan kredensial internal Anda untuk melanjutkan.</p>

                <?php include __DIR__ . '/../partials/flash.php'; ?>

                <form method="POST" action="<?= url('/login') ?>" class="auth-form" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="username" name="username" value="<?= e(old('username')) ?>" autofocus required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Ingat saya di perangkat ini selama 30 hari</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 auth-submit">Masuk</button>
                </form>

                <p class="auth-footnote">Akses internal perusahaan. Hubungi administrator jika mengalami kendala login.</p>
            </div>
        </div>
    </div>
</div>
