<?php
$pageTitle = $def['label'];
$fmt = function ($v): string {
    if (is_array($v)) {
        return count($v) . ' item';
    }
    if (is_float($v) || is_int($v)) {
        return rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    }

    return (string) $v;
};
?>
<div class="page-header page-header-row">
    <div>
        <h2><?= e($def['label']) ?></h2>
        <p class="text-muted"><?= (int) $total ?> baris &middot; asal Excel: <?= e($def['source']) ?>. <?= e($def['description']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('/boq/referensi') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Semua Kategori</a>
        <?php if ($canManage): ?>
        <a href="<?= url('/boq/referensi/' . $type . '/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</a>
        <?php endif; ?>
    </div>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/boq/referensi/' . $type) ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode, nama, atau nilai" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if ($filters['q']): ?><a href="<?= url('/boq/referensi/' . $type) ?>" class="btn btn-light">Reset</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state"><i class="bi bi-journal-text"></i><p>Tidak ada data.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <?php foreach ($def['list'] as $key): ?><th><?= e($def['fields'][$key][0] ?? $key) ?></th><?php endforeach; ?>
                        <th>Status</th>
                        <?php if ($canManage): ?><th class="text-end">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr class="<?= (int) $r['is_active'] === 1 ? '' : 'text-muted' ?>">
                        <td class="mono"><?= e($r['code']) ?></td>
                        <?php foreach ($def['list'] as $key): ?>
                            <?php $v = $r['attrs'][$key] ?? null; $opts = $def['fields'][$key][2] ?? null; ?>
                            <td><?= $v === null ? '<span class="text-muted">-</span>' : e($opts !== null && isset($opts[$v]) ? $opts[$v] : $fmt($v)) ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ((int) $r['is_active'] === 1): ?><span class="status-dot status-active"></span>Aktif
                            <?php else: ?><span class="status-dot status-inactive"></span>Nonaktif<?php endif; ?>
                        </td>
                        <?php if ($canManage): ?>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a href="<?= url('/boq/referensi/' . $type . '/' . $r['id'] . '/edit') ?>" class="dropdown-item"><i class="bi bi-pencil me-2"></i>Ubah</a></li>
                                    <li>
                                        <form method="POST" action="<?= url('/boq/referensi/' . $type . '/' . $r['id'] . '/toggle-status') ?>" data-confirm="<?= (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> &quot;<?= e($r['code']) ?>&quot;?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item"><i class="bi <?= (int) $r['is_active'] === 1 ? 'bi-toggle2-off' : 'bi-toggle2-on' ?> me-2"></i><?= (int) $r['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="<?= url('/boq/referensi/' . $type . '/' . $r['id'] . '/delete') ?>" data-confirm="Hapus &quot;<?= e($r['code']) ?>&quot; dari Data Referensi?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Hapus</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="card-body pagination-bar">
        <?php $qs = fn ($p) => http_build_query(array_merge($filters, ['page' => $p])); ?>
        <div class="text-muted small">Halaman <?= (int) $page ?> dari <?= (int) $totalPages ?></div>
        <div class="pagination-controls">
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/boq/referensi/' . $type) ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/boq/referensi/' . $type) ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>
