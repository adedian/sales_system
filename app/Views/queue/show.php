<?php
$pageTitle = 'Antrian #' . $queue['queue_number'];
$statusRow = $statusMap[$queue['status']] ?? ['name' => $queue['status'], 'color' => 'muted'];
$priorityRow = $priorityMap[$queue['priority']] ?? ['name' => $queue['priority'], 'color' => 'muted'];
$isOverdue = !empty($queue['deadline']) && $queue['deadline'] < date('Y-m-d') && !in_array($queue['status'], ['done', 'cancelled'], true);
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow">
            Antrian #<?= (int) $queue['queue_number'] ?>
            <?php if ($isOverdue): ?><span class="overdue-badge"><i class="bi bi-exclamation-triangle-fill"></i> Overdue</span><?php endif; ?>
        </div>
        <h2><?= e(\App\Models\SalesQueue::displayTitle($queue)) ?></h2>
        <p class="text-muted">
            <a href="<?= url('/leads/' . $queue['lead_id']) ?>"><?= e($queue['lead_code']) ?></a>
            &middot; <?= e($queue['customer_name']) ?><?= $queue['company_name'] ? ' (' . e($queue['company_name']) . ')' : '' ?>
        </p>
    </div>
    <a href="<?= url('/queue') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card card-elevated">
            <div class="card-header"><h3>Informasi Lead</h3></div>
            <div class="card-body">
                <dl class="lead-dl">
                    <dt>Customer</dt><dd><?= e($queue['customer_name']) ?></dd>
                    <dt>Perusahaan</dt><dd><?= e($queue['company_name'] ?: '-') ?></dd>
                    <dt>Telepon</dt><dd><?= $queue['lead_phone'] ? '<a href="tel:' . e($queue['lead_phone']) . '">' . e($queue['lead_phone']) . '</a>' : '-' ?></dd>
                    <dt>Masuk Antrian</dt><dd><?= e(format_datetime($queue['entered_at'])) ?></dd>
                </dl>
            </div>
        </div>

        <div class="card card-elevated mt-3">
            <div class="card-header"><h3>Catatan</h3></div>
            <div class="card-body">
                <?php if ($canOperate): ?>
                <form method="POST" action="<?= url('/queue/' . $queue['id'] . '/notes') ?>" class="mb-3">
                    <?= csrf_field() ?>
                    <textarea class="form-control mb-2" name="note" rows="2" placeholder="Tambahkan catatan follow up..." required></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">Tambah Catatan</button>
                </form>
                <?php endif; ?>

                <?php
                $notes = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'note'));
                ?>
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
            <div class="card-body lead-side-panel" data-queue-id="<?= (int) $queue['id'] ?>" data-queue-updated-at="<?= e($queue['updated_at']) ?>">

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
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></div>
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

                <?php if ($currentPosition): ?>
                <div class="lead-side-field">
                    <label class="form-label">Proses Saat Ini</label>
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($currentPosition['color']) ?>"><?= e($currentPosition['label']) ?></span></div>
                </div>
                <?php endif; ?>

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
                    <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority"><?= e($priorityRow['name']) ?></span></div>
                </div>

                <div class="lead-side-field">
                    <label class="form-label">Sales Bertugas</label>
                    <select class="form-select queue-live-field" data-endpoint="assign" data-field="sales_id" <?= $canReassign ? '' : 'disabled' ?>>
                        <?php foreach ($salesUsers as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" <?= (int) $queue['sales_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="lead-side-current text-muted" data-badge="sales_name"><?= e($queue['sales_name'] ?? 'Belum ditugaskan') ?></div>
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
                    <div class="lead-side-current text-muted" data-badge="current_pic_name"><?= e($queue['current_pic_name'] ?? 'None') ?></div>
                </div>

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

        <div class="card card-elevated lead-panel mt-3">
            <div class="card-header"><h3>Detail Pekerjaan</h3></div>
            <div class="card-body lead-side-panel">
                <div class="lead-side-field">
                    <label class="form-label">Tanggal Mulai Engineering</label>
                    <input type="date" class="form-control queue-live-field" data-endpoint="engineering-start-date" data-field="engineering_start_date" value="<?= e($queue['engineering_start_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
                </div>
                <div class="lead-side-field">
                    <label class="form-label">Tanggal Akhir Engineering</label>
                    <input type="date" class="form-control queue-live-field" data-endpoint="engineering-end-date" data-field="engineering_end_date" value="<?= e($queue['engineering_end_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
                </div>
                <div class="lead-side-field">
                    <label class="form-label">Tanggal Mulai Procurement</label>
                    <input type="date" class="form-control queue-live-field" data-endpoint="procurement-start-date" data-field="procurement_start_date" value="<?= e($queue['procurement_start_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
                </div>
                <div class="lead-side-field">
                    <label class="form-label">Tanggal Akhir Procurement</label>
                    <input type="date" class="form-control queue-live-field" data-endpoint="procurement-end-date" data-field="procurement_end_date" value="<?= e($queue['procurement_end_date']) ?>" <?= $canOperate ? '' : 'disabled' ?>>
                </div>
                <div class="lead-side-field">
                    <label class="form-label">Catatan Tambahan (Column1)</label>
                    <textarea class="form-control queue-live-field" rows="2" data-endpoint="notes-field" data-field="notes" <?= $canOperate ? '' : 'disabled' ?>><?= e($queue['notes']) ?></textarea>
                </div>
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
                    var badge = panel.querySelector('[data-badge="status"]');
                    badge.textContent = data.label;
                    badge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'priority') {
                    var pbadge = panel.querySelector('[data-badge="priority"]');
                    pbadge.textContent = data.label;
                    pbadge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'assign') {
                    panel.querySelector('[data-badge="sales_name"]').textContent = data.sales_name;
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
                    panel.querySelector('[data-badge="current_pic_name"]').textContent = data.current_pic_name;
                } else if (endpoint === 'task-name') {
                    var heading = document.querySelector('.page-header h2');
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
