<?php
$pageTitle = 'Prelim';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/prelims') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$tiles = [
    'draft' => ['label' => 'Draft', 'icon' => 'bi-file-earmark'],
    'ready_to_send' => ['label' => 'Siap Dikirim', 'icon' => 'bi-hourglass-split'],
    'sent' => ['label' => 'Terkirim', 'icon' => 'bi-send'],
    'client_revision' => ['label' => 'Client Minta Revisi', 'icon' => 'bi-exclamation-triangle'],
    'approved' => ['label' => 'Disetujui (ACC)', 'icon' => 'bi-check-circle'],
];
?>
<div class="page-header">
    <h2>Prelim</h2>
    <p class="text-muted"><?= (int) $total ?> prelim ditemukan. Prelim adalah penawaran awal ke Client sebelum Proposal+BOQ — dibuat dari halaman Lead setelah Data Awal lengkap.</p>
</div>

<div class="stat-grid stat-grid-5">
    <?php foreach ($tiles as $code => $tile): ?>
    <a href="<?= url('/prelims') ?>?status=<?= e($code) ?><?= $code === 'approved' ? '&include_closed=1' : '' ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi <?= e($tile['icon']) ?>"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($tile['label']) ?></span>
            <span class="stat-value"><?= (int) $dashboardCounts[$code] ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/prelims') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode prelim, kode lead, nama customer" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Aktif (belum ACC)</option>
                    <?php foreach ($statusMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($salesUsers)): ?>
            <div class="filter-field">
                <label class="form-label" for="sales_id">Sales</label>
                <select id="sales_id" name="sales_id" class="form-select">
                    <option value="">Semua Sales</option>
                    <?php foreach ($salesUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['sales_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url('/prelims') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($prelims)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-richtext"></i>
                <p>Tidak ada prelim yang cocok.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('prelim_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Lead') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('estimated_value', 'Estimasi Nilai') ?></th>
                        <th><?= $sortLink('sales_name', 'Sales') ?></th>
                        <th><?= $sortLink('created_at', 'Dibuat') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prelims as $p): ?>
                    <tr>
                        <td class="mono"><?= e($p['prelim_code']) ?> <span class="text-muted small">v<?= (int) $p['version'] ?></span></td>
                        <td>
                            <a href="<?= url('/leads/' . $p['lead_id']) ?>" class="mono d-block"><?= e($p['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($p['customer_name']) ?></div>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($statusMap[$p['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$p['status']]['name'] ?? $p['status']) ?></span></td>
                        <td class="mono"><?= $p['estimated_value'] !== null ? 'Rp ' . e(number_format((float) $p['estimated_value'], 0, ',', '.')) : '-' ?></td>
                        <td><?= e($p['sales_name'] ?? '—') ?></td>
                        <td class="text-muted"><?= e(format_datetime($p['created_at'], 'd M Y')) ?></td>
                        <td class="text-end">
                            <a href="<?= url('/prelims/' . $p['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-body pagination-bar">
        <?php $qs = fn ($p) => http_build_query(array_merge($filters, ['page' => $p])); ?>
        <div class="text-muted small">Halaman <?= (int) $page ?> dari <?= (int) $totalPages ?></div>
        <div class="pagination-controls">
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/prelims') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/prelims') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>
