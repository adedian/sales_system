<?php
$pageTitle = 'Antrian Sales';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/queue') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$tiles = [
    'new' => ['label' => 'Baru', 'icon' => 'bi-inbox'],
    'waiting_followup' => ['label' => 'Menunggu Follow Up', 'icon' => 'bi-hourglass-split'],
    'in_progress' => ['label' => 'Sedang Diproses', 'icon' => 'bi-arrow-repeat'],
    'waiting_engineer' => ['label' => 'Menunggu Engineer', 'icon' => 'bi-send'],
    'done' => ['label' => 'Selesai', 'icon' => 'bi-check-circle'],
];
?>
<div class="page-header">
    <h2>Antrian Sales</h2>
    <p class="text-muted"><?= (int) $total ?> antrian ditemukan. Lead baru masuk ke sini lewat halaman detail Lead.</p>
</div>

<div class="stat-grid stat-grid-6">
    <?php foreach ($tiles as $code => $tile): ?>
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi <?= e($tile['icon']) ?>"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($tile['label']) ?></span>
            <span class="stat-value" data-queue-count="<?= e($code) ?>"><?= (int) $dashboardCounts[$code] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="stat-card stat-card-compact stat-card-alert">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body">
            <span class="stat-label">Overdue</span>
            <span class="stat-value" data-queue-count="overdue"><?= (int) $dashboardCounts['overdue'] ?></span>
        </div>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/queue') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode lead, nama, perusahaan" value="<?= e($filters['q']) ?>">
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
                <label class="form-label" for="survey_status_id">Status Survey</label>
                <select id="survey_status_id" name="survey_status_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($surveyStatusMap as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['survey_status_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="form-label" for="stage_id">Catatan Tahap</label>
                <select id="stage_id" name="stage_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($stageMap as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['stage_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label class="form-label" for="priority">Urgensi</label>
                <select id="priority" name="priority" class="form-select">
                    <option value="">Semua Urgensi</option>
                    <?php foreach ($priorityMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $filters['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
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
                <div class="form-check form-check-inline pt-4">
                    <input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1" <?= $filters['overdue'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="overdue">Overdue saja</label>
                </div>
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url('/queue') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($queues)): ?>
            <div class="empty-state">
                <i class="bi bi-list-ol"></i>
                <p>Tidak ada antrian yang cocok.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('queue_number', 'No.') ?></th>
                        <th><?= $sortLink('customer_name', 'Tugas') ?></th>
                        <th>Status Survey</th>
                        <th>Catatan Tahap</th>
                        <th><?= $sortLink('priority', 'Urgensi') ?></th>
                        <th><?= $sortLink('sales_name', 'Sales') ?></th>
                        <th>Estimator</th>
                        <th>Surveyor</th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('deadline', 'Deadline') ?></th>
                        <th><?= $sortLink('followup_date', 'Follow Up') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queues as $q): ?>
                    <?php $isOverdue = !empty($q['deadline']) && $q['deadline'] < date('Y-m-d') && !in_array($q['status'], ['done', 'cancelled'], true); ?>
                    <tr>
                        <td class="mono">#<?= (int) $q['queue_number'] ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $q['lead_id']) ?>" class="mono d-block"><?= e($q['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e(\App\Models\SalesQueue::displayTitle($q)) ?></div>
                        </td>
                        <td class="text-muted"><?= $q['survey_status_name'] ? e($q['survey_status_name']) : '-' ?></td>
                        <td><?= $q['stage_name'] ? '<span class="color-swatch color-swatch-' . e($q['stage_color'] ?: 'muted') . '">' . e($q['stage_name']) . '</span>' : '-' ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$q['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$q['priority']]['name'] ?? $q['priority']) ?></span></td>
                        <td><?= e($q['sales_name'] ?? '—') ?></td>
                        <td class="text-muted"><?= e($q['estimator_name'] ?? 'None') ?></td>
                        <td class="text-muted"><?= e($q['surveyor_name'] ?? 'None') ?></td>
                        <td>
                            <span class="color-swatch color-swatch-<?= e($statusMap[$q['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$q['status']]['name'] ?? $q['status']) ?></span>
                            <?php if ($isOverdue): ?><span class="overdue-badge" title="Melewati deadline"><i class="bi bi-exclamation-triangle-fill"></i></span><?php endif; ?>
                        </td>
                        <td class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $q['deadline'] ? e(format_datetime($q['deadline'], 'd M Y')) : '-' ?></td>
                        <td class="text-muted"><?= $q['followup_date'] ? e(format_datetime($q['followup_date'], 'd M Y')) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= url('/queue/' . $q['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/queue') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/queue') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        function refreshCounts() {
            Api.get(base + '/api/queue/summary').then(function (data) {
                Object.keys(data.counts || {}).forEach(function (code) {
                    var el = document.querySelector('[data-queue-count="' + code + '"]');
                    if (el) el.textContent = data.counts[code];
                });
            }).catch(function () {});
        }
        setInterval(refreshCounts, Math.max(pollInterval, 10000));
    })();
</script>
