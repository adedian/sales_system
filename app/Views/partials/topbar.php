<?php
use App\Core\Jobs;

$user = auth_user();
$initial = strtoupper(substr($user['name'] ?? '?', 0, 1));
?>
<header class="app-topbar">
    <div class="topbar-inner">
        <button type="button" class="topbar-toggle" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>

        <div class="topbar-title">
            <strong><?= e($pageTitle ?? 'Dashboard') ?></strong>
        </div>

        <div class="topbar-actions">
            <div class="icon-pill">
                <div class="dropdown">
                    <button type="button" class="topbar-icon-btn is-tinted" title="Notifikasi" data-bs-toggle="dropdown" aria-expanded="false" id="notifBell">
                        <i class="bi bi-bell"></i>
                        <span class="topbar-badge d-none" id="notifBadge"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notif-panel" aria-labelledby="notifBell">
                        <div class="notif-panel-header">
                            <span>Notifikasi</span>
                            <button type="button" class="btn btn-link btn-sm p-0" id="notifMarkAllRead">Tandai semua dibaca</button>
                        </div>
                        <div class="notif-panel-list" id="notifList">
                            <div class="empty-state py-4"><i class="bi bi-bell-slash"></i><p class="mb-0">Belum ada notifikasi.</p></div>
                        </div>
                        <a href="<?= url('/notifications') ?>" class="notif-panel-footer">Lihat Semua Notifikasi</a>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <button class="topbar-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="topbar-user-avatar"><?= e($initial) ?></span>
                    <span class="topbar-user-info d-none d-md-flex">
                        <strong><?= e($user['name'] ?? '-') ?></strong>
                        <small><?= e(Jobs::label($user)) ?></small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="margin-top:12px">
                    <li><h6 class="dropdown-header"><?= e($user['email'] ?? '') ?></h6></li>
                    <li><a class="dropdown-item" href="<?= url('/profile') ?>"><i class="bi bi-person me-2"></i>Profil Saya</a></li>
                    <li><a class="dropdown-item" href="<?= url('/change-password') ?>"><i class="bi bi-key me-2"></i>Ganti Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="<?= url('/logout') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Keluar</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
