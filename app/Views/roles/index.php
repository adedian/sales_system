<?php $pageTitle = 'Role'; ?>
<div class="page-header page-header-row">
    <div>
        <h2>Role Administratif</h2>
        <p class="text-muted">Role menentukan akses/permission ke sistem. Tidak sama dengan fungsi operasional di lapangan (lihat panel di bawah).</p>
    </div>
    <a href="<?= url('/roles/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Role</a>
</div>

<div class="card card-elevated">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Deskripsi</th>
                        <th class="text-center">Permission</th>
                        <th class="text-center">Pengguna</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td>
                            <strong><?= e($role['name']) ?></strong>
                            <?php if ((int) $role['is_system'] === 1): ?>
                                <span class="badge-pill badge-pill-muted ms-1">Bawaan</span>
                            <?php endif; ?>
                            <div class="user-cell-sub"><?= e($role['slug']) ?></div>
                        </td>
                        <td class="text-muted"><?= e($role['description'] ?: '-') ?></td>
                        <td class="text-center mono"><?= (int) $role['permission_count'] ?></td>
                        <td class="text-center mono"><?= (int) $role['user_count'] ?></td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="<?= url('/roles/' . $role['id'] . '/edit') ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <?php if ((int) $role['is_system'] === 0 && (int) $role['user_count'] === 0): ?>
                                <form method="POST" action="<?= url('/roles/' . $role['id'] . '/delete') ?>" data-confirm="Hapus role <?= e($role['name']) ?>? Tindakan ini tidak dapat dibatalkan.">
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
    </div>
</div>

<div class="page-header mt-4">
    <h2>Fungsi Operasional</h2>
    <p class="text-muted">Master Personnel — fungsi di lapangan, independen dari Role login. Satu pengguna boleh punya lebih dari satu fungsi. Diatur lewat <a href="<?= url('/users') ?>">halaman Pengguna</a>.</p>
</div>

<div class="card card-elevated">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead>
                    <tr>
                        <th>Fungsi</th>
                        <th class="text-center">Jumlah</th>
                        <th>Personel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($operationalFunctions as $fn): ?>
                    <tr>
                        <td><?= e($fn['label']) ?></td>
                        <td class="text-center mono"><?= count($fn['users']) ?></td>
                        <td class="text-muted">
                            <?= !empty($fn['users']) ? e(implode(', ', array_column($fn['users'], 'name'))) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
