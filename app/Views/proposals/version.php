<?php
use App\Models\ProposalVersion;

$snap = $version['data']['proposal'];
$items = $version['data']['items'];
$rp = static fn ($n): string => 'Rp ' . number_format((float) $n, 0, ',', '.');

$fields = [
    'customer_pic' => 'PIC Customer', 'project_name' => 'Nama Proyek', 'scope_description' => 'Ruang Lingkup',
    'timeline_text' => 'Timeline', 'payment_terms' => 'Pembayaran', 'warranty' => 'Garansi',
    'terms_conditions' => 'Syarat & Ketentuan', 'valid_until' => 'Berlaku Hingga', 'notes' => 'Catatan',
];

// Perbandingan dengan versi tersimpan sebelumnya (bila ada).
$changes = [];
$itemDiff = ['added' => [], 'removed' => [], 'changed' => []];
if ($previous !== null) {
    $prev = $previous['data']['proposal'];
    foreach ($fields as $k => $label) {
        if ((string) ($snap[$k] ?? '') !== (string) ($prev[$k] ?? '')) {
            $changes[] = $label;
        }
    }
    foreach (['discount_amount' => 'Diskon', 'tax_amount' => 'Pajak'] as $k => $label) {
        if ((float) ($snap[$k] ?? 0) !== (float) ($prev[$k] ?? 0)) {
            $changes[] = $label;
        }
    }
    $prevByName = [];
    foreach ($previous['data']['items'] as $pi) {
        $prevByName[$pi['item_name']] = $pi;
    }
    $curNames = [];
    foreach ($items as $it) {
        $curNames[$it['item_name']] = true;
        if (!isset($prevByName[$it['item_name']])) {
            $itemDiff['added'][] = $it['item_name'];
        } elseif ((float) $prevByName[$it['item_name']]['unit_price'] !== (float) $it['unit_price']
            || (float) $prevByName[$it['item_name']]['quantity'] !== (float) $it['quantity']) {
            $itemDiff['changed'][] = $it['item_name'];
        }
    }
    foreach ($prevByName as $name => $_) {
        if (!isset($curNames[$name])) {
            $itemDiff['removed'][] = $name;
        }
    }
}
$delta = $previous !== null ? (float) $version['total'] - (float) $previous['total'] : null;
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow"><?= e($proposal['proposal_code']) ?></div>
        <h2>Versi <?= (int) $version['version_no'] ?> <span class="color-swatch color-swatch-primary fs-6 align-middle"><?= e(ProposalVersion::EVENTS[$version['event']] ?? $version['event']) ?></span></h2>
        <p class="text-muted mb-0">
            <?= e($proposal['customer_name']) ?> &middot; <?= e(format_datetime($version['created_at'])) ?> &middot; <?= e($version['created_by_name'] ?? 'Sistem') ?>
            &middot; Salinan baca-saja, bukan isi proposal saat ini.
        </p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/proposals/' . $proposal['id'] . '/versions/' . $version['id'] . '/pdf') ?>" target="_blank" class="btn btn-light"><i class="bi bi-eye me-1"></i>Preview PDF</a>
        <a href="<?= url('/proposals/' . $proposal['id'] . '/versions/' . $version['id'] . '/pdf?download=1') ?>" class="btn btn-light"><i class="bi bi-download me-1"></i>Unduh PDF</a>
        <a href="<?= url('/proposals/' . $proposal['id']) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Isi Versi <?= (int) $version['version_no'] ?></h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <?php foreach ($fields as $k => $label): ?>
                        <?php if (!empty($snap[$k])): ?><dt><?= e($label) ?></dt><dd><?= nl2br(e((string) $snap[$k])) ?></dd><?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Item</h3></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>Item</th><th>Qty</th><th>Harga Jual</th><th>Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div class="user-cell-name"><?= e($it['item_name']) ?></div>
                                    <?php if (!empty($it['specification'])): ?><div class="user-cell-sub"><?= e($it['specification']) ?></div><?php endif; ?>
                                </td>
                                <td class="mono"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.')) ?> <?= e($it['unit']) ?></td>
                                <td class="mono"><?= e($rp($it['unit_price'])) ?></td>
                                <td class="mono"><?= e($rp($it['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?><tr><td colspan="4" class="text-muted">Tidak ada item.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <dl class="lead-dl mt-3 mb-0">
                    <dt>Subtotal</dt><dd class="mono"><?= e($rp($snap['subtotal'] ?? 0)) ?></dd>
                    <?php if ((float) ($snap['discount_amount'] ?? 0) > 0): ?><dt>Diskon</dt><dd class="mono">- <?= e($rp($snap['discount_amount'])) ?></dd><?php endif; ?>
                    <dt>Pajak (<?= e((string) ($snap['tax_percent'] ?? 0)) ?>%)</dt><dd class="mono"><?= e($rp($snap['tax_amount'] ?? 0)) ?></dd>
                    <dt>Total</dt><dd class="mono"><strong><?= e($rp($version['total'])) ?></strong></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card card-elevated">
            <div class="card-header"><h3>Perubahan dari versi tersimpan sebelumnya</h3></div>
            <div class="card-body">
                <?php if ($previous === null): ?>
                    <p class="text-muted mb-0">Ini versi tersimpan pertama.</p>
                <?php else: ?>
                    <p class="small text-muted">Dibanding Versi <?= (int) $previous['version_no'] ?> (<?= e(ProposalVersion::EVENTS[$previous['event']] ?? '') ?>).</p>
                    <p class="mb-2">Total: <strong class="mono"><?= e($rp($previous['total'])) ?> &rarr; <?= e($rp($version['total'])) ?></strong>
                        <?php if ($delta !== 0.0): ?><span class="<?= $delta < 0 ? 'text-success' : 'text-danger' ?> small">(<?= $delta > 0 ? '+' : '-' ?><?= e($rp(abs($delta))) ?>)</span><?php endif; ?></p>
                    <?php if ($changes === [] && !array_filter($itemDiff)): ?>
                        <p class="text-muted mb-0">Isi sama dengan versi sebelumnya.</p>
                    <?php endif; ?>
                    <?php if ($changes !== []): ?><p class="mb-1 small text-uppercase text-muted">Isian berubah</p><p><?= e(implode(', ', $changes)) ?></p><?php endif; ?>
                    <?php foreach (['added' => 'Item ditambah', 'changed' => 'Item diubah (qty/harga)', 'removed' => 'Item dihapus'] as $k => $label): ?>
                        <?php if ($itemDiff[$k] !== []): ?><p class="mb-1 small text-uppercase text-muted"><?= e($label) ?></p><ul class="small"><?php foreach ($itemDiff[$k] as $n): ?><li><?= e($n) ?></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
