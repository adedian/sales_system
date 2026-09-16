<?php
$pageTitle = 'Procurement';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/procurement') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$tiles = [
    'waiting' => ['label' => 'Request Baru', 'icon' => 'bi-inbox'],
    'in_progress' => ['label' => 'In Progress', 'icon' => 'bi-arrow-repeat'],
    'quotation_requested' => ['label' => 'Waiting Quotation', 'icon' => 'bi-hourglass-split'],
    'need_revision' => ['label' => 'Need Revision', 'icon' => 'bi-exclamation-triangle'],
    'pricing_completed' => ['label' => 'Completed', 'icon' => 'bi-check-circle'],
];
?>
<div class="page-header">
    <h2>Procurement</h2>
    <p class="text-muted"><?= (int) $total ?> request ditemukan. Request baru dikirim lewat halaman detail Lead atau Engineer Assignment.</p>
</div>

<div class="stat-grid stat-grid-6">
    <?php foreach ($tiles as $code => $tile): ?>
    <a href="<?= url('/procurement') ?>?status=<?= e($code) ?><?= $code === 'need_revision' ? '&include_closed=1' : '' ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi <?= e($tile['icon']) ?>"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($tile['label']) ?></span>
            <span class="stat-value" data-procurement-count="<?= e($code) ?>"><?= (int) $dashboardCounts[$code] ?></span>
        </div>
    </a>
    <?php endforeach; ?>
    <div class="stat-card stat-card-compact stat-card-alert">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body">
            <span class="stat-label">Overdue</span>
            <span class="stat-value" data-procurement-count="overdue"><?= (int) $dashboardCounts['overdue'] ?></span>
        </div>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/procurement') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode request, kode lead, nama, perusahaan" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Aktif (belum selesai)</option>
                    <?php foreach ($statusMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="form-label" for="priority">Prioritas</label>
                <select id="priority" name="priority" class="form-select">
                    <option value="">Semua Prioritas</option>
                    <?php foreach ($priorityMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $filters['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($procurementUsers)): ?>
            <div class="filter-field">
                <label class="form-label" for="assigned_to">Staff</label>
                <select id="assigned_to" name="assigned_to" class="form-select">
                    <option value="">Semua Staff</option>
                    <?php foreach ($procurementUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['assigned_to'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-field filter-field-actions">
                <div class="form-check form-check-inline pt-4">
                    <input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1" <?= $filters['overdue'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="overdue">Overdue saja</label>
                </div>
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url('/procurement') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="bi bi-truck"></i>
                <p>Tidak ada request yang cocok.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('request_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Lead') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('priority', 'Prioritas') ?></th>
                        <th><?= $sortLink('assigned_to_name', 'Staff') ?></th>
                        <th><?= $sortLink('deadline', 'Deadline') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <?php $isOverdue = !empty($r['deadline']) && $r['deadline'] < date('Y-m-d') && !in_array($r['status'], ['pricing_completed', 'cancelled'], true); ?>
                    <tr>
                        <td class="mono"><?= e($r['request_code']) ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $r['lead_id']) ?>" class="mono d-block"><?= e($r['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($r['customer_name']) ?></div>
                        </td>
                        <td>
                            <span class="color-swatch color-swatch-<?= e($statusMap[$r['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$r['status']]['name'] ?? $r['status']) ?></span>
                            <?php if ($isOverdue): ?><span class="overdue-badge" title="Melewati deadline"><i class="bi bi-exclamation-triangle-fill"></i></span><?php endif; ?>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$r['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$r['priority']]['name'] ?? $r['priority']) ?></span></td>
                        <td><?= e($r['assigned_to_name'] ?? '—') ?></td>
                        <td class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $r['deadline'] ? e(format_datetime($r['deadline'], 'd M Y')) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= url('/procurement/' . $r['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/procurement') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/procurement') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        function refreshCounts() {
            Api.get(base + '/api/procurement/summary').then(function (data) {
                Object.keys(data.counts || {}).forEach(function (code) {
                    var el = document.querySelector('[data-procurement-count="' + code + '"]');
                    if (el) el.textContent = data.counts[code];
                });
            }).catch(function () {});
        }
        setInterval(refreshCounts, Math.max(pollInterval, 10000));
    })();
</script>
