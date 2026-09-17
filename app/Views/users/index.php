<?php $pageTitle = 'Pengguna'; ?>
<div class="page-header page-header-row">
    <div>
        <h2>Pengguna</h2>
        <p class="text-muted"><?= (int) $total ?> pengguna terdaftar.</p>
    </div>
    <?php if (can('user.manage')): ?>
    <a href="<?= url('/users/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Pengguna</a>
    <?php endif; ?>
</div>

<div class="card card-elevated">
    <div class="card-body filter-bar">
        <form method="GET" action="<?= url('/users') ?>" class="filter-form">
            <div class="filter-field filter-field-grow">
                <label class="form-label" for="q">Cari</label>
                <input type="text" id="q" name="q" class="form-control" placeholder="Nama, username, atau email" value="<?= e($filters['q']) ?>">
            </div>
            <div class="filter-field">
                <label class="form-label" for="role_id">Role</label>
                <select id="role_id" name="role_id" class="form-select">
                    <option value="">Semua Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (string) $filters['role_id'] === (string) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
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
                <?php if ($filters['q'] || $filters['role_id'] || $filters['status']): ?>
                    <a href="<?= url('/users') ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <i class="bi bi-people"></i>
                <p>Tidak ada pengguna yang cocok dengan pencarian.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern table-sticky mb-0">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Fungsi</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                        $flagLabels = [
                            'is_sales' => 'Sales',
                            'is_estimator' => 'Estimator',
                            'is_surveyor' => 'Surveyor',
                            'is_engineer' => 'Engineer',
                            'is_sales_engineer' => 'Sales Engineer',
                            'is_director' => 'Direktur',
                        ];
                    ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell">
                                <span class="user-avatar"><?= e(strtoupper(substr($u['name'], 0, 1))) ?></span>
                                <div>
                                    <div class="user-cell-name"><?= e($u['name']) ?></div>
                                    <div class="user-cell-sub"><?= e($u['username']) ?> &middot; <?= e($u['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge-pill"><?= e($u['role_name'] ?? '-') ?></span></td>
                        <td>
                            <?php
                                $activeFlags = array_filter(array_keys($flagLabels), fn ($flag) => !empty($u[$flag]));
                            ?>
                            <?php if (empty($activeFlags)): ?>
                                <span class="text-muted">-</span>
                            <?php else: ?>
                                <?php foreach ($activeFlags as $flag): ?>
                                    <span class="badge-pill badge-pill-muted"><?= e($flagLabels[$flag]) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $u['is_active'] === 1): ?>
                                <span class="status-dot status-active"></span>Aktif
                            <?php else: ?>
                                <span class="status-dot status-inactive"></span>Nonaktif
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= e(format_datetime($u['last_login_at'])) ?></td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="<?= url('/users/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></a>
                                <?php if ((int) $u['id'] !== auth_user()['id']): ?>
                                <form method="POST" action="<?= url('/users/' . $u['id'] . '/toggle-status') ?>" data-confirm="<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?> pengguna <?= e($u['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-light" title="<?= (int) $u['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <i class="bi <?= (int) $u['is_active'] === 1 ? 'bi-toggle2-off' : 'bi-toggle2-on' ?>"></i>
                                    </button>
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
        <?php
            $qs = fn ($p) => http_build_query(array_merge($filters, ['page' => $p]));
        ?>
        <div class="text-muted small">Halaman <?= (int) $page ?> dari <?= (int) $totalPages ?></div>
        <div class="pagination-controls">
            <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= url('/users') ?>?<?= $qs(max(1, $page - 1)) ?>">Sebelumnya</a>
            <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= url('/users') ?>?<?= $qs(min($totalPages, $page + 1)) ?>">Berikutnya</a>
        </div>
    </div>
    <?php endif; ?>
</div>
