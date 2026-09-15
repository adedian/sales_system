<?php
$pageTitle = $pr['request_code'];
$statusRow = $statusMap[$pr['status']] ?? ['name' => $pr['status'], 'color' => 'muted'];
$priorityRow = $priorityMap[$pr['priority']] ?? ['name' => $pr['priority'], 'color' => 'muted'];
$isOverdue = !empty($pr['deadline']) && $pr['deadline'] < date('Y-m-d') && !in_array($pr['status'], ['pricing_completed', 'cancelled'], true);
$workStatuses = ['waiting', 'in_progress', 'quotation_requested', 'need_revision'];
$notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note'));
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow">
            <?= e($pr['request_code']) ?>
            <?php if ($isOverdue): ?><span class="overdue-badge"><i class="bi bi-exclamation-triangle-fill"></i> Overdue</span><?php endif; ?>
        </div>
        <h2><?= e($pr['customer_name']) ?></h2>
        <p class="text-muted">
            <a href="<?= url('/leads/' . $pr['lead_id']) ?>"><?= e($pr['lead_code']) ?></a>
            &middot; <?= e($pr['company_name'] ?: 'Tanpa perusahaan') ?>
            <?php if ($pr['engineer_assignment_code']): ?>
            &middot; dari <a href="<?= url('/engineer/' . $pr['engineer_assignment_id']) ?>"><?= e($pr['engineer_assignment_code']) ?></a>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url('/procurement') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Informasi Lead</h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <dt>Customer</dt><dd><?= e($pr['customer_name']) ?></dd>
                    <dt>Perusahaan</dt><dd><?= e($pr['company_name'] ?: '-') ?></dd>
                    <dt>Telepon</dt><dd><?= $pr['lead_phone'] ? '<a href="tel:' . e($pr['lead_phone']) . '">' . e($pr['lead_phone']) . '</a>' : '-' ?></dd>
                    <dt>Sales</dt><dd><?= e($pr['sales_name'] ?? '-') ?></dd>
                </dl>
                <?php if ($pr['notes']): ?>
                <hr>
                <dl class="lead-dl">
                    <dt>Catatan Request</dt><dd><?= nl2br(e($pr['notes'])) ?></dd>
                </dl>
                <?php endif; ?>
                <?php if ($pr['engineer_result_notes']): ?>
                <hr>
                <dl class="lead-dl">
                    <dt>Hasil Analisa Engineer</dt><dd><?= nl2br(e($pr['engineer_result_notes'])) ?></dd>
                </dl>
                <?php endif; ?>
                <?php if ($pr['status'] === 'need_revision' && $pr['revision_reason']): ?>
                <hr>
                <dl class="lead-dl">
                    <dt>Alasan Butuh Revisi</dt><dd class="text-danger"><?= nl2br(e($pr['revision_reason'])) ?></dd>
                </dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3>Item &amp; Harga Vendor</h3>
                <?php if ($totalPurchasePrice > 0): ?><span class="badge-pill">Total: Rp <?= e(number_format($totalPurchasePrice, 0, ',', '.')) ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/items') ?>" class="row g-2 mb-4">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Nama Item</label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Qty</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" value="1" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-control" placeholder="pcs, meter, unit">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Spesifikasi</label>
                        <input type="text" name="specification" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Tambah Item</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (empty($items)): ?>
                    <p class="text-muted small mb-0">Belum ada item.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Vendor</th>
                                <th>Harga Beli</th>
                                <th>Quotation</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="user-cell-name"><?= e($item['item_name']) ?></div>
                                    <?php if ($item['specification']): ?><div class="user-cell-sub"><?= e($item['specification']) ?></div><?php endif; ?>
                                </td>
                                <td class="mono"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) ?> <?= e($item['unit']) ?></td>
                                <td><?= e($item['vendor_name'] ?? $item['supplier_name'] ?? '-') ?></td>
                                <td class="mono"><?= $item['purchase_price'] !== null ? 'Rp ' . e(number_format((float) $item['purchase_price'], 0, ',', '.')) : '<span class="text-danger">Belum diisi</span>' ?></td>
                                <td>
                                    <?php if ($item['quotation_file_path']): ?>
                                    <a href="<?= url('/procurement/' . $pr['id'] . '/items/' . $item['id'] . '/quotation') ?>" class="btn btn-sm btn-light" title="Unduh Quotation"><i class="bi bi-file-earmark-arrow-down"></i></a>
                                    <?php else: ?>
                                    <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($canOperate): ?>
                                    <div class="row-actions">
                                        <details class="item-edit-details">
                                            <summary class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></summary>
                                        </details>
                                        <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/items/' . $item['id'] . '/delete') ?>" data-confirm="Hapus item <?= e($item['item_name']) ?>?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($canOperate): ?>
                            <tr class="item-edit-row" data-item-row-for="<?= (int) $item['id'] ?>" hidden>
                                <td colspan="6">
                                    <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/items/' . $item['id']) ?>" enctype="multipart/form-data" class="row g-2 p-2">
                                        <?= csrf_field() ?>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Vendor</label>
                                            <select name="vendor_id" class="form-select form-select-sm">
                                                <option value="">Vendor lain / manual</option>
                                                <?php foreach ($vendors as $v): ?>
                                                    <option value="<?= (int) $v['id'] ?>" <?= (int) ($item['vendor_id'] ?? 0) === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Nama Supplier (jika di luar master vendor)</label>
                                            <input type="text" name="supplier_name" class="form-control form-control-sm" value="<?= e($item['supplier_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label class="form-label">Harga Beli (per unit)</label>
                                            <input type="number" step="0.01" min="0" name="purchase_price" class="form-control form-control-sm" value="<?= e($item['purchase_price'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label class="form-label">No. Quotation</label>
                                            <input type="text" name="quotation_number" class="form-control form-control-sm" value="<?= e($item['quotation_number'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label class="form-label">Tanggal Quotation</label>
                                            <input type="date" name="quotation_date" class="form-control form-control-sm" value="<?= e($item['quotation_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label class="form-label">Berlaku Sampai</label>
                                            <input type="date" name="validity_date" class="form-control form-control-sm" value="<?= e($item['validity_date'] ?? '') ?>">
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Lampiran Quotation</label>
                                            <input type="file" name="quotation_attachment" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Catatan Item</label>
                                            <input type="text" name="notes" class="form-control form-control-sm" value="<?= e($item['notes'] ?? '') ?>">
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary btn-sm">Simpan Item</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-2">Format quotation: pdf, doc(x), xls(x), png, jpg &middot; maksimal 10MB.</p>
                <?php endif; ?>

                <?php if ($canOperate && !in_array($pr['status'], ['pricing_completed', 'cancelled'], true)): ?>
                <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/complete') ?>" class="mt-3" data-confirm="Selesaikan pricing dan kembalikan request ini ke sales?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success" <?= $allPriced ? '' : 'disabled title="Semua item harus memiliki harga beli terlebih dahulu"' ?>><i class="bi bi-send-check me-1"></i>Selesaikan Pricing &amp; Kirim ke Sales</button>
                    <?php if (!$allPriced): ?><div class="form-text text-danger">Isi harga beli untuk semua item (minimal 1 item) sebelum bisa diselesaikan.</div><?php endif; ?>
                </form>
                <?php elseif ($pr['status'] === 'pricing_completed'): ?>
                    <div class="badge-pill badge-pill-muted mt-3"><i class="bi bi-check2-circle me-1"></i>Pricing selesai &amp; sudah dikembalikan ke sales</div>
                <?php endif; ?>

                <?php if ($pr['status'] === 'pricing_completed'): ?>
                    <?php if ($existingProposal): ?>
                    <a href="<?= url('/proposals/' . $existingProposal['id']) ?>" class="btn btn-light mt-2"><i class="bi bi-file-earmark-text me-1"></i>Lihat Proposal <?= e($existingProposal['proposal_code']) ?></a>
                    <?php elseif ($canCreateProposal): ?>
                    <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/create-proposal') ?>" class="mt-2" data-confirm="Buat proposal dari hasil pricing ini? Item &amp; harga akan disalin sebagai draft awal.">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-file-earmark-plus me-1"></i>Buat Proposal</button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Catatan</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/notes') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-2" name="note" rows="2" placeholder="Tambahkan catatan (progress komunikasi vendor, dsb)..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Tambah Catatan</button>
                </form>
                <?php endif; ?>

                <?php if (empty($notes)): ?>
                    <p class="text-muted small mb-0">Belum ada catatan.</p>
                <?php else: ?>
                <div class="note-list">
                    <?php foreach ($notes as $n): ?>
                    <div class="note-item">
                        <div class="note-text"><?= nl2br(e($n['note'])) ?></div>
                        <div class="note-meta"><?= e($n['actor']) ?> &middot; <?= e(format_datetime($n['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card card-elevated lead-panel">
            <div class="card-header"><h3>Status &amp; Penugasan</h3></div>
            <div class="card-body lead-side-panel" data-pr-id="<?= (int) $pr['id'] ?>" data-pr-updated-at="<?= e($pr['updated_at']) ?>">

                <div class="lead-side-field">
                    <label class="form-label">Status</label>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></div>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Prioritas</label>
                    <?php if ($canManage): ?>
                    <select class="form-select procurement-live-field" data-endpoint="priority" data-field="priority">
                        <?php foreach ($priorityMap as $code => $row): ?>
                            <option value="<?= e($code) ?>" <?= $pr['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority"><?= e($priorityRow['name']) ?></span></div>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Staff Procurement</label>
                    <?php if ($canManage): ?>
                    <select class="form-select procurement-live-field" data-endpoint="reassign" data-field="assigned_to">
                        <?php foreach ($procurementUsers as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (int) $pr['assigned_to'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <div class="lead-side-current text-muted"><?= e($pr['assigned_to_name'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Deadline</label>
                    <?php if ($canManage): ?>
                    <input type="date" class="form-control procurement-live-field" data-endpoint="deadline" data-field="deadline" value="<?= e($pr['deadline']) ?>">
                    <?php else: ?>
                    <div class="lead-side-current text-muted"><?= $pr['deadline'] ? e(format_datetime($pr['deadline'], 'd M Y')) : 'Tidak ada deadline' ?></div>
                    <?php endif; ?>
                </div>

                <dl class="lead-dl mt-2">
                    <dt>Diminta</dt><dd><?= e(format_datetime($pr['requested_at'])) ?></dd>
                    <?php if ($pr['completed_at']): ?><dt>Selesai</dt><dd><?= e(format_datetime($pr['completed_at'])) ?></dd><?php endif; ?>
                    <dt>Diminta oleh</dt><dd><?= e($pr['requested_by_name'] ?? '-') ?></dd>
                </dl>

                <?php if ($canOperate && in_array($pr['status'], $workStatuses, true)): ?>
                <hr>
                <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/status') ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <label class="form-label">Update Status</label>
                    <select name="status" class="form-select form-select-sm mb-2">
                        <option value="in_progress" <?= $pr['status'] === 'in_progress' ? 'selected' : '' ?>>Sedang Diproses</option>
                        <option value="quotation_requested" <?= $pr['status'] === 'quotation_requested' ? 'selected' : '' ?>>Menunggu Quotation</option>
                    </select>
                    <button type="submit" class="btn btn-light btn-sm w-100">Simpan Status</button>
                </form>

                <details>
                    <summary class="btn btn-outline-danger btn-sm w-100">Tandai Butuh Revisi</summary>
                    <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/need-revision') ?>" class="mt-2" data-confirm="Tandai request ini butuh revisi dari sales/engineer?">
                        <?= csrf_field() ?>
                        <textarea class="form-control form-control-sm mb-2" name="revision_reason" rows="2" placeholder="Data/info apa yang kurang..." required></textarea>
                        <button type="submit" class="btn btn-danger btn-sm w-100">Kirim Permintaan Revisi</button>
                    </form>
                </details>

                <form method="POST" action="<?= url('/procurement/' . $pr['id'] . '/cancel') ?>" class="mt-2" data-confirm="Batalkan request procurement ini?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light btn-sm w-100 text-danger">Batalkan Request</button>
                </form>
                <?php endif; ?>

                <div class="lead-live-indicator text-muted" id="procurementLiveIndicator"><i class="bi bi-broadcast"></i> Realtime aktif</div>
            </div>
        </div>
    </div>
</div>

<div class="card card-elevated mt-3">
    <div class="card-header"><h3>Timeline Aktivitas</h3></div>
    <div class="card-body">
        <?php if (empty($timeline)): ?>
            <div class="empty-state"><i class="bi bi-clock-history"></i><p>Belum ada aktivitas.</p></div>
        <?php else: ?>
        <div class="timeline">
            <?php foreach ($timeline as $event): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-body">
                    <?php if ($event['type'] === 'status'): ?>
                        <div class="timeline-title">
                            <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Request dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
                        </div>
                        <?php if ($event['notes']): ?><div class="timeline-notes"><?= e($event['notes']) ?></div><?php endif; ?>
                    <?php elseif ($event['type'] === 'note'): ?>
                        <div class="timeline-title">Catatan ditambahkan</div>
                        <div class="timeline-notes"><?= nl2br(e($event['note'])) ?></div>
                    <?php else: ?>
                        <div class="timeline-title"><?= e($event['label']) ?></div>
                    <?php endif; ?>
                    <div class="timeline-meta"><?= e($event['actor']) ?> &middot; <?= e(format_datetime($event['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var panel = document.querySelector('[data-pr-id]');
    if (!panel) return;
    var prId = panel.getAttribute('data-pr-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var lastKnownUpdatedAt = panel.getAttribute('data-pr-updated-at');

    document.querySelectorAll('.procurement-live-field').forEach(function (field) {
        field.addEventListener('change', function () {
            var endpoint = field.getAttribute('data-endpoint');
            var payload = {};
            payload[field.getAttribute('data-field')] = field.value;

            Api.post(base + '/api/procurement/' + prId + '/' + endpoint, payload).then(function (data) {
                if (!data || data.success === false) {
                    Toast.show('Gagal menyimpan perubahan.', 'danger');
                    return;
                }
                Toast.show('Perubahan tersimpan.', 'success');
                if (data.updated_at) lastKnownUpdatedAt = data.updated_at;

                if (endpoint === 'priority') {
                    var badge = panel.querySelector('[data-badge="priority"]');
                    badge.textContent = data.label;
                    badge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'reassign') {
                    Toast.show('Request dialihkan ke ' + data.assigned_to_name + '.', 'info');
                    setTimeout(function () { location.reload(); }, 800);
                }
            }).catch(function () {
                Toast.show('Gagal menyimpan perubahan, silakan coba lagi.', 'danger');
            });
        });
    });

    // Expand/collapse the inline item-edit row when its <details> "Ubah" summary is toggled.
    document.querySelectorAll('.item-edit-details').forEach(function (details) {
        details.addEventListener('toggle', function () {
            var tr = details.closest('tr');
            var itemRow = tr ? tr.nextElementSibling : null;
            if (itemRow && itemRow.hasAttribute('data-item-row-for')) {
                itemRow.hidden = !details.open;
            }
        });
    });

    var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
    function ping() {
        Api.get(base + '/api/procurement/' + prId + '/ping').then(function (data) {
            if (!data || data.updated_at === lastKnownUpdatedAt) return;
            lastKnownUpdatedAt = data.updated_at;
            Toast.show('Request ini baru saja diperbarui. Muat ulang untuk melihat perubahan terbaru.', 'info', 6000);
            var indicator = document.getElementById('procurementLiveIndicator');
            if (indicator) indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <a href="javascript:location.reload()">Ada pembaruan — muat ulang</a>';
        }).catch(function () {});
    }
    setInterval(ping, Math.max(pollInterval, 10000));
})();
</script>
