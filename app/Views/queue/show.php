<?php
$pageTitle = 'Antrian #' . $queue['queue_number'];
$statusRow = $statusMap[$queue['status']] ?? ['name' => $queue['status'], 'color' => 'muted'];
$priorityRow = $priorityMap[$queue['priority']] ?? ['name' => $queue['priority'], 'color' => 'muted'];
$isOverdue = !empty($queue['deadline']) && $queue['deadline'] < date('Y-m-d') && !in_array($queue['status'], ['done', 'cancelled'], true);
?>
<a href="<?= url('/queue') ?>" class="lead-back-link"><i class="bi bi-arrow-left"></i> Kembali ke Antrian</a>

<div class="lead-header-v2">
    <div class="lead-header-main">
        <div class="lead-header-eyebrow">
            <span class="mono">Antrian #<?= (int) $queue['queue_number'] ?></span>
            <?php if ($isOverdue): ?><span class="overdue-badge"><i class="bi bi-exclamation-triangle-fill"></i> Overdue</span><?php endif; ?>
        </div>
        <h2><?= e(\App\Models\SalesQueue::displayTitle($queue)) ?></h2>
        <div class="lead-header-meta">
            <a href="<?= url('/leads/' . $queue['lead_id']) ?>" class="mono"><?= e($queue['lead_code']) ?></a>
            &middot; <?= e($queue['customer_name']) ?><?= $queue['company_name'] ? ' (' . e($queue['company_name']) . ')' : '' ?>
        </div>
    </div>
    <div class="lead-header-side">
        <div class="lead-header-side-item">
            <span class="lead-header-side-label">Sales</span>
            <span class="lead-header-side-value" data-badge="sales_name"><?= e($queue['sales_name'] ?? 'Belum ditugaskan') ?></span>
        </div>
        <div class="lead-header-side-item">
            <span class="lead-header-side-label">Masuk Antrian</span>
            <span class="lead-header-side-value"><?= e(format_datetime($queue['entered_at'])) ?></span>
        </div>
    </div>
    <div class="lead-header-actions">
        <a href="<?= url('/leads/' . $queue['lead_id']) ?>" class="btn btn-light btn-sm"><i class="bi bi-person-lines-fill me-1"></i>Lihat Lead</a>
    </div>
</div>

<div class="process-panel">
    <div class="process-panel-grid">
        <div class="process-panel-item">
            <span class="process-panel-label">Status Antrian</span>
            <span class="process-panel-value"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></span>
        </div>
        <div class="process-panel-item process-panel-item-primary">
            <span class="process-panel-label">Current Process</span>
            <span class="process-panel-value process-panel-value-lg"><?= $currentPosition ? e($currentPosition['label']) : '-' ?></span>
        </div>
        <div class="process-panel-item">
            <span class="process-panel-label">Current PIC</span>
            <span class="process-panel-value text-muted" data-badge="current_pic_name"><?= e($queue['current_pic_name'] ?? 'None') ?></span>
        </div>
        <div class="process-panel-item">
            <span class="process-panel-label">Urgensi</span>
            <span class="process-panel-value"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority_display"><?= e($priorityRow['name']) ?></span></span>
        </div>
    </div>
</div>

<div class="detail-row">
    <div class="detail-section">
        <h3 class="detail-section-title">Informasi Lead</h3>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Customer</span><span class="info-item-value"><?= e($queue['customer_name']) ?></span></div>
            <div class="info-item"><span class="info-item-label">Perusahaan</span><span class="info-item-value"><?= e($queue['company_name'] ?: '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Telepon</span><span class="info-item-value"><?= $queue['lead_phone'] ? '<a href="tel:' . e($queue['lead_phone']) . '">' . e($queue['lead_phone']) . '</a>' : '-' ?></span></div>
            <div class="info-item"><span class="info-item-label">Masuk Antrian</span><span class="info-item-value"><?= e(format_datetime($queue['entered_at'])) ?></span></div>
        </div>

        <hr class="detail-divider">

        <h3 class="detail-section-title">Catatan</h3>
        <?php if ($canOperate): ?>
        <form method="POST" action="<?= url('/queue/' . $queue['id'] . '/notes') ?>" class="mb-3">
            <?= csrf_field() ?>
            <textarea class="form-control mb-2" name="note" rows="2" placeholder="Tambahkan catatan follow up..." required></textarea>
            <button type="submit" class="btn btn-primary btn-sm">Tambah Catatan</button>
        </form>
        <?php endif; ?>

        <?php $notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note')); ?>
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

    <div class="detail-section">
        <div class="lead-side-panel" data-queue-id="<?= (int) $queue['id'] ?>" data-queue-updated-at="<?= e($queue['updated_at']) ?>">

            <h3 class="detail-section-title">Status</h3>
            <div class="lead-side-field">
                <label class="form-label" for="taskName">Tugas</label>
                <input type="text" class="form-control queue-live-field" id="taskName" data-endpoint="task-name" data-field="task_name" value="<?= e($queue['task_name']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Status</label>
                <select class="form-select queue-live-field" data-endpoint="status" data-field="status" <?= $canOperate ? '' : 'disabled' ?>>
                    <?php foreach ($statusMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $queue['status'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Status Survey</label>
                <select class="form-select queue-live-field" data-endpoint="survey-status" data-field="survey_status_id" <?= $canOperate ? '' : 'disabled' ?>>
                    <option value="">Belum diisi</option>
                    <?php foreach ($surveyStatusMap as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) ($queue['survey_status_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($queue['survey_status_color'] ?? 'muted') ?>" data-badge="survey_status"><?= e($queue['survey_status_name'] ?? 'Belum diisi') ?></span></div>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Catatan Tahap (manual)</label>
                <select class="form-select queue-live-field" data-endpoint="stage" data-field="stage_id" <?= $canOperate ? '' : 'disabled' ?>>
                    <option value="">Belum diisi</option>
                    <?php foreach ($stageMap as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) ($queue['stage_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($queue['stage_color'] ?? 'muted') ?>" data-badge="stage"><?= e($queue['stage_name'] ?? 'Belum diisi') ?></span></div>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Urgensi</label>
                <select class="form-select queue-live-field" data-endpoint="priority" data-field="priority" <?= $canOperate ? '' : 'disabled' ?>>
                    <?php foreach ($priorityMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $queue['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr class="detail-divider">

            <h3 class="detail-section-title">Penugasan</h3>
            <div class="lead-side-field">
                <label class="form-label">Sales Bertugas</label>
                <select class="form-select queue-live-field" data-endpoint="assign" data-field="sales_id" <?= $canReassign ? '' : 'disabled' ?>>
                    <?php foreach ($salesUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) $queue['sales_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Estimator</label>
                <select class="form-select queue-live-field" data-endpoint="estimator" data-field="estimator_id" <?= $canOperate ? '' : 'disabled' ?>>
                    <option value="">None</option>
                    <?php foreach ($estimators as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) ($queue['estimator_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current text-muted" data-badge="estimator_name"><?= e($queue['estimator_name'] ?? 'None') ?></div>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Surveyor</label>
                <select class="form-select queue-live-field" data-endpoint="surveyor" data-field="surveyor_id" <?= $canOperate ? '' : 'disabled' ?>>
                    <option value="">None</option>
                    <?php foreach ($surveyors as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) ($queue['surveyor_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current text-muted" data-badge="surveyor_name"><?= e($queue['surveyor_name'] ?? 'None') ?></div>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Current PIC</label>
                <select class="form-select queue-live-field" data-endpoint="current-pic" data-field="current_pic_id" <?= $canOperate ? '' : 'disabled' ?>>
                    <option value="">None</option>
                    <?php foreach ($currentPicUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) ($queue['current_pic_id'] ?? 0) === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <hr class="detail-divider">

            <h3 class="detail-section-title">Jadwal</h3>
            <div class="lead-side-field">
                <label class="form-label">Deadline</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="deadline" data-field="deadline" value="<?= e($queue['deadline']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Tanggal Follow Up</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="follow-up-date" data-field="follow_up_date" value="<?= e($queue['followup_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>

            <div class="lead-live-indicator text-muted" id="queueLiveIndicator"><i class="bi bi-broadcast"></i> Realtime aktif</div>
        </div>
    </div>
</div>

<div class="detail-section">
    <h3 class="detail-section-title">Detail Pekerjaan</h3>
    <div class="detail-row">
        <div>
            <h4 class="text-muted small text-uppercase mb-2">Engineering</h4>
            <div class="lead-side-field">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="engineering-start-date" data-field="engineering_start_date" value="<?= e($queue['engineering_start_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>
            <div class="lead-side-field">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="engineering-end-date" data-field="engineering_end_date" value="<?= e($queue['engineering_end_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>
        </div>
        <div>
            <h4 class="text-muted small text-uppercase mb-2">Procurement</h4>
            <div class="lead-side-field">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="procurement-start-date" data-field="procurement_start_date" value="<?= e($queue['procurement_start_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>
            <div class="lead-side-field">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" class="form-control queue-live-field" data-endpoint="procurement-end-date" data-field="procurement_end_date" value="<?= e($queue['procurement_end_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
            </div>
        </div>
    </div>
    <div class="lead-side-field mt-2">
        <label class="form-label">Catatan Tambahan</label>
        <textarea class="form-control queue-live-field" rows="2" data-endpoint="notes-field" data-field="notes" <?= $canOperate ? '' : 'disabled' ?>><?= e($queue['notes']) ?></textarea>
    </div>
</div>

<div class="detail-section">
    <h3 class="detail-section-title">Timeline Aktivitas</h3>
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
                        <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Antrian dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
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

<script>
(function () {
    var panel = document.querySelector('[data-queue-id]');
    if (!panel) return;
    var queueId = panel.getAttribute('data-queue-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var lastKnownUpdatedAt = panel.getAttribute('data-queue-updated-at');

    document.querySelectorAll('.queue-live-field').forEach(function (field) {
        field.addEventListener('change', function () {
            var endpoint = field.getAttribute('data-endpoint');
            var payload = {};
            payload[field.getAttribute('data-field')] = field.value;

            Api.post(base + '/api/queue/' + queueId + '/' + endpoint, payload).then(function (data) {
                if (!data || data.success === false) {
                    Toast.show('Gagal menyimpan perubahan.', 'danger');
                    return;
                }
                Toast.show('Perubahan tersimpan.', 'success');
                if (data.updated_at) lastKnownUpdatedAt = data.updated_at;

                if (endpoint === 'status') {
                    var badge = document.querySelector('[data-badge="status"]');
                    badge.textContent = data.label;
                    badge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'priority') {
                    var pdisplay = document.querySelector('[data-badge="priority_display"]');
                    if (pdisplay) { pdisplay.textContent = data.label; pdisplay.className = 'color-swatch color-swatch-' + data.color; }
                } else if (endpoint === 'assign') {
                    var salesBadge = document.querySelector('[data-badge="sales_name"]');
                    if (salesBadge) salesBadge.textContent = data.sales_name;
                } else if (endpoint === 'survey-status') {
                    var sbadge = panel.querySelector('[data-badge="survey_status"]');
                    sbadge.textContent = data.label;
                    sbadge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'stage') {
                    var stbadge = panel.querySelector('[data-badge="stage"]');
                    stbadge.textContent = data.label;
                    stbadge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'estimator') {
                    panel.querySelector('[data-badge="estimator_name"]').textContent = data.estimator_name;
                } else if (endpoint === 'surveyor') {
                    panel.querySelector('[data-badge="surveyor_name"]').textContent = data.surveyor_name;
                } else if (endpoint === 'current-pic') {
                    var picBadge = document.querySelector('[data-badge="current_pic_name"]');
                    if (picBadge) picBadge.textContent = data.current_pic_name;
                } else if (endpoint === 'task-name') {
                    var heading = document.querySelector('.lead-header-main h2');
                    if (heading) heading.textContent = data.display_title;
                }
            }).catch(function () {
                Toast.show('Gagal menyimpan perubahan, silakan coba lagi.', 'danger');
            });
        });
    });

    var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);
    function ping() {
        Api.get(base + '/api/queue/' + queueId + '/ping').then(function (data) {
            if (!data || data.updated_at === lastKnownUpdatedAt) return;
            lastKnownUpdatedAt = data.updated_at;
            Toast.show('Antrian ini baru saja diperbarui. Muat ulang untuk melihat perubahan terbaru.', 'info', 6000);
            var indicator = document.getElementById('queueLiveIndicator');
            if (indicator) indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <a href="javascript:location.reload()">Ada pembaruan — muat ulang</a>';
        }).catch(function () {});
    }
    setInterval(ping, Math.max(pollInterval, 10000));
})();
</script>
