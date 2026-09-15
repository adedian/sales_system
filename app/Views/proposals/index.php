<?php
$pageTitle = 'Proposal';
$sortLink = function (string $column, string $label) use ($filters) {
    $dir = ($filters['sort'] === $column && $filters['dir'] === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(array_merge($filters, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
    $icon = '';
    if ($filters['sort'] === $column) {
        $icon = $filters['dir'] === 'asc' ? '<i class="bi bi-arrow-up"></i>' : '<i class="bi bi-arrow-down"></i>';
    }
    return '<a href="' . url('/proposals') . '?' . $qs . '" class="sort-link">' . e($label) . ' ' . $icon . '</a>';
};
$tiles = [
    'draft' => ['label' => 'Draft', 'icon' => 'bi-file-earmark'],
    'internal_review' => ['label' => 'Review Internal', 'icon' => 'bi-hourglass-split'],
    'revision' => ['label' => 'Perlu Revisi', 'icon' => 'bi-exclamation-triangle'],
    'sent' => ['label' => 'Terkirim', 'icon' => 'bi-send'],
    'negotiation' => ['label' => 'Negosiasi', 'icon' => 'bi-chat-dots'],
    'accepted' => ['label' => 'Diterima', 'icon' => 'bi-check-circle'],
];
?>
<div class="page-header">
    <h2>Proposal</h2>
    <p class="text-muted"><?= (int) $total ?> proposal ditemukan. Proposal baru dibuat lewat halaman Procurement (setelah pricing selesai) atau langsung dari Lead.</p>
</div>

<div class="stat-grid stat-grid-6">
    <?php foreach ($tiles as $code => $tile): ?>
    <a href="<?= url('/proposals') ?>?status=<?= e($code) ?><?= in_array($code, ['revision', 'accepted'], true) ? '&include_closed=1' : '' ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-<?= e($statusMap[$code]['color'] ?? 'muted') ?>"><i class="bi <?= e($tile['icon']) ?>"></i></div>
        <div class="stat-body">
            <span class="stat-label"><?= e($tile['label']) ?></span>
            <span class="stat-value" data-proposal-count="<?= e($code) ?>"><?= (int) $dashboardCounts[$code] ?></span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/proposals') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode proposal, kode lead, nama, project" value="<?= e($filters['q']) ?>">
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
                <a href="<?= url('/proposals') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($proposals)): ?>
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <p>Tidak ada proposal yang cocok.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th><?= $sortLink('proposal_code', 'Kode') ?></th>
                        <th><?= $sortLink('customer_name', 'Lead') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <th><?= $sortLink('total', 'Total') ?></th>
                        <th><?= $sortLink('sales_name', 'Sales') ?></th>
                        <th><?= $sortLink('valid_until', 'Berlaku Sampai') ?></th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($proposals as $p): ?>
                    <?php $isExpiringSoon = !empty($p['valid_until']) && $p['valid_until'] < date('Y-m-d') && !in_array($p['status'], ['accepted', 'rejected', 'expired'], true); ?>
                    <tr>
                        <td class="mono"><?= e($p['proposal_code']) ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $p['lead_id']) ?>" class="mono d-block"><?= e($p['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($p['customer_name']) ?></div>
                        </td>
                        <td>
                            <span class="color-swatch color-swatch-<?= e($statusMap[$p['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$p['status']]['name'] ?? $p['status']) ?></span>
                            <?php if ($isExpiringSoon): ?><span class="overdue-badge" title="Melewati masa berlaku"><i class="bi bi-exclamation-triangle-fill"></i></span><?php endif; ?>
                        </td>
                        <td class="mono">Rp <?= e(number_format((float) $p['total'], 0, ',', '.')) ?></td>
                        <td><?= e($p['sales_name'] ?? '—') ?></td>
                        <td class="<?= $isExpiringSoon ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $p['valid_until'] ? e(format_datetime($p['valid_until'], 'd M Y')) : '-' ?></td>
                        <td class="text-end">
                            <a href="<?= url('/proposals/' . $p['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/proposals') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/proposals') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
        function refreshCounts() {
            Api.get(base + '/api/proposals/summary').then(function (data) {
                Object.keys(data.counts || {}).forEach(function (code) {
                    var el = document.querySelector('[data-proposal-count="' + code + '"]');
                    if (el) el.textContent = data.counts[code];
                });
            }).catch(function () {});
        }
        setInterval(refreshCounts, Math.max(pollInterval, 10000));
    })();
</script>
