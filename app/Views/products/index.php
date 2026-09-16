<?php $pageTitle = 'Katalog Produk'; ?>
<div class="page-header page-header-row">
    <div>
        <h2>Katalog Produk</h2>
        <p class="text-muted"><?= (int) $total ?> produk terdaftar. Dipakai sebagai quick-fill saat menambah item Proposal/Procurement.</p>
    </div>
    <a href="<?= url('/products/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Produk</a>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/products') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode atau nama produk" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
            </div>
            <div class="filter-field filter-field-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                <?php if ($filters['q'] || $filters['status']): ?>
                    <a href="<?= url('/products') ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($products)): ?>
            <div class="empty-state">
                <i class="bi bi-box-seam"></i>
                <p>Belum ada produk.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Produk</th>
                        <th>Kategori</th>
                        <th>Satuan</th>
                        <th>Harga Default</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="mono"><?= e($p['product_code']) ?></td>
                        <td>
                            <div class="user-cell-name"><?= e($p['name']) ?></div>
                            <?php if ($p['description']): ?><div class="user-cell-sub"><?= e(mb_strimwidth($p['description'], 0, 60, '...')) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($p['category_name'] ?: '-') ?></td>
                        <td class="text-muted"><?= e($p['unit_name'] ?: '-') ?></td>
                        <td class="mono"><?= $p['default_price'] !== null ? 'Rp ' . e(number_format((float) $p['default_price'], 0, ',', '.')) : '-' ?></td>
                        <td>
                            <?php if ((int) $p['is_active'] === 1): ?>
                                <span class="status-dot status-active"></span>Aktif
                            <?php else: ?>
                                <span class="status-dot status-inactive"></span>Nonaktif
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="<?= url('/products/' . $p['id'] . '/edit') ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="<?= url('/products/' . $p['id'] . '/toggle-status') ?>" data-confirm="<?= (int) $p['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> produk <?= e($p['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light" title="<?= (int) $p['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi <?= (int) $p['is_active'] === 1 ? 'bi-toggle2-off' : 'bi-toggle2-on' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= url('/products/' . $p['id'] . '/delete') ?>" data-confirm="Hapus produk <?= e($p['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/products') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/products') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>
