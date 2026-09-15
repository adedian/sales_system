<?php
$pageTitle = 'Workspace Saya';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
?>
<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e($firstName) ?> 👋</h2>
        <p class="text-muted">Ringkasan assignment engineer Anda hari ini.</p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/engineer') ?>" class="btn btn-primary"><i class="bi bi-tools me-1"></i>Lihat Semua Assignment</a>
    </div>
</div>

<div class="stat-grid stat-grid-6">
    <a href="<?= url('/engineer') ?>?status=pending" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-muted"><i class="bi bi-inbox"></i></div>
        <div class="stat-body"><span class="stat-label">Assignment Baru</span><span class="stat-value" data-ws-count="pending"><?= (int) $summary['new_assignment'] ?></span></div>
    </a>
    <a href="<?= url('/engineer') ?>?status=accepted" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-check2"></i></div>
        <div class="stat-body"><span class="stat-label">Accepted</span><span class="stat-value" data-ws-count="accepted"><?= (int) $summary['accepted'] ?></span></div>
    </a>
    <a href="<?= url('/engineer') ?>?status=in_progress" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-arrow-repeat"></i></div>
        <div class="stat-body"><span class="stat-label">In Progress</span><span class="stat-value" data-ws-count="in_progress"><?= (int) $summary['in_progress'] ?></span></div>
    </a>
    <a href="<?= url('/engineer') ?>?status=waiting" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-body"><span class="stat-label">Waiting</span><span class="stat-value" data-ws-count="waiting"><?= (int) $summary['waiting'] ?></span></div>
    </a>
    <a href="<?= url('/engineer') ?>?status=completed&include_closed=1" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check-circle"></i></div>
        <div class="stat-body"><span class="stat-label">Completed</span><span class="stat-value" data-ws-count="completed"><?= (int) $summary['completed'] ?></span></div>
    </a>
    <a href="<?= url('/engineer') ?>?status=rejected&include_closed=1" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-x-circle"></i></div>
        <div class="stat-body"><span class="stat-label">Rejected</span><span class="stat-value" data-ws-count="rejected"><?= (int) $summary['rejected'] ?></span></div>
    </a>
</div>

<?php if ($summary['new_assignment'] > 0 || $summary['overdue'] > 0): ?>
<div class="notify-bar">
    <i class="bi bi-bell-fill"></i>
    <div class="notify-bar-items">
        <?php if ($summary['new_assignment'] > 0): ?><a href="<?= url('/engineer') ?>?status=pending"><?= (int) $summary['new_assignment'] ?> assignment baru menunggu respon</a><?php endif; ?>
        <?php if ($summary['overdue'] > 0): ?><a href="<?= url('/engineer') ?>?overdue=1"><?= (int) $summary['overdue'] ?> assignment melewati deadline</a><?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="notify-bar notify-bar-calm"><i class="bi bi-check-circle"></i> Semua beres — tidak ada yang mendesak hari ini.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Assignment Perlu Ditindaklanjuti</h3></div>
            <div class="card-body p-0">
                <?php if (empty($openAssignments)): ?>
                    <div class="empty-state"><i class="bi bi-check2-circle"></i><p>Tidak ada assignment yang perlu ditindaklanjuti.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Kode</th><th>Lead</th><th>Status</th><th>Prioritas</th><th>Deadline</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($openAssignments as $a): ?>
                            <?php $isOverdue = !empty($a['deadline']) && $a['deadline'] < date('Y-m-d'); ?>
                            <tr>
                                <td class="mono"><?= e($a['assignment_code']) ?></td>
                                <td>
                                    <a href="<?= url('/leads/' . $a['lead_id']) ?>" class="mono d-block"><?= e($a['lead_code']) ?></a>
                                    <div class="user-cell-name"><?= e($a['customer_name']) ?></div>
                                </td>
                                <td><span class="color-swatch color-swatch-<?= e($statusMap[$a['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$a['status']]['name'] ?? $a['status']) ?></span></td>
                                <td><span class="color-swatch color-swatch-<?= e($priorityMap[$a['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$a['priority']]['name'] ?? $a['priority']) ?></span></td>
                                <td class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>"><?= $a['deadline'] ? e(format_datetime($a['deadline'], 'd M Y')) : '-' ?></td>
                                <td class="text-end"><a href="<?= url('/engineer/' . $a['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
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
            Api.get(base + '/api/engineer/summary').then(function (data) {
                var c = data.counts || {};
                var setIf = function (key, val) {
                    var el = document.querySelector('[data-ws-count="' + key + '"]');
                    if (el) el.textContent = val;
                };
                setIf('pending', c.pending || 0);
                setIf('accepted', c.accepted || 0);
                setIf('in_progress', c.in_progress || 0);
                setIf('waiting', c.waiting || 0);
                setIf('completed', c.completed || 0);
                setIf('rejected', c.rejected || 0);
            }).catch(function () {});
        }

        setInterval(refresh, Math.max(pollInterval, 10000));
    })();
</script>
