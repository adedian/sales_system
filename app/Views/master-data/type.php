<?php
$colorOptions = ['primary' => 'Biru', 'indigo' => 'Indigo', 'emerald' => 'Hijau', 'amber' => 'Kuning', 'danger' => 'Merah', 'muted' => 'Netral'];
?>
<div class="page-header page-header-row">
    <div>
        <h2><?= e($type['label']) ?></h2>
        <p class="text-muted"><?= e($type['description']) ?> &middot; <?= (int) $total ?> data.</p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah <?= e($type['singular']) ?>
    </button>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/master-data/' . $typeSlug) ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Kode, nama, atau deskripsi" value="<?= e($filters['q']) ?>">
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
                    <a href="<?= url('/master-data/' . $typeSlug) ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <i class="bi <?= e($type['icon']) ?>"></i>
                <p>Belum ada data <?= e(strtolower($type['label'])) ?>.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <?php if ($type['has_color']): ?><th>Warna</th><?php endif; ?>
                        <th class="text-center">Urutan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><code><?= e($row['code']) ?></code> <?php if ((int) $row['is_system'] === 1): ?><span class="badge-pill badge-pill-muted ms-1">Bawaan</span><?php endif; ?></td>
                        <td>
                            <div class="user-cell-name"><?= e($row['name']) ?></div>
                            <?php if (!empty($row['description'])): ?><div class="user-cell-sub"><?= e($row['description']) ?></div><?php endif; ?>
                        </td>
                        <?php if ($type['has_color']): ?>
                        <td><?php if (!empty($row['color'])): ?><span class="color-swatch color-swatch-<?= e($row['color']) ?>"><?= e($colorOptions[$row['color']] ?? $row['color']) ?></span><?php endif; ?></td>
                        <?php endif; ?>
                        <td class="text-center mono text-muted"><?= (int) $row['sort_order'] ?></td>
                        <td>
                            <?php if ((int) $row['is_active'] === 1): ?>
                                <span class="status-dot status-active"></span>Aktif
                            <?php else: ?>
                                <span class="status-dot status-inactive"></span>Nonaktif
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <button type="button" class="btn btn-sm btn-light" title="Ubah" data-bs-toggle="modal" data-bs-target="#editModal<?= (int) $row['id'] ?>"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="<?= url('/master-data/' . $typeSlug . '/' . $row['id'] . '/toggle-status') ?>" data-confirm="<?= (int) $row['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> &quot;<?= e($row['name']) ?>&quot;?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light" title="<?= (int) $row['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi <?= (int) $row['is_active'] === 1 ? 'bi-toggle2-off' : 'bi-toggle2-on' ?>"></i>
                                    </button>
                                </form>
                                <?php if ((int) $row['is_system'] === 0): ?>
                                <form method="POST" action="<?= url('/master-data/' . $typeSlug . '/' . $row['id'] . '/delete') ?>" data-confirm="Hapus &quot;<?= e($row['name']) ?>&quot;? Tindakan ini tidak dapat dibatalkan.">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
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
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/master-data/' . $typeSlug) ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/master-data/' . $typeSlug) ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('/master-data/' . $typeSlug) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Tambah <?= e($type['singular']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="create_code">Kode</label>
                        <input type="text" class="form-control" id="create_code" name="code" placeholder="mis. website" value="<?= e(old('code')) ?>" required>
                        <div class="form-text">Huruf kecil, tanpa spasi. Otomatis dirapikan.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_name">Nama</label>
                        <input type="text" class="form-control" id="create_name" name="name" value="<?= e(old('name')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_description">Deskripsi</label>
                        <input type="text" class="form-control" id="create_description" name="description" value="<?= e(old('description')) ?>">
                    </div>
                    <div class="row g-3">
                        <?php if ($type['has_color']): ?>
                        <div class="col-6">
                            <label class="form-label" for="create_color">Warna</label>
                            <select class="form-select" id="create_color" name="color">
                                <option value="">Tanpa warna</option>
                                <?php foreach ($colorOptions as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= old('color') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="<?= $type['has_color'] ? 'col-6' : 'col-12' ?>">
                            <label class="form-label" for="create_sort_order">Urutan</label>
                            <input type="number" class="form-control" id="create_sort_order" name="sort_order" value="<?= e(old('sort_order') !== '' ? old('sort_order') : '0') ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modals (one per row — keeps the form pre-filled without JS) -->
<?php foreach ($rows as $row): ?>
<div class="modal fade" id="editModal<?= (int) $row['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('/master-data/' . $typeSlug . '/' . $row['id']) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Ubah <?= e($type['singular']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ((int) $row['is_system'] === 1): ?>
                        <div class="alert alert-warning py-2 px-3" style="font-size:12.5px;">Baris bawaan sistem &mdash; kode terkunci, hanya label/deskripsi/urutan yang bisa diubah.</div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Kode</label>
                        <input type="text" class="form-control" name="code" value="<?= e($row['code']) ?>" <?= (int) $row['is_system'] === 1 ? 'readonly' : 'required' ?>>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" class="form-control" name="name" value="<?= e($row['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <input type="text" class="form-control" name="description" value="<?= e($row['description'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <?php if ($type['has_color']): ?>
                        <div class="col-6">
                            <label class="form-label">Warna</label>
                            <select class="form-select" name="color">
                                <option value="">Tanpa warna</option>
                                <?php foreach ($colorOptions as $val => $label): ?>
                                    <option value="<?= e($val) ?>" <?= $row['color'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="<?= $type['has_color'] ? 'col-6' : 'col-12' ?>">
                            <label class="form-label">Urutan</label>
                            <input type="number" class="form-control" name="sort_order" value="<?= (int) $row['sort_order'] ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (!empty($openModal)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalId = <?= json_encode($openModal === 'create' ? 'createModal' : 'editModal' . str_replace('edit-', '', $openModal), JSON_UNESCAPED_SLASHES) ?>;
        var el = document.getElementById(modalId);
        if (el && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(el).show();
        }
    });
</script>
<?php endif; ?>
