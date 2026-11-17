<?php
$pageTitle = 'Dashboard';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
$rupiah = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
// Nilai besar dipendekkan agar muat di kartu KPI (Rp 1,25 M / Rp 340 jt).
$rupiahShort = function ($v) {
    $v = (float) $v;
    if ($v >= 1e12) return ['Rp', rtrim(rtrim(number_format($v / 1e12, 2, ',', '.'), '0'), ',') . ' T'];
    if ($v >= 1e9) return ['Rp', rtrim(rtrim(number_format($v / 1e9, 2, ',', '.'), '0'), ',') . ' M'];
    if ($v >= 1e6) return ['Rp', rtrim(rtrim(number_format($v / 1e6, 1, ',', '.'), '0'), ',') . ' jt'];
    return ['Rp', number_format($v, 0, ',', '.')];
};
$deltaChip = function (?int $d, string $suffix = '') {
    if ($d === null) return '<span class="delta-chip"><i class="bi bi-stars"></i>Baru' . $suffix . '</span>';
    if ($d === 0) return '<span class="delta-chip flat"><i class="bi bi-dash"></i>0%' . $suffix . '</span>';
    return $d > 0
        ? '<span class="delta-chip"><i class="bi bi-arrow-up"></i>' . $d . '%' . $suffix . '</span>'
        : '<span class="delta-chip down"><i class="bi bi-arrow-down"></i>' . abs($d) . '%' . $suffix . '</span>';
};
$fmtRate = fn ($r) => rtrim(rtrim(number_format((float) $r, 1), '0'), '.');

[$wonCur, $wonNum] = $rupiahShort($kpi['won_value']);
[$pipCur, $pipNum] = $rupiahShort($kpi['pipeline_value']);

// Gauge: setengah lingkaran bersegmen; terisi = porsi lead yang masih berjalan.
$gaugeN = 26;
$gaugeFilled = $kpi['total_leads'] > 0 ? (int) round(($kpi['proses_count'] / $kpi['total_leads']) * $gaugeN) : 0;
$gaugeShare = $kpi['total_leads'] > 0 ? (int) round(($kpi['proses_count'] / $kpi['total_leads']) * 100) : 0;
$lerp = fn ($a, $b, $t) => (int) round($a + ($b - $a) * $t);
$gaugeSegs = [];
for ($i = 0; $i < $gaugeN; $i++) {
    $th = M_PI - $i * (M_PI / ($gaugeN - 1));
    $x1 = 170 + 104 * cos($th); $y1 = 168 - 104 * sin($th);
    $x2 = 170 + 150 * cos($th); $y2 = 168 - 150 * sin($th);
    $t = $i / max(1, $gaugeN - 1);
    $color = $i < $gaugeFilled ? sprintf('#%02x%02x%02x', $lerp(0x3f, 0x2a, $t), $lerp(0xcf, 0xa9, $t), $lerp(0xb0, 0xdb, $t)) : '#dfe8ef';
    $gaugeSegs[] = sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s"/>', $x1, $y1, $x2, $y2, $color);
}

$avatarCls = ['', 'c2', 'c3', 'c4'];
$weekJson = json_encode($week, JSON_UNESCAPED_UNICODE);
$todayIdx = count($week['days']) - 1;
?>

<div class="page-header page-header-row">
    <div>
        <h2>Ringkasan Penjualan</h2>
        <p class="text-muted">Halo, <?= e($firstName) ?> — kondisi pipeline dan aktivitas tim hari ini.</p>
    </div>
    <div class="page-actions">
        <?php if (can('report.view')): ?>
        <div class="dropdown">
            <button type="button" class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Laporan</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= url('/reports/lead-monitoring') ?>"><i class="bi bi-diagram-3 me-2"></i>Lead Monitoring</a></li>
                <li><a class="dropdown-item" href="<?= url('/reports/performance') ?>"><i class="bi bi-people me-2"></i>Kinerja Sales</a></li>
                <li><a class="dropdown-item" href="<?= url('/reports/deals') ?>"><i class="bi bi-trophy me-2"></i>Deal Won / Lost</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= url('/reports') ?>">Semua laporan</a></li>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (can('report.export')): ?>
        <a href="<?= url('/reports/leads') ?>?format=csv" class="btn btn-light"><i class="bi bi-box-arrow-up me-1"></i>Ekspor</a>
        <?php endif; ?>
        <?php if (can('lead.create')): ?>
        <a href="<?= url('/leads/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Lead Baru</a>
        <?php endif; ?>
    </div>
</div>

<!-- BARIS 1 — angka utama -->
<div class="dash-grid mb-4">
    <div class="dash-col-4 dash-col-first">
        <div class="kpi-stack h-100">
            <div class="kpi-card kpi-card-navy card-navy">
                <div class="kpi-card-head">
                    <span class="kpi-card-title">Nilai Deal (Won)</span>
                    <a href="<?= url('/leads') ?>?status=won" class="card-link-btn" title="Lihat lead Won"><i class="bi bi-arrow-up-right"></i></a>
                </div>
                <div class="kpi-card-value"><span><span class="unit"><?= e($wonCur) ?></span> <?= e($wonNum) ?></span> <?= $deltaChip($wonMonth['delta']) ?></div>
                <div class="kpi-card-note">Bulan ini <?= e($rupiah($wonMonth['this'])) ?> &middot; bulan lalu <?= e($rupiah($wonMonth['last'])) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-card-head">
                    <span class="kpi-card-title">Nilai Pipeline</span>
                    <a href="<?= url('/leads') ?>?simple_status=proses" class="card-link-btn" title="Lihat lead berjalan"><i class="bi bi-arrow-up-right"></i></a>
                </div>
                <div class="kpi-card-value"><span><span class="unit"><?= e($pipCur) ?></span> <?= e($pipNum) ?></span> <span class="delta-chip flat"><?= (int) $kpi['proses_count'] ?> lead aktif</span></div>
                <div class="kpi-card-note">Proposal terbuka: <?= e($rupiah($kpi['proposal_value'])) ?></div>
            </div>
        </div>
    </div>

    <div class="dash-col-4">
        <div class="card h-100">
            <div class="card-header">
                <h3>Pertumbuhan Lead</h3>
                <a href="<?= url('/leads') ?>" class="card-link-btn" title="Lihat semua lead"><i class="bi bi-arrow-up-right"></i></a>
            </div>
            <div class="card-body pt-2">
                <div class="pill-tabs" id="growthTabs">
                    <?php $gi = 0; foreach ($growth as $days => $g): ?>
                    <button type="button" class="pill-tab <?= $gi++ === 1 ? 'active' : '' ?>" data-days="<?= (int) $days ?>"><?= e($g['label']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="growth-value mt-4"><span id="growthCount"><?= (int) $growth[30]['count'] ?></span> <span id="growthDelta"><?= $deltaChip($growth[30]['delta']) ?></span></div>
                <div class="kpi-card-note mt-1">lead baru pada periode terpilih &middot; <strong><?= (int) $newToday ?></strong> masuk hari ini</div>
                <div class="growth-bar"><div class="growth-bar-fill" style="width: <?= max(0, min(100, (float) $kpi['conversion_rate'])) ?>%"></div></div>
                <div class="growth-meta">
                    <span>Konversi (Deal ÷ Deal + Cancel)</span>
                    <strong><?= e($fmtRate($kpi['conversion_rate'])) ?>%</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-col-4">
        <div class="card h-100">
            <div class="card-header">
                <h3>Lead Dalam Proses</h3>
                <a href="<?= url('/leads') ?>?simple_status=proses" class="card-link-btn" title="Lihat lead berjalan"><i class="bi bi-arrow-up-right"></i></a>
            </div>
            <div class="card-body pt-2">
                <div class="gauge-wrap">
                    <svg viewBox="0 0 340 186" role="img" aria-label="<?= (int) $gaugeShare ?>% lead masih berjalan">
                        <g stroke-width="10" stroke-linecap="round"><?= implode('', $gaugeSegs) ?></g>
                    </svg>
                    <div class="gauge-center">
                        <div class="gauge-value"><?= (int) $kpi['proses_count'] ?></div>
                        <div class="gauge-label">dari <?= (int) $kpi['total_leads'] ?> total lead</div>
                    </div>
                </div>
                <div class="gauge-foot">
                    <span><?= (int) $kpi['deal_count'] ?> deal &middot; <?= (int) $kpi['cancel_count'] ?> cancel</span>
                    <span class="delta-chip flat"><?= (int) $gaugeShare ?>% masih berjalan</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 2 — tren mingguan + yang perlu tindakan -->
<div class="dash-grid mb-4">
    <div class="dash-col-7">
        <div class="card h-100">
            <div class="card-header">
                <h3>Statistik Mingguan</h3>
                <div class="pill-tabs" id="weekTabs">
                    <?php $wi = 0; foreach ($week['metrics'] as $key => $m): ?>
                    <button type="button" class="pill-tab <?= $wi++ === 0 ? 'active' : '' ?>" data-metric="<?= e($key) ?>"><?= e($m['label']) ?></button>
                    <?php endforeach; ?>
                    <span class="pill-tab pill-tab-static">7 hari terakhir</span>
                </div>
            </div>
            <div class="card-body">
                <div class="week-stats">
                    <div class="week-stats-big">
                        <div class="big" id="weekBig">0</div>
                        <p id="weekDesc"></p>
                    </div>
                    <div class="week-bars" id="weekBars"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dash-col-5">
        <div class="card card-map h-100">
            <div class="card-header">
                <h3>Perlu Perhatian</h3>
                <a href="<?= url('/reports/lead-monitoring') ?>" class="card-link-btn" title="Lead Monitoring"><i class="bi bi-arrow-up-right"></i></a>
            </div>
            <?php if (empty($attentionRequired)): ?>
                <div class="attn-empty"><i class="bi bi-check-circle"></i>Semua lead berjalan lancar.<br><small class="text-muted">Tidak ada yang urgent, tertahan, atau menunggu validasi.</small></div>
            <?php else: ?>
            <span class="map-pin"><i class="bi bi-exclamation-lg"></i></span>
            <div class="attn-list">
                <?php foreach ($attentionRequired as $i => $a): ?>
                <?php
                    if ($a['priority'] === 'urgent') { $tag = 'Urgent'; $tagCls = 'urgent'; }
                    elseif ($a['is_pending_validation']) { $tag = 'Validasi Direktur'; $tagCls = 'warn'; }
                    elseif ($a['is_pending_prelim']) { $tag = 'Tunggu ACC'; $tagCls = 'warn'; }
                    else { $tag = (int) $a['days_idle'] . ' hari idle'; $tagCls = ''; }
                    $metaBits = array_filter([$a['lead_code'], $a['position_label'], ($a['days_idle'] > 0 && $tagCls !== '') ? (int) $a['days_idle'] . ' hari tanpa update' : null]);
                ?>
                <div class="attn-row">
                    <a href="<?= url('/leads/' . $a['id']) ?>" class="attn-main">
                        <span class="attn-avatar <?= e($avatarCls[$i % 4]) ?>"><?= e(strtoupper(mb_substr($a['customer_name'], 0, 1))) ?></span>
                        <span class="attn-text">
                            <strong><?= e($a['customer_name']) ?><?= $a['company_name'] ? ' · ' . e($a['company_name']) : '' ?></strong>
                            <span><?= e(implode(' · ', $metaBits)) ?></span>
                        </span>
                    </a>
                    <span class="attn-tag <?= e($tagCls) ?>"><?= e($tag) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- BARIS 3 — alur proses -->
<div class="card mb-4">
    <div class="card-header">
        <div>
            <h3>Alur Proses Lead</h3>
            <div class="sub">Posisi lead yang sedang berjalan, berurutan dari awal hingga deal. Klik tahap untuk membuka modulnya.</div>
        </div>
    </div>
    <div class="card-body p-0 mt-3">
        <div class="flow-strip">
            <?php foreach ($flowStages as $i => $st): ?>
                <?php
                    $tag = $st['can'] ? 'a' : 'div';
                    $href = $st['can'] ? ' href="' . e(url($st['url'])) . '"' : '';
                    $isPeak = $st['total'] > 0 && $st['total'] === $flowMax;
                ?>
                <<?= $tag ?><?= $href ?> class="flow-step <?= $isPeak ? 'is-peak' : '' ?>">
                    <span class="flow-step-num"><b><?= $i + 1 ?></b>Tahap <?= $i + 1 ?></span>
                    <span class="flow-step-label"><?= e($st['label']) ?></span>
                    <span class="flow-step-hint"><?= e($st['hint']) ?></span>
                    <span class="flow-step-value"><?= (int) $st['total'] ?></span>
                    <span class="flow-step-bar"><i style="width: <?= (int) round(($st['total'] / $flowMax) * 100) ?>%"></i></span>
                </<?= $tag ?>>
                <span class="flow-arrow"><i class="bi bi-chevron-right"></i></span>
            <?php endforeach; ?>
            <a href="<?= url('/leads') ?>?simple_status=deal" class="flow-step is-end">
                <span class="flow-step-num"><b><i class="bi bi-check-lg"></i></b>Selesai</span>
                <span class="flow-step-label">Deal</span>
                <span class="flow-step-hint">Lead berhasil closing</span>
                <span class="flow-step-value"><?= (int) $kpi['deal_count'] ?></span>
                <span class="flow-step-bar"><i style="width:100%"></i></span>
            </a>
        </div>
    </div>
</div>

<!-- BARIS 4 — detail analitik dalam satu kartu bertab -->
<?php
    $workloadCards = [
        ['key' => 'surveyor', 'role' => 'Surveyor', 'count' => 'Antrian aktif'],
        ['key' => 'sales_engineer', 'role' => 'Sales Engineer', 'count' => 'Assignment aktif'],
        ['key' => 'engineer', 'role' => 'Engineer', 'count' => 'Assignment aktif'],
        ['key' => 'procurement', 'role' => 'Procurement', 'count' => 'Request aktif'],
    ];
    $maxWon = 1;
    foreach ($salesPerformance as $sp) { $maxWon = max($maxWon, (float) $sp['won_value']); }
?>
<div class="card tab-card">
    <div class="card-header">
        <h3>Detail Analitik</h3>
        <div class="pill-tabs">
            <button type="button" class="pill-tab active" data-tab-group="detail" data-tab-target="tab-active">Lead Aktif</button>
            <button type="button" class="pill-tab" data-tab-group="detail" data-tab-target="tab-sales">Kinerja Sales</button>
            <button type="button" class="pill-tab" data-tab-group="detail" data-tab-target="tab-aging">Usia &amp; Hambatan</button>
            <button type="button" class="pill-tab" data-tab-group="detail" data-tab-target="tab-team">Beban Kerja Tim</button>
            <button type="button" class="pill-tab" data-tab-group="detail" data-tab-target="tab-log">Aktivitas Terbaru</button>
        </div>
    </div>
    <div class="card-body p-0">

        <div class="tab-pane-x active" id="tab-active" data-tab-pane="detail">
            <?php if (empty($activeLeads)): ?>
                <div class="empty-state"><i class="bi bi-person-lines-fill"></i><p>Tidak ada lead yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead><tr><th>Lead</th><th>Sales</th><th>Proses Saat Ini</th><th>Prioritas</th><th>Update</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($activeLeads as $lead): ?>
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <span class="user-cell-name"><?= e($lead['customer_name']) ?></span>
                                    <span class="cell-sub"><a href="<?= url('/leads/' . $lead['id']) ?>" class="mono"><?= e($lead['lead_code']) ?></a><?= $lead['company_name'] ? ' · ' . e($lead['company_name']) : '' ?></span>
                                </div>
                            </td>
                            <td class="text-muted"><?= e($lead['sales_name'] ?? '—') ?></td>
                            <td>
                                <div class="cell-stack">
                                    <span><span class="color-swatch color-swatch-<?= e($lead['position_color']) ?>"><?= e($lead['position_label']) ?></span></span>
                                    <span class="cell-sub-faint">PIC: <?= e($lead['pic_display']) ?></span>
                                </div>
                            </td>
                            <td><span class="color-swatch color-swatch-<?= e($priorityMap[$lead['priority']]['color'] ?? 'muted') ?>"><?= e($priorityMap[$lead['priority']]['name'] ?? $lead['priority']) ?></span></td>
                            <td class="text-muted"><?= e(format_datetime($lead['updated_at'], 'd M Y')) ?></td>
                            <td class="text-end"><a href="<?= url('/leads/' . $lead['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end"><a href="<?= url('/leads') ?>" class="fw-semibold">Lihat semua lead <i class="bi bi-arrow-right"></i></a></div>
            <?php endif; ?>
        </div>

        <div class="tab-pane-x" id="tab-sales" data-tab-pane="detail">
            <?php if (empty($salesPerformance)): ?>
                <div class="empty-state"><i class="bi bi-people"></i><p>Belum ada akun Sales aktif.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead><tr><th>Sales</th><th>Lead Aktif</th><th>Menang</th><th>Kalah</th><th>Konversi</th><th style="min-width:220px">Nilai Menang</th></tr></thead>
                    <tbody>
                        <?php foreach ($salesPerformance as $sp): ?>
                        <tr>
                            <td><div class="user-cell"><span class="user-avatar"><?= e(strtoupper(mb_substr($sp['name'], 0, 1))) ?></span><span class="user-cell-name"><?= e($sp['name']) ?></span></div></td>
                            <td class="mono"><?= (int) $sp['open_leads'] ?></td>
                            <td class="mono text-success"><?= (int) $sp['won_count'] ?></td>
                            <td class="mono text-danger"><?= (int) $sp['lost_count'] ?></td>
                            <td class="mono"><?= e($fmtRate($sp['conversion_rate'])) ?>%</td>
                            <td>
                                <div class="bar-cell">
                                    <div class="bar-cell-track"><i style="width: <?= (int) round(((float) $sp['won_value'] / $maxWon) * 100) ?>%"></i></div>
                                    <span class="text-muted small text-nowrap"><?= e($rupiah($sp['won_value'])) ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane-x" id="tab-aging" data-tab-pane="detail">
            <?php if (empty($aging)): ?>
                <div class="empty-state"><i class="bi bi-hourglass"></i><p>Tidak ada lead yang sedang berjalan.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead><tr><th>Status</th><th>Jumlah Lead</th><th>Rata-rata Usia</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($aging as $row): ?>
                        <?php $isBottleneck = $bottleneck && $row['status'] === $bottleneck['status']; ?>
                        <?php $slaRisk = $row['avg_age_days'] > $slaDays; ?>
                        <tr>
                            <td><span class="color-swatch color-swatch-<?= e($statusMap[$row['status']]['color'] ?? 'muted') ?>"><?= e($statusMap[$row['status']]['name'] ?? $row['status']) ?></span></td>
                            <td class="mono"><?= (int) $row['total'] ?></td>
                            <td class="mono <?= $slaRisk ? 'text-danger' : '' ?>"><?= e($row['avg_age_days']) ?> hari</td>
                            <td>
                                <?php if ($isBottleneck): ?><span class="badge-pill badge-pill-amber"><i class="bi bi-exclamation-triangle-fill me-1"></i>Hambatan</span><?php endif; ?>
                                <?php if ($slaRisk): ?><span class="badge-pill badge-pill-danger">Lewat SLA (<?= (int) $slaDays ?> hari)</span><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane-x" id="tab-team" data-tab-pane="detail">
            <div class="team-grid">
                <?php foreach ($workloadCards as $wc): ?>
                <div class="team-col">
                    <div class="team-col-title"><?= e($wc['role']) ?></div>
                    <?php if (empty($workload[$wc['key']])): ?>
                        <div class="empty-state py-4"><p class="small">Belum ada akun <?= e($wc['role']) ?> aktif.</p></div>
                    <?php else: ?>
                    <table class="table table-modern">
                        <thead><tr><th><?= e($wc['count']) ?></th><th class="text-end">Selesai</th></tr></thead>
                        <tbody>
                            <?php foreach ($workload[$wc['key']] as $w): ?>
                            <tr>
                                <td><div class="d-flex justify-content-between align-items-center gap-3"><span><?= e($w['name']) ?></span><span class="mono"><?= (int) $w['active_count'] ?></span></div></td>
                                <td class="mono text-muted text-end"><?= (int) $w['completed_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="tab-pane-x" id="tab-log" data-tab-pane="detail">
            <?php if (empty($recentActivity)): ?>
                <div class="empty-state"><i class="bi bi-inbox"></i><p>Belum ada aktivitas tercatat.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern">
                    <thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Modul</th></tr></thead>
                    <tbody>
                        <?php foreach ($recentActivity as $log): ?>
                        <tr>
                            <td class="text-nowrap text-muted"><?= e(format_datetime($log['created_at'])) ?></td>
                            <td><?= e($log['user_name'] ?? 'Sistem') ?></td>
                            <td><span class="badge-pill"><?= e(audit_label((string) $log['action'])) ?></span></td>
                            <td><?= e($log['module']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    // --- Pertumbuhan Lead: ganti periode ---
    var growth = <?= json_encode(array_map(fn ($g) => ['count' => $g['count'], 'delta' => $g['delta']], $growth)) ?>;
    var countEl = document.getElementById('growthCount');
    var deltaEl = document.getElementById('growthDelta');
    function chip(d) {
        if (d === null) return '<span class="delta-chip"><i class="bi bi-stars"></i>Baru</span>';
        if (d === 0) return '<span class="delta-chip flat"><i class="bi bi-dash"></i>0%</span>';
        return d > 0 ? '<span class="delta-chip"><i class="bi bi-arrow-up"></i>' + d + '%</span>'
                     : '<span class="delta-chip down"><i class="bi bi-arrow-down"></i>' + Math.abs(d) + '%</span>';
    }
    document.querySelectorAll('#growthTabs .pill-tab').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('#growthTabs .pill-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
            var g = growth[t.getAttribute('data-days')];
            countEl.textContent = g.count;
            deltaEl.innerHTML = chip(g.delta);
        });
    });

    // --- Statistik Mingguan ---
    var week = <?= $weekJson ?>;
    var todayIdx = <?= (int) $todayIdx ?>;
    var barsEl = document.getElementById('weekBars');
    var bigEl = document.getElementById('weekBig');
    var descEl = document.getElementById('weekDesc');
    function renderWeek(key) {
        var m = week.metrics[key];
        var max = Math.max.apply(null, m.values.concat([1]));
        var peak = Math.max.apply(null, m.values);
        barsEl.innerHTML = m.values.map(function (v, i) {
            var h = 24 + Math.round((v / max) * 136);
            var isPeak = v > 0 && v === peak;
            return '<div class="week-bar-col">' +
                '<span class="week-bar-chip' + (v === 0 ? ' zero' : '') + (isPeak ? ' peak' : '') + '">' + v + '</span>' +
                '<div class="week-bar' + (isPeak ? ' peak' : '') + '" style="height:' + h + 'px"></div>' +
                '<span class="week-bar-day' + (i === todayIdx ? ' today' : '') + '">' + week.day_labels[i] + '</span></div>';
        }).join('');
        var d = m.delta;
        bigEl.textContent = d === null ? '+' + m.total : (d > 0 ? '+' + d + '%' : d + '%');
        descEl.textContent = m.total + ' ' + m.noun + ' dalam 7 hari terakhir' +
            (m.prev_total > 0 ? ', dibanding ' + m.prev_total + ' di minggu sebelumnya.' : '. Minggu sebelumnya belum ada.');
    }
    document.querySelectorAll('#weekTabs [data-metric]').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('#weekTabs [data-metric]').forEach(function (x) { x.classList.toggle('active', x === t); });
            renderWeek(t.getAttribute('data-metric'));
        });
    });
    renderWeek('leads');
})();
</script>
