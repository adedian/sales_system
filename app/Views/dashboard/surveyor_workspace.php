<?php
use App\Models\Survey;

$pageTitle = 'Workspace Surveyor';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
$today = date('Y-m-d');
$cards = [
    ['label' => 'Penugasan Baru', 'value' => $counts['requested'], 'icon' => 'bi-inbox', 'tone' => 'muted', 'href' => '/surveys?status=requested'],
    ['label' => 'Dijadwalkan', 'value' => $counts['scheduled'], 'icon' => 'bi-calendar-check', 'tone' => 'primary', 'href' => '/surveys?status=scheduled'],
    ['label' => 'Sedang Berlangsung', 'value' => $counts['in_progress'], 'icon' => 'bi-arrow-repeat', 'tone' => 'indigo', 'href' => '/surveys?status=in_progress'],
    ['label' => 'Laporan Belum Selesai', 'value' => $counts['report_open'], 'icon' => 'bi-file-earmark-bar-graph', 'tone' => 'amber', 'href' => '/surveys?report_status=draft'],
    ['label' => 'Terlambat', 'value' => $counts['overdue'], 'icon' => 'bi-exclamation-triangle', 'tone' => 'danger', 'href' => '/surveys?status=scheduled'],
    ['label' => 'Survey Selesai', 'value' => $counts['completed'], 'icon' => 'bi-check-circle', 'tone' => 'emerald', 'href' => '/surveys?status=completed'],
];
?>
<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e($firstName) ?></h2>
        <p class="text-muted">Penugasan survey lapangan dan Survey Report Anda.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/surveys') ?>" class="btn btn-primary"><i class="bi bi-geo-alt me-1"></i>Lihat Semua Survey</a>
    </div>
</div>

<div class="stat-grid stat-grid-6">
    <?php foreach ($cards as $c): ?>
    <a href="<?= url($c['href']) ?>" class="stat-card stat-card-compact stat-card-link <?= ($c['tone'] === 'danger' && $c['value'] > 0) ? 'stat-card-alert' : '' ?>">
        <div class="stat-icon stat-icon-<?= e($c['tone']) ?>"><i class="bi <?= e($c['icon']) ?>"></i></div>
        <div class="stat-body"><span class="stat-label"><?= e($c['label']) ?></span><span class="stat-value"><?= (int) $c['value'] ?></span></div>
    </a>
    <?php endforeach; ?>
</div>

<div class="card card-elevated mt-4">
    <div class="card-header"><h5 class="mb-0">Pekerjaan Survey yang Perlu Ditindaklanjuti</h5></div>
    <div class="card-body p-0">
        <?php if (empty($openSurveys)): ?>
            <div class="empty-state"><i class="bi bi-geo-alt"></i><p>Tidak ada survey yang perlu ditindaklanjuti.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead><tr><th>Survey</th><th>Lead / Customer</th><th>Lokasi</th><th>Jadwal</th><th>Status</th><th>Laporan</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($openSurveys as $r): ?>
                    <?php
                    $st = Survey::STATUS_META[$r['status']];
                    $rp = $r['report_status'] ? Survey::REPORT_META[$r['report_status']] : null;
                    $late = in_array($r['status'], ['requested', 'scheduled'], true) && $r['scheduled_date'] && $r['scheduled_date'] < $today;
                    ?>
                    <tr>
                        <td class="mono"><a href="<?= url('/surveys/' . $r['id']) ?>"><?= e($r['survey_code']) ?></a></td>
                        <td><span class="mono d-block"><?= e($r['lead_code']) ?></span><span class="user-cell-name"><?= e($r['customer_name']) ?></span></td>
                        <td class="text-muted"><?= e($r['site_location'] ?: '-') ?></td>
                        <td><?= e(format_date($r['scheduled_date'])) ?><?= $late ? ' <span class="badge text-bg-danger">Terlambat</span>' : '' ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($st['color']) ?>"><?= e($st['name']) ?></span></td>
                        <td><?= $rp ? '<span class="color-swatch color-swatch-' . e($rp['color']) . '">' . e($rp['name']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-end"><a href="<?= url('/surveys/' . $r['id']) ?>" class="btn btn-sm btn-primary">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
