<?php
$pageTitle = $proposal['proposal_code'];
$statusRow = $statusMap[$proposal['status']] ?? ['name' => $proposal['status'], 'color' => 'muted'];
$isExpiringSoon = !empty($proposal['valid_until']) && $proposal['valid_until'] < date('Y-m-d') && !in_array($proposal['status'], ['accepted', 'rejected', 'expired'], true);
$editableStatuses = ['draft', 'revision'];
$isEditable = in_array($proposal['status'], $editableStatuses, true);
$notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note'));
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow">
            <?= e($proposal['proposal_code']) ?>
            <?php if ($isExpiringSoon): ?><span class="overdue-badge"><i class="bi bi-exclamation-triangle-fill"></i> Melewati masa berlaku</span><?php endif; ?>
        </div>
        <h2><?= e($proposal['customer_name']) ?></h2>
        <p class="text-muted">
            <a href="<?= url('/leads/' . $proposal['lead_id']) ?>"><?= e($proposal['lead_code']) ?></a>
            &middot; <?= e($proposal['company_name'] ?: 'Tanpa perusahaan') ?>
            <?php if ($proposal['procurement_request_code']): ?>
            &middot; dari <a href="<?= url('/procurement/' . $proposal['procurement_request_id']) ?>"><?= e($proposal['procurement_request_code']) ?></a>
            <?php endif; ?>
        </p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/proposals/' . $proposal['id'] . '/pdf') ?>" target="_blank" class="btn btn-light"><i class="bi bi-eye me-1"></i>Preview PDF</a>
        <a href="<?= url('/proposals/' . $proposal['id'] . '/pdf?download=1') ?>" class="btn btn-light"><i class="bi bi-download me-1"></i>Unduh PDF</a>
        <a href="<?= url('/proposals') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Informasi Lead</h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <dt>Customer</dt><dd><?= e($proposal['customer_name']) ?></dd>
                    <dt>Perusahaan</dt><dd><?= e($proposal['company_name'] ?: '-') ?></dd>
                    <dt>Telepon</dt><dd><?= $proposal['lead_phone'] ? '<a href="tel:' . e($proposal['lead_phone']) . '">' . e($proposal['lead_phone']) . '</a>' : '-' ?></dd>
                    <dt>Sales</dt><dd><?= e($proposal['sales_name'] ?? '-') ?></dd>
                </dl>
                <?php if ($proposal['status'] === 'rejected' && $proposal['rejection_reason']): ?>
                <hr>
                <dl class="lead-dl"><dt>Alasan Ditolak</dt><dd class="text-danger"><?= nl2br(e($proposal['rejection_reason'])) ?></dd></dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Detail Proposal</h3></div>
            <div class="card-body">
                <?php if ($canOperate && $isEditable): ?>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id']) ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Nama Project</label>
                        <input type="text" name="project_name" class="form-control" value="<?= e($proposal['project_name'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">PIC Customer</label>
                        <input type="text" name="customer_pic" class="form-control" value="<?= e($proposal['customer_pic'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Ruang Lingkup Pekerjaan</label>
                        <textarea name="scope_description" class="form-control" rows="2"><?= e($proposal['scope_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Diskon (%)</label>
                        <input type="number" step="0.01" min="0" max="100" name="discount_percent" class="form-control" value="<?= e($proposal['discount_percent'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">PPN (%)</label>
                        <input type="number" step="0.01" min="0" name="tax_percent" class="form-control" value="<?= e($proposal['tax_percent'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Berlaku Sampai</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= e($proposal['valid_until'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Timeline Pengerjaan</label>
                        <input type="text" name="timeline_text" class="form-control" placeholder="mis. 14 hari kerja" value="<?= e($proposal['timeline_text'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Syarat Pembayaran</label>
                        <textarea name="payment_terms" class="form-control" rows="2"><?= e($proposal['payment_terms'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Garansi</label>
                        <textarea name="warranty" class="form-control" rows="2"><?= e($proposal['warranty'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Syarat &amp; Ketentuan</label>
                        <textarea name="terms_conditions" class="form-control" rows="3"><?= e($proposal['terms_conditions'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan Internal</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($proposal['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Detail</button>
                    </div>
                </form>
                <?php else: ?>
                <dl class="lead-dl">
                    <dt>Project</dt><dd><?= e($proposal['project_name'] ?: '-') ?></dd>
                    <dt>PIC Customer</dt><dd><?= e($proposal['customer_pic'] ?: '-') ?></dd>
                    <dt>Ruang Lingkup</dt><dd><?= $proposal['scope_description'] ? nl2br(e($proposal['scope_description'])) : '-' ?></dd>
                    <dt>Timeline</dt><dd><?= e($proposal['timeline_text'] ?: '-') ?></dd>
                    <dt>Syarat Pembayaran</dt><dd><?= $proposal['payment_terms'] ? nl2br(e($proposal['payment_terms'])) : '-' ?></dd>
                    <dt>Garansi</dt><dd><?= e($proposal['warranty'] ?: '-') ?></dd>
                    <dt>Syarat &amp; Ketentuan</dt><dd><?= $proposal['terms_conditions'] ? nl2br(e($proposal['terms_conditions'])) : '-' ?></dd>
                    <dt>Berlaku Sampai</dt><dd><?= $proposal['valid_until'] ? e(format_datetime($proposal['valid_until'], 'd M Y')) : '-' ?></dd>
                    <?php if ($proposal['notes']): ?><dt>Catatan Internal</dt><dd><?= nl2br(e($proposal['notes'])) ?></dd><?php endif; ?>
                </dl>
                <?php if (!$isEditable): ?><p class="text-muted small mt-2 mb-0"><i class="bi bi-lock"></i> Detail terkunci karena proposal sudah diajukan/dikirim.</p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3>Item Penawaran</h3>
                <span class="badge-pill">Total: Rp <?= e(number_format((float) $proposal['total'], 0, ',', '.')) ?></span>
            </div>
            <div class="card-body">
                <?php if ($canOperate && $isEditable): ?>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/items') ?>" class="row g-2 mb-4">
                    <?= csrf_field() ?>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Nama Item</label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Qty</label>
                        <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" value="1" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-control">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Harga Modal</label>
                        <input type="number" step="0.01" min="0" name="unit_cost" class="form-control">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Harga Jual</label>
                        <input type="number" step="0.01" min="0" name="unit_price" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <input type="text" name="specification" class="form-control form-control-sm mb-2" placeholder="Spesifikasi (opsional)">
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
                                <th>Item</th><th>Qty</th><th>Modal</th><th>Harga Jual</th><th>Margin</th><th>Subtotal</th>
                                <?php if ($canOperate && $isEditable): ?><th class="text-end">Aksi</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <?php
                                $margin = ((float) $item['unit_price'] > 0 && $item['unit_cost'] !== null)
                                    ? (((float) $item['unit_price'] - (float) $item['unit_cost']) / (float) $item['unit_price']) * 100
                                    : null;
                            ?>
                            <tr>
                                <td>
                                    <div class="user-cell-name"><?= e($item['item_name']) ?></div>
                                    <?php if ($item['specification']): ?><div class="user-cell-sub"><?= e($item['specification']) ?></div><?php endif; ?>
                                </td>
                                <td class="mono"><?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, '.', ''), '0'), '.')) ?> <?= e($item['unit']) ?></td>
                                <td class="mono text-muted"><?= $item['unit_cost'] !== null ? 'Rp ' . e(number_format((float) $item['unit_cost'], 0, ',', '.')) : '-' ?></td>
                                <td class="mono">Rp <?= e(number_format((float) $item['unit_price'], 0, ',', '.')) ?></td>
                                <td class="mono <?= $margin !== null && $margin < 0 ? 'text-danger' : 'text-muted' ?>"><?= $margin !== null ? e(number_format($margin, 1)) . '%' : '-' ?></td>
                                <td class="mono">Rp <?= e(number_format((float) $item['subtotal'], 0, ',', '.')) ?></td>
                                <?php if ($canOperate && $isEditable): ?>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <details class="item-edit-details">
                                            <summary class="btn btn-sm btn-light" title="Ubah"><i class="bi bi-pencil"></i></summary>
                                        </details>
                                        <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/items/' . $item['id'] . '/delete') ?>" data-confirm="Hapus item <?= e($item['item_name']) ?>?">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($canOperate && $isEditable): ?>
                            <tr class="item-edit-row" hidden>
                                <td colspan="7">
                                    <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/items/' . $item['id']) ?>" class="row g-2 p-2">
                                        <?= csrf_field() ?>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">Nama Item</label>
                                            <input type="text" name="item_name" class="form-control form-control-sm" value="<?= e($item['item_name']) ?>" required>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Qty</label>
                                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control form-control-sm" value="<?= e($item['quantity']) ?>" required>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Satuan</label>
                                            <input type="text" name="unit" class="form-control form-control-sm" value="<?= e($item['unit'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Harga Modal</label>
                                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control form-control-sm" value="<?= e($item['unit_cost'] ?? '') ?>">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Harga Jual</label>
                                            <input type="number" step="0.01" min="0" name="unit_price" class="form-control form-control-sm" value="<?= e($item['unit_price']) ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Spesifikasi</label>
                                            <input type="text" name="specification" class="form-control form-control-sm mb-2" value="<?= e($item['specification'] ?? '') ?>">
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
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end">Subtotal</td>
                                <td class="mono">Rp <?= e(number_format((float) $proposal['subtotal'], 0, ',', '.')) ?></td>
                                <?php if ($canOperate && $isEditable): ?><td></td><?php endif; ?>
                            </tr>
                            <?php if ((float) $proposal['discount_amount'] > 0): ?>
                            <tr>
                                <td colspan="5" class="text-end">Diskon<?= $proposal['discount_percent'] !== null ? ' (' . e(rtrim(rtrim(number_format((float) $proposal['discount_percent'], 2, '.', ''), '0'), '.')) . '%)' : '' ?></td>
                                <td class="mono">- Rp <?= e(number_format((float) $proposal['discount_amount'], 0, ',', '.')) ?></td>
                                <?php if ($canOperate && $isEditable): ?><td></td><?php endif; ?>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td colspan="5" class="text-end">PPN (<?= e(rtrim(rtrim(number_format((float) ($proposal['tax_percent'] ?? 0), 2, '.', ''), '0'), '.')) ?>%)</td>
                                <td class="mono">Rp <?= e(number_format((float) $proposal['tax_amount'], 0, ',', '.')) ?></td>
                                <?php if ($canOperate && $isEditable): ?><td></td><?php endif; ?>
                            </tr>
                            <tr>
                                <td colspan="5" class="text-end fw-semibold">Total</td>
                                <td class="mono fw-semibold">Rp <?= e(number_format((float) $proposal['total'], 0, ',', '.')) ?></td>
                                <?php if ($canOperate && $isEditable): ?><td></td><?php endif; ?>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Catatan</h3></div>
            <div class="card-body">
                <?php if ($canOperate || $canApprove): ?>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/notes') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-2" name="note" rows="2" placeholder="Tambahkan catatan internal..." required></textarea>
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

        <?php if ($canNegotiate || !empty($negotiations)): ?>
        <div class="card card-elevated mt-3" id="negotiations">
            <div class="card-header"><h3>Riwayat Negosiasi</h3></div>
            <div class="card-body">
                <?php if ($canNegotiate): ?>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/negotiations') ?>" class="row g-2 mb-3">
                    <?= csrf_field() ?>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Jenis</label>
                        <select name="type" class="form-select">
                            <option value="customer_feedback">Feedback Customer</option>
                            <option value="sales_response">Respon Sales</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Penawaran Customer (Rp)</label>
                        <input type="number" step="0.01" min="0" name="requested_total" class="form-control" placeholder="opsional">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Catatan</label>
                        <textarea name="message" class="form-control" rows="1" placeholder="Ringkasan negosiasi..." required></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Catat Negosiasi</button>
                    </div>
                </form>
                <?php endif; ?>

                <?php if (empty($negotiations)): ?>
                    <p class="text-muted small mb-0">Belum ada catatan negosiasi.</p>
                <?php else: ?>
                <?php $negotiationLabels = ['customer_feedback' => 'Feedback Customer', 'sales_response' => 'Respon Sales', 'revision_request' => 'Permintaan Revisi']; ?>
                <div class="note-list">
                    <?php foreach ($negotiations as $n): ?>
                    <div class="note-item">
                        <div class="note-text">
                            <span class="badge-pill <?= $n['type'] === 'revision_request' ? 'badge-pill-danger' : '' ?>"><?= e($negotiationLabels[$n['type']] ?? $n['type']) ?></span>
                            <?php if ($n['requested_total'] !== null): ?> &middot; Rp <?= e(number_format((float) $n['requested_total'], 0, ',', '.')) ?><?php endif; ?>
                            <div class="mt-1"><?= nl2br(e($n['message'])) ?></div>
                        </div>
                        <div class="note-meta"><?= e($n['user_name'] ?? 'Sistem') ?> &middot; <?= e(format_datetime($n['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card card-elevated lead-panel">
            <div class="card-header"><h3>Status &amp; Aksi</h3></div>
            <div class="card-body lead-side-panel" data-proposal-id="<?= (int) $proposal['id'] ?>" data-proposal-updated-at="<?= e($proposal['updated_at']) ?>">

                <div class="lead-side-field">
                    <label class="form-label">Status</label>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></div>
                </div>

                <dl class="lead-dl mt-2">
                    <dt>Dibuat</dt><dd><?= e(format_datetime($proposal['created_at'])) ?></dd>
                    <?php if ($proposal['submitted_at']): ?><dt>Diajukan Review</dt><dd><?= e(format_datetime($proposal['submitted_at'])) ?></dd><?php endif; ?>
                    <?php if ($proposal['approved_at']): ?><dt>Disetujui</dt><dd><?= e(format_datetime($proposal['approved_at'])) ?> &middot; <?= e($proposal['approved_by_name'] ?? '-') ?></dd><?php endif; ?>
                    <?php if ($proposal['sent_at']): ?><dt>Terkirim</dt><dd><?= e(format_datetime($proposal['sent_at'])) ?></dd><?php endif; ?>
                    <?php if ($proposal['responded_at']): ?><dt>Respon Customer</dt><dd><?= e(format_datetime($proposal['responded_at'])) ?></dd><?php endif; ?>
                </dl>

                <?php if ($canOperate && in_array($proposal['status'], ['draft', 'revision'], true)): ?>
                <hr>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/submit-review') ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light w-100"><i class="bi bi-send-check me-1"></i>Ajukan Review Internal</button>
                </form>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/send') ?>" data-confirm="Kirim proposal ini langsung ke customer tanpa review internal?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Kirim ke Customer</button>
                </form>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/delete') ?>" class="mt-2" data-confirm="Hapus draft proposal ini?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light btn-sm w-100 text-danger">Hapus Draft</button>
                </form>
                <?php endif; ?>

                <?php if ($canApprove && $proposal['status'] === 'internal_review'): ?>
                <hr>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/approve') ?>" class="mb-2" data-confirm="Setujui proposal ini?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i>Setujui</button>
                </form>
                <details>
                    <summary class="btn btn-outline-danger w-100">Minta Revisi</summary>
                    <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/request-revision') ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <textarea class="form-control form-control-sm mb-2" name="revision_reason" rows="2" placeholder="Apa yang perlu diperbaiki..." required></textarea>
                        <button type="submit" class="btn btn-danger btn-sm w-100">Kirim Permintaan Revisi</button>
                    </form>
                </details>
                <?php endif; ?>

                <?php if ($canOperate && $proposal['status'] === 'approved'): ?>
                <hr>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/send') ?>" data-confirm="Kirim proposal ini ke customer?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Kirim ke Customer</button>
                </form>
                <?php endif; ?>

                <?php if ($canOperate && in_array($proposal['status'], ['sent', 'viewed', 'negotiation'], true)): ?>
                <hr>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/status') ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <label class="form-label">Update Respon Customer</label>
                    <select name="status" class="form-select form-select-sm mb-2">
                        <?php if ($proposal['status'] === 'sent'): ?><option value="viewed">Sudah Dilihat Customer</option><?php endif; ?>
                        <option value="negotiation" <?= $proposal['status'] === 'negotiation' ? 'selected' : '' ?>>Negosiasi</option>
                        <option value="expired">Kadaluarsa</option>
                    </select>
                    <button type="submit" class="btn btn-light btn-sm w-100">Simpan</button>
                </form>
                <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/status') ?>" class="mb-2" data-confirm="Tandai proposal ini DITERIMA oleh customer?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="accepted">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i>Customer Terima</button>
                </form>
                <?php if ($proposal['status'] === 'negotiation'): ?>
                <details class="mb-2">
                    <summary class="btn btn-outline-primary w-100">Revisi Harga &amp; Kirim Ulang</summary>
                    <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/revise-negotiation') ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <textarea class="form-control form-control-sm mb-2" name="reason" rows="2" placeholder="Permintaan revisi dari customer..." required></textarea>
                        <input type="number" step="0.01" min="0" name="requested_total" class="form-control form-control-sm mb-2" placeholder="Target harga customer (opsional)">
                        <button type="submit" class="btn btn-primary btn-sm w-100">Buka untuk Revisi</button>
                    </form>
                </details>
                <?php endif; ?>
                <details>
                    <summary class="btn btn-outline-danger w-100">Customer Tolak</summary>
                    <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/status') ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="rejected">
                        <textarea class="form-control form-control-sm mb-2" name="reason" rows="2" placeholder="Alasan penolakan..." required></textarea>
                        <button type="submit" class="btn btn-danger btn-sm w-100">Tandai Ditolak</button>
                    </form>
                </details>
                <?php endif; ?>

                <?php if (in_array($proposal['status'], ['accepted', 'rejected', 'expired'], true)): ?>
                <hr>
                <div class="badge-pill badge-pill-muted"><i class="bi bi-flag me-1"></i>Proposal sudah final (<?= e($statusRow['name']) ?>)</div>
                <?php endif; ?>

                <div class="lead-live-indicator text-muted" id="proposalLiveIndicator"><i class="bi bi-broadcast"></i> Realtime aktif</div>
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
                            <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Proposal dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
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
    var panel = document.querySelector('[data-proposal-id]');
    if (!panel) return;
    var proposalId = panel.getAttribute('data-proposal-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var lastKnownUpdatedAt = panel.getAttribute('data-proposal-updated-at');

    document.querySelectorAll('.item-edit-details').forEach(function (details) {
        details.addEventListener('toggle', function () {
            var tr = details.closest('tr');
            var itemRow = tr ? tr.nextElementSibling : null;
            if (itemRow && itemRow.classList.contains('item-edit-row')) {
                itemRow.hidden = !details.open;
            }
        });
    });

    var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
    function ping() {
        Api.get(base + '/api/proposals/' + proposalId + '/ping').then(function (data) {
            if (!data || data.updated_at === lastKnownUpdatedAt) return;
            lastKnownUpdatedAt = data.updated_at;
            Toast.show('Proposal ini baru saja diperbarui. Muat ulang untuk melihat perubahan terbaru.', 'info', 6000);
            var indicator = document.getElementById('proposalLiveIndicator');
            if (indicator) indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <a href="javascript:location.reload()">Ada pembaruan — muat ulang</a>';
        }).catch(function () {});
    }
    setInterval(ping, Math.max(pollInterval, 10000));
})();
</script>
