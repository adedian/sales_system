<?php
$pageTitle = 'Workspace Saya';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';

$daysLate = function (string $date): int {
    return (int) floor((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
};
?>
<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e($firstName) ?> 👋</h2>
        <p class="text-muted">Ringkasan pekerjaan Anda hari ini.</p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/leads/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Lead</a>
        <a href="<?= url('/leads') ?>" class="btn btn-light">Lead Saya</a>
        <a href="<?= url('/queue') ?>" class="btn btn-light">Antrian Saya</a>
        <a href="<?= url('/proposals') ?>" class="btn btn-light">Proposal Saya</a>
        <a href="<?= url('/follow-ups') ?>" class="btn btn-light">Follow Up Calendar</a>
    </div>
</div>

<div class="stat-grid stat-grid-6">
    <a href="<?= url('/leads') ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-person-lines-fill"></i></div>
        <div class="stat-body"><span class="stat-label">My Leads</span><span class="stat-value" data-ws-count="my_leads"><?= (int) $summary['my_leads'] ?></span></div>
    </a>
    <a href="<?= url('/queue') ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-list-ol"></i></div>
        <div class="stat-body"><span class="stat-label">My Queue</span><span class="stat-value" data-ws-count="my_queue"><?= (int) $summary['my_queue'] ?></span></div>
    </a>
    <a href="<?= url('/follow-ups') ?>" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-body"><span class="stat-label">Follow Up Today</span><span class="stat-value" data-ws-count="follow_up_today"><?= (int) $summary['follow_up_today'] ?></span></div>
    </a>
    <a href="<?= url('/queue') ?>?overdue=1" class="stat-card stat-card-compact stat-card-link <?= $summary['overdue'] > 0 ? 'stat-card-alert' : '' ?>">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body"><span class="stat-label">Overdue</span><span class="stat-value" data-ws-count="overdue"><?= (int) $summary['overdue'] ?></span></div>
    </a>
    <a href="<?= url('/queue') ?>?status=waiting_engineer" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-send"></i></div>
        <div class="stat-body"><span class="stat-label">Waiting Engineer</span><span class="stat-value" data-ws-count="waiting_engineer"><?= (int) $summary['waiting_engineer'] ?></span></div>
    </a>
    <a href="<?= url('/queue') ?>?status=done" class="stat-card stat-card-compact stat-card-link">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-check-circle"></i></div>
        <div class="stat-body"><span class="stat-label">Completed</span><span class="stat-value" data-ws-count="completed"><?= (int) $summary['completed'] ?></span></div>
    </a>
</div>

<?php if ($summary['overdue'] > 0 || $summary['follow_up_today'] > 0 || $summary['waiting_engineer'] > 0): ?>
<div class="notify-bar">
    <i class="bi bi-bell-fill"></i>
    <div class="notify-bar-items">
        <?php if ($summary['overdue'] > 0): ?><a href="<?= url('/queue') ?>?overdue=1"><?= (int) $summary['overdue'] ?> antrian melewati deadline</a><?php endif; ?>
        <?php if ($summary['follow_up_today'] > 0): ?><a href="<?= url('/follow-ups') ?>"><?= (int) $summary['follow_up_today'] ?> follow up perlu ditindaklanjuti</a><?php endif; ?>
        <?php if ($summary['waiting_engineer'] > 0): ?><a href="<?= url('/queue') ?>?status=waiting_engineer"><?= (int) $summary['waiting_engineer'] ?> antrian menunggu engineer</a><?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="notify-bar notify-bar-calm"><i class="bi bi-check-circle"></i> Semua beres — tidak ada yang mendesak hari ini.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <div class="card card-elevated" id="follow-ups">
            <div class="card-header"><h3>Follow Up &amp; Overdue</h3></div>
            <div class="card-body p-0">
                <?php if (empty($followUps)): ?>
                    <div class="empty-state"><i class="bi bi-check2-circle"></i><p>Tidak ada follow up yang jatuh tempo.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Lead</th><th>Tipe</th><th>Jatuh Tempo</th><th></th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($followUps as $f): ?>
                            <?php
                                $date = $f['follow_up_date'] ?? $f['followup_date'];
                                $late = $daysLate($date);
                                $link = $f['source'] === 'lead' ? url('/leads/' . $f['id']) : url('/queue/' . $f['id']);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= $link ?>" class="mono d-block"><?= e($f['lead_code']) ?></a>
                                    <div class="user-cell-name"><?= e($f['customer_name']) ?></div>
                                </td>
                                <td><span class="badge-pill badge-pill-muted"><?= $f['source'] === 'lead' ? 'Lead' : 'Antrian #' . (int) $f['queue_number'] ?></span></td>
                                <td class="<?= $late > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(format_datetime($date, 'd M Y')) ?></td>
                                <td><?= $late > 0 ? '<span class="overdue-badge">Terlambat ' . $late . ' hari</span>' : '<span class="badge-pill">Hari ini</span>' ?></td>
                                <td class="text-end"><a href="<?= $link ?>" class="btn btn-sm btn-light">Buka</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Proposal Saya</h3></div>
            <div class="card-body p-0">
                <?php if (empty($openProposals)): ?>
                    <div class="empty-state"><i class="bi bi-file-earmark-text"></i><p>Tidak ada proposal yang perlu ditindaklanjuti.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Kode</th><th>Lead</th><th>Status</th><th>Total</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($openProposals as $p): ?>
                            <?php $pStatus = $proposalStatusMap[$p['status']] ?? ['name' => $p['status'], 'color' => 'muted']; ?>
                            <tr>
                                <td class="mono"><?= e($p['proposal_code']) ?></td>
                                <td>
                                    <a href="<?= url('/leads/' . $p['lead_id']) ?>" class="mono d-block"><?= e($p['lead_code']) ?></a>
                                    <div class="user-cell-name"><?= e($p['customer_name']) ?></div>
                                </td>
                                <td><span class="color-swatch color-swatch-<?= e($pStatus['color']) ?>"><?= e($pStatus['name']) ?></span></td>
                                <td class="mono">Rp <?= e(number_format((float) $p['total'], 0, ',', '.')) ?></td>
                                <td class="text-end"><a href="<?= url('/proposals/' . $p['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Lead Terbaru Saya</h3></div>
            <div class="card-body p-0">
                <?php if (empty($recentLeads)): ?>
                    <div class="empty-state"><i class="bi bi-person-lines-fill"></i><p>Belum ada lead.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Kode</th><th>Customer</th><th>Status</th><th>Dibuat</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentLeads as $lead): ?>
                            <tr>
                                <td><a href="<?= url('/leads/' . $lead['id']) ?>" class="mono"><?= e($lead['lead_code']) ?></a></td>
                                <td>
                                    <div class="user-cell-name"><?= e($lead['customer_name']) ?></div>
                                    <?php if ($lead['company_name']): ?><div class="user-cell-sub"><?= e($lead['company_name']) ?></div><?php endif; ?>
                                </td>
                                <td><span class="badge-pill"><?= e(ucfirst(str_replace('_', ' ', $lead['status']))) ?></span></td>
                                <td class="text-muted"><?= e(format_datetime($lead['created_at'], 'd M Y')) ?></td>
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

        function apply(prefix, counts, map) {
            Object.keys(map).forEach(function (key) {
                var el = document.querySelector('[data-ws-count="' + prefix + key + '"]');
                if (el && counts[map[key]] !== undefined) el.textContent = counts[map[key]];
            });
        }

        function refresh() {
            Api.get(base + '/api/leads/summary').then(function (data) {
                var c = data.counts || {};
                var el = document.querySelector('[data-ws-count="my_leads"]');
                if (el) el.textContent = Object.values(c).reduce(function (a, b) { return a + b; }, 0);
            }).catch(function () {});

            Api.get(base + '/api/queue/summary').then(function (data) {
                var c = data.counts || {};
                var active = (c.new || 0) + (c.waiting_followup || 0) + (c.in_progress || 0) + (c.waiting_engineer || 0);
                var setIf = function (key, val) {
                    var el = document.querySelector('[data-ws-count="' + key + '"]');
                    if (el) el.textContent = val;
                };
                setIf('my_queue', active);
                setIf('overdue', c.overdue || 0);
                setIf('waiting_engineer', c.waiting_engineer || 0);
                setIf('completed', c.done || 0);
            }).catch(function () {});
        }

        setInterval(refresh, Math.max(pollInterval, 10000));
    })();
</script>
