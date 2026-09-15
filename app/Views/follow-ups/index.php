<?php
$pageTitle = 'Follow Up Calendar';
$responseColors = [
    'interested' => 'emerald',
    'negotiating' => 'amber',
    'need_info' => 'indigo',
    'not_interested' => 'danger',
    'no_answer' => 'muted',
];
$renderLeadTable = function (array $rows, string $emptyText) {
    if (empty($rows)) {
        echo '<p class="text-muted small mb-0">' . e($emptyText) . '</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Kode</th><th>Customer</th><th>Sales</th><th>Tanggal Follow Up</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><a href="<?= url('/leads/' . $row['id']) ?>" class="mono"><?= e($row['lead_code']) ?></a></td>
                    <td>
                        <div class="user-cell-name"><?= e($row['customer_name']) ?></div>
                        <?php if (!empty($row['company_name'])): ?><div class="user-cell-sub"><?= e($row['company_name']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-muted"><?= e($row['sales_name'] ?? '-') ?></td>
                    <td class="mono"><?= $row['follow_up_date'] ? e(format_datetime($row['follow_up_date'], 'd M Y')) : '-' ?></td>
                    <td class="text-end"><a href="<?= url('/leads/' . $row['id']) ?>" class="btn btn-sm btn-light">Follow Up</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>
<div class="page-header page-header-row">
    <div>
        <h2>Follow Up Calendar</h2>
        <p class="text-muted">Jadwal follow up lead — terlambat, hari ini, dan akan datang.</p>
    </div>
    <a href="<?= url('/leads') ?>" class="btn btn-light"><i class="bi bi-person-lines-fill me-1"></i>Lihat Semua Lead</a>
</div>

<div class="stat-grid">
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-danger"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="stat-body"><span class="stat-label">Terlambat</span><span class="stat-value"><?= count($overdue) ?></span></div>
    </div>
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-body"><span class="stat-label">Hari Ini</span><span class="stat-value"><?= count($today) ?></span></div>
    </div>
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-indigo"><i class="bi bi-calendar-week"></i></div>
        <div class="stat-body"><span class="stat-label">Akan Datang</span><span class="stat-value"><?= count($upcoming) ?></span></div>
    </div>
</div>

<?php if (!empty($salesUsers)): ?>
<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/follow-ups') ?>" class="filter-form">
            <div class="filter-field">
                <label class="form-label" for="sales_id">Sales</label>
                <select id="sales_id" name="sales_id" class="form-select">
                    <option value="">Semua Sales</option>
                    <?php foreach ($salesUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $filters['sales_id'] === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="<?= url('/follow-ups') ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card card-elevated mt-3">
    <div class="card-header"><h3><i class="bi bi-exclamation-triangle text-danger me-1"></i>Terlambat</h3></div>
    <div class="card-body"><?php $renderLeadTable($overdue, 'Tidak ada follow up yang terlewat.'); ?></div>
</div>

<div class="card card-elevated mt-3">
    <div class="card-header"><h3><i class="bi bi-calendar-check text-warning me-1"></i>Hari Ini</h3></div>
    <div class="card-body"><?php $renderLeadTable($today, 'Tidak ada follow up untuk hari ini.'); ?></div>
</div>

<div class="card card-elevated mt-3">
    <div class="card-header"><h3><i class="bi bi-calendar-week me-1"></i>Akan Datang</h3></div>
    <div class="card-body"><?php $renderLeadTable($upcoming, 'Belum ada follow up terjadwal ke depan.'); ?></div>
</div>

<div class="card card-elevated mt-3">
    <div class="card-header"><h3>Riwayat Follow Up Terbaru</h3></div>
    <div class="card-body">
        <?php if (empty($recentLog)): ?>
            <div class="empty-state"><i class="bi bi-telephone"></i><p>Belum ada follow up yang tercatat.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead><tr><th>Lead</th><th>Metode</th><th>Respon Customer</th><th>Catatan</th><th>Next Action</th><th>Follow Up Berikutnya</th><th>Oleh</th></tr></thead>
                <tbody>
                    <?php foreach ($recentLog as $log): ?>
                    <tr>
                        <td><a href="<?= url('/leads/' . $log['lead_id']) ?>" class="mono"><?= e($log['lead_code']) ?></a><div class="user-cell-sub"><?= e($log['customer_name']) ?></div></td>
                        <td><span class="badge-pill"><?= e($methodLabels[$log['method']] ?? $log['method']) ?></span></td>
                        <td><?= $log['customer_response'] ? '<span class="color-swatch color-swatch-' . e($responseColors[$log['customer_response']] ?? 'muted') . '">' . e($responseLabels[$log['customer_response']] ?? $log['customer_response']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                        <td class="text-muted"><?= $log['notes'] ? e(mb_strimwidth($log['notes'], 0, 60, '...')) : '-' ?></td>
                        <td class="text-muted"><?= $log['next_action'] ? e($log['next_action']) : '-' ?></td>
                        <td class="mono"><?= $log['next_followup_date'] ? e(format_datetime($log['next_followup_date'], 'd M Y')) : '-' ?></td>
                        <td class="text-muted"><?= e($log['sales_name'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
