<?php
$pageTitle = 'Engineer Sales';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/engineer') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$tiles = [
    'pending' => ['label' => 'Assignment Baru', 'icon' => 'bi-inbox'],
    'accepted' => ['label' => 'Accepted', 'icon' => 'bi-check2'],
    'in_progress' => ['label' => 'In Progress', 'icon' => 'bi-arrow-repeat'],
    'waiting' => ['label' => 'Waiting', 'icon' => 'bi-hourglass-split'],
    'completed' => ['label' => 'Completed', 'icon' => 'bi-check-circle'],
    'rejected' => ['label' => 'Rejected', 'icon' => 'bi-x-circle'],
];
?>
<div class="page-header">
    <h2>Engineer Sales</h2>
    <p class="text-muted"><?= (int) $total ?> assignment ditemukan. Assignment baru dikirim lewat halaman detail Lead.</p>
</div>

<div class="stat-grid stat-grid-6">
    <?php foreach ($tiles as $code => $tile): ?>
    <a href="<?= url('/engineer') ?>?status=<?= e($code) ?><?= $code === 'rejected' ? '&include_closed=1' : '' ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi <?= e($tile['icon']) ?>"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($tile['label']) ?></span>
            <span class="stat-value" data-engineer-count="<?= e($code) ?>"><?= (int) $dashboardCounts[$code] ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/engineer') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode assignment, kode lead, nama, perusahaan" value="<?= e($filters['q']) ?>">
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
            <div class="filter-field">
                <label class="form-label" for="assignment_type">Tipe</label>
                <select id="assignment_type" name="assignment_type" class="form-select">
                    <option value="">Semua Tipe</option>
                    <option value="engineer" <?= $filters['assignment_type'] === 'engineer' ? 'selected' : '' ?>>Engineer</option>
                    <option value="sales_engineer" <?= $filters['assignment_type'] === 'sales_engineer' ? 'selected' : '' ?>>Sales Engineer</option>
                </select>
            </div>
            <?php if (count($engineerUsers)): ?>
            <div class="filter-field">
                <label class="form-label" for="engineer_id">Engineer</label>
                <select id="engineer_id" name="engineer_id" class="form-select">
                    <option value="">Semua Engineer</option>
                    <?php foreach ($engineerUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['engineer_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
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
                <a href="<?= url('/engineer') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($assignments)): ?>
            <div class="empty-state">
                <i class="bi bi-tools"></i>
                <p>Tidak ada assignment yang cocok.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('assignment_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Lead') ?></th>
                        <th>Tipe</th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('priority', 'Prioritas') ?></th>
                        <th><?= $sortLink('engineer_name', 'Engineer') ?></th>
                        <th><?= $sortLink('deadline', 'Deadline') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $a): ?>
                    <?php $isOverdue = !empty($a['deadline']) && $a['deadline'] < date('Y-m-d') && !in_array($a['status'], ['completed', 'rejected', 'returned'], true); ?>
                    <tr>
                        <td class="mono"><?= e($a['assignment_code']) ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $a['lead_id']) ?>" class="mono d-block"><?= e($a['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($a['customer_name']) ?></div>
                        </td>
                        <td><span class="badge-pill <?= $a['assignment_type'] === 'engineer' ? 'badge-pill-muted' : '' ?>"><?= $a['assignment_type'] === 'engineer' ? 'Engineer' : 'Sales Engineer' ?></span></td>
                        <td>
                            <span class="color-swatch color-swatch-<?= e($statusMap[$a['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$a['status']]['name'] ?? $a['status']) ?></span>
                            <?php if ($isOverdue): ?><span class="overdue-badge" title="Melewati deadline"><i class="bi bi-exclamation-triangle-fill"></i></span><?php endif; ?>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$a['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$a['priority']]['name'] ?? $a['priority']) ?></span></td>
                        <td><?= e($a['engineer_name'] ?? '—') ?></td>
                        <td class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $a['deadline'] ? e(format_datetime($a['deadline'], 'd M Y')) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= url('/engineer/' . $a['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/engineer') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/engineer') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        function refreshCounts() {
            Api.get(base + '/api/engineer/summary').then(function (data) {
                Object.keys(data.counts || {}).forEach(function (code) {
                    var el = document.querySelector('[data-engineer-count="' + code + '"]');
                    if (el) el.textContent = data.counts[code];
                });
            }).catch(function () {});
        }
        setInterval(refreshCounts, Math.max(pollInterval, 10000));
    })();
</script>
