<?php
$pageTitle = 'Dashboard';
$rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
// Same status-color tokens as color-swatch-* in app.css, resolved to hex so
// the pipeline chart's bars carry the same meaning as the badges elsewhere
// (Follow Up = amber, Won = emerald, ...) instead of one flat blue.
$statusColorHex = ['primary' => '#2952e3', 'indigo' => '#6242c9', 'amber' => '#b7791f', 'emerald' => '#0f9d6a', 'danger' => '#d3402f', 'muted' => '#9aa4b2'];
$pipelineColors = array_map(fn ($row) => $statusColorHex[$statusMap[$row['code']]['color'] ?? 'muted'] ?? '#2952e3', $pipelineByStatus);
?>

<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e(explode(' ', auth_user()['name'] ?? '')[0] ?? '') ?></h2>
        <p class="text-muted">Ringkasan performa sales — data langsung dari database.</p>
    </div>
    <?php if (can('report.view')): ?>
    <a href="<?= url('/reports') ?>" class="btn btn-light"><i class="bi bi-graph-up-arrow me-1"></i>Lihat Laporan</a>
    <a href="<?= url('/reports/lead-monitoring') ?>" class="btn btn-light"><i class="bi bi-diagram-3-fill me-1"></i>Lihat Lead Monitoring</a>
    <?php endif; ?>
</div>

<div class="pipeline-overview">
    <div class="pipeline-overview-grid">
        <div class="pipeline-overview-item">
            <span class="pipeline-overview-label">Total Leads</span>
            <span class="pipeline-overview-value"><?= (int) $kpi['total_leads'] ?></span>
        </div>
        <div class="pipeline-overview-item">
            <span class="pipeline-overview-label">Proses</span>
            <span class="pipeline-overview-value"><?= (int) $kpi['proses_count'] ?></span>
        </div>
        <div class="pipeline-overview-item">
            <span class="pipeline-overview-label">Deal</span>
            <span class="pipeline-overview-value emerald"><?= (int) $kpi['deal_count'] ?></span>
        </div>
        <div class="pipeline-overview-item">
            <span class="pipeline-overview-label">Cancel</span>
            <span class="pipeline-overview-value danger"><?= (int) $kpi['cancel_count'] ?></span>
        </div>
    </div>
</div>

<div class="metric-strip">
    <div class="metric-strip-item">
        <span class="metric-strip-label">Pipeline Value</span>
        <span class="metric-strip-value"><?= e($rupiah($kpi['pipeline_value'])) ?></span>
    </div>
    <div class="metric-strip-item">
        <span class="metric-strip-label">Proposal Value</span>
        <span class="metric-strip-value"><?= e($rupiah($kpi['proposal_value'])) ?></span>
    </div>
    <div class="metric-strip-item">
        <span class="metric-strip-label">Won Value</span>
        <span class="metric-strip-value text-success"><?= e($rupiah($kpi['won_value'])) ?></span>
    </div>
    <div class="metric-strip-item">
        <span class="metric-strip-label">Lost Value</span>
        <span class="metric-strip-value text-danger"><?= e($rupiah($kpi['lost_value'])) ?></span>
    </div>
    <div class="metric-strip-item">
        <span class="metric-strip-label">Conversion Rate</span>
        <span class="metric-strip-value"><?= e(rtrim(rtrim(number_format($kpi['conversion_rate'], 1), '0'), '.')) ?>%</span>
    </div>
</div>

<div class="card card-elevated mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3>Active Leads</h3>
        <a href="<?= url('/leads') ?>" class="sort-link">Lihat semua <i class="bi bi-arrow-right"></i></a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($activeLeads)): ?>
            <div class="empty-state"><i class="bi bi-person-lines-fill"></i><p>Tidak ada lead yang sedang berjalan.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead><tr><th>Lead</th><th>Customer</th><th>Sales</th><th>Process</th><th>PIC</th><th>Priority</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($activeLeads as $lead): ?>
                    <tr>
                        <td class="mono"><?= e($lead['lead_code']) ?></td>
                        <td>
                            <div class="user-cell-name"><?= e($lead['customer_name']) ?></div>
                            <?php if ($lead['company_name']): ?><div class="user-cell-sub"><?= e($lead['company_name']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($lead['sales_name'] ?? '—') ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($lead['position_color']) ?>"><?= e($lead['position_label']) ?></span></td>
                        <td class="text-muted"><?= e($lead['pic_display']) ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($priorityMap[$lead['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$lead['priority']]['name'] ?? $lead['priority']) ?></span></td>
                        <td class="text-muted"><?= e(format_datetime($lead['updated_at'], 'd M Y')) ?></td>
                        <td class="text-end"><a href="<?= url('/leads/' . $lead['id']) ?>" class="btn btn-sm btn-light" title="Detail"><i class="bi bi-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-1">
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Process Distribution</h3></div>
            <div class="card-body">
                <?php
                    $distributionMax = 1;
                    foreach ($positionCounts as $pc) { $distributionMax = max($distributionMax, (int) $pc['total']); }
                    $distributionMax = max($distributionMax, (int) $notYetQueuedCount);
                ?>
                <div class="process-distribution">
                    <?php foreach ($positionCounts as $pc): ?>
                    <div class="process-distribution-item">
                        <span class="process-distribution-label"><?= e($pc['label']) ?></span>
                        <div class="process-distribution-track"><div class="process-distribution-fill color-swatch-<?= e($pc['color']) ?>" style="width:<?= (int) round(((int) $pc['total'] / $distributionMax) * 100) ?>%; background:currentColor;"></div></div>
                        <span class="process-distribution-value"><?= (int) $pc['total'] ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div class="process-distribution-item">
                        <span class="process-distribution-label text-muted">Belum Antrian</span>
                        <div class="process-distribution-track"><div class="process-distribution-fill color-swatch-muted" style="width:<?= (int) round(($notYetQueuedCount / $distributionMax) * 100) ?>%; background:currentColor;"></div></div>
                        <span class="process-distribution-value"><?= (int) $notYetQueuedCount ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Requires Attention</h3></div>
            <div class="card-body">
                <?php if (empty($attentionRequired)): ?>
                    <div class="empty-state"><i class="bi bi-check-circle"></i><p>Tidak ada lead yang butuh perhatian khusus saat ini.</p></div>
                <?php else: ?>
                <div class="attention-list">
                    <?php foreach ($attentionRequired as $a): ?>
                    <div class="attention-item">
                        <div class="attention-item-main">
                            <a href="<?= url('/leads/' . $a['id']) ?>" class="attention-item-name"><?= e($a['customer_name']) ?><?= $a['company_name'] ? ' &middot; ' . e($a['company_name']) : '' ?></a>
                            <span class="attention-item-meta">
                                <?= e($a['lead_code']) ?> &middot; <?= e($a['position_label']) ?>
                                <?= $a['is_pending_validation'] ? ' &middot; Menunggu validasi Direktur' : '' ?>
                                <?= $a['is_pending_prelim'] ? ' &middot; Menunggu ACC Prelim' : '' ?>
                                <?= $a['days_idle'] > 0 ? ' &middot; ' . (int) $a['days_idle'] . ' hari tanpa update' : '' ?>
                            </span>
                        </div>
                        <div class="attention-item-side">
                            <span class="color-swatch color-swatch-<?= e($priorityMap[$a['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$a['priority']]['name'] ?? $a['priority']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Lead Pipeline per Status</h3></div>
            <div class="card-body">
                <?php if (empty($pipelineByStatus)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i><p>Belum ada data lead.</p></div>
                <?php else: ?>
                <canvas id="pipelineChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Sales Performance (Won Value)</h3></div>
            <div class="card-body">
                <?php if (empty($salesPerformance)): ?>
                    <div class="empty-state"><i class="bi bi-bar-chart"></i><p>Belum ada akun Sales aktif.</p></div>
                <?php else: ?>
                <canvas id="salesChart" height="220"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-0">
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Sales Performance</h3></div>
            <div class="card-body p-0">
                <?php if (empty($salesPerformance)): ?>
                    <div class="empty-state"><i class="bi bi-people"></i><p>Belum ada akun Sales aktif.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Sales</th><th>Lead Aktif</th><th>Won</th><th>Lost</th><th>Conversion</th><th>Won Value</th></tr></thead>
                        <tbody>
                            <?php foreach ($salesPerformance as $sp): ?>
                            <tr>
                                <td><?= e($sp['name']) ?></td>
                                <td class="mono"><?= (int) $sp['open_leads'] ?></td>
                                <td class="mono text-success"><?= (int) $sp['won_count'] ?></td>
                                <td class="mono text-danger"><?= (int) $sp['lost_count'] ?></td>
                                <td class="mono"><?= e(rtrim(rtrim(number_format($sp['conversion_rate'], 1), '0'), '.')) ?>%</td>
                                <td class="mono"><?= e($rupiah($sp['won_value'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Aging &amp; Bottleneck</h3></div>
            <div class="card-body p-0">
                <?php if (empty($aging)): ?>
                    <div class="empty-state"><i class="bi bi-hourglass"></i><p>Tidak ada lead yang sedang berjalan.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Status</th><th>Jumlah Lead</th><th>Rata-rata Usia</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($aging as $row): ?>
                            <?php $isBottleneck = $bottleneck && $row['status'] === $bottleneck['status']; ?>
                            <?php $slaRisk = $row['avg_age_days'] > $slaDays; ?>
                            <tr class="<?= $isBottleneck ? 'table-warning' : '' ?>">
                                <td><span class="color-swatch color-swatch-<?= e($statusMap[$row['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$row['status']]['name'] ?? $row['status']) ?></span></td>
                                <td class="mono"><?= (int) $row['total'] ?></td>
                                <td class="mono <?= $slaRisk ? 'text-danger fw-semibold' : '' ?>"><?= e($row['avg_age_days']) ?> hari</td>
                                <td>
                                    <?php if ($isBottleneck): ?><span class="badge-pill badge-pill-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Bottleneck</span><?php endif; ?>
                                    <?php if ($slaRisk): ?><span class="badge-pill badge-pill-danger">Lewat SLA (<?= (int) $slaDays ?> hari)</span><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
    $workloadCards = [
        ['key' => 'surveyor', 'title' => 'Beban Kerja Tim — Surveyor', 'nameCol' => 'Surveyor', 'countCol' => 'Antrian Aktif', 'icon' => 'bi-geo-alt', 'empty' => 'Belum ada akun Surveyor aktif.'],
        ['key' => 'sales_engineer', 'title' => 'Beban Kerja Tim — Sales Engineer', 'nameCol' => 'Sales Engineer', 'countCol' => 'Assignment Aktif', 'icon' => 'bi-person-gear', 'empty' => 'Belum ada akun Sales Engineer aktif.'],
        ['key' => 'engineer', 'title' => 'Beban Kerja Tim — Engineer', 'nameCol' => 'Engineer', 'countCol' => 'Assignment Aktif', 'icon' => 'bi-tools', 'empty' => 'Belum ada akun Engineer aktif.'],
        ['key' => 'procurement', 'title' => 'Beban Kerja Tim — Procurement', 'nameCol' => 'Staff', 'countCol' => 'Request Aktif', 'icon' => 'bi-truck', 'empty' => 'Belum ada akun Procurement aktif.'],
    ];
?>
<div class="row g-3 mt-0">
    <?php foreach ($workloadCards as $card): ?>
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3><?= e($card['title']) ?></h3></div>
            <div class="card-body p-0">
                <?php if (empty($workload[$card['key']])): ?>
                    <div class="empty-state"><i class="bi <?= e($card['icon']) ?>"></i><p><?= e($card['empty']) ?></p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th><?= e($card['nameCol']) ?></th><th><?= e($card['countCol']) ?></th><th>Selesai</th></tr></thead>
                        <tbody>
                            <?php foreach ($workload[$card['key']] as $w): ?>
                            <tr>
                                <td><?= e($w['name']) ?></td>
                                <td class="mono"><?= (int) $w['active_count'] ?></td>
                                <td class="mono text-muted"><?= (int) $w['completed_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mt-0">
    <div class="col-12 col-xl-8">
        <div class="card card-elevated h-100">
            <div class="card-header">
                <h3>Aktivitas Terbaru</h3>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentActivity)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <p>Belum ada aktivitas tercatat.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>User</th>
                                    <th>Aksi</th>
                                    <th>Modul</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivity as $log): ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(format_datetime($log['created_at'])) ?></td>
                                    <td><?= e($log['user_name'] ?? 'Sistem') ?></td>
                                    <td><span class="badge-pill"><?= e($log['action']) ?></span></td>
                                    <td><?= e($log['module']) ?></td>
                                    <td class="text-muted"><?= e($log['ip_address']) ?></td>
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
        <div class="card card-elevated h-100">
            <div class="card-header">
                <h3>Roadmap Modul</h3>
            </div>
            <div class="card-body">
                <ul class="roadmap-list">
                    <li><span class="roadmap-dot done"></span> Fondasi, Auth &amp; RBAC</li>
                    <li><span class="roadmap-dot done"></span> User, Role &amp; Master Data</li>
                    <li><span class="roadmap-dot done"></span> Manajemen Lead</li>
                    <li><span class="roadmap-dot done"></span> Antrian Sales</li>
                    <li><span class="roadmap-dot done"></span> Engineer Sales</li>
                    <li><span class="roadmap-dot done"></span> Procurement</li>
                    <li><span class="roadmap-dot done"></span> Pricing &amp; Proposal</li>
                    <li><span class="roadmap-dot done"></span> Follow Up &amp; Negosiasi</li>
                    <li><span class="roadmap-dot done"></span> Deal Management</li>
                    <li><span class="roadmap-dot done"></span> Notifikasi &amp; Realtime</li>
                    <li><span class="roadmap-dot done"></span> Dashboard &amp; Analytics</li>
                    <li><span class="roadmap-dot done"></span> Reporting</li>
                    <li><span class="roadmap-dot done"></span> Master Data &amp; System Settings</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    // Small inline plugin (no extra CDN dependency) — prints the bar's value
    // above it, so the count is readable at a glance instead of only on hover.
    var valueLabelPlugin = {
        id: 'valueLabel',
        afterDatasetsDraw: function (chart) {
            var ctx = chart.ctx;
            chart.data.datasets.forEach(function (dataset, i) {
                var meta = chart.getDatasetMeta(i);
                if (meta.hidden) return;
                meta.data.forEach(function (element, index) {
                    var value = dataset.data[index];
                    if (!value) return;
                    ctx.save();
                    ctx.fillStyle = '#1b2430';
                    ctx.font = '600 12px Inter, sans-serif';
                    ctx.textAlign = 'center';
                    var pos = element.tooltipPosition();
                    if (chart.options.indexAxis === 'y') {
                        ctx.textAlign = 'left';
                        ctx.fillText(value, pos.x + 6, pos.y + 4);
                    } else {
                        ctx.fillText(value, pos.x, pos.y - 8);
                    }
                    ctx.restore();
                });
            });
        },
    };

    var pipelineEl = document.getElementById('pipelineChart');
    if (pipelineEl) {
        new Chart(pipelineEl, {
            type: 'bar',
            plugins: [valueLabelPlugin],
            data: {
                labels: <?= json_encode(array_column($pipelineByStatus, 'label'), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Jumlah Lead',
                    data: <?= json_encode(array_column($pipelineByStatus, 'total')) ?>,
                    backgroundColor: <?= json_encode($pipelineColors) ?>,
                    borderRadius: 6,
                    maxBarThickness: 42,
                }],
            },
            options: {
                layout: { padding: { top: 20 } },
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grace: '15%' },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    var salesEl = document.getElementById('salesChart');
    if (salesEl) {
        new Chart(salesEl, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($salesPerformance, 'name'), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Won Value',
                    data: <?= json_encode(array_map(fn ($r) => (float) $r['won_value'], $salesPerformance)) ?>,
                    backgroundColor: '#1aa66c',
                    borderRadius: 6,
                    maxBarThickness: 42,
                }],
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } },
            },
        });
    }
})();
</script>
