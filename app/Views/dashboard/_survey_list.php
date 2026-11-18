<?php
/**
 * Daftar Survey ringkas untuk dashboard.
 *
 * @var string $surveyTitle   judul kartu
 * @var string $surveyEmpty   teks bila kosong
 * @var array  $surveyRows    baris dari Survey::search()/openForSupport()
 * @var string $surveyAllUrl  tautan "Lihat semua"
 */

use App\Models\Survey;
?>
<div class="card card-elevated mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><?= e($surveyTitle) ?></h3>
        <a href="<?= url($surveyAllUrl) ?>" class="small">Lihat semua</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($surveyRows)): ?>
            <div class="empty-state"><i class="bi bi-geo-alt"></i><p><?= e($surveyEmpty) ?></p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-modern mb-0">
                <thead><tr><th>Survey</th><th>Lead</th><th>Surveyor</th><th>Jadwal</th><th>Status</th><th>Laporan</th></tr></thead>
                <tbody>
                <?php foreach ($surveyRows as $sv): ?>
                    <?php
                    $svMeta = Survey::STATUS_META[$sv['status']] ?? ['name' => $sv['status'], 'color' => 'muted'];
                    $rpMeta = !empty($sv['report_status']) ? (Survey::REPORT_META[$sv['report_status']] ?? null) : null;
                    ?>
                    <tr>
                        <td class="mono"><a href="<?= url('/surveys/' . $sv['id']) ?>"><?= e($sv['survey_code']) ?></a></td>
                        <td>
                            <?= lead_link($sv['lead_id'], e($sv['lead_code']), 'mono d-block') ?>
                            <div class="user-cell-name"><?= e($sv['customer_name']) ?></div>
                        </td>
                        <td><?= e($sv['surveyor_name'] ?? '-') ?></td>
                        <td class="text-muted"><?= e(format_date($sv['scheduled_date'])) ?></td>
                        <td><span class="color-swatch color-swatch-<?= e($svMeta['color']) ?>"><?= e($svMeta['name']) ?></span></td>
                        <td><?= $rpMeta ? '<span class="color-swatch color-swatch-' . e($rpMeta['color']) . '">' . e($rpMeta['name']) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
