<?php
$pageTitle = $lead['lead_code'];
$statusRow = $statusMap[$lead['status']] ?? ['name' => $lead['status'], 'color' => 'muted'];
$priorityRow = $priorityMap[$lead['priority']] ?? ['name' => $lead['priority'], 'color' => 'muted'];
$actionLabels = [
    'lead_updated' => 'Data lead diperbarui',
    'lead_status_changed' => 'Status diubah',
    'lead_priority_changed' => 'Prioritas diubah',
    'lead_assigned' => 'Penugasan sales diubah',
    'lead_followup_date_changed' => 'Tanggal follow up diubah',
    'followup_logged' => 'Follow up dicatat',
    'lead_deleted' => 'Lead dipindahkan ke sampah',
    'lead_restored' => 'Lead dipulihkan',
];
$responseColors = [
    'interested' => 'emerald',
    'negotiating' => 'amber',
    'need_info' => 'indigo',
    'not_interested' => 'danger',
    'no_answer' => 'muted',
];
$simpleLabels = ['proses' => 'Proses', 'deal' => 'Deal', 'cancel' => 'Cancel'];
$simpleColors = ['proses' => 'amber', 'deal' => 'emerald', 'cancel' => 'danger'];
$tagParts = array_values(array_filter([$lead['type_name'] ?? null, $lead['system_name'] ?? null, $lead['funding_name'] ?? null]));
$otherSales = array_filter($assignedSales, fn ($r) => (int) $r['id'] !== (int) $lead['sales_id']);

/*
 * Process Workflow — the 8 stages below mirror the exact precedence already
 * coded in App\Models\Lead::currentPosition() (see that method's docblock),
 * just reversed into chronological order. This view does not invent new
 * business logic: $currentPosition['label'] is the same value already
 * computed server-side and shown elsewhere (dashboard, monitoring section).
 * The only extra piece of information not carried by that single label is
 * disambiguating the two "Sales" stops (initial ownership vs. closing after
 * price approval) — done via lead.status === 'pricing_ready', an existing
 * column, not a new rule.
 *
 * Known simplification: currentPosition() also cannot distinguish
 * "Procurement" before Direktur validation from "Procurement" finalizing
 * after approval (both just return the label 'Procurement'), so this
 * stepper only has one Procurement stop. Improving that would need a new
 * backend signal, not a view-only change.
 */
$posLabel = $currentPosition['label'];
$steps = [
    ['label' => 'Sales'],
    ['label' => 'Survey'],
    ['label' => 'Data Antrian'],
    ['label' => $posLabel === 'Engineer' ? 'Engineer' : 'Sales Engineer'],
    ['label' => 'Procurement'],
    ['label' => 'Approval Harga'],
    ['label' => 'Sales'],
    ['label' => 'Proposal'],
];
$stepIndexByLabel = [
    'Survey' => 1,
    'Data Antrian' => 2,
    'Sales Engineer' => 3,
    'Engineer' => 3,
    'Procurement' => 4,
    'Approval Harga' => 5,
    'Proposal' => 7,
];
if (in_array($lead['status'], ['won', 'lost'], true)) {
    $currentStepIndex = count($steps);
} elseif ($posLabel === 'Sales') {
    $currentStepIndex = $lead['status'] === 'pricing_ready' ? 6 : 0;
} else {
    $currentStepIndex = $stepIndexByLabel[$posLabel] ?? 0;
}

// Current PIC + "Since" for the Process Panel — picks whichever timestamp/name
// already exists for the active module behind $currentPosition; no new queries.
$sinceAt = $lead['updated_at'];
$picValue = $lead['sales_name'] ?? 'Belum ditugaskan';
if ($posLabel === 'Approval Harga') {
    $pendingRow = null;
    foreach ($priceValidations as $v) {
        if ($v['status'] === 'pending') { $pendingRow = $v; break; }
    }
    $picValue = 'Menunggu validasi Direktur';
    $sinceAt = $pendingRow['submitted_at'] ?? $sinceAt;
} elseif ($posLabel === 'Procurement') {
    $picValue = $activeProcurementRequest['assigned_to_name'] ?? '-';
    $sinceAt = $activeProcurementRequest['requested_at'] ?? $sinceAt;
} elseif ($posLabel === 'Sales Engineer') {
    $picValue = $activeSalesEngineerAssignment['engineer_name'] ?? '-';
    $sinceAt = $activeSalesEngineerAssignment['assigned_at'] ?? $sinceAt;
} elseif ($posLabel === 'Engineer') {
    $picValue = $activeEngineerAssignment['engineer_name'] ?? '-';
    $sinceAt = $activeEngineerAssignment['assigned_at'] ?? $sinceAt;
} elseif ($posLabel === 'Data Antrian' || $posLabel === 'Survey') {
    $picValue = $activeQueue['current_pic_name'] ?? ($activeQueue['surveyor_name'] ?? '-');
    $sinceAt = $activeQueue['entered_at'] ?? $sinceAt;
} elseif ($posLabel === 'Proposal') {
    $picValue = $lead['sales_name'] ?? '-';
    $sinceAt = !empty($proposals) ? $proposals[0]['created_at'] : $sinceAt;
} elseif ($lead['status'] === 'won' || $lead['status'] === 'lost') {
    $sinceAt = $lead['closing_date'] ?? $sinceAt;
}

$latestPriceValidation = $priceValidations[0] ?? null;
?>
<div class="lead-page-root" data-lead-id="<?= (int) $lead['id'] ?>" data-lead-updated-at="<?= e($lead['updated_at']) ?>">

<a href="<?= url('/leads') ?>" class="lead-back-link"><i class="bi bi-arrow-left"></i> Kembali ke Leads</a>

<div class="lead-header-v2">
    <div class="lead-header-main">
        <div class="lead-header-eyebrow">
            <span class="mono"><?= e($lead['lead_code']) ?></span>
            <?php if ($lead['deleted_at']): ?><span class="badge-pill badge-pill-muted">Di Sampah</span><?php endif; ?>
            <span class="color-swatch color-swatch-<?= e($simpleColors[$simplifiedStatus]) ?>"><?= e($simpleLabels[$simplifiedStatus]) ?></span>
        </div>
        <h2><?= e($lead['customer_name']) ?></h2>
        <div class="lead-header-meta">
            <?= e($lead['company_name'] ?: 'Tanpa perusahaan') ?><?= !empty($tagParts) ? ' · ' . e(implode(' · ', $tagParts)) : '' ?>
        </div>
    </div>
    <div class="lead-header-side">
        <div class="lead-header-side-item">
            <span class="lead-header-side-label">Sales</span>
            <span class="lead-header-side-value"><?= e($lead['sales_name'] ?? 'Belum ditugaskan') ?><?= !empty($otherSales) ? ' / ' . e(implode(' / ', array_map(fn ($r) => $r['name'], $otherSales))) : '' ?></span>
        </div>
        <div class="lead-header-side-item">
            <span class="lead-header-side-label">Terakhir diperbarui</span>
            <span class="lead-header-side-value" data-field="updated_at_display"><?= e(format_datetime($lead['updated_at'])) ?></span>
        </div>
    </div>
    <div class="lead-header-actions">
        <?php if ($canManage && !$lead['deleted_at']): ?>
        <a href="<?= url('/leads/' . $lead['id'] . '/edit') ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <div class="dropdown">
            <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots"></i> More
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php if ($activeQueue): ?>
                <li><a class="dropdown-item" href="<?= url('/queue/' . $activeQueue['id']) ?>"><i class="bi bi-list-ol me-2"></i>Lihat Antrian #<?= (int) $activeQueue['queue_number'] ?></a></li>
                <?php elseif ($canEnqueue && !$lead['deleted_at']): ?>
                <li>
                    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/enqueue') ?>" data-confirm="Masukkan lead ini ke antrian sales?">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item"><i class="bi bi-list-ol me-2"></i>Masukkan ke Antrian</button>
                    </form>
                </li>
                <?php endif; ?>
                <?php if ($latestEngineerAssignment): ?>
                <li><a class="dropdown-item" href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>"><i class="bi bi-tools me-2"></i>Lihat Assignment Engineer</a></li>
                <?php elseif (!$activeEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && count($engineerFieldUsers)): ?>
                <li><a class="dropdown-item" href="#request-engineer"><i class="bi bi-tools me-2"></i>Minta Engineer</a></li>
                <?php endif; ?>
                <?php if ($latestSalesEngineerAssignment): ?>
                <li><a class="dropdown-item" href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>"><i class="bi bi-person-gear me-2"></i>Lihat Assignment Sales Engineer</a></li>
                <?php elseif (!$activeSalesEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && count($salesEngineerUsers)): ?>
                <li><a class="dropdown-item" href="#request-sales-engineer"><i class="bi bi-person-gear me-2"></i>Minta Sales Engineer</a></li>
                <?php endif; ?>
                <?php if ($latestProcurementRequest): ?>
                <li><a class="dropdown-item" href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>"><i class="bi bi-truck me-2"></i>Lihat Request Procurement</a></li>
                <?php elseif (!$activeProcurementRequest && $canRequestProcurement && !$lead['deleted_at'] && count($procurementUsers)): ?>
                <li><a class="dropdown-item" href="#request-procurement"><i class="bi bi-truck me-2"></i>Minta Procurement</a></li>
                <?php endif; ?>
                <?php if (!empty($proposals)): ?>
                <li><a class="dropdown-item" href="<?= url('/proposals/' . $proposals[0]['id']) ?>"><i class="bi bi-file-earmark-text me-2"></i>Lihat Proposal</a></li>
                <?php elseif ($canCreateProposal && !$lead['deleted_at']): ?>
                <li><a class="dropdown-item" href="#proposals"><i class="bi bi-file-earmark-plus me-2"></i>Buat Proposal</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<div class="process-panel">
    <div class="process-panel-grid">
        <div class="process-panel-item">
            <span class="process-panel-label">Lead Status</span>
            <span class="process-panel-value"><span class="color-swatch color-swatch-<?= e($simpleColors[$simplifiedStatus]) ?>" data-badge="simple_status"><?= e($simpleLabels[$simplifiedStatus]) ?></span></span>
        </div>
        <div class="process-panel-item">
            <span class="process-panel-label">Current Process</span>
            <span class="process-panel-value process-panel-value-lg"><?= e($posLabel === 'Sales' && $currentStepIndex === 6 ? 'Sales (Closing)' : $posLabel) ?></span>
            <span class="process-panel-since">Sejak <?= e(format_datetime($sinceAt)) ?></span>
        </div>
        <div class="process-panel-item">
            <span class="process-panel-label">Current PIC</span>
            <span class="process-panel-value"><?= e($picValue) ?></span>
        </div>
        <div class="process-panel-item">
            <span class="process-panel-label">Priority</span>
            <span class="process-panel-value"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority_display"><?= e($priorityRow['name']) ?></span></span>
        </div>
    </div>
</div>

<div class="detail-row">
    <div class="detail-section">
        <h3 class="detail-section-title">Customer Information</h3>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Customer</span><span class="info-item-value"><?= e($lead['customer_name']) ?></span></div>
            <div class="info-item"><span class="info-item-label">Perusahaan</span><span class="info-item-value"><?= e($lead['company_name'] ?: '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Telepon</span><span class="info-item-value"><?= $lead['phone'] ? '<a href="tel:' . e($lead['phone']) . '">' . e($lead['phone']) . '</a>' : '-' ?></span></div>
            <div class="info-item"><span class="info-item-label">Email</span><span class="info-item-value"><?= $lead['email'] ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>' : '-' ?></span></div>
            <div class="info-item"><span class="info-item-label">Lokasi</span><span class="info-item-value"><?= e($lead['site_location'] ?: '-') ?></span></div>
            <div class="info-item info-item-full"><span class="info-item-label">Alamat</span><span class="info-item-value"><?= $lead['address'] ? nl2br(e($lead['address'])) : '-' ?></span></div>
        </div>

        <hr class="detail-divider">

        <h3 class="detail-section-title">Lead Information</h3>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Type</span><span class="info-item-value"><?= e($lead['type_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">System</span><span class="info-item-value"><?= e($lead['system_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Funding</span><span class="info-item-value"><?= e($lead['funding_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Size (KWp)</span><span class="info-item-value"><?= $lead['size_kwp'] !== null ? e(rtrim(rtrim(number_format((float) $lead['size_kwp'], 2, '.', ''), '0'), '.')) : '-' ?></span></div>
            <div class="info-item"><span class="info-item-label">Sumber</span><span class="info-item-value"><?= e($lead['source_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Kategori</span><span class="info-item-value"><?= e($lead['category_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Jenis Kebutuhan</span><span class="info-item-value"><?= e($lead['need_type_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Estimasi Nilai</span><span class="info-item-value"><?= $lead['estimated_value'] !== null ? 'Rp ' . number_format((float) $lead['estimated_value'], 0, ',', '.') : '-' ?></span></div>
            <div class="info-item info-item-full"><span class="info-item-label">Deskripsi Kebutuhan</span><span class="info-item-value"><?= $lead['needs_description'] ? nl2br(e($lead['needs_description'])) : '-' ?></span></div>
            <?php if ($lead['notes'] || $lead['note2']): ?>
            <div class="info-item info-item-full">
                <span class="info-item-label">Catatan</span>
                <span class="info-item-value">
                    <?= $lead['notes'] ? nl2br(e($lead['notes'])) : '' ?>
                    <?php if ($lead['note2']): ?><br><?= nl2br(e($lead['note2'])) ?><?php endif; ?>
                    <?php if ($lead['note_updated_at']): ?><br><span class="text-muted small"><?= e(format_datetime($lead['note_updated_at'])) ?></span><?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="info-item"><span class="info-item-label">Dibuat oleh</span><span class="info-item-value"><?= e($lead['created_by_name'] ?? 'Sistem') ?> &middot; <?= e(format_datetime($lead['created_at'])) ?></span></div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Assignment &amp; Prioritas</h3>
        <?php
            // Only these move manually via this quick-select (see LeadApiController::STATUSES).
            // The rest (procurement/pricing_ready/proposal/won/lost) are driven by their own
            // dedicated workflow actions elsewhere on this page.
            $manualStatuses = ['new', 'in_queue', 'follow_up', 'engineering'];
            $statusIsManual = in_array($lead['status'], $manualStatuses, true);
        ?>
        <div class="lead-side-panel">
            <div class="lead-side-field">
                <label class="form-label">Status Detail</label>
                <select class="form-select lead-live-field" data-endpoint="status" data-field="status" <?= ($canManage && $statusIsManual) ? '' : 'disabled' ?>>
                    <?php foreach ($statusMap as $code => $row): ?>
                        <?php if (!in_array($code, $manualStatuses, true) && $code !== $lead['status']) continue; ?>
                        <option value="<?= e($code) ?>" <?= $lead['status'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($statusRow['color']) ?>" data-badge="status"><?= e($statusRow['name']) ?></span></div>
                <?php if (!$statusIsManual): ?><p class="text-muted small mt-1 mb-0">Status ini dikelola otomatis dari alur kerja di bawah.</p><?php endif; ?>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Prioritas</label>
                <select class="form-select lead-live-field" data-endpoint="priority" data-field="priority" <?= $canManage ? '' : 'disabled' ?>>
                    <?php foreach ($priorityMap as $code => $row): ?>
                        <option value="<?= e($code) ?>" <?= $lead['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current"><span class="color-swatch color-swatch-<?= e($priorityRow['color']) ?>" data-badge="priority"><?= e($priorityRow['name']) ?></span></div>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Sales Bertugas</label>
                <select class="form-select lead-live-field" data-endpoint="assign" data-field="sales_id" <?= $canAssign ? '' : 'disabled' ?>>
                    <option value="">Belum ditugaskan</option>
                    <?php foreach ($salesUsers as $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (int) $lead['sales_id'] === (int) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="lead-side-current text-muted" data-badge="sales_name"><?= e($lead['sales_name'] ?? 'Belum ditugaskan') ?></div>
                <?php if (!empty($otherSales)): ?>
                <div class="mt-1">
                    <label class="form-label small mb-0">Sales Lain</label>
                    <div class="text-muted small"><?= e(implode(' / ', array_map(fn ($r) => $r['name'], $otherSales))) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="lead-side-field">
                <label class="form-label">Tanggal Follow Up</label>
                <input type="date" class="form-control lead-live-field" data-endpoint="follow-up-date" data-field="follow_up_date" value="<?= e($lead['follow_up_date']) ?>" <?= $canManage ? '' : 'disabled' ?>>
            </div>

            <div class="lead-live-indicator text-muted" id="leadLiveIndicator"><i class="bi bi-broadcast"></i> Realtime aktif</div>
        </div>
    </div>
</div>

<div class="detail-section">
    <h3 class="detail-section-title">Process Workflow</h3>
    <div class="workflow-stepper">
        <?php foreach ($steps as $i => $step): ?>
            <?php
                if ($lead['status'] === 'lost') {
                    $state = $i < $currentStepIndex ? 'completed' : 'cancelled';
                } else {
                    $state = $i < $currentStepIndex ? 'completed' : ($i === $currentStepIndex ? 'current' : 'upcoming');
                }
            ?>
            <div class="workflow-step workflow-step-<?= e($state) ?>">
                <div class="workflow-step-dot"></div>
                <div class="workflow-step-label"><?= e($step['label']) ?></div>
            </div>
        <?php endforeach; ?>
        <?php
            $finalState = $lead['status'] === 'won' ? 'completed' : ($lead['status'] === 'lost' ? 'cancelled' : 'upcoming');
        ?>
        <div class="workflow-step workflow-step-<?= e($finalState) ?>">
            <div class="workflow-step-dot"></div>
            <div class="workflow-step-label"><?= $lead['status'] === 'lost' ? 'Cancel' : 'Client' ?></div>
        </div>
    </div>
</div>

<div class="detail-row">
    <div class="detail-section">
        <h3 class="detail-section-title">Process History</h3>
        <?php $statusEvents = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'status')); ?>
        <?php if (empty($statusEvents)): ?>
            <p class="text-muted small mb-0">Belum ada perubahan status.</p>
        <?php else: ?>
        <div class="process-history">
            <?php foreach ($statusEvents as $event): ?>
            <div class="process-history-item">
                <div class="process-history-date"><?= e(format_datetime($event['created_at'], 'd M Y')) ?></div>
                <div class="process-history-body">
                    <div>
                        <?= $event['from'] ? '<strong>' . e($statusMap[$event['to']]['name'] ?? $event['to']) . '</strong> (dari ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ')' : 'Lead dibuat &middot; <strong>' . e($statusMap[$event['to']]['name'] ?? $event['to']) . '</strong>' ?>
                    </div>
                    <?php if ($event['notes']): ?><div class="process-history-notes"><?= e($event['notes']) ?></div><?php endif; ?>
                    <div class="process-history-notes"><?= e($event['actor']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Activity</h3>
        <?php $auditEvents = array_values(array_filter($timeline, fn ($e) => $e['type'] === 'audit')); ?>
        <?php if (empty($auditEvents)): ?>
            <p class="text-muted small mb-0">Belum ada aktivitas lain tercatat.</p>
        <?php else: ?>
        <div class="timeline timeline-compact">
            <?php foreach ($auditEvents as $event): ?>
            <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-body">
                    <div class="timeline-title"><?= e($actionLabels[$event['action']] ?? $event['action']) ?></div>
                    <div class="timeline-meta"><?= e($event['actor']) ?> &middot; <?= e(format_datetime($event['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="detail-section">
    <h3 class="detail-section-title">Survey</h3>
    <?php if ($activeQueue): ?>
    <div class="info-grid">
        <div class="info-item"><span class="info-item-label">Survey Status</span><span class="info-item-value"><?= e($activeQueue['survey_status_name'] ?? 'Belum diisi') ?></span></div>
        <div class="info-item"><span class="info-item-label">Surveyor</span><span class="info-item-value"><?= e($activeQueue['surveyor_name'] ?? 'Not Assigned') ?></span></div>
        <div class="info-item"><span class="info-item-label">Estimator</span><span class="info-item-value"><?= e($activeQueue['estimator_name'] ?? 'Not Assigned') ?></span></div>
        <div class="info-item"><span class="info-item-label">Masuk Antrian</span><span class="info-item-value"><?= e(format_datetime($activeQueue['entered_at'])) ?></span></div>
    </div>
    <a href="<?= url('/queue/' . $activeQueue['id']) ?>" class="btn btn-sm btn-light mt-3">Lihat Detail Antrian &amp; Catatan</a>
    <?php else: ?>
    <div class="info-grid">
        <div class="info-item"><span class="info-item-label">Survey Status</span><span class="info-item-value">Belum masuk antrian</span></div>
        <div class="info-item"><span class="info-item-label">Surveyor</span><span class="info-item-value">Not Assigned</span></div>
    </div>
    <?php endif; ?>
</div>

<div class="detail-section" id="request-engineer-section">
    <h3 class="detail-section-title">Engineering</h3>
    <div class="detail-row">
        <div>
            <h4 class="text-muted small text-uppercase mb-2">Engineer (Survey Teknis &amp; Desain)</h4>
            <?php if ($latestEngineerAssignment): ?>
                <?php $eaStatus = $engineerStatusMap[$latestEngineerAssignment['status']] ?? ['name' => $latestEngineerAssignment['status'], 'color' => 'muted']; ?>
                <div class="info-grid mb-3">
                    <div class="info-item"><span class="info-item-label">Kode Assignment</span><span class="info-item-value"><a href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>" class="mono"><?= e($latestEngineerAssignment['assignment_code']) ?></a></span></div>
                    <div class="info-item"><span class="info-item-label">Engineer</span><span class="info-item-value"><?= e($latestEngineerAssignment['engineer_name'] ?? '-') ?></span></div>
                    <div class="info-item"><span class="info-item-label">Status</span><span class="info-item-value"><span class="color-swatch color-swatch-<?= e($eaStatus['color']) ?>"><?= e($eaStatus['name']) ?></span></span></div>
                    <div class="info-item"><span class="info-item-label">Deadline</span><span class="info-item-value"><?= $latestEngineerAssignment['deadline'] ? e(format_datetime($latestEngineerAssignment['deadline'], 'd M Y')) : '-' ?></span></div>
                </div>
                <?php if ($latestEngineerAssignment['result_notes']): ?>
                    <div class="note-item mb-3"><div class="note-text"><?= nl2br(e($latestEngineerAssignment['result_notes'])) ?></div><div class="note-meta">Hasil dari engineer</div></div>
                <?php endif; ?>
                <a href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>" class="btn btn-sm btn-light" id="request-engineer">Lihat Detail Assignment</a>
            <?php else: ?>
                <p class="text-muted small mb-3" id="request-engineer">Belum ada assignment engineer untuk lead ini.</p>
            <?php endif; ?>

            <?php if (!$activeEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && !empty($engineerFieldUsers)): ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-engineer') ?>" class="row g-2 mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_type" value="engineer">
                <div class="col-12">
                    <label class="form-label">Engineer</label>
                    <select name="engineer_id" class="form-select" required>
                        <option value="">Pilih engineer...</option>
                        <?php foreach ($engineerFieldUsers as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Prioritas</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priorityMap as $code => $row): ?>
                            <option value="<?= e($code) ?>" <?= $lead['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan untuk Engineer</label>
                    <textarea name="notes_from_sales" class="form-control" rows="2" placeholder="Konteks/instruksi untuk engineer (opsional)"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Kirim ke Engineer</button>
                </div>
            </form>
            <?php elseif (empty($engineerFieldUsers) && !$activeEngineerAssignment): ?>
                <p class="text-muted small mb-0">Belum ada akun Engineer yang aktif.</p>
            <?php endif; ?>
        </div>

        <div>
            <h4 class="text-muted small text-uppercase mb-2">Sales Engineer (Analisa Teknis)</h4>
            <?php if ($latestSalesEngineerAssignment): ?>
                <?php $seStatus = $engineerStatusMap[$latestSalesEngineerAssignment['status']] ?? ['name' => $latestSalesEngineerAssignment['status'], 'color' => 'muted']; ?>
                <div class="info-grid mb-3">
                    <div class="info-item"><span class="info-item-label">Kode Assignment</span><span class="info-item-value"><a href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>" class="mono"><?= e($latestSalesEngineerAssignment['assignment_code']) ?></a></span></div>
                    <div class="info-item"><span class="info-item-label">Sales Engineer</span><span class="info-item-value"><?= e($latestSalesEngineerAssignment['engineer_name'] ?? '-') ?></span></div>
                    <div class="info-item"><span class="info-item-label">Status</span><span class="info-item-value"><span class="color-swatch color-swatch-<?= e($seStatus['color']) ?>"><?= e($seStatus['name']) ?></span></span></div>
                    <div class="info-item"><span class="info-item-label">Deadline</span><span class="info-item-value"><?= $latestSalesEngineerAssignment['deadline'] ? e(format_datetime($latestSalesEngineerAssignment['deadline'], 'd M Y')) : '-' ?></span></div>
                </div>
                <?php if ($latestSalesEngineerAssignment['result_notes']): ?>
                    <div class="note-item mb-3"><div class="note-text"><?= nl2br(e($latestSalesEngineerAssignment['result_notes'])) ?></div><div class="note-meta">Hasil analisa dari sales engineer</div></div>
                <?php endif; ?>
                <a href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>" class="btn btn-sm btn-light" id="request-sales-engineer">Lihat Detail Assignment</a>
            <?php else: ?>
                <p class="text-muted small mb-3" id="request-sales-engineer">Belum ada assignment sales engineer untuk lead ini.</p>
            <?php endif; ?>

            <?php if (!$activeSalesEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && !empty($salesEngineerUsers)): ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-engineer') ?>" class="row g-2 mt-1">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_type" value="sales_engineer">
                <div class="col-12">
                    <label class="form-label">Sales Engineer</label>
                    <select name="engineer_id" class="form-select" required>
                        <option value="">Pilih sales engineer...</option>
                        <?php foreach ($salesEngineerUsers as $row): ?>
                            <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Prioritas</label>
                    <select name="priority" class="form-select">
                        <?php foreach ($priorityMap as $code => $row): ?>
                            <option value="<?= e($code) ?>" <?= $lead['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan untuk Sales Engineer</label>
                    <textarea name="notes_from_sales" class="form-control" rows="2" placeholder="Konteks/instruksi untuk sales engineer (opsional)"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Kirim ke Sales Engineer</button>
                </div>
            </form>
            <?php elseif (empty($salesEngineerUsers) && !$activeSalesEngineerAssignment): ?>
                <p class="text-muted small mb-0">Belum ada akun Sales Engineer yang aktif.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="detail-section" id="request-procurement">
    <h3 class="detail-section-title">Procurement</h3>
    <?php if ($latestProcurementRequest): ?>
        <?php $prStatus = $procurementStatusMap[$latestProcurementRequest['status']] ?? ['name' => $latestProcurementRequest['status'], 'color' => 'muted']; ?>
        <div class="info-grid mb-3">
            <div class="info-item"><span class="info-item-label">Kode Request</span><span class="info-item-value"><a href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>" class="mono"><?= e($latestProcurementRequest['request_code']) ?></a></span></div>
            <div class="info-item"><span class="info-item-label">Staff Procurement</span><span class="info-item-value"><?= e($latestProcurementRequest['assigned_to_name'] ?? '-') ?></span></div>
            <div class="info-item"><span class="info-item-label">Status</span><span class="info-item-value"><span class="color-swatch color-swatch-<?= e($prStatus['color']) ?>"><?= e($prStatus['name']) ?></span></span></div>
            <div class="info-item"><span class="info-item-label">Deadline</span><span class="info-item-value"><?= $latestProcurementRequest['deadline'] ? e(format_datetime($latestProcurementRequest['deadline'], 'd M Y')) : '-' ?></span></div>
        </div>
        <a href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>" class="btn btn-sm btn-light mb-3">Lihat Detail Request</a>
    <?php else: ?>
        <p class="text-muted small mb-3">Belum ada request procurement untuk lead ini. <span class="text-muted">(Waiting)</span></p>
    <?php endif; ?>

    <?php if (!$activeProcurementRequest && $canRequestProcurement && !$lead['deleted_at'] && !empty($procurementUsers)): ?>
    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-procurement') ?>" class="row g-2">
        <?= csrf_field() ?>
        <div class="col-12 col-md-4">
            <label class="form-label">Staff Procurement</label>
            <select name="assigned_to" class="form-select" required>
                <option value="">Pilih staff...</option>
                <?php foreach ($procurementUsers as $row): ?>
                    <option value="<?= (int) $row['id'] ?>"><?= e($row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Prioritas</label>
            <select name="priority" class="form-select">
                <?php foreach ($priorityMap as $code => $row): ?>
                    <option value="<?= e($code) ?>" <?= $lead['priority'] === $code ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Deadline</label>
            <input type="date" name="deadline" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Catatan untuk Procurement</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Konteks/instruksi untuk procurement (opsional)"></textarea>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Kirim ke Procurement</button>
        </div>
    </form>
    <?php elseif (empty($procurementUsers) && !$activeProcurementRequest): ?>
        <p class="text-muted small mb-0">Belum ada akun Procurement yang aktif.</p>
    <?php endif; ?>

    <hr class="detail-divider">

    <h3 class="detail-section-title">Price Validation</h3>
    <?php if ($latestPriceValidation === null): ?>
        <p class="text-muted small mb-0">Belum ada pengajuan validasi harga untuk lead ini.</p>
    <?php else: ?>
        <?php
            $pvStatusLabels = ['pending' => 'Menunggu Validasi', 'approved' => 'Approved', 'revision_required' => 'Revision Required'];
            $pvStatusColors = ['pending' => 'amber', 'approved' => 'emerald', 'revision_required' => 'danger'];
        ?>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Status</span><span class="info-item-value"><span class="color-swatch color-swatch-<?= e($pvStatusColors[$latestPriceValidation['status']] ?? 'muted') ?>"><?= e($pvStatusLabels[$latestPriceValidation['status']] ?? $latestPriceValidation['status']) ?></span></span></div>
            <div class="info-item"><span class="info-item-label">Total Harga</span><span class="info-item-value">Rp <?= e(number_format((float) $latestPriceValidation['total_price'], 0, ',', '.')) ?></span></div>
            <div class="info-item"><span class="info-item-label">Diajukan oleh</span><span class="info-item-value"><?= e($latestPriceValidation['submitted_by_name'] ?? '-') ?> &middot; <?= e(format_datetime($latestPriceValidation['submitted_at'])) ?></span></div>
            <?php if ($latestPriceValidation['validated_by_name']): ?>
            <div class="info-item"><span class="info-item-label">Divalidasi oleh</span><span class="info-item-value"><?= e($latestPriceValidation['validated_by_name']) ?> &middot; <?= e(format_datetime($latestPriceValidation['validated_at'])) ?></span></div>
            <?php endif; ?>
            <?php if ($latestPriceValidation['notes']): ?>
            <div class="info-item info-item-full"><span class="info-item-label">Catatan</span><span class="info-item-value"><?= nl2br(e($latestPriceValidation['notes'])) ?></span></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div class="detail-section" id="proposals">
    <h3 class="detail-section-title">Proposal</h3>
    <?php if (empty($proposals)): ?>
        <p class="text-muted small <?= $canCreateProposal ? 'mb-3' : 'mb-0' ?>">Belum ada proposal untuk lead ini.</p>
    <?php else: ?>
    <div class="table-responsive mb-3">
        <table class="table table-modern mb-0">
            <thead><tr><th>Kode</th><th>Project</th><th>Status</th><th>Total</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($proposals as $p): ?>
                <?php $pStatus = $proposalStatusMap[$p['status']] ?? ['name' => $p['status'], 'color' => 'muted']; ?>
                <tr>
                    <td class="mono"><?= e($p['proposal_code']) ?></td>
                    <td><?= e($p['project_name'] ?: '-') ?></td>
                    <td><span class="color-swatch color-swatch-<?= e($pStatus['color']) ?>"><?= e($pStatus['name']) ?></span></td>
                    <td class="mono">Rp <?= e(number_format((float) $p['total'], 0, ',', '.')) ?></td>
                    <td class="text-end"><a href="<?= url('/proposals/' . $p['id']) ?>" class="btn btn-sm btn-light">Buka</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($canCreateProposal): ?>
    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/proposals') ?>" data-confirm="Buat proposal baru (kosong) untuk lead ini?">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-plus me-1"></i>Buat Proposal Baru</button>
    </form>
    <?php endif; ?>
</div>

<div class="detail-section" id="deal">
    <h3 class="detail-section-title">Deal &amp; Penutupan</h3>
    <?php if ($lead['status'] === 'won'): ?>
        <div class="badge-pill badge-pill-emerald mb-3"><i class="bi bi-trophy-fill me-1"></i>Deal Won</div>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Nilai Deal</span><span class="info-item-value fw-semibold">Rp <?= e(number_format((float) ($lead['deal_value'] ?? 0), 0, ',', '.')) ?></span></div>
            <div class="info-item"><span class="info-item-label">Tanggal Closing</span><span class="info-item-value"><?= $lead['closing_date'] ? e(format_datetime($lead['closing_date'], 'd M Y')) : '-' ?></span></div>
            <?php if (!empty($lead['won_proposal_id'])): ?>
            <div class="info-item">
                <span class="info-item-label">Proposal Final</span>
                <span class="info-item-value">
                    <?php $wp = array_values(array_filter($proposals, fn ($p) => (int) $p['id'] === (int) $lead['won_proposal_id'])); ?>
                    <?= !empty($wp) ? '<a href="' . url('/proposals/' . $wp[0]['id']) . '" class="mono">' . e($wp[0]['proposal_code']) . '</a>' : '-' ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="info-item"><span class="info-item-label">Konfirmasi Customer</span><span class="info-item-value"><?= (int) $lead['customer_confirmed'] === 1 ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Terkonfirmasi</span>' : '<span class="text-muted">Belum dikonfirmasi</span>' ?></span></div>
            <?php if ($lead['confirmation_notes']): ?><div class="info-item info-item-full"><span class="info-item-label">Catatan Konfirmasi</span><span class="info-item-value"><?= nl2br(e($lead['confirmation_notes'])) ?></span></div><?php endif; ?>
        </div>
        <?php if ($canManageDeal): ?>
        <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/reopen-deal') ?>" class="mt-3" data-confirm="Buka kembali status deal lead ini?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-light btn-sm">Buka Kembali Status Deal</button>
        </form>
        <?php endif; ?>

    <?php elseif ($lead['status'] === 'lost'): ?>
        <div class="badge-pill badge-pill-danger mb-3"><i class="bi bi-x-circle-fill me-1"></i>Deal Lost</div>
        <div class="info-grid">
            <div class="info-item"><span class="info-item-label">Tanggal Closing</span><span class="info-item-value"><?= $lead['closing_date'] ? e(format_datetime($lead['closing_date'], 'd M Y')) : '-' ?></span></div>
            <div class="info-item info-item-full"><span class="info-item-label">Alasan Kalah</span><span class="info-item-value"><?= $lead['lost_reason'] ? nl2br(e($lead['lost_reason'])) : '-' ?></span></div>
        </div>
        <?php if ($canManageDeal): ?>
        <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/reopen-deal') ?>" class="mt-3" data-confirm="Buka kembali status deal lead ini?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-light btn-sm">Buka Kembali Status Deal</button>
        </form>
        <?php endif; ?>

    <?php elseif ($canManageDeal): ?>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <details>
                    <summary class="btn btn-success w-100"><i class="bi bi-trophy me-1"></i>Tandai Won</summary>
                    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/mark-won') ?>" class="mt-3 row g-2">
                        <?= csrf_field() ?>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Nilai Deal (Rp)</label>
                            <input type="number" step="0.01" min="0" name="deal_value" class="form-control" value="<?= e($lead['estimated_value'] ?? '') ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Tanggal Closing</label>
                            <input type="date" name="closing_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <?php if (!empty($wonLostProposals)): ?>
                        <div class="col-12">
                            <label class="form-label">Proposal Final</label>
                            <select name="won_proposal_id" class="form-select">
                                <option value="">- Tidak terkait proposal -</option>
                                <?php foreach ($wonLostProposals as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['proposal_code']) ?> &middot; Rp <?= e(number_format((float) $p['total'], 0, ',', '.')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-12 form-check">
                            <input type="checkbox" name="customer_confirmed" value="1" class="form-check-input" id="customerConfirmed">
                            <label class="form-check-label" for="customerConfirmed">Customer sudah konfirmasi tertulis/lisan</label>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan Konfirmasi</label>
                            <textarea name="confirmation_notes" class="form-control" rows="2" placeholder="mis. PO diterima via email tgl..."></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-sm w-100">Simpan sebagai Won</button>
                        </div>
                    </form>
                </details>
            </div>
            <div class="col-12 col-md-6">
                <details>
                    <summary class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>Tandai Lost</summary>
                    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/mark-lost') ?>" class="mt-3 row g-2">
                        <?= csrf_field() ?>
                        <div class="col-12">
                            <label class="form-label">Tanggal Closing</label>
                            <input type="date" name="closing_date" class="form-control" value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alasan Kalah</label>
                            <textarea name="lost_reason" class="form-control" rows="2" placeholder="mis. Harga kalah kompetitif, budget dibatalkan..." required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-danger btn-sm w-100">Simpan sebagai Lost</button>
                        </div>
                    </form>
                </details>
            </div>
        </div>
    <?php else: ?>
        <p class="text-muted small mb-0">Deal belum ditutup.</p>
    <?php endif; ?>
</div>

<div class="detail-section" id="follow-ups">
    <h3 class="detail-section-title">Follow Up &amp; Reminder</h3>
    <?php if ($canLogFollowUp): ?>
    <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/follow-ups') ?>" class="row g-2 mb-3">
        <?= csrf_field() ?>
        <div class="col-6 col-md-2">
            <label class="form-label">Metode</label>
            <select name="method" class="form-select" required>
                <?php foreach ($followUpMethodLabels as $code => $label): ?>
                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Respon Customer</label>
            <select name="customer_response" class="form-select">
                <option value="">- Belum jelas -</option>
                <?php foreach ($followUpResponseLabels as $code => $label): ?>
                    <option value="<?= e($code) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Next Action</label>
            <input type="text" name="next_action" class="form-control" placeholder="mis. Kirim ulang penawaran">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Follow Up Berikutnya</label>
            <input type="date" name="next_followup_date" class="form-control">
        </div>
        <div class="col-12 col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-telephone me-1"></i>Catat Follow Up</button>
        </div>
        <div class="col-12">
            <label class="form-label">Hasil Komunikasi</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Ringkasan hasil kontak dengan customer..."></textarea>
        </div>
    </form>
    <?php endif; ?>

    <?php if (empty($followUps)): ?>
        <p class="text-muted small mb-0">Belum ada follow up yang tercatat untuk lead ini.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-modern mb-0">
            <thead><tr><th>Tanggal</th><th>Metode</th><th>Respon</th><th>Catatan</th><th>Next Action</th><th>Follow Up Berikutnya</th><th>Oleh</th></tr></thead>
            <tbody>
                <?php foreach ($followUps as $f): ?>
                <tr>
                    <td class="mono"><?= e(format_datetime($f['followup_date'])) ?></td>
                    <td><span class="badge-pill"><?= e($followUpMethodLabels[$f['method']] ?? $f['method']) ?></span></td>
                    <td><?= $f['customer_response'] ? '<span class="color-swatch color-swatch-' . e($responseColors[$f['customer_response']] ?? 'muted') . '">' . e($followUpResponseLabels[$f['customer_response']] ?? $f['customer_response']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                    <td class="text-muted"><?= $f['notes'] ? nl2br(e($f['notes'])) : '-' ?></td>
                    <td class="text-muted"><?= $f['next_action'] ? e($f['next_action']) : '-' ?></td>
                    <td class="mono"><?= $f['next_followup_date'] ? e(format_datetime($f['next_followup_date'], 'd M Y')) : '-' ?></td>
                    <td class="text-muted"><?= e($f['sales_name'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</div>

<script>
(function () {
    var root = document.querySelector('.lead-page-root');
    if (!root) return;
    var leadId = root.getAttribute('data-lead-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');

    var lastKnownUpdatedAt = root.getAttribute('data-lead-updated-at');

    document.querySelectorAll('.lead-live-field').forEach(function (field) {
        field.addEventListener('change', function () {
            var endpoint = field.getAttribute('data-endpoint');
            var payload = {};
            payload[field.getAttribute('data-field')] = field.value;

            Api.post(base + '/api/leads/' + leadId + '/' + endpoint, payload).then(function (data) {
                if (!data || data.success === false) {
                    Toast.show('Gagal menyimpan perubahan.', 'danger');
                    return;
                }
                Toast.show('Perubahan tersimpan.', 'success');
                if (data.updated_at) {
                    lastKnownUpdatedAt = data.updated_at;
                    var updatedAtEl = root.querySelector('[data-field="updated_at_display"]');
                    if (updatedAtEl) updatedAtEl.textContent = data.updated_at;
                }

                if (endpoint === 'status') {
                    var badge = root.querySelector('[data-badge="status"]');
                    badge.textContent = data.label;
                    badge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'priority') {
                    var pbadge = root.querySelector('[data-badge="priority"]');
                    pbadge.textContent = data.label;
                    pbadge.className = 'color-swatch color-swatch-' + data.color;
                    var panelBadge = root.querySelector('[data-badge="priority_display"]');
                    if (panelBadge) { panelBadge.textContent = data.label; panelBadge.className = 'color-swatch color-swatch-' + data.color; }
                } else if (endpoint === 'assign') {
                    root.querySelector('[data-badge="sales_name"]').textContent = data.sales_name;
                }
            }).catch(function () {
                Toast.show('Gagal menyimpan perubahan, silakan coba lagi.', 'danger');
            });
        });
    });

    // Realtime: notice (without reloading) if someone else changed this lead.
    var pollInterval = parseInt(document.querySelector('meta[name="poll-interval"]')?.getAttribute('content') || '30000', 10);

    function ping() {
        Api.get(base + '/api/leads/' + leadId + '/ping').then(function (data) {
            if (!data || data.updated_at === lastKnownUpdatedAt) return;
            lastKnownUpdatedAt = data.updated_at;
            Toast.show('Lead ini baru saja diperbarui oleh pengguna lain. Muat ulang untuk melihat perubahan terbaru.', 'info', 6000);
            var indicator = document.getElementById('leadLiveIndicator');
            if (indicator) indicator.innerHTML = '<i class="bi bi-arrow-clockwise"></i> <a href="javascript:location.reload()">Ada pembaruan — muat ulang</a>';
        }).catch(function () {});
    }
    setInterval(ping, Math.max(pollInterval, 10000));
})();
</script>
