<?php
$pageTitle = 'Validasi Harga';
?>
<div class="page-header">
    <h2>Validasi Harga</h2>
    <p class="text-muted"><?= count($rows) ?> request menunggu validasi harga Anda.</p>
</div>

<div class="card card-elevated">
    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="bi bi-shield-check"></i>
                <p>Tidak ada request yang menunggu validasi.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Lead</th>
                        <th>Sales</th>
                        <th>Total Harga</th>
                        <th>Diajukan Oleh</th>
                        <th>Diajukan Pada</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="mono"><?= e($row['request_code']) ?></td>
                        <td>
                            <a href="<?= url('/leads/' . $row['lead_id']) ?>" class="mono d-block"><?= e($row['lead_code']) ?></a>
                            <div class="user-cell-name"><?= e($row['customer_name']) ?></div>
                        </td>
                        <td class="text-muted"><?= e($row['sales_name'] ?? '-') ?></td>
                        <td class="mono">Rp <?= number_format((float) $row['total_price'], 0, ',', '.') ?></td>
                        <td class="text-muted"><?= e($row['submitted_by_name'] ?? '-') ?></td>
                        <td class="text-muted"><?= e(format_datetime($row['submitted_at'])) ?></td>
                        <td class="text-end">
                            <a href="<?= url('/procurement/' . $row['procurement_request_id']) ?>" class="btn btn-sm btn-primary" title="Validasi"><i class="bi bi-shield-check"></i> Validasi</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
