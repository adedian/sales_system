<?php
use App\Core\Navigation;

$navPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $navQuery);
$navSections = Navigation::build($navPath, $navQuery);
?>
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-label="Menu utama">
    <div class="sidebar-head">
        <a href="<?= url('/dashboard') ?>" class="sidebar-brand" aria-label="HME Sales">
            <span class="sidebar-brand-mark"><img src="<?= asset('img/logo.png') ?>" alt=""></span>
            <span class="sidebar-brand-text"><strong>HME <span>Sales</span></strong><small>Lead &amp; Proposal Desk</small></span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Tutup menu"></button>
    </div>

    <nav class="sidebar-nav">
        <?php
        // Satu kelompok saja -> langsung terbuka; bila banyak, hanya kelompok yang berisi halaman aktif.
        $collapsibleCount = count(array_filter($navSections, static fn (array $g): bool => $g['collapsible']));
        $anyGroupActive = in_array(true, array_column(array_filter($navSections, static fn (array $g): bool => $g['collapsible']), 'active'), true);
        foreach ($navSections as $section):
            // Grup yang berisi halaman aktif terbuka; bila belum ada yang aktif (mis. Dashboard)
            // dan grupnya sedikit (<=3), semuanya terbuka agar menu langsung terlihat.
            $open = !$section['collapsible'] || $section['active'] || ($collapsibleCount <= 3 && !$anyGroupActive);
        ?>
        <div class="sb-section">
            <?php if ($section['collapsible']): ?>
            <button type="button" class="sb-label sb-group <?= $section['active'] ? 'active' : '' ?>" data-bs-toggle="collapse" data-bs-target="#<?= e($section['id']) ?>"
                    aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= e($section['id']) ?>">
                <span><?= e($section['section']) ?></span>
                <i class="bi bi-chevron-down sb-chevron"></i>
            </button>
            <?php else: ?>
            <div class="sb-label"><?= e($section['section']) ?></div>
            <?php endif; ?>
            <div class="<?= $section['collapsible'] ? 'collapse sb-group-body' . ($open ? ' show' : '') : '' ?>" <?= $section['collapsible'] ? 'id="' . e($section['id']) . '"' : '' ?>>
            <?php foreach ($section['items'] as $item): ?>
                <?php if (!empty($item['children'])): ?>
                <button type="button" class="sb-item sb-parent <?= $item['active'] ? 'active' : '' ?>"
                        data-bs-toggle="collapse" data-bs-target="#<?= e($item['id']) ?>"
                        aria-expanded="<?= $item['active'] ? 'true' : 'false' ?>" aria-controls="<?= e($item['id']) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span><?= e($item['title']) ?></span>
                    <i class="bi bi-chevron-down sb-chevron"></i>
                </button>
                <div class="collapse sb-children <?= $item['active'] ? 'show' : '' ?>" id="<?= e($item['id']) ?>">
                    <?php foreach ($item['children'] as $child): ?>
                    <a href="<?= url($child['href']) ?>" class="sb-sub <?= $child['active'] ? 'active' : '' ?>"><?= e($child['title']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <a href="<?= url($item['href']) ?>" class="sb-item <?= $item['active'] ? 'active' : '' ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span><?= e($item['title']) ?></span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>
</aside>
