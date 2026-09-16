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
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow">
        <?= e($lead['lead_code']) ?>
        <?php if ($lead['deleted_at']): ?><span class="badge-pill badge-pill-muted">Di Sampah</span><?php endif; ?>
        <?php $simpleLabels = ['proses' => 'Proses', 'deal' => 'Deal', 'cancel' => 'Cancel']; ?>
        <?php $simpleColors = ['proses' => 'amber', 'deal' => 'emerald', 'cancel' => 'danger']; ?>
        <span class="color-swatch color-swatch-<?= e($simpleColors[$simplifiedStatus]) ?>"><?= e($simpleLabels[$simplifiedStatus]) ?></span>
    </div>
        <h2><?= e($lead['customer_name']) ?></h2>
        <p class="text-muted"><?= e($lead['company_name'] ?: 'Tanpa perusahaan') ?></p>
    </div>
    <div class="row-actions">
        <?php if ($activeQueue): ?>
        <a href="<?= url('/queue/' . $activeQueue['id']) ?>" class="btn btn-light"><i class="bi bi-list-ol me-1"></i>Lihat Antrian #<?= (int) $activeQueue['queue_number'] ?></a>
        <?php elseif ($canEnqueue && !$lead['deleted_at']): ?>
        <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/enqueue') ?>" data-confirm="Masukkan lead ini ke antrian sales?">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-list-ol me-1"></i>Masukkan ke Antrian</button>
        </form>
        <?php endif; ?>
        <?php if ($latestEngineerAssignment): ?>
        <a href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>" class="btn btn-light"><i class="bi bi-tools me-1"></i>Lihat Assignment Engineer</a>
        <?php endif; ?>
        <?php if (!$activeEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && count($engineerFieldUsers)): ?>
        <a href="#request-engineer" class="btn btn-light"><i class="bi bi-tools me-1"></i>Minta Engineer</a>
        <?php endif; ?>
        <?php if ($latestSalesEngineerAssignment): ?>
        <a href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>" class="btn btn-light"><i class="bi bi-person-gear me-1"></i>Lihat Assignment Sales Engineer</a>
        <?php endif; ?>
        <?php if (!$activeSalesEngineerAssignment && $canRequestEngineer && !$lead['deleted_at'] && count($salesEngineerUsers)): ?>
        <a href="#request-sales-engineer" class="btn btn-light"><i class="bi bi-person-gear me-1"></i>Minta Sales Engineer</a>
        <?php endif; ?>
        <?php if ($latestProcurementRequest): ?>
        <a href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>" class="btn btn-light"><i class="bi bi-truck me-1"></i>Lihat Request Procurement</a>
        <?php endif; ?>
        <?php if (!$activeProcurementRequest && $canRequestProcurement && !$lead['deleted_at'] && count($procurementUsers)): ?>
        <a href="#request-procurement" class="btn btn-light"><i class="bi bi-truck me-1"></i>Minta Procurement</a>
        <?php endif; ?>
        <?php if (!empty($proposals)): ?>
        <a href="<?= url('/proposals/' . $proposals[0]['id']) ?>" class="btn btn-light"><i class="bi bi-file-earmark-text me-1"></i>Lihat Proposal</a>
        <?php endif; ?>
        <?php if ($canCreateProposal && !$lead['deleted_at']): ?>
        <a href="#proposals" class="btn btn-light"><i class="bi bi-file-earmark-plus me-1"></i>Buat Proposal</a>
        <?php endif; ?>
        <?php if ($canManage && !$lead['deleted_at']): ?>
        <a href="<?= url('/leads/' . $lead['id'] . '/edit') ?>" class="btn btn-light"><i class="bi bi-pencil me-1"></i>Ubah</a>
        <?php endif; ?>
        <a href="<?= url('/leads') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="lead-detail-grid" data-lead-id="<?= (int) $lead['id'] ?>" data-lead-updated-at="<?= e($lead['updated_at']) ?>">
    <!-- LEFT: Customer information -->
    <div class="card card-elevated lead-panel">
        <div class="card-header"><h3>Informasi Customer</h3></div>
        <div class="card-body">
            <dl class="lead-dl">
                <dt>Nama</dt><dd><?= e($lead['customer_name']) ?></dd>
                <dt>Perusahaan</dt><dd><?= e($lead['company_name'] ?: '-') ?></dd>
                <dt>Telepon</dt><dd><?= $lead['phone'] ? '<a href="tel:' . e($lead['phone']) . '">' . e($lead['phone']) . '</a>' : '-' ?></dd>
                <dt>Email</dt><dd><?= $lead['email'] ? '<a href="mailto:' . e($lead['email']) . '">' . e($lead['email']) . '</a>' : '-' ?></dd>
                <dt>Alamat</dt><dd><?= $lead['address'] ? nl2br(e($lead['address'])) : '-' ?></dd>
                <dt>Site Location</dt><dd><?= e($lead['site_location'] ?: '-') ?></dd>
            </dl>
        </div>
    </div>

    <!-- CENTER: Lead details -->
    <div class="card card-elevated lead-panel">
        <div class="card-header"><h3>Detail Lead</h3></div>
        <div class="card-body">
            <dl class="lead-dl">
                <dt>Sumber</dt><dd><?= e($lead['source_name'] ?? '-') ?></dd>
                <dt>Kategori</dt><dd><?= e($lead['category_name'] ?? '-') ?></dd>
                <dt>Type</dt><dd><?= e($lead['type_name'] ?? '-') ?></dd>
                <dt>System</dt><dd><?= e($lead['system_name'] ?? '-') ?></dd>
                <dt>Funding</dt><dd><?= e($lead['funding_name'] ?? '-') ?></dd>
                <dt>Size (KWp)</dt><dd><?= $lead['size_kwp'] !== null ? e(rtrim(rtrim(number_format((float) $lead['size_kwp'], 2, '.', ''), '0'), '.')) : '-' ?></dd>
                <dt>Jenis Kebutuhan</dt><dd><?= e($lead['need_type_name'] ?? '-') ?></dd>
                <dt>Estimasi Nilai</dt><dd><?= $lead['estimated_value'] !== null ? 'Rp ' . number_format((float) $lead['estimated_value'], 0, ',', '.') : '-' ?></dd>
                <dt>Deskripsi Kebutuhan</dt><dd><?= $lead['needs_description'] ? nl2br(e($lead['needs_description'])) : '-' ?></dd>
                <dt>Catatan 1</dt><dd><?= $lead['notes'] ? nl2br(e($lead['notes'])) : '-' ?></dd>
                <dt>Catatan 2</dt><dd><?= $lead['note2'] ? nl2br(e($lead['note2'])) : '-' ?></dd>
                <dt>Tanggal Note Update</dt><dd><?= $lead['note_updated_at'] ? e(format_datetime($lead['note_updated_at'])) : '-' ?></dd>
            </dl>
            <hr>
            <dl class="lead-dl">
                <dt>Dibuat oleh</dt><dd><?= e($lead['created_by_name'] ?? 'Sistem') ?> &middot; <?= e(format_datetime($lead['created_at'])) ?></dd>
                <dt>Terakhir diperbarui</dt><dd><span data-field="updated_at"><?= e(format_datetime($lead['updated_at'])) ?></span></dd>
            </dl>
        </div>
    </div>

    <!-- RIGHT: Status / Assignment / Priority -->
    <div class="card card-elevated lead-panel">
        <div class="card-header"><h3>Status &amp; Penugasan</h3></div>
        <div class="card-body lead-side-panel">

            <?php
                // Only these move manually via this quick-select (see LeadApiController::STATUSES).
                // The rest (procurement/pricing_ready/proposal/won/lost) are driven by their own
                // dedicated workflow actions elsewhere on this page.
                $manualStatuses = ['new', 'in_queue', 'follow_up', 'engineering'];
                $statusIsManual = in_array($lead['status'], $manualStatuses, true);
            ?>
            <div class="lead-side-field">
                <label class="form-label">Status</label>
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
                <?php
                    $otherSales = array_filter($assignedSales, fn ($r) => (int) $r['id'] !== (int) $lead['sales_id']);
                ?>
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

<!-- Monitoring Saat Ini (Phase C) -->
<div class="card card-elevated mt-3">
    <div class="card-header"><h3>Monitoring Saat Ini</h3></div>
    <div class="card-body">
        <dl class="lead-dl">
            <dt>Current Position</dt>
            <dd><span class="color-swatch color-swatch-<?= e($currentPosition['color']) ?>"><?= e($currentPosition['label']) ?></span></dd>
            <dt>Current PIC</dt>
            <dd><?= $activeQueue ? e($activeQueue['current_pic_name'] ?? 'None') : 'Belum masuk antrian' ?></dd>
            <dt>Status Survey</dt>
            <dd><?= $activeQueue ? e($activeQueue['survey_status_name'] ?? 'Belum diisi') : 'Belum masuk antrian' ?></dd>
            <dt>Estimator</dt>
            <dd><?= $activeQueue ? e($activeQueue['estimator_name'] ?? 'None') : 'Belum masuk antrian' ?></dd>
            <dt>Surveyor</dt>
            <dd><?= $activeQueue ? e($activeQueue['surveyor_name'] ?? 'None') : 'Belum masuk antrian' ?></dd>
        </dl>
    </div>
</div>

<!-- Follow Up & Reminder (Phase 9) -->
<div class="card card-elevated mt-3" id="follow-ups">
    <div class="card-header"><h3>Follow Up &amp; Reminder</h3></div>
    <div class="card-body">
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

<!-- Engineer: survey teknis lapangan lanjutan / desain teknis (Revisi Sub-Fase 2) -->
<div class="card card-elevated mt-3" id="request-engineer">
    <div class="card-header"><h3>Engineer (Survey Teknis &amp; Desain)</h3></div>
    <div class="card-body">
        <?php if ($latestEngineerAssignment): ?>
            <?php $eaStatus = $engineerStatusMap[$latestEngineerAssignment['status']] ?? ['name' => $latestEngineerAssignment['status'], 'color' => 'muted']; ?>
            <dl class="lead-dl mb-3">
                <dt>Kode Assignment</dt><dd><a href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>" class="mono"><?= e($latestEngineerAssignment['assignment_code']) ?></a></dd>
                <dt>Engineer</dt><dd><?= e($latestEngineerAssignment['engineer_name'] ?? '-') ?></dd>
                <dt>Status</dt><dd><span class="color-swatch color-swatch-<?= e($eaStatus['color']) ?>"><?= e($eaStatus['name']) ?></span></dd>
                <dt>Deadline</dt><dd><?= $latestEngineerAssignment['deadline'] ? e(format_datetime($latestEngineerAssignment['deadline'], 'd M Y')) : '-' ?></dd>
            </dl>
            <?php if ($latestEngineerAssignment['result_notes']): ?>
                <div class="note-item mb-0"><div class="note-text"><?= nl2br(e($latestEngineerAssignment['result_notes'])) ?></div><div class="note-meta">Hasil dari engineer</div></div>
            <?php endif; ?>
            <a href="<?= url('/engineer/' . $latestEngineerAssignment['id']) ?>" class="btn btn-sm btn-light mt-3">Lihat Detail Assignment</a>
        <?php endif; ?>

        <?php if (!$activeEngineerAssignment && $canRequestEngineer && !$lead['deleted_at']): ?>
            <?php if (empty($engineerFieldUsers)): ?>
                <p class="text-muted small <?= $latestEngineerAssignment ? 'mt-3' : '' ?> mb-0">Belum ada akun Engineer yang aktif.</p>
            <?php else: ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-engineer') ?>" class="row g-2 <?= $latestEngineerAssignment ? 'mt-2' : '' ?>">
                <?php if ($latestEngineerAssignment): ?><hr class="mt-1"><?php endif; ?>
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_type" value="engineer">
                <div class="col-12 col-md-4">
                    <label class="form-label">Engineer</label>
                    <select name="engineer_id" class="form-select" required>
                        <option value="">Pilih engineer...</option>
                        <?php foreach ($engineerFieldUsers as $row): ?>
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
                    <label class="form-label">Catatan untuk Engineer</label>
                    <textarea name="notes_from_sales" class="form-control" rows="2" placeholder="Konteks/instruksi untuk engineer (opsional)"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim ke Engineer</button>
                </div>
            </form>
            <?php endif; ?>
        <?php elseif (!$latestEngineerAssignment): ?>
            <p class="text-muted small mb-0">Belum ada assignment engineer untuk lead ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Sales Engineer: analisa teknis & koordinasi (modul asli, sekarang assignment_type='sales_engineer') -->
<div class="card card-elevated mt-3" id="request-sales-engineer">
    <div class="card-header"><h3>Sales Engineer (Analisa Teknis)</h3></div>
    <div class="card-body">
        <?php if ($latestSalesEngineerAssignment): ?>
            <?php $seStatus = $engineerStatusMap[$latestSalesEngineerAssignment['status']] ?? ['name' => $latestSalesEngineerAssignment['status'], 'color' => 'muted']; ?>
            <dl class="lead-dl mb-3">
                <dt>Kode Assignment</dt><dd><a href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>" class="mono"><?= e($latestSalesEngineerAssignment['assignment_code']) ?></a></dd>
                <dt>Sales Engineer</dt><dd><?= e($latestSalesEngineerAssignment['engineer_name'] ?? '-') ?></dd>
                <dt>Status</dt><dd><span class="color-swatch color-swatch-<?= e($seStatus['color']) ?>"><?= e($seStatus['name']) ?></span></dd>
                <dt>Deadline</dt><dd><?= $latestSalesEngineerAssignment['deadline'] ? e(format_datetime($latestSalesEngineerAssignment['deadline'], 'd M Y')) : '-' ?></dd>
            </dl>
            <?php if ($latestSalesEngineerAssignment['result_notes']): ?>
                <div class="note-item mb-0"><div class="note-text"><?= nl2br(e($latestSalesEngineerAssignment['result_notes'])) ?></div><div class="note-meta">Hasil analisa dari sales engineer</div></div>
            <?php endif; ?>
            <a href="<?= url('/engineer/' . $latestSalesEngineerAssignment['id']) ?>" class="btn btn-sm btn-light mt-3">Lihat Detail Assignment</a>
        <?php endif; ?>

        <?php if (!$activeSalesEngineerAssignment && $canRequestEngineer && !$lead['deleted_at']): ?>
            <?php if (empty($salesEngineerUsers)): ?>
                <p class="text-muted small <?= $latestSalesEngineerAssignment ? 'mt-3' : '' ?> mb-0">Belum ada akun Sales Engineer yang aktif.</p>
            <?php else: ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-engineer') ?>" class="row g-2 <?= $latestSalesEngineerAssignment ? 'mt-2' : '' ?>">
                <?php if ($latestSalesEngineerAssignment): ?><hr class="mt-1"><?php endif; ?>
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_type" value="sales_engineer">
                <div class="col-12 col-md-4">
                    <label class="form-label">Sales Engineer</label>
                    <select name="engineer_id" class="form-select" required>
                        <option value="">Pilih sales engineer...</option>
                        <?php foreach ($salesEngineerUsers as $row): ?>
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
                    <label class="form-label">Catatan untuk Sales Engineer</label>
                    <textarea name="notes_from_sales" class="form-control" rows="2" placeholder="Konteks/instruksi untuk sales engineer (opsional)"></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim ke Sales Engineer</button>
                </div>
            </form>
            <?php endif; ?>
        <?php elseif (!$latestSalesEngineerAssignment): ?>
            <p class="text-muted small mb-0">Belum ada assignment sales engineer untuk lead ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Procurement: request / active request summary -->
<div class="card card-elevated mt-3" id="request-procurement">
    <div class="card-header"><h3>Procurement</h3></div>
    <div class="card-body">
        <?php if ($latestProcurementRequest): ?>
            <?php $prStatus = $procurementStatusMap[$latestProcurementRequest['status']] ?? ['name' => $latestProcurementRequest['status'], 'color' => 'muted']; ?>
            <dl class="lead-dl mb-3">
                <dt>Kode Request</dt><dd><a href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>" class="mono"><?= e($latestProcurementRequest['request_code']) ?></a></dd>
                <dt>Staff Procurement</dt><dd><?= e($latestProcurementRequest['assigned_to_name'] ?? '-') ?></dd>
                <dt>Status</dt><dd><span class="color-swatch color-swatch-<?= e($prStatus['color']) ?>"><?= e($prStatus['name']) ?></span></dd>
                <dt>Deadline</dt><dd><?= $latestProcurementRequest['deadline'] ? e(format_datetime($latestProcurementRequest['deadline'], 'd M Y')) : '-' ?></dd>
            </dl>
            <a href="<?= url('/procurement/' . $latestProcurementRequest['id']) ?>" class="btn btn-sm btn-light mt-3">Lihat Detail Request</a>
        <?php endif; ?>

        <?php if (!$activeProcurementRequest && $canRequestProcurement && !$lead['deleted_at']): ?>
            <?php if (empty($procurementUsers)): ?>
                <p class="text-muted small <?= $latestProcurementRequest ? 'mt-3' : '' ?> mb-0">Belum ada akun Procurement yang aktif.</p>
            <?php else: ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/request-procurement') ?>" class="row g-2 <?= $latestProcurementRequest ? 'mt-2' : '' ?>">
                <?php if ($latestProcurementRequest): ?><hr class="mt-1"><?php endif; ?>
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
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Kirim ke Procurement</button>
                </div>
            </form>
            <?php endif; ?>
        <?php elseif (!$latestProcurementRequest): ?>
            <p class="text-muted small mb-0">Belum ada request procurement untuk lead ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Proposal: list + buat baru (lead boleh punya beberapa proposal seiring waktu) -->
<div class="card card-elevated mt-3" id="proposals">
    <div class="card-header"><h3>Proposal</h3></div>
    <div class="card-body">
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
</div>

<!-- Deal Management (Phase 10): close the lead out as Won/Lost, or show the recorded outcome -->
<div class="card card-elevated mt-3" id="deal">
    <div class="card-header"><h3>Deal &amp; Penutupan</h3></div>
    <div class="card-body">
        <?php if ($lead['status'] === 'won'): ?>
            <div class="badge-pill badge-pill-emerald mb-3"><i class="bi bi-trophy-fill me-1"></i>Deal Won</div>
            <dl class="lead-dl">
                <dt>Nilai Deal</dt><dd class="fw-semibold">Rp <?= e(number_format((float) ($lead['deal_value'] ?? 0), 0, ',', '.')) ?></dd>
                <dt>Tanggal Closing</dt><dd><?= $lead['closing_date'] ? e(format_datetime($lead['closing_date'], 'd M Y')) : '-' ?></dd>
                <?php if (!empty($lead['won_proposal_id'])): ?>
                <dt>Proposal Final</dt><dd>
                    <?php $wp = array_values(array_filter($proposals, fn ($p) => (int) $p['id'] === (int) $lead['won_proposal_id'])); ?>
                    <?= !empty($wp) ? '<a href="' . url('/proposals/' . $wp[0]['id']) . '" class="mono">' . e($wp[0]['proposal_code']) . '</a>' : '-' ?>
                </dd>
                <?php endif; ?>
                <dt>Konfirmasi Customer</dt><dd><?= (int) $lead['customer_confirmed'] === 1 ? '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Terkonfirmasi</span>' : '<span class="text-muted">Belum dikonfirmasi</span>' ?></dd>
                <?php if ($lead['confirmation_notes']): ?><dt>Catatan Konfirmasi</dt><dd><?= nl2br(e($lead['confirmation_notes'])) ?></dd><?php endif; ?>
            </dl>
            <?php if ($canManageDeal): ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/reopen-deal') ?>" class="mt-2" data-confirm="Buka kembali status deal lead ini?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-light btn-sm">Buka Kembali Status Deal</button>
            </form>
            <?php endif; ?>

        <?php elseif ($lead['status'] === 'lost'): ?>
            <div class="badge-pill badge-pill-danger mb-3"><i class="bi bi-x-circle-fill me-1"></i>Deal Lost</div>
            <dl class="lead-dl">
                <dt>Tanggal Closing</dt><dd><?= $lead['closing_date'] ? e(format_datetime($lead['closing_date'], 'd M Y')) : '-' ?></dd>
                <dt>Alasan Kalah</dt><dd><?= $lead['lost_reason'] ? nl2br(e($lead['lost_reason'])) : '-' ?></dd>
            </dl>
            <?php if ($canManageDeal): ?>
            <form method="POST" action="<?= url('/leads/' . $lead['id'] . '/reopen-deal') ?>" class="mt-2" data-confirm="Buka kembali status deal lead ini?">
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
</div>

<!-- BELOW: Timeline -->
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
                            <?= $event['from'] ? 'Status diubah: ' . e($statusMap[$event['from']]['name'] ?? $event['from']) . ' &rarr; ' . e($statusMap[$event['to']]['name'] ?? $event['to']) : 'Lead dibuat dengan status ' . e($statusMap[$event['to']]['name'] ?? $event['to']) ?>
                        </div>
                        <?php if ($event['notes']): ?><div class="timeline-notes"><?= e($event['notes']) ?></div><?php endif; ?>
                    <?php else: ?>
                        <div class="timeline-title"><?= e($actionLabels[$event['action']] ?? $event['action']) ?></div>
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
    var grid = document.querySelector('.lead-detail-grid');
    if (!grid) return;
    var leadId = grid.getAttribute('data-lead-id');
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');

    var lastKnownUpdatedAt = grid.getAttribute('data-lead-updated-at');

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
                    var updatedAtEl = grid.querySelector('[data-field="updated_at"]');
                    if (updatedAtEl) updatedAtEl.textContent = data.updated_at;
                }

                if (endpoint === 'status') {
                    var badge = grid.querySelector('[data-badge="status"]');
                    badge.textContent = data.label;
                    badge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'priority') {
                    var pbadge = grid.querySelector('[data-badge="priority"]');
                    pbadge.textContent = data.label;
                    pbadge.className = 'color-swatch color-swatch-' + data.color;
                } else if (endpoint === 'assign') {
                    grid.querySelector('[data-badge="sales_name"]').textContent = data.sales_name;
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
