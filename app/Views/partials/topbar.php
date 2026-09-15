<?php $user = auth_user(); ?>
<header class="app-topbar">
    <button type="button" class="topbar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
        <?php if (!empty($breadcrumb ?? [])): ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <?php foreach ($breadcrumb as $label => $link): ?>
                    <?php if (is_string($label)): ?>
                        <li class="breadcrumb-item"><a href="<?= e($link) ?>"><?= e($label) ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($link) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>
    </div>

    <div class="topbar-actions">
        <div class="dropdown">
            <button type="button" class="topbar-icon-btn" title="Notifikasi" data-bs-toggle="dropdown" aria-expanded="false" id="notifBell">
                <i class="bi bi-bell"></i>
                <span class="topbar-badge d-none" id="notifBadge"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow-sm notif-panel" aria-labelledby="notifBell">
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

        <div class="dropdown">
            <button class="topbar-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="topbar-user-avatar"><?= e(strtoupper(substr($user['name'] ?? '?', 0, 1))) ?></span>
                <span class="topbar-user-info d-none d-md-flex">
                    <strong><?= e($user['name'] ?? '-') ?></strong>
                    <small><?= e($user['role_name'] ?? '-') ?></small>
                </span>
                <i class="bi bi-chevron-down d-none d-md-inline"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
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
</header>
