<?php
$pageTitle = 'Workspace Saya';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
?>
<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e($firstName) ?> 👋</h2>
        <p class="text-muted">Ringkasan request procurement Anda hari ini.</p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/procurement') ?>" class="btn btn-primary"><i class="bi bi-truck me-1"></i>Lihat Semua Request</a>
    </div>
</div>

<div class="stat-grid stat-grid-6">
    <a href="<?= url('/procurement') ?>?status=waiting" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-muted"><i class="bi bi-inbox"></i></div>
        <div class="stat-body"><span class="stat-label">Request Baru</span><span class="stat-value" data-ws-count="new_requests"><?= (int) $summary['new_requests'] ?></span></div>
    </a>
    <a href="<?= url('/procurement') ?>?status=in_progress" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-arrow-repeat"></i></div>
        <div class="stat-body"><span class="stat-label">In Progress</span><span class="stat-value" data-ws-count="in_progress"><?= (int) $summary['in_progress'] ?></span></div>
    </a>
    <a href="<?= url('/procurement') ?>?status=quotation_requested" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-body"><span class="stat-label">Waiting Quotation</span><span class="stat-value" data-ws-count="quotation_requested"><?= (int) $summary['quotation_requested'] ?></span></div>
    </a>
    <a href="<?= url('/procurement') ?>?status=need_revision&include_closed=1" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body"><span class="stat-label">Need Revision</span><span class="stat-value" data-ws-count="need_revision"><?= (int) $summary['need_revision'] ?></span></div>
    </a>
    <a href="<?= url('/procurement') ?>?status=pricing_completed&include_closed=1" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check-circle"></i></div>
        <div class="stat-body"><span class="stat-label">Completed</span><span class="stat-value" data-ws-count="completed"><?= (int) $summary['completed'] ?></span></div>
    </a>
    <a href="<?= url('/procurement') ?>?overdue=1" class="stat-card stat-card-compact stat-card-link <?= $summary['overdue'] > 0 ? 'stat-card-alert' : '' ?>">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body"><span class="stat-label">Overdue</span><span class="stat-value" data-ws-count="overdue"><?= (int) $summary['overdue'] ?></span></div>
    </a>
</div>

<?php if ($summary['new_requests'] > 0 || $summary['overdue'] > 0): ?>
<div class="notify-bar">
    <i class="bi bi-bell-fill"></i>
    <div class="notify-bar-items">
        <?php if ($summary['new_requests'] > 0): ?><a href="<?= url('/procurement') ?>?status=waiting"><?= (int) $summary['new_requests'] ?> request baru menunggu diproses</a><?php endif; ?>
        <?php if ($summary['overdue'] > 0): ?><a href="<?= url('/procurement') ?>?overdue=1"><?= (int) $summary['overdue'] ?> request melewati deadline</a><?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="notify-bar notify-bar-calm"><i class="bi bi-check-circle"></i> Semua beres — tidak ada yang mendesak hari ini.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Request Perlu Ditindaklanjuti</h3></div>
            <div class="card-body p-0">
                <?php if (empty($openRequests)): ?>
                    <div class="empty-state"><i class="bi bi-check2-circle"></i><p>Tidak ada request yang perlu ditindaklanjuti.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Kode</th><th>Lead</th><th>Status</th><th>Prioritas</th><th>Deadline</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($openRequests as $r): ?>
                            <?php $isOverdue = !empty($r['deadline']) && $r['deadline'] < date('Y-m-d'); ?>
                            <tr>
                                <td class="mono"><?= e($r['request_code']) ?></td>
                                <td>
                                    <a href="<?= url('/leads/' . $r['lead_id']) ?>" class="mono d-block"><?= e($r['lead_code']) ?></a>
                                    <div class="user-cell-name"><?= e($r['customer_name']) ?></div>
                                </td>
                                <td><span class="color-swatch color-swatch-<?= e($statusMap[$r['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$r['status']]['name'] ?? $r['status']) ?></span></td>
                                <td><span class="color-swatch color-swatch-<?= e($priorityMap[$r['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$r['priority']]['name'] ?? $r['priority']) ?></span></td>
                                <td class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $r['deadline'] ? e(format_datetime($r['deadline'], 'd M Y')) : '-' ?></td>
                                <td class="text-end"><a href="<?= url('/procurement/' . $r['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card card-elevated">
            <div class="card-header"><h3>Aktivitas Saya</h3></div>
            <div class="card-body p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="empty-state"><i class="bi bi-clock-history"></i><p>Belum ada aktivitas.</p></div>
                <?php else: ?>
                <div class="timeline timeline-compact p-3">
                    <?php foreach ($recentActivity as $log): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-body">
                            <div class="timeline-title"><?= e($log['action']) ?> <span class="text-muted">&middot; <?= e($log['module']) ?></span></div>
                            <div class="timeline-meta"><?= e(format_datetime($log['created_at'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
        var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');

        function refresh() {
            Api.get(base + '/api/procurement/summary').then(function (data) {
                var c = data.counts || {};
                var setIf = function (key, val) {
                    var el = document.querySelector('[data-ws-count="' + key + '"]');
                    if (el) el.textContent = val;
                };
                setIf('new_requests', c.waiting || 0);
                setIf('in_progress', c.in_progress || 0);
                setIf('quotation_requested', c.quotation_requested || 0);
                setIf('need_revision', c.need_revision || 0);
                setIf('completed', c.pricing_completed || 0);
                setIf('overdue', c.overdue || 0);
            }).catch(function () {});
        }

        setInterval(refresh, Math.max(pollInterval, 10000));
    })();
</script>
