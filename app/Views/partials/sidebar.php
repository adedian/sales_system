<?php
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$isActive = fn (string $needle) => str_ends_with($currentPath, $needle);
?>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <span class="sidebar-brand-mark"><i class="bi bi-hexagon-fill"></i></span>
        <span class="sidebar-brand-text">
            <strong>Sistem Internal</strong>
            <small>Sales Platform</small>
        </span>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Menu</div>
        <ul class="sidebar-nav-list">
            <li>
                <a href="<?= url('/dashboard') ?>" class="sidebar-link <?= $isActive('/dashboard') ? 'active' : '' ?>" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">Sales Pipeline</div>
        <ul class="sidebar-nav-list">
            <li>
                <a href="<?= url('/leads') ?>" class="sidebar-link <?= str_contains($currentPath, '/leads') ? 'active' : '' ?>" title="Leads">
                    <i class="bi bi-person-lines-fill"></i>
                    <span>Leads</span>
                </a>
            </li>
            <?php if (can('queue.view')): ?>
            <li>
                <a href="<?= url('/queue') ?>" class="sidebar-link <?= str_contains($currentPath, '/queue') ? 'active' : '' ?>" title="Antrian Sales">
                    <i class="bi bi-list-ol"></i>
                    <span>Antrian Sales</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('engineer.view')): ?>
            <li>
                <a href="<?= url('/engineer') ?>" class="sidebar-link <?= str_contains($currentPath, '/engineer') ? 'active' : '' ?>" title="Engineer Sales">
                    <i class="bi bi-tools"></i>
                    <span>Engineer Sales</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('procurement.view')): ?>
            <li>
                <a href="<?= url('/procurement') ?>" class="sidebar-link <?= $currentPath !== '' && str_contains($currentPath, '/procurement') && !str_contains($currentPath, '/procurement/validation') ? 'active' : '' ?>" title="Procurement">
                    <i class="bi bi-truck"></i>
                    <span>Procurement</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ((int) (auth_user()['is_director'] ?? 0) === 1): ?>
            <li>
                <a href="<?= url('/procurement/validation') ?>" class="sidebar-link <?= str_contains($currentPath, '/procurement/validation') ? 'active' : '' ?>" title="Validasi Harga">
                    <i class="bi bi-shield-check"></i>
                    <span>Validasi Harga</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('proposal.view')): ?>
            <li>
                <a href="<?= url('/proposals') ?>" class="sidebar-link <?= str_contains($currentPath, '/proposals') ? 'active' : '' ?>" title="Proposal">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Proposal</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('followup.view')): ?>
            <li>
                <a href="<?= url('/follow-ups') ?>" class="sidebar-link <?= str_contains($currentPath, '/follow-ups') ? 'active' : '' ?>" title="Follow Up">
                    <i class="bi bi-telephone-outbound"></i>
                    <span>Follow Up</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('report.view')): ?>
            <li>
                <a href="<?= url('/reports') ?>" class="sidebar-link <?= str_contains($currentPath, '/reports') ? 'active' : '' ?>" title="Laporan">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Laporan</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <?php if (can('master_data.manage') || can('vendor.manage') || can('product.manage')): ?>
        <div class="sidebar-section-label">Konfigurasi</div>
        <ul class="sidebar-nav-list">
            <?php if (can('master_data.manage')): ?>
            <li>
                <a href="<?= url('/master-data') ?>" class="sidebar-link <?= str_contains($currentPath, '/master-data') ? 'active' : '' ?>" title="Master Data">
                    <i class="bi bi-database-gear"></i>
                    <span>Master Data</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('vendor.manage')): ?>
            <li>
                <a href="<?= url('/vendors') ?>" class="sidebar-link <?= str_contains($currentPath, '/vendors') ? 'active' : '' ?>" title="Vendor">
                    <i class="bi bi-building"></i>
                    <span>Vendor</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('product.manage')): ?>
            <li>
                <a href="<?= url('/products') ?>" class="sidebar-link <?= str_contains($currentPath, '/products') ? 'active' : '' ?>" title="Katalog Produk">
                    <i class="bi bi-box-seam"></i>
                    <span>Katalog Produk</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>

        <?php if (can('user.manage') || can('system.manage')): ?>
        <div class="sidebar-section-label">Administrasi</div>
        <ul class="sidebar-nav-list">
            <?php if (can('user.manage')): ?>
            <li>
                <a href="<?= url('/users') ?>" class="sidebar-link <?= str_contains($currentPath, '/users') ? 'active' : '' ?>" title="Pengguna">
                    <i class="bi bi-people-fill"></i>
                    <span>Pengguna</span>
                </a>
            </li>
            <li>
                <a href="<?= url('/roles') ?>" class="sidebar-link <?= str_contains($currentPath, '/roles') ? 'active' : '' ?>" title="Role &amp; Permission">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Role &amp; Permission</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (can('system.manage')): ?>
            <li>
                <a href="<?= url('/settings') ?>" class="sidebar-link <?= str_contains($currentPath, '/settings') ? 'active' : '' ?>" title="Pengaturan">
                    <i class="bi bi-gear-fill"></i>
                    <span>Pengaturan</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <span class="text-muted small"><?= e(\App\Models\Setting::get('company_name', '') ?: 'Sistem Internal Sales') ?></span>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
