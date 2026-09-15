<?php $pageTitle = 'Vendor'; ?>
<div class="page-header page-header-row">
    <div>
        <h2>Vendor</h2>
        <p class="text-muted"><?= (int) $total ?> vendor terdaftar. Dipakai oleh Procurement untuk mencatat sumber quotation.</p>
    </div>
    <a href="<?= url('/vendors/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Vendor</a>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/vendors') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode, nama, kontak, telepon, email" value="<?= e($filters['q']) ?>">
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
                    <a href="<?= url('/vendors') ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($vendors)): ?>
            <div class="empty-state">
                <i class="bi bi-truck"></i>
                <p>Belum ada vendor.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Vendor</th>
                        <th>Kontak</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendors as $v): ?>
                    <tr>
                        <td class="mono"><?= e($v['vendor_code']) ?></td>
                        <td>
                            <div class="user-cell-name"><?= e($v['name']) ?></div>
                            <?php if ($v['email']): ?><div class="user-cell-sub"><?= e($v['email']) ?></div><?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e($v['contact_person'] ?: '-') ?><?= $v['phone'] ? ' &middot; ' . e($v['phone']) : '' ?></td>
                        <td class="text-muted"><?= e($v['category'] ?: '-') ?></td>
                        <td>
                            <?php if ((int) $v['is_active'] === 1): ?>
                                <span class="status-dot status-active"></span>Aktif
                            <?php else: ?>
                                <span class="status-dot status-inactive"></span>Nonaktif
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="<?= url('/vendors/' . $v['id'] . '/edit') ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="<?= url('/vendors/' . $v['id'] . '/toggle-status') ?>" data-confirm="<?= (int) $v['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> vendor <?= e($v['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light" title="<?= (int) $v['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi <?= (int) $v['is_active'] === 1 ? 'bi-toggle2-off' : 'bi-toggle2-on' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= url('/vendors/' . $v['id'] . '/delete') ?>" data-confirm="Hapus vendor <?= e($v['name']) ?>?">
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/vendors') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/vendors') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>
