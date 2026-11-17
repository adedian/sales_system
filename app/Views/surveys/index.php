<?php
use App\Models\Survey;

$rows = $result['rows'];
$today = date('Y-m-d');
$qs = function (array $override = []) use ($filters) {
    $q = array_filter(array_merge(['q' => $filters['q'], 'status' => $filters['status'], 'report_status' => $filters['report_status']], $override), fn ($v) => $v !== '' && $v !== null);

    return '?' . http_build_query($q);
};
?>
<div class="page-header page-header-row">
    <div>
        <h2>Survey Lapangan</h2>
        <p class="text-muted"><?= (int) $result['total'] ?> penugasan Survey ditemukan. Survey dan Survey Report wajib selesai sebelum Prelim dapat dibuat.</p>
    </div>
</div>

<div class="pill-tabs mb-3">
    <a href="<?= url('/surveys') ?>" class="pill-tab <?= $filters['status'] === '' && $filters['report_status'] === '' ? 'active' : '' ?>">Semua</a>
    <?php foreach (Survey::STATUS_META as $code => $meta): ?>
        <a href="<?= url('/surveys') . $qs(['status' => $code, 'report_status' => '', 'page' => null]) ?>" class="pill-tab <?= $filters['status'] === $code ? 'active' : '' ?>"><?= e($meta['name']) ?></a>
    <?php endforeach; ?>
    <span class="pill-sep"></span>
    <?php foreach (Survey::REPORT_META as $code => $meta): ?>
        <a href="<?= url('/surveys') . $qs(['report_status' => $code, 'status' => '', 'page' => null]) ?>" class="pill-tab <?= $filters['report_status'] === $code ? 'active' : '' ?>">Laporan: <?= e($meta['name']) ?></a>
    <?php endforeach; ?>
</div>

<form method="GET" action="<?= url('/surveys') ?>" class="card card-elevated mb-3">
    <div class="card-body d-flex gap-2 flex-wrap">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <input type="hidden" name="report_status" value="<?= e($filters['report_status']) ?>">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" class="form-control" style="max-width:420px" placeholder="Cari kode survey, kode lead, nama customer, perusahaan...">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
        <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['report_status'] !== ''): ?>
            <a href="<?= url('/surveys') ?>" class="btn btn-light">Reset</a>
        <?php endif; ?>
    </div>
</form>

<div class="card card-elevated">
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-geo-alt"></i><p>Belum ada penugasan Survey.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr><th>Survey</th><th>Lead / Customer</th><th>Lokasi</th><th>Surveyor</th><th>Jadwal</th><th>Status</th><th>Laporan</th><th class="text-end">Aksi</th></tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $st = Survey::STATUS_META[$r['status']] ?? ['name' => $r['status'], 'color' => 'muted'];
                    $rp = $r['report_status'] ? (Survey::REPORT_META[$r['report_status']] ?? null) : null;
                    $late = in_array($r['status'], ['requested', 'scheduled'], true) && $r['scheduled_date'] && $r['scheduled_date'] < $today;
                    ?>
                    <tr>
                        <td class="mono"><a href="<?= url('/surveys/' . $r['id']) ?>"><?= e($r['survey_code']) ?></a></td>
                        <td>
                            <a href="<?= url('/leads/' . $r['lead_id']) ?>" class="mono d-block"><?= e($r['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($r['customer_name']) ?></div>
                        </td>
                        <td class="text-muted"><?= e($r['site_location'] ?: '-') ?></td>
                        <td class="text-muted"><?= e($r['surveyor_name'] ?? '-') ?></td>
                        <td>
                            <?= e(format_date($r['scheduled_date'])) ?>
                            <?php if ($late): ?><span class="badge text-bg-danger ms-1">Terlambat</span><?php endif; ?>
                        </td>
                        <td><span class="color-swatch color-swatch-<?= e($st['color']) ?>"><?= e($st['name']) ?></span></td>
                        <td><?= $rp ? '<span class="color-swatch color-swatch-' . e($rp['color']) . '">' . e($rp['name']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-end"><a href="<?= url('/surveys/' . $r['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($result['totalPages'] > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3">
            <span class="text-muted small">Halaman <?= (int) $result['page'] ?> dari <?= (int) $result['totalPages'] ?></span>
            <div class="d-flex gap-2">
                <?php if ($result['page'] > 1): ?><a class="btn btn-sm btn-light" href="<?= url('/surveys') . $qs(['page' => $result['page'] - 1]) ?>">&laquo; Sebelumnya</a><?php endif; ?>
                <?php if ($result['page'] < $result['totalPages']): ?><a class="btn btn-sm btn-light" href="<?= url('/surveys') . $qs(['page' => $result['page'] + 1]) ?>">Berikutnya &raquo;</a><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
