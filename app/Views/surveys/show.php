<?php
use App\Models\Survey;

$st = Survey::STATUS_META[$survey['status']] ?? ['name' => $survey['status'], 'color' => 'muted'];
$rp = $report ? (Survey::REPORT_META[$report['status']] ?? ['name' => $report['status'], 'color' => 'muted']) : null;
$open = in_array($survey['status'], ['requested', 'scheduled'], true);
$late = $open && $survey['scheduled_date'] && $survey['scheduled_date'] < $today;
?>
<div class="page-header page-header-row">
    <div>
        <h2><?= e($survey['survey_code']) ?> <span class="color-swatch color-swatch-<?= e($st['color']) ?> fs-6 align-middle"><?= e($st['name']) ?></span></h2>
        <p class="text-muted mb-0">
            <a href="<?= url('/leads/' . $survey['lead_id']) ?>" class="mono"><?= e($survey['lead_code']) ?></a> · <?= e($survey['customer_name']) ?>
            <?= $survey['company_name'] ? ' · ' . e($survey['company_name']) : '' ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/surveys') ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="detail-section">
            <h3 class="detail-section-title">Informasi Survey</h3>
            <div class="info-grid">
                <div class="info-item"><span class="info-item-label">Customer</span><span class="info-item-value"><?= e($survey['customer_name']) ?></span></div>
                <div class="info-item"><span class="info-item-label">Telepon</span><span class="info-item-value"><?= e($survey['lead_phone'] ?: '-') ?></span></div>
                <div class="info-item"><span class="info-item-label">Lokasi</span><span class="info-item-value"><?= e($survey['site_location'] ?: '-') ?></span></div>
                <div class="info-item"><span class="info-item-label">Alamat</span><span class="info-item-value"><?= e($survey['lead_address'] ?: '-') ?>
                    <?php if (!empty($survey['maps_url'])): ?> <a href="<?= e($survey['maps_url']) ?>" target="_blank" rel="noopener" class="small">Buka peta</a><?php endif; ?></span></div>
                <div class="info-item"><span class="info-item-label">Jadwal Survey</span><span class="info-item-value"><?= e(format_date($survey['scheduled_date'])) ?><?= $late ? ' <span class="badge text-bg-danger">Terlambat</span>' : '' ?></span></div>
                <div class="info-item"><span class="info-item-label">Surveyor</span><span class="info-item-value"><?= e($survey['surveyor_name'] ?? '-') ?></span></div>
                <div class="info-item"><span class="info-item-label">Sales</span><span class="info-item-value"><?= e($survey['sales_name'] ?? '-') ?></span></div>
                <div class="info-item"><span class="info-item-label">Diajukan oleh</span><span class="info-item-value"><?= e($survey['requested_by_name'] ?? '-') ?> · <?= e(format_datetime($survey['created_at'])) ?></span></div>
                <?php if ($survey['started_at']): ?><div class="info-item"><span class="info-item-label">Mulai</span><span class="info-item-value"><?= e(format_datetime($survey['started_at'])) ?></span></div><?php endif; ?>
                <?php if ($survey['completed_at']): ?><div class="info-item"><span class="info-item-label">Selesai</span><span class="info-item-value"><?= e(format_datetime($survey['completed_at'])) ?></span></div><?php endif; ?>
            </div>
            <?php if (!empty($survey['request_notes'])): ?>
                <p class="mt-3 mb-1 text-muted small text-uppercase">Catatan permintaan</p>
                <p class="mb-0"><?= nl2br(e($survey['request_notes'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($participants)): ?>
                <p class="mt-3 mb-1 text-muted small text-uppercase">Pihak yang dilibatkan</p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($participants as $p): ?>
                        <span class="color-swatch color-swatch-primary"><?= e($p['user_name']) ?> · <?= e(Survey::PARTICIPANT_ROLES[$p['role']] ?? $p['role']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($report || $survey['status'] === 'completed'): ?>
        <div class="detail-section" id="report-section">
            <h3 class="detail-section-title">Survey Report
                <?php if ($rp): ?><span class="color-swatch color-swatch-<?= e($rp['color']) ?> ms-2 fs-6"><?= e($rp['name']) ?></span><?php endif; ?>
            </h3>

            <?php if ($report && $report['status'] === 'revision' && $report['review_notes']): ?>
                <div class="alert alert-warning py-2 px-3"><strong>Perlu revisi.</strong> <?= nl2br(e($report['review_notes'])) ?></div>
            <?php endif; ?>

            <?php if ($report && in_array($report['status'], ['draft', 'revision'], true) && $canWork): ?>
                <p class="text-muted small">Isi form Survey Report (informasi lokasi, bangunan, kelistrikan, proteksi, kendala, kesimpulan) dan unggah foto dokumentasi. Foto terunggah: <strong><?= (int) $photoCount ?></strong>.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= url('/surveys/' . $survey['id'] . '/report/edit') ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Isi Form Survey Report</a>
                    <a href="<?= url('/surveys/' . $survey['id'] . '/report/pdf') ?>" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Pratinjau PDF</a>
                </div>
                <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/report/submit') ?>" class="mt-2" data-confirm="Ajukan Survey Report untuk diperiksa?">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i>Ajukan untuk Diperiksa</button>
                </form>
            <?php elseif ($report): ?>
                <p class="mb-1 text-muted small text-uppercase">Ringkasan temuan &amp; kesimpulan</p>
                <p><?= $report['summary'] ? nl2br(e($report['summary'])) : '<span class="text-muted">Belum diisi.</span>' ?></p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <a href="<?= url('/surveys/' . $survey['id'] . '/report/pdf') ?>" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Lihat PDF</a>
                    <a href="<?= url('/surveys/' . $survey['id'] . '/report/pdf?download=1') ?>" class="btn btn-light btn-sm"><i class="bi bi-download me-1"></i>Unduh PDF</a>
                </div>
                <?php if ($report['reviewed_by_name']): ?>
                    <p class="text-muted small mb-0">Diperiksa oleh <?= e($report['reviewed_by_name']) ?> · <?= e(format_datetime($report['reviewed_at'])) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted mb-0">Survey Report akan dibuat setelah Survey lapangan selesai.</p>
            <?php endif; ?>

            <?php if (!empty($versions)): ?>
                <p class="mt-3 mb-1 text-muted small text-uppercase">Riwayat versi diajukan</p>
                <ul class="list-unstyled small mb-0">
                    <?php foreach ($versions as $v): ?>
                        <li>Rev<?= str_pad((string) (int) $v['revision_no'], 2, '0', STR_PAD_LEFT) ?> · <?= e(format_datetime($v['created_at'])) ?> · <?= e($v['created_by_name'] ?? 'Sistem') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($report && $report['status'] === 'pending_review' && $canReview): ?>
                <hr class="detail-divider">
                <h4 class="small text-uppercase text-muted mb-2">Pemeriksaan Survey Report</h4>
                <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/report/review') ?>" class="mb-2" data-confirm="Setujui Survey Report ini? Lead akan dapat melanjutkan ke Prelim.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="decision" value="approve">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Setujui</button>
                </form>
                <details>
                    <summary class="btn btn-outline-danger btn-sm">Minta Revisi</summary>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/report/review') ?>" class="mt-2" data-confirm="Minta revisi Survey Report ke Surveyor?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="decision" value="revision">
                        <textarea class="form-control mb-2" name="notes" rows="3" placeholder="Catatan revisi (wajib)" required></textarea>
                        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Kirim Permintaan Revisi</button>
                    </form>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="detail-section">
            <h3 class="detail-section-title">Riwayat Aktivitas</h3>
            <?php if (empty($history)): ?>
                <p class="text-muted mb-0">Belum ada riwayat.</p>
            <?php else: ?>
            <ul class="timeline-list list-unstyled mb-0">
                <?php foreach ($history as $h): ?>
                    <?php
                    $meta = $h['entity'] === 'report' ? Survey::REPORT_META : Survey::STATUS_META;
                    $fromLabel = $h['from_status'] ? ($meta[$h['from_status']]['name'] ?? $h['from_status']) : null;
                    $toLabel = $meta[$h['to_status']]['name'] ?? $h['to_status'];
                    ?>
                    <li class="mb-3">
                        <strong><?= $h['entity'] === 'report' ? 'Laporan' : 'Survey' ?>:</strong>
                        <?= $fromLabel && $fromLabel !== $toLabel ? e($fromLabel) . ' → ' : '' ?><?= e($toLabel) ?>
                        <?php if ($h['notes']): ?><div class="text-muted small"><?= nl2br(e($h['notes'])) ?></div><?php endif; ?>
                        <div class="text-muted small"><?= e($h['changed_by_name'] ?? 'Sistem') ?> · <?= e(format_datetime($h['created_at'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="detail-section">
            <h3 class="detail-section-title">Tindakan</h3>

            <?php if ($canWork): ?>
                <?php if ($survey['status'] === 'requested'): ?>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/accept') ?>" class="mb-2" data-confirm="Terima penugasan Survey ini?">
                        <?= csrf_field() ?><button class="btn btn-primary w-100" type="submit"><i class="bi bi-check2 me-1"></i>Terima Penugasan</button>
                    </form>
                <?php elseif ($survey['status'] === 'scheduled'): ?>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/start') ?>" class="mb-2" data-confirm="Mulai Survey lapangan sekarang?">
                        <?= csrf_field() ?><button class="btn btn-primary w-100" type="submit"><i class="bi bi-play-circle me-1"></i>Mulai Survey</button>
                    </form>
                <?php elseif ($survey['status'] === 'in_progress'): ?>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/complete') ?>" class="mb-2" data-confirm="Tandai Survey lapangan selesai? Survey Report akan dibuat sebagai draft.">
                        <?= csrf_field() ?><button class="btn btn-success w-100" type="submit"><i class="bi bi-flag me-1"></i>Selesaikan Survey</button>
                    </form>
                <?php endif; ?>
                <?php if ($open): ?>
                <details class="mb-2">
                    <summary class="btn btn-outline-danger btn-sm w-100">Tolak penugasan</summary>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/reject') ?>" class="mt-2" data-confirm="Tolak penugasan Survey ini?">
                        <?= csrf_field() ?>
                        <textarea class="form-control mb-2" name="reason" rows="2" placeholder="Alasan penolakan (wajib)" required></textarea>
                        <button class="btn btn-danger btn-sm" type="submit">Kirim Penolakan</button>
                    </form>
                </details>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($open && ($canWork || $canManage)): ?>
                <details class="mb-2">
                    <summary class="btn btn-light btn-sm w-100">Jadwalkan ulang</summary>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/reschedule') ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="date" class="form-control mb-2" name="scheduled_date" min="<?= e($today) ?>" value="<?= e($survey['scheduled_date']) ?>" required>
                        <textarea class="form-control mb-2" name="reason" rows="2" placeholder="Alasan jadwal ulang (wajib)" required></textarea>
                        <button class="btn btn-primary btn-sm" type="submit">Simpan Jadwal</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if ($open && $canManage): ?>
                <details class="mb-2">
                    <summary class="btn btn-outline-secondary btn-sm w-100">Batalkan Survey</summary>
                    <form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/cancel') ?>" class="mt-2" data-confirm="Batalkan Survey ini?">
                        <?= csrf_field() ?>
                        <textarea class="form-control mb-2" name="reason" rows="2" placeholder="Alasan pembatalan (wajib)" required></textarea>
                        <button class="btn btn-secondary btn-sm" type="submit">Batalkan Survey</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (!$canWork && !$canManage && !$canReview): ?>
                <p class="text-muted small mb-0">Tidak ada tindakan yang tersedia untuk Anda pada status ini.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
