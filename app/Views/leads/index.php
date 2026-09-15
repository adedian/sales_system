<?php
$pageTitle = 'Leads';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/leads') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$statusLabels = ['new' => 'Baru', 'in_queue' => 'Antrian', 'follow_up' => 'Follow Up', 'engineering' => 'Analisa', 'won' => 'Won', 'lost' => 'Lost'];
?>
<div class="page-header page-header-row">
    <div>
        <h2><?= !empty($filters['trashed']) ? 'Sampah Lead' : 'Leads' ?></h2>
        <p class="text-muted"><?= (int) $total ?> lead ditemukan.</p>
    </div>
    <?php if (empty($filters['trashed'])): ?>
    <a href="<?= url('/leads/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Lead</a>
    <?php endif; ?>
</div>

<?php if (empty($filters['trashed'])): ?>
<div class="stat-grid stat-grid-6">
    <?php foreach ($statusLabels as $code => $label): ?>
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi bi-flag"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($statusMap[$code]['name'] ?? $label) ?></span>
            <span class="stat-value" data-status-count="<?= e($code) ?>"><?= (int) $statusCounts[$code] ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/leads') ?>" class="filter-form">
            <?php if (!empty($filters['trashed'])): ?><input type="hidden" name="trashed" value="1"><?php endif; ?>
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode, nama, perusahaan, telepon, email" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
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
                <label class="form-label" for="source_id">Sumber</label>
                <select id="source_id" name="source_id" class="form-select">
                    <option value="">Semua Sumber</option>
                    <?php foreach ($sources as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['source_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canAssign && count($salesUsers)): ?>
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
            <div class="filter-field">
                <label class="form-label" for="follow_up">Follow Up</label>
                <select id="follow_up" name="follow_up" class="form-select">
                    <option value="">Semua Tanggal</option>
                    <option value="overdue" <?= $filters['follow_up'] === 'overdue' ? 'selected' : '' ?>>Terlewat</option>
                    <option value="today" <?= $filters['follow_up'] === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                    <option value="upcoming" <?= $filters['follow_up'] === 'upcoming' ? 'selected' : '' ?>>Akan Datang</option>
                </select>
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url('/leads') ?><?= !empty($filters['trashed']) ? '?trashed=1' : '' ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($leads)): ?>
            <div class="empty-state">
                <i class="bi bi-person-lines-fill"></i>
                <p><?= !empty($filters['trashed']) ? 'Sampah lead kosong.' : 'Belum ada lead yang cocok dengan pencarian.' ?></p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('lead_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Customer') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('priority', 'Prioritas') ?></th>
                        <th><?= $sortLink('sales_name', 'Sales') ?></th>
                        <th><?= $sortLink('follow_up_date', 'Follow Up') ?></th>
                        <th><?= $sortLink('created_at', 'Dibuat') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td><a href="<?= url('/leads/' . $lead['id']) ?>" class="mono"><?= e($lead['lead_code']) ?></a></td>
                        <td>
                            <div class="user-cell-name"><?= e($lead['customer_name']) ?></div>
                            <?php if ($lead['company_name']): ?><div class="user-cell-sub"><?= e($lead['company_name']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($statusMap[$lead['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$lead['status']]['name'] ?? $lead['status']) ?></span></td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$lead['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$lead['priority']]['name'] ?? $lead['priority']) ?></span></td>
                        <td><?= e($lead['sales_name'] ?? '—') ?></td>
                        <td class="<?= (!empty($lead['follow_up_date']) && $lead['follow_up_date'] < date('Y-m-d') && empty($filters['trashed'])) ? 'text-danger' : 'text-muted' ?>">
                            <?= $lead['follow_up_date'] ? e(format_datetime($lead['follow_up_date'], 'd M Y')) : '-' ?>
                        </td>
                        <td class="text-muted"><?= e(format_datetime($lead['created_at'], 'd M Y')) ?></td>
                        <td class="text-end">
                            <div class="row-actions">
                                <?php if (!empty($filters['trashed'])): ?>
                                    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/restore') ?>" data-confirm="Pulihkan lead <?= e($lead['lead_code']) ?>?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-light" title="Pulihkan"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?= url('/leads/' . $lead['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
                                    <?php if ($canManage): ?>
                                    <a href="<?= url('/leads/' . $lead['id'] . '/edit') ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/delete') ?>" data-confirm="Pindahkan lead <?= e($lead['lead_code']) ?> ke sampah?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/leads') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/leads') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($canDelete): ?>
<p class="mt-3">
    <?php if (!empty($filters['trashed'])): ?>
        <a href="<?= url('/leads') ?>"><i class="bi bi-arrow-left me-1"></i>Kembali ke daftar lead</a>
    <?php else: ?>
        <a href="<?= url('/leads') ?>?trashed=1" class="text-muted"><i class="bi bi-trash me-1"></i>Lihat sampah</a>
    <?php endif; ?>
</p>
<?php endif; ?>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        function refreshCounts() {
            Api.get(base + '/api/leads/summary').then(function (data) {
                Object.keys(data.counts || {}).forEach(function (code) {
                    var el = document.querySelector('[data-status-count="' + code + '"]');
                    if (el) el.textContent = data.counts[code];
                });
            }).catch(function () {});
        }
        <?php if (empty($filters['trashed'])): ?>
        setInterval(refreshCounts, Math.max(pollInterval, 10000));
        <?php endif; ?>
    })();
</script>
