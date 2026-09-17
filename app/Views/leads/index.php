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

$findById = function (array $rows, $id) {
    foreach ($rows as $row) {
        if ((string) $row['id'] === (string) $id) return $row;
    }
    return null;
};

// Active filter chips — everything except the search box itself (kept as a
// plain input, per spec) and the trashed/sort/dir/page bookkeeping fields.
$chips = [];
if ($filters['status'] !== '') {
    $chips[] = ['key' => 'status', 'label' => 'Status: ' . ($statusMap[$filters['status']]['name'] ?? $filters['status'])];
}
if ($filters['simple_status'] !== '') {
    $chips[] = ['key' => 'simple_status', 'label' => 'Status Sederhana: ' . ucfirst($filters['simple_status'])];
}
if ($filters['type_id'] !== '') {
    $row = $findById($leadTypes, $filters['type_id']);
    $chips[] = ['key' => 'type_id', 'label' => 'Type: ' . ($row['name'] ?? $filters['type_id'])];
}
if ($filters['system_id'] !== '') {
    $row = $findById($leadSystems, $filters['system_id']);
    $chips[] = ['key' => 'system_id', 'label' => 'System: ' . ($row['name'] ?? $filters['system_id'])];
}
if ($filters['funding_id'] !== '') {
    $row = $findById($fundingSources, $filters['funding_id']);
    $chips[] = ['key' => 'funding_id', 'label' => 'Funding: ' . ($row['name'] ?? $filters['funding_id'])];
}
if ($filters['priority'] !== '') {
    $chips[] = ['key' => 'priority', 'label' => 'Prioritas: ' . ($priorityMap[$filters['priority']]['name'] ?? $filters['priority'])];
}
if ($filters['source_id'] !== '') {
    $row = $findById($sources, $filters['source_id']);
    $chips[] = ['key' => 'source_id', 'label' => 'Sumber: ' . ($row['name'] ?? $filters['source_id'])];
}
if (!empty($filters['sales_id'])) {
    $row = $findById($salesUsers, $filters['sales_id']);
    $chips[] = ['key' => 'sales_id', 'label' => 'Sales: ' . ($row['name'] ?? $filters['sales_id'])];
}
if ($filters['follow_up'] !== '') {
    $followUpLabels = ['overdue' => 'Terlewat', 'today' => 'Hari Ini', 'upcoming' => 'Akan Datang'];
    $chips[] = ['key' => 'follow_up', 'label' => 'Follow Up: ' . ($followUpLabels[$filters['follow_up']] ?? $filters['follow_up'])];
}
$chipRemoveUrl = fn ($key) => url('/leads') . '?' . http_build_query(array_merge($filters, [$key => '', 'page' => 1]));
$resetUrl = url('/leads') . (!empty($filters['trashed']) ? '?trashed=1' : '');
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
        <form method="GET" action="<?= url('/leads') ?>" id="leadFilterForm">
            <?php if (!empty($filters['trashed'])): ?><input type="hidden" name="trashed" value="1"><?php endif; ?>
            <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
            <input type="hidden" name="dir" value="<?= e($filters['dir']) ?>">

            <div class="filter-quick-bar">
                <div class="filter-quick-search">
                    <input type="text" name="q" class="form-control" placeholder="Cari kode, nama, perusahaan, telepon, email..." value="<?= e($filters['q']) ?>">
                </div>
                <button type="button" class="btn btn-light" data-bs-toggle="offcanvas" data-bs-target="#leadFilterOffcanvas">
                    <i class="bi bi-funnel me-1"></i>Filter<?php if (!empty($chips)): ?> <span class="badge-pill badge-pill-muted ms-1"><?= count($chips) ?></span><?php endif; ?>
                </button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
            </div>

            <?php if (!empty($chips)): ?>
            <div class="filter-chips">
                <?php foreach ($chips as $chip): ?>
                <a href="<?= e($chipRemoveUrl($chip['key'])) ?>" class="filter-chip"><?= e($chip['label']) ?> <i class="bi bi-x-lg"></i></a>
                <?php endforeach; ?>
                <a href="<?= e($resetUrl) ?>" class="filter-chip filter-chip-clear">Reset semua</a>
            </div>
            <?php endif; ?>

            <div class="offcanvas offcanvas-end" tabindex="-1" id="leadFilterOffcanvas">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title">Filter Leads</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
                </div>
                <div class="offcanvas-body">
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <?php foreach ($statusMap as $code => $row): ?>
                                <option value="<?= e($code) ?>" <?= $filters['status'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="simple_status">Status Sederhana</label>
                        <select id="simple_status" name="simple_status" class="form-select">
                            <option value="">Semua</option>
                            <option value="proses" <?= $filters['simple_status'] === 'proses' ? 'selected' : '' ?>>Proses</option>
                            <option value="deal" <?= $filters['simple_status'] === 'deal' ? 'selected' : '' ?>>Deal</option>
                            <option value="cancel" <?= $filters['simple_status'] === 'cancel' ? 'selected' : '' ?>>Cancel</option>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="type_id">Type</label>
                        <select id="type_id" name="type_id" class="form-select">
                            <option value="">Semua Type</option>
                            <?php foreach ($leadTypes as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['type_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="system_id">System</label>
                        <select id="system_id" name="system_id" class="form-select">
                            <option value="">Semua System</option>
                            <?php foreach ($leadSystems as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['system_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="funding_id">Funding</label>
                        <select id="funding_id" name="funding_id" class="form-select">
                            <option value="">Semua Funding</option>
                            <?php foreach ($fundingSources as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['funding_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="priority">Prioritas</label>
                        <select id="priority" name="priority" class="form-select">
                            <option value="">Semua Prioritas</option>
                            <?php foreach ($priorityMap as $code => $row): ?>
                                <option value="<?= e($code) ?>" <?= $filters['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="source_id">Sumber</label>
                        <select id="source_id" name="source_id" class="form-select">
                            <option value="">Semua Sumber</option>
                            <?php foreach ($sources as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['source_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($canAssign && count($salesUsers)): ?>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="sales_id">Sales</label>
                        <select id="sales_id" name="sales_id" class="form-select">
                            <option value="">Semua Sales</option>
                            <?php foreach ($salesUsers as $row): ?>
                                <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['sales_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="filter-offcanvas-field">
                        <label class="form-label" for="follow_up">Follow Up</label>
                        <select id="follow_up" name="follow_up" class="form-select">
                            <option value="">Semua Tanggal</option>
                            <option value="overdue" <?= $filters['follow_up'] === 'overdue' ? 'selected' : '' ?>>Terlewat</option>
                            <option value="today" <?= $filters['follow_up'] === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                            <option value="upcoming" <?= $filters['follow_up'] === 'upcoming' ? 'selected' : '' ?>>Akan Datang</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Terapkan Filter</button>
                        <a href="<?= e($resetUrl) ?>" class="btn btn-light">Reset</a>
                    </div>
                </div>
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
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('lead_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Customer') ?></th>
                        <th><?= $sortLink('sales_name', 'Sales') ?></th>
                        <th>Type</th>
                        <th>System</th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th>Current Process</th>
                        <th>Current PIC</th>
                        <th><?= $sortLink('priority', 'Prioritas') ?></th>
                        <th>Site Location</th>
                        <th>Size (KWp)</th>
                        <th>Funding</th>
                        <th>Catatan</th>
                        <th><?= $sortLink('follow_up_date', 'Follow Up') ?></th>
                        <th><?= $sortLink('created_at', 'Dibuat') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($leads as $lead): ?>
                    <?php $simple = \App\Models\Lead::simplifiedStatus($lead['status']); $simpleLabels = ['proses' => 'Proses', 'deal' => 'Deal', 'cancel' => 'Cancel']; ?>
                    <tr>
                        <td><a href="<?= url('/leads/' . $lead['id']) ?>" class="mono"><?= e($lead['lead_code']) ?></a></td>
                        <td>
                            <div class="user-cell-name"><?= e($lead['customer_name']) ?></div>
                            <?php if ($lead['company_name']): ?><div class="user-cell-sub"><?= e($lead['company_name']) ?></div><?php endif; ?>
                        </td>
                        <td><?= !empty($lead['sales_names']) ? e(implode(' / ', array_column($lead['sales_names'], 'name'))) : '—' ?></td>
                        <td class="text-muted"><?= e($lead['type_name'] ?? '-') ?></td>
                        <td class="text-muted"><?= e($lead['system_name'] ?? '-') ?></td>
                        <td>
                            <span class="color-swatch color-swatch-<?= e($simpleLabels[$simple] === 'Proses' ? 'amber' : ($simpleLabels[$simple] === 'Deal' ? 'emerald' : 'danger')) ?>"><?= e($simpleLabels[$simple]) ?></span>
                            <div class="text-muted small"><?= e($statusMap[$lead['status']]['name'] ?? $lead['status']) ?></div>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($lead['current_position']['color']) ?>"><?= e($lead['current_position']['label']) ?></span></td>
                        <td class="text-muted"><?= e($lead['current_pic_display']) ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$lead['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$lead['priority']]['name'] ?? $lead['priority']) ?></span></td>
                        <td class="text-muted"><?= e($lead['site_location'] ?: '-') ?></td>
                        <td class="mono text-muted"><?= $lead['size_kwp'] !== null ? e(rtrim(rtrim(number_format((float) $lead['size_kwp'], 2, '.', ''), '0'), '.')) : '-' ?></td>
                        <td class="text-muted"><?= e($lead['funding_name'] ?? '-') ?></td>
                        <td class="text-muted" style="max-width:200px">
                            <?php if ($lead['notes']): ?><div class="text-truncate" title="<?= e($lead['notes']) ?>"><?= e(mb_strimwidth($lead['notes'], 0, 40, '...')) ?></div><?php endif; ?>
                            <?php if ($lead['note2']): ?><div class="text-truncate small" title="<?= e($lead['note2']) ?>"><?= e(mb_strimwidth($lead['note2'], 0, 40, '...')) ?></div><?php endif; ?>
                            <?php if (!$lead['notes'] && !$lead['note2']): ?>-<?php endif; ?>
                        </td>
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
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More"><i class="bi bi-three-dots"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li>
                                                <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/delete') ?>" data-confirm="Pindahkan lead <?= e($lead['lead_code']) ?> ke sampah?">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Hapus</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
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
