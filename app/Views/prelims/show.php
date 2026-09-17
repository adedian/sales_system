<?php
$pageTitle = $prelim['prelim_code'];
$statusRow = $statusMap[$prelim['status']] ?? ['name' => $prelim['status'], 'color' => 'muted'];
$isEditable = in_array($prelim['status'], ['draft', 'ready_to_send', 'client_revision'], true);
$notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note'));
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow"><?= e($prelim['prelim_code']) ?> &middot; v<?= (int) $prelim['version'] ?></div>
        <h2><?= e($prelim['customer_name']) ?></h2>
        <p class="text-muted">
            <a href="<?= url('/leads/' . $prelim['lead_id']) ?>"><?= e($prelim['lead_code']) ?></a>
            &middot; <?= e($prelim['company_name'] ?: 'Tanpa perusahaan') ?>
        </p>
    </div>
    <div class="row-actions">
        <a href="<?= url('/prelims') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Informasi Lead</h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <dt>Customer</dt><dd><?= e($prelim['customer_name']) ?></dd>
                    <dt>Perusahaan</dt><dd><?= e($prelim['company_name'] ?: '-') ?></dd>
                    <dt>Telepon</dt><dd><?= $prelim['lead_phone'] ? '<a href="tel:' . e($prelim['lead_phone']) . '">' . e($prelim['lead_phone']) . '</a>' : '-' ?></dd>
                    <dt>Sales</dt><dd><?= e($prelim['sales_name'] ?? '-') ?></dd>
                </dl>
                <?php if ($prelim['status'] === 'client_revision' && $prelim['client_revision_reason']): ?>
                <hr>
                <dl class="lead-dl"><dt>Alasan Revisi Client</dt><dd class="text-danger"><?= nl2br(e($prelim['client_revision_reason'])) ?></dd></dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Isi Penawaran</h3></div>
            <div class="card-body">
                <?php if ($canOperate && $isEditable): ?>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id']) ?>" class="row g-3">
                    <?= csrf_field() ?>
                    <div class="col-12">
                        <label class="form-label">Isi Penawaran (Prelim)</label>
                        <textarea name="content" class="form-control" rows="6" placeholder="Ringkasan penawaran awal untuk client..."><?= e($prelim['content'] ?? '') ?></textarea>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Estimasi Nilai (Rp)</label>
                        <input type="number" step="0.01" min="0" name="estimated_value" class="form-control" value="<?= e($prelim['estimated_value'] ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Berlaku Sampai</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= e($prelim['valid_until'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Isi Penawaran</button>
                    </div>
                </form>
                <?php else: ?>
                <dl class="lead-dl">
                    <dt>Isi Penawaran</dt><dd><?= $prelim['content'] ? nl2br(e($prelim['content'])) : '-' ?></dd>
                    <dt>Estimasi Nilai</dt><dd><?= $prelim['estimated_value'] !== null ? 'Rp ' . e(number_format((float) $prelim['estimated_value'], 0, ',', '.')) : '-' ?></dd>
                    <dt>Berlaku Sampai</dt><dd><?= $prelim['valid_until'] ? e(format_datetime($prelim['valid_until'], 'd M Y')) : '-' ?></dd>
                </dl>
                <?php if (!$isEditable): ?><p class="text-muted small mt-2 mb-0"><i class="bi bi-lock"></i> Isi terkunci karena prelim sudah terkirim/disetujui.</p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Dokumen Prelim</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/documents') ?>" enctype="multipart/form-data" class="mb-3 d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="file" name="document" class="form-control" style="max-width:320px" required>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Unggah</button>
                </form>
                <p class="text-muted small">Format: pdf, doc(x), xls(x), png, jpg &middot; maksimal 10MB. Biasanya berupa file penawaran yang sudah disiapkan Sales.</p>
                <?php endif; ?>

                <?php if (empty($documents)): ?>
                    <p class="text-muted small mb-0">Belum ada dokumen.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead><tr><th>File</th><th>Ukuran</th><th>Diunggah oleh</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><i class="bi bi-file-earmark-text me-1"></i><?= e($doc['file_name']) ?></td>
                                <td class="text-muted"><?= e(number_format($doc['file_size'] / 1024, 1)) ?> KB</td>
                                <td class="text-muted"><?= e($doc['uploaded_by_name'] ?? '-') ?> &middot; <?= e(format_datetime($doc['created_at'], 'd M Y')) ?></td>
                                <td class="text-end row-actions">
                                    <a href="<?= url('/prelims/' . $prelim['id'] . '/documents/' . $doc['id']) ?>" class="btn btn-sm btn-light" title="Unduh"><i class="bi bi-download"></i></a>
                                    <?php if ($canOperate): ?>
                                    <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/documents/' . $doc['id'] . '/delete') ?>" data-confirm="Hapus file ini?">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Catatan</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/notes') ?>" class="mb-3">
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
    </div>

    <div class="col-12 col-lg-4">
        <div class="card card-elevated lead-panel">
            <div class="card-header"><h3>Status &amp; Aksi</h3></div>
            <div class="card-body lead-side-panel">

                <div class="lead-side-field">
                    <label class="form-label">Status</label>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>"><?= e($statusRow['name']) ?></span></div>
                </div>

                <dl class="lead-dl mt-2">
                    <dt>Dibuat</dt><dd><?= e(format_datetime($prelim['created_at'])) ?></dd>
                    <?php if ($prelim['sent_at']): ?><dt>Terkirim</dt><dd><?= e(format_datetime($prelim['sent_at'])) ?></dd><?php endif; ?>
                    <?php if ($prelim['responded_at']): ?><dt>Respon Client</dt><dd><?= e(format_datetime($prelim['responded_at'])) ?></dd><?php endif; ?>
                    <?php if ($prelim['approved_at']): ?><dt>Disetujui (ACC)</dt><dd><?= e(format_datetime($prelim['approved_at'])) ?></dd><?php endif; ?>
                </dl>

                <?php if ($canOperate && $prelim['status'] === 'draft'): ?>
                <hr>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/ready') ?>" class="mb-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light w-100"><i class="bi bi-check2 me-1"></i>Tandai Siap Dikirim</button>
                </form>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/send') ?>" data-confirm="Kirim prelim ini ke Client?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i>Kirim ke Client</button>
                </form>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/delete') ?>" class="mt-2" data-confirm="Hapus draft prelim ini?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-light btn-sm w-100 text-danger">Hapus Draft</button>
                </form>
                <?php endif; ?>

                <?php if ($canOperate && in_array($prelim['status'], ['ready_to_send', 'client_revision'], true)): ?>
                <hr>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/send') ?>" data-confirm="Kirim prelim ini ke Client?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-1"></i><?= $prelim['status'] === 'client_revision' ? 'Kirim Ulang ke Client' : 'Kirim ke Client' ?></button>
                </form>
                <?php endif; ?>

                <?php if ($canOperate && $prelim['status'] === 'sent'): ?>
                <hr>
                <label class="form-label">Respon Client</label>
                <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/client-response') ?>" class="mb-2" data-confirm="Tandai Prelim ini DISETUJUI (ACC) oleh Client?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="response" value="approved">
                    <button type="submit" class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i>Client ACC</button>
                </form>
                <details>
                    <summary class="btn btn-outline-danger w-100">Client Minta Revisi</summary>
                    <form method="POST" action="<?= url('/prelims/' . $prelim['id'] . '/client-response') ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="response" value="client_revision">
                        <textarea class="form-control form-control-sm mb-2" name="client_revision_reason" rows="2" placeholder="Apa yang diminta client untuk direvisi..." required></textarea>
                        <button type="submit" class="btn btn-danger btn-sm w-100">Kirim Permintaan Revisi</button>
                    </form>
                </details>
                <?php endif; ?>

                <?php if ($prelim['status'] === 'approved'): ?>
                <hr>
                <div class="badge-pill badge-pill-emerald"><i class="bi bi-flag me-1"></i>Prelim disetujui (ACC) &mdash; lanjut ke Sales Engineer/Engineer</div>
                <p class="text-muted small mt-2 mb-0"><a href="<?= url('/leads/' . $prelim['lead_id']) ?>">Buka Lead</a> untuk meminta assignment Sales Engineer/Engineer.</p>
                <?php endif; ?>
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
                            <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Prelim dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
                        </div>
                        <?php if ($event['notes']): ?><div class="timeline-notes"><?= e($event['notes']) ?></div><?php endif; ?>
                    <?php elseif ($event['type'] === 'note'): ?>
                        <div class="timeline-title">Catatan ditambahkan</div>
                        <div class="timeline-notes"><?= nl2br(e($event['note'])) ?></div>
                    <?php else: ?>
                        <div class="timeline-title">Dokumen diunggah: <?= e($event['file_name']) ?></div>
                    <?php endif; ?>
                    <div class="timeline-meta"><?= e($event['actor']) ?> &middot; <?= e(format_datetime($event['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
