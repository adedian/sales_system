<?php
$pageTitle = 'Dashboard';
$rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
?>

<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e(explode(' ', auth_user()['name'] ?? '')[0] ?? '') ?> 👋</h2>
        <p class="text-muted">Ringkasan performa sales — data langsung dari database.</p>
    </div>
    <?php if (can('report.view')): ?>
    <a href="<?= url('/reports') ?>" class="btn btn-light"><i class="bi bi-graph-up-arrow me-1"></i>Lihat Laporan</a>
    <?php endif; ?>
</div>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-person-lines-fill"></i></div>
        <div class="stat-body">
            <span class="stat-label">Total Leads</span>
            <span class="stat-value"><?= (int) $kpi['total_leads'] ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-funnel-fill"></i></div>
        <div class="stat-body">
            <span class="stat-label">Pipeline Value</span>
            <span class="stat-value" style="font-size:17px"><?= e($rupiah($kpi['pipeline_value'])) ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-file-earmark-text-fill"></i></div>
        <div class="stat-body">
            <span class="stat-label">Proposal Value</span>
            <span class="stat-value" style="font-size:17px"><?= e($rupiah($kpi['proposal_value'])) ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald"><i class="bi bi-trophy-fill"></i></div>
        <div class="stat-body">
            <span class="stat-label">Won Value</span>
            <span class="stat-value" style="font-size:17px"><?= e($rupiah($kpi['won_value'])) ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-x-circle-fill"></i></div>
        <div class="stat-body">
            <span class="stat-label">Lost Value</span>
            <span class="stat-value" style="font-size:17px"><?= e($rupiah($kpi['lost_value'])) ?></span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-body">
            <span class="stat-label">Conversion Rate</span>
            <span class="stat-value"><?= e(rtrim(rtrim(number_format($kpi['conversion_rate'], 1), '0'), '.')) ?>%</span>
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
                            <?php $slaRisk = $row['avg_age_days'] > 7; ?>
                            <tr class="<?= $isBottleneck ? 'table-warning' : '' ?>">
                                <td><span class="color-swatch color-swatch-<?= e($statusMap[$row['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$row['status']]['name'] ?? $row['status']) ?></span></td>
                                <td class="mono"><?= (int) $row['total'] ?></td>
                                <td class="mono <?= $slaRisk ? 'text-danger fw-semibold' : '' ?>"><?= e($row['avg_age_days']) ?> hari</td>
                                <td>
                                    <?php if ($isBottleneck): ?><span class="badge-pill badge-pill-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Bottleneck</span><?php endif; ?>
                                    <?php if ($slaRisk): ?><span class="badge-pill badge-pill-danger">Lewat SLA (7 hari)</span><?php endif; ?>
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

<div class="row g-3 mt-0">
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Beban Kerja Tim — Engineer</h3></div>
            <div class="card-body p-0">
                <?php if (empty($workload['engineer'])): ?>
                    <div class="empty-state"><i class="bi bi-tools"></i><p>Belum ada akun Engineer Sales aktif.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Engineer</th><th>Assignment Aktif</th><th>Selesai</th></tr></thead>
                        <tbody>
                            <?php foreach ($workload['engineer'] as $w): ?>
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
    <div class="col-12 col-xl-6">
        <div class="card card-elevated h-100">
            <div class="card-header"><h3>Beban Kerja Tim — Procurement</h3></div>
            <div class="card-body p-0">
                <?php if (empty($workload['procurement'])): ?>
                    <div class="empty-state"><i class="bi bi-truck"></i><p>Belum ada akun Procurement aktif.</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Staff</th><th>Request Aktif</th><th>Selesai</th></tr></thead>
                        <tbody>
                            <?php foreach ($workload['procurement'] as $w): ?>
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
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    var pipelineEl = document.getElementById('pipelineChart');
    if (pipelineEl) {
        new Chart(pipelineEl, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($pipelineByStatus, 'label'), JSON_UNESCAPED_UNICODE) ?>,
                datasets: [{
                    label: 'Jumlah Lead',
                    data: <?= json_encode(array_column($pipelineByStatus, 'total')) ?>,
                    backgroundColor: '#2952e3',
                    borderRadius: 6,
                    maxBarThickness: 42,
                }],
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
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
