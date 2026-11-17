<?php
$pageTitle = 'Workspace Approval';
$firstName = explode(' ', auth_user()['name'] ?? '')[0] ?? '';
?>
<div class="page-header page-header-row">
    <div>
        <h2>Selamat datang, <?= e($firstName) ?></h2>
        <p class="text-muted">Pekerjaan yang menunggu keputusan Anda sebagai Direktur.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/procurement/validation') ?>" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Buka Approval Harga</a>
    </div>
</div>

<div class="stat-grid">
    <a href="<?= url('/procurement/validation') ?>" class="stat-card stat-card-compact stat-card-link <?= count($queue) > 0 ? 'stat-card-alert' : '' ?>">
        <div class="stat-icon stat-icon-amber"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-body"><span class="stat-label">Menunggu Approval Harga</span><span class="stat-value"><?= count($queue) ?></span></div>
    </a>
    <div class="stat-card stat-card-compact">
        <div class="stat-icon stat-icon-primary"><i class="bi bi-cash-stack"></i></div>
        <div class="stat-body"><span class="stat-label">Total Nilai Menunggu</span><span class="stat-value">Rp <?= number_format($queueTotal, 0, ',', '.') ?></span></div>
    </div>
</div>

<div class="card card-elevated mt-4">
    <div class="card-header"><h5 class="mb-0">Menunggu Approval Harga</h5></div>
    <div class="card-body p-0">
        <?php if (empty($queue)): ?>
            <div class="empty-state"><i class="bi bi-shield-check"></i><p>Tidak ada harga yang menunggu validasi.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead><tr><th>Kode</th><th>Lead</th><th>Sales</th><th>Total Harga</th><th>Diajukan</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($queue as $row): ?>
                    <tr>
                        <td class="mono"><?= e($row['request_code']) ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $row['lead_id']) ?>" class="mono d-block"><?= e($row['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($row['customer_name']) ?></div>
                        </td>
                        <td class="text-muted"><?= e($row['sales_name'] ?? '-') ?></td>
                        <td class="mono">Rp <?= number_format((float) $row['total_price'], 0, ',', '.') ?></td>
                        <td class="text-muted"><?= e(format_datetime($row['submitted_at'])) ?></td>
                        <td class="text-end"><a href="<?= url('/procurement/' . $row['procurement_request_id']) ?>" class="btn btn-sm btn-primary"><i class="bi bi-shield-check"></i> Validasi</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
