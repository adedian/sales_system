<?php
$user = auth_user();
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$has = fn (string $needle) => str_contains($currentPath, $needle);
$isDirector = (int) ($user['is_director'] ?? 0) === 1;

/*
 * Struktur menu — satu sumber untuk navbar desktop dan menu mobile.
 * "Pipeline" berisi tahap kerja BERURUTAN (nomor = urutan alur proses lead),
 * jadi pengguna selalu tahu pekerjaan ini ada di tahap ke berapa.
 */
$pipelineSteps = array_values(array_filter([
    can('queue.view') ? ['n' => 1, 'href' => '/queue', 'match' => '/queue', 'title' => 'Antrian Sales', 'hint' => 'Survey & kelengkapan data awal', 'icon' => 'bi-list-ol'] : null,
    can('prelim.view') ? ['n' => 2, 'href' => '/prelims', 'match' => '/prelims', 'title' => 'Prelim', 'hint' => 'Penawaran awal ke client', 'icon' => 'bi-file-earmark-richtext'] : null,
    can('engineer.view') ? ['n' => 3, 'href' => '/engineer', 'match' => '/engineer', 'title' => 'Engineer Sales', 'hint' => 'Analisa teknis & BOQ', 'icon' => 'bi-tools'] : null,
    can('procurement.view') ? ['n' => 4, 'href' => '/procurement', 'match' => '/procurement', 'exclude' => '/procurement/validation', 'title' => 'Procurement', 'hint' => 'Harga & penawaran vendor', 'icon' => 'bi-truck'] : null,
    $isDirector ? ['n' => 5, 'href' => '/procurement/validation', 'match' => '/procurement/validation', 'title' => 'Validasi Harga', 'hint' => 'Persetujuan Direktur', 'icon' => 'bi-shield-check'] : null,
    can('proposal.view') ? ['n' => 6, 'href' => '/proposals', 'match' => '/proposals', 'title' => 'Proposal', 'hint' => 'Penawaran final ke client', 'icon' => 'bi-file-earmark-text'] : null,
]));

$adminGroups = [];
if (can('master_data.manage') || can('vendor.manage') || can('product.manage')) {
    $adminGroups['Data Referensi'] = array_values(array_filter([
        can('master_data.manage') ? ['href' => '/master-data', 'match' => '/master-data', 'title' => 'Master Data', 'hint' => 'Status, sumber, tipe, dll.', 'icon' => 'bi-database-gear'] : null,
        can('vendor.manage') ? ['href' => '/vendors', 'match' => '/vendors', 'title' => 'Vendor', 'hint' => 'Daftar pemasok', 'icon' => 'bi-building'] : null,
        can('product.manage') ? ['href' => '/products', 'match' => '/products', 'title' => 'Katalog Produk', 'hint' => 'Produk & harga acuan', 'icon' => 'bi-box-seam'] : null,
    ]));
}
if (can('user.manage')) {
    $adminGroups['Akses Pengguna'] = [
        ['href' => '/users', 'match' => '/users', 'title' => 'Pengguna', 'hint' => 'Akun & penugasan', 'icon' => 'bi-people-fill'],
        ['href' => '/roles', 'match' => '/roles', 'title' => 'Role & Permission', 'hint' => 'Hak akses per peran', 'icon' => 'bi-shield-lock-fill'],
    ];
}
if (can('system.manage')) {
    $adminGroups['Sistem'] = [
        ['href' => '/settings', 'match' => '/settings', 'title' => 'Pengaturan', 'hint' => 'Profil perusahaan, SLA, notifikasi', 'icon' => 'bi-gear-fill'],
    ];
}

$stepActive = fn (array $s) => $has($s['match']) && (empty($s['exclude']) || !$has($s['exclude']));
$pipelineActive = count(array_filter($pipelineSteps, $stepActive)) > 0;
$adminActive = false;
foreach ($adminGroups as $items) {
    foreach ($items as $it) {
        if ($has($it['match'])) { $adminActive = true; }
    }
}
$dashActive = $currentPath === '' || str_ends_with($currentPath, '/dashboard') || $currentPath === rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/');
$leadsActive = $has('/leads');
$fuActive = $has('/follow-ups');
$reportActive = $has('/reports');
$initial = strtoupper(substr($user['name'] ?? '?', 0, 1));
?>
<header class="app-topbar">
    <div class="topbar-inner">
        <button type="button" class="topbar-toggle" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="Buka menu">
            <i class="bi bi-list"></i>
        </button>

        <a href="<?= url('/dashboard') ?>" class="brand" aria-label="HME Sales">
            <span class="brand-mark"><img src="<?= asset('img/logo.png') ?>" alt=""></span>
            <span class="brand-text">HME <span>Sales</span></span>
        </a>

        <nav class="topnav" aria-label="Menu utama">
            <a href="<?= url('/dashboard') ?>" class="topnav-link <?= $dashActive ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= url('/leads') ?>" class="topnav-link <?= $leadsActive ? 'active' : '' ?>">Leads</a>

            <?php if (!empty($pipelineSteps)): ?>
            <div class="dropdown">
                <button type="button" class="topnav-link dropdown-toggle <?= $pipelineActive ? 'active' : '' ?>" data-bs-toggle="dropdown" aria-expanded="false">
                    Pipeline <i class="bi bi-chevron-down"></i>
                </button>
                <div class="dropdown-menu nav-menu">
                    <div class="nav-menu-label">Tahap kerja berurutan</div>
                    <?php foreach ($pipelineSteps as $s): ?>
                    <a href="<?= url($s['href']) ?>" class="nav-menu-item <?= $stepActive($s) ? 'active' : '' ?>">
                        <span class="nav-menu-step"><?= (int) $s['n'] ?></span>
                        <span class="nav-menu-text"><strong><?= e($s['title']) ?></strong><small><?= e($s['hint']) ?></small></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (can('followup.view')): ?>
            <a href="<?= url('/follow-ups') ?>" class="topnav-link <?= $fuActive ? 'active' : '' ?>">Follow Up</a>
            <?php endif; ?>
            <?php if (can('report.view')): ?>
            <a href="<?= url('/reports') ?>" class="topnav-link <?= $reportActive ? 'active' : '' ?>">Laporan</a>
            <?php endif; ?>
        </nav>

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

                <?php if (!empty($adminGroups)): ?>
                <div class="dropdown">
                    <button type="button" class="topbar-icon-btn <?= $adminActive ? 'is-tinted' : '' ?>" title="Pengaturan" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end nav-menu">
                        <?php $gi = 0; foreach ($adminGroups as $label => $items): ?>
                            <?php if ($gi++ > 0): ?><div class="nav-menu-sep"></div><?php endif; ?>
                            <div class="nav-menu-label"><?= e($label) ?></div>
                            <?php foreach ($items as $it): ?>
                            <a href="<?= url($it['href']) ?>" class="nav-menu-item <?= $has($it['match']) ? 'active' : '' ?>">
                                <span class="nav-menu-icon"><i class="bi <?= e($it['icon']) ?>"></i></span>
                                <span class="nav-menu-text"><strong><?= e($it['title']) ?></strong><small><?= e($it['hint']) ?></small></span>
                            </a>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="dropdown">
                <button class="topbar-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="topbar-user-avatar"><?= e($initial) ?></span>
                    <span class="topbar-user-info d-none d-md-flex">
                        <strong><?= e($user['name'] ?? '-') ?></strong>
                        <small><?= e($user['role_name'] ?? '-') ?></small>
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

<!-- Menu mobile -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileNav" style="width:320px">
    <div class="offcanvas-header">
        <a href="<?= url('/dashboard') ?>" class="brand">
            <span class="brand-mark"><img src="<?= asset('img/logo.png') ?>" alt=""></span>
            <span class="brand-text">HME <span>Sales</span></span>
        </a>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body">
        <a href="<?= url('/dashboard') ?>" class="nav-menu-item <?= $dashActive ? 'active' : '' ?>"><span class="nav-menu-icon"><i class="bi bi-grid-1x2-fill"></i></span><span class="nav-menu-text"><strong>Dashboard</strong></span></a>
        <a href="<?= url('/leads') ?>" class="nav-menu-item <?= $leadsActive ? 'active' : '' ?>"><span class="nav-menu-icon"><i class="bi bi-person-lines-fill"></i></span><span class="nav-menu-text"><strong>Leads</strong></span></a>

        <?php if (!empty($pipelineSteps)): ?>
        <div class="nav-menu-label mt-2">Pipeline — tahap berurutan</div>
        <?php foreach ($pipelineSteps as $s): ?>
        <a href="<?= url($s['href']) ?>" class="nav-menu-item <?= $stepActive($s) ? 'active' : '' ?>">
            <span class="nav-menu-step"><?= (int) $s['n'] ?></span>
            <span class="nav-menu-text"><strong><?= e($s['title']) ?></strong><small><?= e($s['hint']) ?></small></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="nav-menu-label mt-2">Pemantauan</div>
        <?php if (can('followup.view')): ?>
        <a href="<?= url('/follow-ups') ?>" class="nav-menu-item <?= $fuActive ? 'active' : '' ?>"><span class="nav-menu-icon"><i class="bi bi-telephone-outbound"></i></span><span class="nav-menu-text"><strong>Follow Up</strong></span></a>
        <?php endif; ?>
        <?php if (can('report.view')): ?>
        <a href="<?= url('/reports') ?>" class="nav-menu-item <?= $reportActive ? 'active' : '' ?>"><span class="nav-menu-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="nav-menu-text"><strong>Laporan</strong></span></a>
        <?php endif; ?>
        <a href="<?= url('/notifications') ?>" class="nav-menu-item"><span class="nav-menu-icon"><i class="bi bi-bell"></i></span><span class="nav-menu-text"><strong>Notifikasi</strong></span></a>

        <?php foreach ($adminGroups as $label => $items): ?>
        <div class="nav-menu-label mt-2"><?= e($label) ?></div>
        <?php foreach ($items as $it): ?>
        <a href="<?= url($it['href']) ?>" class="nav-menu-item <?= $has($it['match']) ? 'active' : '' ?>"><span class="nav-menu-icon"><i class="bi <?= e($it['icon']) ?>"></i></span><span class="nav-menu-text"><strong><?= e($it['title']) ?></strong></span></a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
