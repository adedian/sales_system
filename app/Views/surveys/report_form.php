<?php
use App\Models\Survey;
use App\Services\SurveyReportSchema as Schema;

$g = fn (string $s, string $k) => $data[$s][$k] ?? '';

/** Pilihan (radio/checkbox) dalam grid; opsi "other" disertai kolom teks. */
$renderChoices = function (array $row, string $section, string $name, mixed $current, bool $multi, string $key) use ($data) {
    $other = $row['other'] ?? [];
    $selected = $multi ? (array) $current : [(string) $current];
    echo '<div class="choice-grid">';
    foreach ($row['options'] as $value => $label) {
        $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $section . '_' . $key . '_' . $value);
        $checked = in_array($value, $selected, true) ? 'checked' : '';
        $inputName = $multi ? "d[{$section}][{$key}][]" : "d[{$section}][{$key}]";
        echo '<label class="choice-item" for="' . e($id) . '">';
        echo '<input type="' . ($multi ? 'checkbox' : 'radio') . '" class="form-check-input" id="' . e($id) . '" name="' . e($inputName) . '" value="' . e($value) . '" ' . $checked . '> ';
        echo '<span>' . e($label) . '</span>';
        if (in_array($value, $other, true)) {
            $txt = (string) ($data[$section][$key . '_other'] ?? '');
            echo ' <input type="text" class="form-control form-control-sm d-inline-block choice-other" name="d[' . e($section) . '][' . e($key) . '_other]" value="' . e($txt) . '" placeholder="isi di sini" maxlength="150">';
        }
        echo '</label>';
    }
    echo '</div>';
};

/** Satu baris isian menurut tipenya. */
$renderRow = function (array $row, string $section) use ($data, $g, $renderChoices) {
    $key = $row['key'];
    $name = "d[{$section}][{$key}]";
    $id = 'f_' . $section . '_' . $key;
    echo '<div class="report-field">';
    echo '<label class="form-label" for="' . e($id) . '">' . e($row['label_id']) . ' <span class="text-muted small">(' . e($row['label']) . ')</span>' . (!empty($row['required']) ? ' <span class="text-danger">*</span>' : '') . '</label>';

    switch ($row['type']) {
        case 'text':
            echo '<div class="input-group"><input type="text" class="form-control" id="' . e($id) . '" name="' . e($name) . '" value="' . e($g($section, $key)) . '" maxlength="200">';
            if (!empty($row['suffix'])) {
                echo '<span class="input-group-text">' . e($row['suffix']) . '</span>';
            }
            echo '</div>';
            break;
        case 'textarea':
            echo '<textarea class="form-control" id="' . e($id) . '" name="' . e($name) . '" rows="2" maxlength="2000">' . e($g($section, $key)) . '</textarea>';
            break;
        case 'date':
            echo '<input type="date" class="form-control" id="' . e($id) . '" name="' . e($name) . '" value="' . e($g($section, $key)) . '">';
            break;
        case 'choice':
        case 'multi':
            $renderChoices($row, $section, $name, $g($section, $key), $row['type'] === 'multi', $key);
            if (!empty($row['note'])) {
                echo '<input type="text" class="form-control form-control-sm mt-2" name="d[' . e($section) . '][' . e($row['note']) . ']" value="' . e($g($section, $row['note'])) . '" placeholder="Catatan (opsional)" maxlength="500">';
            }
            break;
        case 'dim':
            $dims = (array) $g($section, $key);
            echo '<div class="dim-grid">';
            foreach ($row['letters'] as $l) {
                echo '<div class="input-group input-group-sm"><span class="input-group-text">' . e($l) . '</span><input type="text" class="form-control" name="d[' . e($section) . '][' . e($key) . '][' . e($l) . ']" value="' . e($dims[$l] ?? '') . '" maxlength="20"><span class="input-group-text">mm</span></div>';
            }
            echo '</div>';
            break;
        case 'parts':
            foreach ($row['parts'] as $part) {
                $pk = $key . '_' . $part['key'];
                echo '<div class="part-row"><span class="part-label">' . e($part['label_id']) . '</span><div class="part-body">';
                if ($part['type'] === 'choice') {
                    $renderChoices($part, $section, '', $g($section, $pk), false, $pk);
                } else {
                    echo '<input type="text" class="form-control form-control-sm" name="d[' . e($section) . '][' . e($pk) . ']" value="' . e($g($section, $pk)) . '" maxlength="200">';
                }
                echo '</div></div>';
            }
            break;
        case 'panels':
            $cells = (array) $g($section, $key);
            echo '<div class="table-responsive"><table class="table table-sm table-bordered mb-0 align-middle"><thead><tr><th>Item</th>';
            foreach ($row['columns'] as $c) {
                echo '<th>Panel ' . e($c) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($row['rows'] as $rk => $rl) {
                echo '<tr><td class="small">' . e($row['rows_id'][$rk]) . '</td>';
                foreach ($row['columns'] as $c) {
                    echo '<td><input type="text" class="form-control form-control-sm" name="d[' . e($section) . '][' . e($key) . '][' . e($c) . '][' . e($rk) . ']" value="' . e($cells[$c][$rk] ?? '') . '" maxlength="100"></td>';
                }
                echo '</tr>';
            }
            echo '<tr><td class="small">' . e($row['flag']['label_id']) . '</td>';
            foreach ($row['columns'] as $c) {
                echo '<td class="text-center"><input type="checkbox" class="form-check-input" name="d[' . e($section) . '][' . e($key) . '][' . e($c) . '][' . e($row['flag']['key']) . ']" value="1" ' . (!empty($cells[$c][$row['flag']['key']]) ? 'checked' : '') . '></td>';
            }
            echo '</tr></tbody></table></div>';
            break;
    }
    echo '</div>';
};

$byCat = ['site_overview' => 0, 'layout' => 0, 'notes' => 0];
foreach ($photos as $p) {
    $byCat[$p['category']]++;
}
$isRevision = $report['status'] === 'revision';
?>
<div class="page-header page-header-row">
    <div>
        <h2>Survey Report <?= e($survey['survey_code']) ?> <span class="color-swatch color-swatch-<?= $isRevision ? 'danger' : 'muted' ?> fs-6 align-middle"><?= $isRevision ? 'Perlu Revisi' : 'Draft' ?></span></h2>
        <p class="text-muted mb-0"><?= e($survey['lead_code']) ?> · <?= e($survey['customer_name']) ?> · Rev<?= str_pad((string) ((int) $report['revision_no']), 2, '0', STR_PAD_LEFT) ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= url('/surveys/' . $survey['id'] . '/report/pdf') ?>" target="_blank" class="btn btn-light"><i class="bi bi-file-earmark-pdf me-1"></i>Pratinjau PDF</a>
        <a href="<?= url('/surveys/' . $survey['id']) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>
</div>

<?php if ($isRevision && $report['review_notes']): ?>
<div class="alert alert-warning"><strong>Catatan revisi dari pemeriksa:</strong> <?= nl2br(e($report['review_notes'])) ?></div>
<?php endif; ?>

<div class="alert alert-info py-2 px-3 small">
    Data customer, lokasi, ID PLN, dan jadwal sudah terisi otomatis dari Lead. Periksa dan lengkapi hasil pengamatan lapangan.
    Klik <strong>Simpan</strong> secara berkala; foto, keterangan, dan isian disimpan bersamaan.
</div>

<form method="POST" action="<?= url('/surveys/' . $survey['id'] . '/report') ?>" enctype="multipart/form-data" id="reportForm">
    <?= csrf_field() ?>

    <?php foreach ($sections as $section): ?>
    <div class="detail-section">
        <h3 class="detail-section-title"><?= e($section['title_id']) ?> <span class="text-muted fs-6 fw-normal">(<?= e($section['title']) ?>)</span></h3>
        <?php foreach ($section['rows'] as $row) { $renderRow($row, $section['key']); } ?>
    </div>
    <?php endforeach; ?>

    <div class="detail-section" id="photos">
        <h3 class="detail-section-title">Foto Dokumentasi <span class="text-muted fs-6 fw-normal">(<?= count($photos) ?> / <?= (int) $maxPhotos ?>)</span></h3>
        <p class="text-muted small">
            Kategori foto mengikuti halaman template: <strong>Site Overview</strong> (<?= (int) $byCat['site_overview'] ?>),
            <strong>Layout</strong> (<?= (int) $byCat['layout'] ?>), dan <strong>Catatan</strong> (<?= (int) $byCat['notes'] ?>).
            Foto asli disimpan utuh sebagai lampiran Lead; PDF memakai versi yang diperkecil tanpa mengubah proporsi.
        </p>

        <?php if (!empty($photos)): ?>
        <div class="photo-grid mb-3">
            <?php foreach ($photos as $p): ?>
            <div class="photo-card">
                <a href="<?= url('/surveys/' . $survey['id'] . '/photos/' . $p['id'] . '?v=orig') ?>" target="_blank" title="Buka foto asli">
                    <img src="<?= url('/surveys/' . $survey['id'] . '/photos/' . $p['id']) ?>" alt="<?= e($p['caption'] ?: $p['original_name']) ?>" loading="lazy">
                </a>
                <input type="text" class="form-control form-control-sm mt-2" name="photo[<?= (int) $p['id'] ?>][caption]" value="<?= e($p['caption']) ?>" placeholder="Keterangan foto" maxlength="255">
                <select class="form-select form-select-sm mt-1" name="photo[<?= (int) $p['id'] ?>][category]">
                    <?php foreach (Schema::PHOTO_CATEGORIES as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $p['category'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex justify-content-between align-items-center mt-1">
                    <span class="text-muted small" title="<?= e($p['original_name']) ?>"><?= e(format_datetime($p['created_at'])) ?></span>
                    <label class="form-check small text-danger mb-0"><input type="checkbox" class="form-check-input" name="photo_delete[]" value="<?= (int) $p['id'] ?>"> Hapus</label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label" for="photosInput">Tambah foto (bisa banyak sekaligus)</label>
                <input type="file" class="form-control" id="photosInput" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label" for="newCat">Kategori foto yang diunggah</label>
                <select class="form-select" id="newCat" name="new_photo_category">
                    <?php foreach (Schema::PHOTO_CATEGORIES as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= $code === 'notes' ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2"><span class="small text-muted" id="uploadInfo">JPG/PNG/WEBP, maks. 10MB per foto.</span></div>
        </div>
        <p class="small text-muted mt-2 mb-0">Batas server: total <?= e($postLimit) ?> per simpan. Bila foto banyak/besar, unggah bertahap (simpan beberapa kali).</p>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Kesimpulan &amp; Rekomendasi <span class="text-danger">*</span></h3>
        <textarea class="form-control" name="summary" rows="5" maxlength="4000" placeholder="Ringkasan temuan lapangan, kendala, dan rekomendasi sistem."><?= e($report['summary'] ?? '') ?></textarea>
    </div>

    <div class="report-actionbar">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a href="<?= url('/surveys/' . $survey['id']) ?>" class="btn btn-light">Kembali tanpa menyimpan</a>
        <span class="text-muted small ms-2">Setelah disimpan, ajukan untuk diperiksa dari halaman Survey.</span>
    </div>
</form>

<script>
(function () {
    var input = document.getElementById('photosInput');
    var info = document.getElementById('uploadInfo');
    if (!input || !info) return;
    input.addEventListener('change', function () {
        var total = 0;
        Array.prototype.forEach.call(input.files, function (f) { total += f.size; });
        var mb = (total / 1048576).toFixed(1);
        info.textContent = input.files.length + ' foto · ' + mb + ' MB';
        info.className = 'small ' + (total > 8 * 1048576 ? 'text-danger fw-semibold' : 'text-muted');
        if (total > 8 * 1048576) info.textContent += ' — mendekati batas server, pertimbangkan unggah bertahap';
    });
})();
</script>
