<?php
$pageTitle = $assignment['assignment_code'];
$statusRow = $statusMap[$assignment['status']] ?? ['name' => $assignment['status'], 'color' => 'muted'];
$priorityRow = $priorityMap[$assignment['priority']] ?? ['name' => $assignment['priority'], 'color' => 'muted'];
$isOverdue = !empty($assignment['deadline']) && $assignment['deadline'] < date('Y-m-d') && !in_array($assignment['status'], ['completed', 'rejected', 'returned'], true);
$workStatuses = ['accepted', 'in_progress', 'waiting'];
$notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note'));
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow">
            <?= e($assignment['assignment_code']) ?>
            <?php if ($isOverdue): ?><span class="overdue-badge"><i class="bi bi-exclamation-triangle-fill"></i> Overdue</span><?php endif; ?>
        </div>
        <h2><?= e($assignment['customer_name']) ?></h2>
        <p class="text-muted">
            <a href="<?= url('/leads/' . $assignment['lead_id']) ?>"><?= e($assignment['lead_code']) ?></a>
            &middot; <?= e($assignment['company_name'] ?: 'Tanpa perusahaan') ?>
        </p>
    </div>
    <a href="<?= url('/engineer') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Informasi Lead</h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <dt>Customer</dt><dd><?= e($assignment['customer_name']) ?></dd>
                    <dt>Perusahaan</dt><dd><?= e($assignment['company_name'] ?: '-') ?></dd>
                    <dt>Telepon</dt><dd><?= $assignment['lead_phone'] ? '<a href="tel:' . e($assignment['lead_phone']) . '">' . e($assignment['lead_phone']) . '</a>' : '-' ?></dd>
                    <dt>Alamat</dt><dd><?= $assignment['lead_address'] ? nl2br(e($assignment['lead_address'])) : '-' ?></dd>
                    <dt>Kebutuhan</dt><dd><?= $assignment['lead_needs_description'] ? nl2br(e($assignment['lead_needs_description'])) : '-' ?></dd>
                    <dt>Sales</dt><dd><?= e($assignment['sales_name'] ?? '-') ?></dd>
                </dl>
                <?php if ($assignment['notes_from_sales']): ?>
                <hr>
                <dl class="lead-dl">
                    <dt>Catatan dari Sales</dt><dd><?= nl2br(e($assignment['notes_from_sales'])) ?></dd>
                </dl>
                <?php endif; ?>
                <?php if ($assignment['status'] === 'rejected' && $assignment['rejection_reason']): ?>
                <hr>
                <dl class="lead-dl">
                    <dt>Alasan Penolakan</dt><dd class="text-danger"><?= nl2br(e($assignment['rejection_reason'])) ?></dd>
                </dl>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Hasil Analisa / Result</h3></div>
            <div class="card-body">
                <?php if ($canOperate && in_array($assignment['status'], array_merge($workStatuses, ['completed']), true)): ?>
                <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/result') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-2" name="result_notes" rows="4" placeholder="Tuliskan hasil analisa/rekomendasi teknis..." required><?= e($assignment['result_notes'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Hasil Analisa</button>
                </form>
                <?php elseif ($assignment['result_notes']): ?>
                    <div class="note-item mb-0"><div class="note-text"><?= nl2br(e($assignment['result_notes'])) ?></div></div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada hasil analisa. Terima assignment terlebih dahulu.</p>
                <?php endif; ?>

                <?php if ($canOperate && $assignment['status'] === 'completed'): ?>
                <?php if ($activeProcurementRequest): ?>
                    <a href="<?= url('/procurement/' . $activeProcurementRequest['id']) ?>" class="btn btn-light mt-3"><i class="bi bi-truck me-1"></i>Lihat Request Procurement <?= e($activeProcurementRequest['request_code']) ?></a>
                <?php else: ?>
                <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/return') ?>" class="mt-3" data-confirm="Kembalikan assignment ini ke sales? Assignment akan diarsipkan setelah ini.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-success"><i class="bi bi-send-check me-1"></i>Kembalikan ke Sales</button>
                </form>
                <?php if (count($procurementUsers)): ?>
                <details class="mt-2">
                    <summary class="btn btn-outline-primary w-100">Kirim ke Procurement (butuh vendor/harga)</summary>
                    <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/send-to-procurement') ?>" class="mt-2" data-confirm="Kirim assignment ini ke Procurement untuk pencarian vendor & pricing?">
                        <?= csrf_field() ?>
                        <label class="form-label">Staff Procurement</label>
                        <select name="procurement_id" class="form-select form-select-sm mb-2" required>
                            <option value="">Pilih staff...</option>
                            <?php foreach ($procurementUsers as $row): ?>
                                <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan untuk procurement (opsional)"></textarea>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Kirim ke Procurement</button>
                    </form>
                </details>
                <?php endif; ?>
                <?php endif; ?>
                <?php elseif ($assignment['status'] === 'returned'): ?>
                    <?php if ($activeProcurementRequest): ?>
                        <a href="<?= url('/procurement/' . $activeProcurementRequest['id']) ?>" class="btn btn-light mt-2"><i class="bi bi-truck me-1"></i>Lihat Request Procurement <?= e($activeProcurementRequest['request_code']) ?></a>
                    <?php else: ?>
                    <div class="badge-pill badge-pill-muted mt-2"><i class="bi bi-check2-circle me-1"></i>Sudah dikembalikan ke sales</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Catatan Teknis</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/notes') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-2" name="note" rows="2" placeholder="Tambahkan catatan teknis (progress, temuan lapangan, dsb)..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Tambah Catatan</button>
                </form>
                <?php endif; ?>

                <?php if (empty($notes)): ?>
                    <p class="text-muted small mb-0">Belum ada catatan teknis.</p>
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

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>File Pendukung</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/documents') ?>" enctype="multipart/form-data" class="mb-3 d-flex gap-2 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="file" name="document" class="form-control" style="max-width:320px" required>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i>Unggah</button>
                </form>
                <p class="text-muted small">Format: pdf, doc(x), xls(x), png, jpg, zip &middot; maksimal 10MB.</p>
                <?php endif; ?>

                <?php if (empty($documents)): ?>
                    <p class="text-muted small mb-0">Belum ada file pendukung.</p>
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
                                    <a href="<?= url('/engineer/' . $assignment['id'] . '/documents/' . $doc['id']) ?>" class="btn btn-sm btn-light" title="Unduh"><i class="bi bi-download"></i></a>
                                    <?php if ($canOperate): ?>
                                    <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/documents/' . $doc['id'] . '/delete') ?>" data-confirm="Hapus file ini?">
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
    </div>

    <div class="col-12 col-lg-4">
        <div class="card card-elevated lead-panel">
            <div class="card-header"><h3>Status &amp; Penugasan</h3></div>
            <div class="card-body lead-side-panel" data-assignment-id="<?= (int) $assignment['id'] ?>" data-assignment-updated-at="<?= e($assignment['updated_at']) ?>">

                <div class="lead-side-field">
                    <label class="form-label">Status</label>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></div>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Prioritas</label>
                    <?php if ($canManage): ?>
                    <select class="form-select engineer-live-field" data-endpoint="priority" data-field="priority">
                        <?php foreach ($priorityMap as $code => $row): ?>
                            <option value="<?= e($code) ?>" <?= $assignment['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority"><?= e($priorityRow['name']) ?></span></div>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Engineer Bertugas</label>
                    <?php if ($canManage): ?>
                    <select class="form-select engineer-live-field" data-endpoint="reassign" data-field="engineer_id">
                        <?php foreach ($engineerUsers as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (int) $assignment['engineer_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <div class="lead-side-current text-muted"><?= e($assignment['engineer_name'] ?? '-') ?></div>
                    <?php endif; ?>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Deadline</label>
                    <?php if ($canManage): ?>
                    <input type="date" class="form-control engineer-live-field" data-endpoint="deadline" data-field="deadline" value="<?= e($assignment['deadline']) ?>">
                    <?php else: ?>
                    <div class="lead-side-current text-muted"><?= $assignment['deadline'] ? e(format_datetime($assignment['deadline'], 'd M Y')) : 'Tidak ada deadline' ?></div>
                    <?php endif; ?>
                </div>

                <dl class="lead-dl mt-2">
                    <dt>Diminta</dt><dd><?= e(format_datetime($assignment['assigned_at'])) ?></dd>
                    <?php if ($assignment['accepted_at']): ?><dt>Diterima</dt><dd><?= e(format_datetime($assignment['accepted_at'])) ?></dd><?php endif; ?>
                    <?php if ($assignment['completed_at']): ?><dt>Selesai</dt><dd><?= e(format_datetime($assignment['completed_at'])) ?></dd><?php endif; ?>
                    <dt>Diminta oleh</dt><dd><?= e($assignment['assigned_by_name'] ?? '-') ?></dd>
                </dl>

                <?php if ($canOperate): ?>
                <hr>
                <?php if ($assignment['status'] === 'pending'): ?>
                    <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/accept') ?>" data-confirm="Terima assignment ini?" class="mb-2">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2 me-1"></i>Terima Assignment</button>
                    </form>
                    <details>
                        <summary class="btn btn-outline-danger w-100">Tolak Assignment</summary>
                        <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/reject') ?>" class="mt-2" data-confirm="Tolak assignment ini?">
                            <?= csrf_field() ?>
                            <textarea class="form-control form-control-sm mb-2" name="rejection_reason" rows="2" placeholder="Alasan penolakan..." required></textarea>
                            <button type="submit" class="btn btn-danger btn-sm w-100">Kirim Penolakan</button>
                        </form>
                    </details>
                <?php elseif (in_array($assignment['status'], $workStatuses, true)): ?>
                    <form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label">Update Status Pengerjaan</label>
                        <select name="status" class="form-select form-select-sm mb-2">
                            <option value="in_progress" <?= $assignment['status'] === 'in_progress' ? 'selected' : '' ?>>Sedang Dikerjakan</option>
                            <option value="waiting" <?= $assignment['status'] === 'waiting' ? 'selected' : '' ?>>Menunggu</option>
                        </select>
                        <input type="text" name="notes" class="form-control form-control-sm mb-2" placeholder="Catatan (opsional)">
                        <button type="submit" class="btn btn-light btn-sm w-100">Simpan Status</button>
                    </form>
                <?php endif; ?>
                <?php endif; ?>

                <div class="lead-live-indicator text-muted" id="engineerLiveIndicator"><i class="bi bi-broadcast"></i> Realtime aktif</div>
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
                            <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Assignment dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
                        </div>
                        <?php if ($event['notes']): ?><div class="timeline-notes"><?= e($event['notes']) ?></div><?php endif; ?>
                    <?php elseif ($event['type'] === 'note'): ?>
                        <div class="timeline-title">Catatan teknis ditambahkan</div>
                        <div class="timeline-notes"><?= nl2br(e($event['note'])) ?></div>
                    <?php elseif ($event['type'] === 'document'): ?>
                        <div class="timeline-title">File diunggah: <?= e($event['file_name']) ?></div>
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
    var panel = document.querySelector('[data-assignment-id]');
    if (!panel) return;
    var assignmentId = panel.getAttribute('data-assignment-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var lastKnownUpdatedAt = panel.getAttribute('data-assignment-updated-at');

    document.querySelectorAll('.engineer-live-field').forEach(function (field) {
        field.addEventListener('change', function () {
            var endpoint = field.getAttribute('data-endpoint');
            var payload = {};
            payload[field.getAttribute('data-field')] = field.value;

            Api.post(base + '/api/engineer/' + assignmentId + '/' + endpoint, payload).then(function (data) {
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
                    Toast.show('Assignment dialihkan ke ' + data.engineer_name + '.', 'info');
                    setTimeout(function () { location.reload(); }, 800);
                }
            }).catch(function () {
                Toast.show('Gagal menyimpan perubahan, silakan coba lagi.', 'danger');
            });
        });
    });

    var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
    function ping() {
        Api.get(base + '/api/engineer/' + assignmentId + '/ping').then(function (data) {
            if (!data || data.updated_at === lastKnownUpdatedAt) return;
            lastKnownUpdatedAt = data.updated_at;
            Toast.show('Assignment ini baru saja diperbarui. Muat ulang untuk melihat perubahan terbaru.', 'info', 6000);
            var indicator = document.getElementById('engineerLiveIndicator');
            if (indicator) indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <a href="javascript:location.reload()">Ada pembaruan — muat ulang</a>';
        }).catch(function () {});
    }
    setInterval(ping, Math.max(pollInterval, 10000));
})();
</script>
