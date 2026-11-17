<?php
/**
 * HTML Survey Report untuk dompdf. Mengikuti template perusahaan
 * ("Survey Report Bravo Tuban Rev00"): tabel berlabel dengan kotak centang,
 * satu bagian per halaman. Label PDF memakai istilah asli template.
 *
 * Variabel: $data (isi terstruktur), $photos (kategori => daftar foto dengan
 * 'uri','w','h','caption'), $meta (judul, kode dokumen, revisi, surveyor,
 * reporter, tanggal, header image).
 */
use App\Services\SurveyReportSchema as Schema;

$e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$g = fn (string $s, string $k) => $data[$s][$k] ?? '';

/** Kotak pilihan dalam grid N kolom. */
$choiceGrid = function (array $row, string $section, string $key, array $selected, int $cols = 3, string $label = '') use ($e, $data) {
    $other = $row['other'] ?? [];
    $cells = [];
    foreach ($row['options'] as $value => $_label) {
        $checked = in_array($value, $selected, true);
        $text = $e($value);
        if (in_array($value, $other, true)) {
            $txt = trim((string) ($data[$section][$key . '_other'] ?? ''));
            $text = $e($value) . ': ' . ($checked && $txt !== '' ? '<u>' . $e($txt) . '</u>' : '_____________');
        }
        $cells[] = '<td class="opt">' . Schema::box($checked) . ' ' . $text . '</td>';
    }
    // Label sub-isian (mis. "Availability :") menjadi sel pertama agar sebaris dengan pilihan.
    $labelCell = $label !== '' ? '<td class="opt plabel" style="width:24%">' . $e($label) . ' :</td>' : '';
    $blankCell = $label !== '' ? '<td class="opt" style="width:24%"></td>' : '';
    $html = '<table class="grid"><tr>' . $labelCell;
    foreach ($cells as $i => $c) {
        if ($i > 0 && $i % $cols === 0) {
            $html .= '</tr><tr>' . $blankCell;
        }
        $html .= $c;
    }
    $rem = count($cells) % $cols;
    if ($rem !== 0) {
        $html .= str_repeat('<td class="opt"></td>', $cols - $rem);
    }

    return $html . '</tr></table>';
};

$fitSize = function (array $p, int $maxW, int $maxH): array {
    $w = max(1, (int) $p['w']);
    $h = max(1, (int) $p['h']);
    $s = min($maxW / $w, $maxH / $h, 1.0);
    if ($s >= 1.0) {
        $s = min($maxW / $w, $maxH / $h);
    }

    return [(int) round($w * $s), (int) round($h * $s)];
};

$renderRow = function (array $row, string $section) use ($data, $e, $g, $choiceGrid) {
    $key = $row['key'];
    $type = $row['type'];
    $val = '';

    if ($type === 'text') {
        $v = trim((string) $g($section, $key));
        $val = $v === '' ? '' : $e($v) . (!empty($row['suffix']) ? ' ' . $e($row['suffix']) : '');
    } elseif ($type === 'textarea') {
        $val = nl2br($e($g($section, $key)));
    } elseif ($type === 'date') {
        $d = (string) $g($section, $key);
        $val = $d === '' ? '' : $e(date('d/m/Y', strtotime($d)));
    } elseif ($type === 'choice') {
        $val = $choiceGrid($row, $section, $key, [(string) $g($section, $key)], (int) ($row['columns'] ?? 3));
        if (!empty($row['note']) && trim((string) $g($section, $row['note'])) !== '') {
            $val .= '<div class="note">' . $e($g($section, $row['note'])) . '</div>';
        }
    } elseif ($type === 'multi') {
        $val = $choiceGrid($row, $section, $key, (array) $g($section, $key), 3);
        if (!empty($row['note']) && trim((string) $g($section, $row['note'])) !== '') {
            $val .= '<div class="note">' . $e($g($section, $row['note'])) . '</div>';
        }
    } elseif ($type === 'dim') {
        $dims = (array) $g($section, $key);
        $tilt = trim((string) $g($section, 'roof_tilt'));
        $val = '<table class="grid"><tr>';
        foreach ($row['letters'] as $i => $l) {
            if ($i > 0 && $i % 3 === 0) {
                $val .= '</tr><tr>';
            }
            $d = trim((string) ($dims[$l] ?? ''));
            $val .= '<td class="opt">' . $e($l) . ' : ' . ($d === '' ? '_________' : $e($d)) . ' mm</td>';
        }
        $val .= '</tr></table>';
    } elseif ($type === 'parts') {
        foreach ($row['parts'] as $part) {
            $pk = $key . '_' . $part['key'];
            $pv = $g($section, $pk);
            if ($part['type'] === 'choice') {
                $cols = count($part['options']) <= 2 ? 2 : (count($part['options']) === 3 ? 3 : 4);
                $val .= $choiceGrid($part, $section, $pk, [(string) $pv], $cols, (string) $part['label']);
            } else {
                $pv = trim((string) $pv);
                $val .= '<div class="line"><span class="plabel">' . $e($part['label']) . ' :</span> ' . ($pv === '' ? '____________' : $e($pv)) . '</div>';
            }
        }
    } elseif ($type === 'panels') {
        $cells = (array) $g($section, $key);
        $val = '<table class="inner"><tr><th>Item</th>';
        foreach ($row['columns'] as $c) {
            $val .= '<th>' . $e($c) . '</th>';
        }
        $val .= '</tr>';
        foreach ($row['rows'] as $rk => $rl) {
            $val .= '<tr><td>' . $e($rl) . '</td>';
            foreach ($row['columns'] as $c) {
                $val .= '<td>' . $e($cells[$c][$rk] ?? '') . '</td>';
            }
            $val .= '</tr>';
        }
        $val .= '<tr><td>' . $e($row['flag']['label']) . '</td>';
        foreach ($row['columns'] as $c) {
            $val .= '<td class="ctr">' . Schema::box(!empty($cells[$c][$row['flag']['key']])) . '</td>';
        }
        $val .= '</tr></table>';
    }

    return '<tr><td class="lab">' . $e($row['label']) . '</td><td class="val">' . $val . '</td></tr>';
};

$photoImg = function (array $p, int $maxW, int $maxH) use ($fitSize, $e) {
    [$w, $h] = $fitSize($p, $maxW, $maxH);

    return '<img src="' . $p['uri'] . '" width="' . $w . '" height="' . $h . '" alt="">';
};
$sectionByKey = [];
foreach (Schema::sections() as $s) {
    $sectionByKey[$s['key']] = $s;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22mm 18mm 20mm 18mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #222; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    h1.sec { font-size: 13pt; text-align: center; margin: 0 0 14px 0; letter-spacing: .3px; }
    table.t { width: 100%; border-collapse: collapse; }
    table.t td { border: 1px solid #cfe6bd; padding: 6px 8px; vertical-align: top; }
    td.lab { width: 24%; }
    td.val { width: 76%; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid td.opt { border: 0; padding: 2px 4px 2px 0; }
    table.inner { width: 100%; border-collapse: collapse; }
    table.inner th, table.inner td { border: 1px solid #cfe6bd; padding: 4px 6px; font-size: 9pt; }
    table.inner th { background: #f3faee; }
    .ctr { text-align: center; }
    .line { margin-bottom: 3px; }
    .plabel { color: #444; }
    .note { margin-top: 4px; color: #555; font-style: italic; }
    .hdr { text-align: center; margin-bottom: 10px; }
    .sign { width: 62%; margin-left: 38%; margin-top: 150px; border-collapse: collapse; }
    .sign th, .sign td { border: 1px solid #555; padding: 6px; text-align: center; font-size: 9pt; }
    .sign td { height: 42px; }
    .caption { font-size: 9pt; margin: 4px 0 10px 0; }
    .photos td { text-align: center; vertical-align: top; padding: 6px; }
    .muted { color: #777; }
</style>
</head>
<body>

<?php /* ------------------------------------------------------------ SITE INFORMATION */ ?>
<div class="page">
    <?php if (!empty($meta['header_uri'])): ?><div class="hdr"><img src="<?= $meta['header_uri'] ?>" style="max-height:60px; max-width:100%"></div><?php endif; ?>
    <h1 class="sec"><?= $e($sectionByKey['site']['title']) ?></h1>
    <table class="t">
        <?php foreach ($sectionByKey['site']['rows'] as $row) { echo $renderRow($row, 'site'); } ?>
    </table>
    <table class="sign">
        <tr><th>Date</th><th>Surveyor</th><th>Reporter</th></tr>
        <tr><td><?= $e($meta['date']) ?></td><td><?= $e($meta['surveyor']) ?></td><td><?= $e($meta['reporter']) ?></td></tr>
    </table>
</div>

<?php /* ------------------------------------------------------------ SITE OVERVIEW */ ?>
<div class="page">
    <h1 class="sec">SITE OVERVIEW</h1>
    <?php if (empty($photos['site_overview'])): ?>
        <p class="muted" style="text-align:center">(Tidak ada foto)</p>
    <?php else: foreach ($photos['site_overview'] as $p): ?>
        <div style="text-align:center; margin-bottom: 14px;">
            <?= $photoImg($p, 640, 420) ?>
            <?php if (!empty($p['caption'])): ?><div class="caption"><?= $e($p['caption']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php /* ------------------------------------------------------------ BUILDING INFORMATION */ ?>
<div class="page">
    <h1 class="sec"><?= $e($sectionByKey['building']['title']) ?></h1>
    <table class="t">
        <?php foreach ($sectionByKey['building']['rows'] as $row) { echo $renderRow($row, 'building'); } ?>
    </table>
</div>

<?php /* ------------------------------------------------------------ ELECTRICAL OVERVIEW */ ?>
<div class="page">
    <h1 class="sec"><?= $e($sectionByKey['electrical']['title']) ?></h1>
    <table class="t">
        <?php foreach ($sectionByKey['electrical']['rows'] as $row) { echo $renderRow($row, 'electrical'); } ?>
    </table>
</div>

<?php /* ------------------------------------------------------------ PROTECTIONS */ ?>
<div class="page">
    <h1 class="sec"><?= $e($sectionByKey['protection']['title']) ?></h1>
    <table class="t">
        <?php foreach ($sectionByKey['protection']['rows'] as $row) { echo $renderRow($row, 'protection'); } ?>
    </table>
</div>

<?php /* ------------------------------------------------------------ LAYOUT */ ?>
<div class="page">
    <h1 class="sec">LAYOUT</h1>
    <?php if (empty($photos['layout'])): ?>
        <p class="muted" style="text-align:center">(Tidak ada foto)</p>
    <?php else: foreach ($photos['layout'] as $p): ?>
        <div style="text-align:center; margin-bottom: 14px;">
            <?= $photoImg($p, 640, 420) ?>
            <?php if (!empty($p['caption'])): ?><div class="caption"><?= $e($p['caption']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php /* ------------------------------------------------------------ SPECIFIC CONCERN */ ?>
<div class="page">
    <h1 class="sec"><?= $e($sectionByKey['concern']['title']) ?></h1>
    <table class="t">
        <?php foreach ($sectionByKey['concern']['rows'] as $row) { echo $renderRow($row, 'concern'); } ?>
    </table>
</div>

<?php /* ------------------------------------------------------------ NOTES */ ?>
<div class="page">
    <h1 class="sec">NOTES</h1>
    <?php if (!empty($meta['summary'])): ?>
        <p><strong>Conclusion &amp; recommendation:</strong><br><?= nl2br($e($meta['summary'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($photos['notes'])): ?>
        <p><strong>Note:</strong></p>
        <table class="photos" style="width:100%; border-collapse:collapse;">
            <?php foreach (array_chunk($photos['notes'], 2, true) as $pair): ?>
            <tr>
                <?php $i = 0; foreach ($pair as $idx => $p): $i++; ?>
                <td style="width:50%">
                    <?= $photoImg($p, 300, 230) ?>
                    <div class="caption"><?= ($idx + 1) ?>. <?= $e($p['caption'] ?: 'Dokumentasi') ?></div>
                </td>
                <?php endforeach; if ($i === 1): ?><td style="width:50%"></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php elseif (empty($meta['summary'])): ?>
        <p class="muted" style="text-align:center">(Tidak ada catatan)</p>
    <?php endif; ?>
</div>

</body>
</html>
