<?php

/**
 * Verifikasi BoqCalculator terhadap nilai tersimpan di Excel "Detailed Calculation BoQ".
 * Tidak memakai database: data referensi dibaca dari database/seeders/boq_reference.json.
 *
 *   php tools/boq/verify.php
 *
 * Keluar dengan kode 1 bila ada pemeriksaan gagal.
 */

use App\Services\Boq\BoqCalculator;
use App\Services\Boq\BoqReference;

require_once __DIR__ . '/../../vendor/autoload.php';

$ref = BoqReference::fromJsonFile(__DIR__ . '/../../database/seeders/boq_reference.json');
$calc = new BoqCalculator($ref);

$pass = 0;
$fail = 0;
function check(string $name, mixed $actual, mixed $expected, float $tol = 1e-6): void
{
    global $pass, $fail;
    $ok = is_float($expected) || is_float($actual) ? abs((float) $actual - (float) $expected) <= $tol : $actual === $expected;
    if ($ok) {
        $pass++;
        echo "  ok    {$name}\n";
    } else {
        $fail++;
        echo "  GAGAL {$name}: hasil " . json_encode($actual, JSON_UNESCAPED_UNICODE) . ', harusnya ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

function line(array $result, string $item): ?array
{
    foreach ($result['lines'] as $l) {
        if ($l['item'] === $item) {
            return $l;
        }
    }

    return null;
}

// ------------------------------------------------------------------------------------------
echo "== Skenario 1: sample di sheet 'BoQ On Grid (PV-INVERTER-AC)' (1 fasa, 2x SG6.0RS + 2x SG5.0RT-P2) ==\n";
$r = $calc->calculate([
    'standard' => 'HME',
    'module' => ['model' => 'TSM-NEG21C.20 720 Wp', 'qty' => 550, 'wp' => 630], // Excel H3 = 630 (input manual)
    'inverters' => [
        ['model' => 'SG6.0RS', 'qty' => 2, 'length' => 20, 'core' => '1C', 'skun_ac' => 'Ferrules', 'skun_pe' => 'Type Y'],
        ['model' => 'SG5.0RT-P2', 'qty' => 2, 'length' => 10, 'core' => '1C', 'skun_ac' => 'Ferrules', 'skun_pe' => 'Ferrules'],
    ],
    'interconnection' => ['length' => 10, 'core' => '1C', 'runs' => 3, 'skun_ac' => 'Type SC', 'skun_pe' => 'Type SC'],
    'monitoring' => ['device' => 'Sungrow|WiNet-S', 'qty' => 1],
]);
$s = $r['summary'];
check('Wp sistem (H2)', $s['system_wp'], 346500.0);
check('Daya inverter total (F3)', $s['inverter_w'], 22000.0);
check('DC/AC (H1)', $s['dc_ac_ratio'], 15.75);
check('Status DC/AC (K1)', $s['dc_ac_status'], 'NEED TO ADJUST');
check('Arus total inverter (Data Cabling D34)', $s['total_current_a'], 71.2);
check('Breaker utama (Breaker Selection AQ4 = 100A)', $s['main_breaker_a'], 100.0);
check('Fasa (C8 = 1 fasa)', $s['phase'], 1);

$expect = [
    'Cable AC — Inverter 1 Side' => [120.0, 'NYY 2Rx1Cx6mm²'],
    'Cable Grounding AC — Inverter 1 Side' => [40.0, 'NYA 2Rx1Cx6mm²'],
    'Cable AC — Inverter 2 Side' => [60.0, 'NYY 2Rx1Cx2,5mm²'],
    'Cable Grounding AC — Inverter 2 Side' => [20.0, 'NYA 2Rx1Cx2,5mm²'],
    'Cable AC — Interconnection Side' => [90.0, 'NYY 3Rx1Cx4mm²'],
    'Cable Grounding AC — Interconnection Side' => [30.0, 'NYA 3Rx1Cx4mm²'],
];
foreach ($expect as $item => [$qty, $spec]) {
    $l = line($r, $item);
    check("{$item}: panjang", $l['qty'] ?? null, $qty);
    check("{$item}: spesifikasi", $l['spec'] ?? null, $spec);
}

$skun = [
    'Skun Cable AC — Inverter 1' => ['Ferrules E 6012 6mm sq', 17.0],
    'Skun Cable Grounding — Inverter 1' => ['Type Y SVL - 5,5 - 4 6mm sq', 5.0],
    'Skun Cable AC — Inverter 2' => ['Ferrules E 2508 2,5mm sq', 17.0],
    'Skun Cable Grounding — Inverter 2' => ['Ferrules E 2508 2,5mm sq', 5.0],
    'Skun Cable AC — Interconnection' => ['Type SC SC-4 4mm sq', 26.0],
    'Skun Cable Grounding — Interconnection' => ['Type SC SC-4 4mm sq', 7.0],
    'Vinyl Insulation AC — Inverter 1' => ['Vinyl Insulation V - 3,5', 17.0],
    'Vinyl Insulation Grounding — Inverter 1' => ['Vinyl Insulation V - 3,5', 5.0],
    'Vinyl Insulation AC — Interconnection' => ['Vinyl Insulation V - 2', 26.0],
    'Vinyl Insulation Grounding — Interconnection' => ['Vinyl Insulation V - 2', 7.0],
];
foreach ($skun as $item => [$spec, $qty]) {
    $l = line($r, $item);
    check("{$item}: spesifikasi", $l['spec'] ?? null, $spec);
    check("{$item}: jumlah", $l['qty'] ?? null, $qty);
}
// Excel mengembalikan "Tidak Tersedia" untuk vinyl 2,5 mm² karena rentang XLOOKUP mulai di baris 27 (melewatkan baris 26).
// Sistem memakai tabel lengkap, jadi hasil yang benar adalah V - 1,25.
check('Vinyl inverter 2 AC (Excel salah rentang; benar: V - 1,25)', line($r, 'Vinyl Insulation AC — Inverter 2')['spec'] ?? null, 'Vinyl Insulation V - 1,25');

// Excel AC COMB H14/H15 (nilai tersimpan): kode dipilih dengan kecocokan persis seri + kutub (mengikuti fasa SISTEM) + rating + kA 10.
check('Breaker inverter 1: kode Excel H14 = A9F84140 (IC60N/H/L 1P 40A 10kA)', str_contains(line($r, 'Breaker Inverter 1')['spec'] ?? '', 'A9F84140'), true);
check('Breaker inverter 2: kode Excel H15 = A9F84116 (1P 16A 10kA; kutub mengikuti fasa sistem)', str_contains(line($r, 'Breaker Inverter 2')['spec'] ?? '', 'A9F84116'), true);
check('Breaker utama 2P 100A C120N 10kA: Excel H13 = #N/A, sistem memberi peringatan', count(array_filter($r['checks'], fn ($c) => str_contains($c['text'], 'Breaker utama') && str_contains($c['text'], '#N/A'))), 1);
check('Aturan Excel: campuran fasa (inverter 2 berfasa 3 di sistem 1 fasa) ditandai error', count(array_filter($r['checks'], fn ($c) => $c['level'] === 'error' && str_contains($c['text'], 'satu fasa'))), 1);
check('Aturan Excel: tipe skun Type SC tidak diizinkan untuk breaker C120N (interconnection) ditandai', count(array_filter($r['checks'], fn ($c) => str_contains($c['text'], 'Type SC') && str_contains($c['text'], 'tidak sesuai breaker'))) >= 1, true);
$cab = array_column($r['cables'], null, 'label');
check('%VD kabel inverter 1 (M5 = 0,0050303)', $cab['Kabel AC Inverter 1']['vd_pct'], 0.50303253, 1e-6);
check('%VD kabel inverter 2 (M23 = 0,0018397)', $cab['Kabel AC Inverter 2']['vd_pct'], 0.18397085, 1e-6);
check('%VD kabel interconnection (M34 = 0,0098182)', $cab['Kabel AC Interconnection']['vd_pct'], 0.98182397, 1e-6);
check('Panel Komunikasi tidak muncul untuk WiNet-S (INVERTER!AX = 0)', line($r, 'Panel Komunikasi'), null);

$jasa = $r['jasa'];
check('Jasa: tier kWp (K1 = 400)', (float) $jasa['tier_kwp'], 400.0);
check('Jasa: engineering (K2)', $jasa['items'][0]['price'], 5980000.0);
check('Jasa: training (K3)', $jasa['items'][1]['price'], 4000000.0);
check('Jasa: ATK dibulatkan (M4)', $jasa['items'][2]['price'], 1000000.0);
check('Jasa: contingency (M5)', $jasa['items'][3]['price'], 549000.0);
check('Jasa: total (M6)', $jasa['total'], 11529000.0);

// ------------------------------------------------------------------------------------------
echo "== Skenario 2: sheet tersembunyi 'Interconnection Point Cabling' (3 fasa, 8x SG150CX) ==\n";
$r2 = $calc->calculate([
    'module' => ['model' => 'TSM-NEG21C.20 720 Wp', 'qty' => 2000],
    'inverters' => [['model' => 'SG150CX', 'qty' => 8, 'length' => 50, 'core' => '1C']],
    'interconnection' => ['length' => 30, 'core' => '1C', 'runs' => 4],
    'monitoring' => ['device' => 'Sungrow|COM100E', 'qty' => 1],
]);
check('Fasa 3', $r2['summary']['phase'], 3);
check('Arus total (D6 = 2005,6)', $r2['summary']['total_current_a'], 2005.6);
check('Kabel inverter: spesifikasi (I5 = 150)', line($r2, 'Cable AC — Inverter 1 Side')['spec'] ?? null, 'NYY 8Rx1Cx150mm²');
check('Kabel inverter: panjang (C9 = 1600)', line($r2, 'Cable AC — Inverter 1 Side')['qty'] ?? null, 1600.0);
check('PE inverter: spesifikasi (F10 = 95)', line($r2, 'Cable Grounding AC — Inverter 1 Side')['spec'] ?? null, 'NYA 8Rx1Cx95mm²');
check('PE inverter: panjang (C10 = 400)', line($r2, 'Cable Grounding AC — Inverter 1 Side')['qty'] ?? null, 400.0);
check('Kabel interconnection: spesifikasi (I6 = 500)', line($r2, 'Cable AC — Interconnection Side')['spec'] ?? null, 'NYY 4Rx1Cx500mm²');
check('PE interconnection (F12 = 240)', line($r2, 'Cable Grounding AC — Interconnection Side')['spec'] ?? null, 'NYA 4Rx1Cx240mm²');
// Sheet tersembunyi itu mengalikan panjang combiner dengan jumlah inverter (C11 = 3840 = 30x4x8x4); blok "UP TO 1 INVERTER"
// yang dipakai BoQ utama tidak (30x4x4 = 480). Sistem mengikuti blok yang dipakai BoQ utama.
check('Kabel interconnection: panjang (blok BoQ utama)', line($r2, 'Cable AC — Interconnection Side')['qty'] ?? null, 480.0);
$cab2 = array_column($r2['cables'], null, 'label');
check('%VD inverter (M5 = 0,0046494)', $cab2['Kabel AC Inverter 1']['vd_pct'], 0.4649419525, 1e-6);
check('%VD interconnection (M6 = 0,0065872)', $cab2['Kabel AC Interconnection']['vd_pct'], 0.6587177598, 1e-6);

// ------------------------------------------------------------------------------------------
echo "== Aturan pilihan Excel (data validation) ==
";
check('Inti 1 fasa = 1C, 3C', $ref->coresFor(1), ['1C', '3C']);
check('Inti 3 fasa = 1C, 4C', $ref->coresFor(3), ['1C', '4C']);
check('Kutub 1 fasa = 1P, 2P; 3 fasa = 3P, 4P', [$ref->polesFor(1), $ref->polesFor(3)], [['1P', '2P'], ['3P', '4P']]);
check('Seri 1 fasa hanya IC60N/H/L dan C120N', $ref->seriesFor(1), ['IC60N/H/L', 'C120N']);
check('kA 1 fasa = 6, 10', array_map('floatval', $ref->kaFor(1)), [6.0, 10.0]);
check('Trafo 0 kVA -> 10 kA; 100 -> 25; 1000 -> 36; 1250 -> 50', [$ref->transformerKa(0)['ka'], $ref->transformerKa(100)['ka'], $ref->transformerKa(1000)['ka'], $ref->transformerKa(1250)['ka']], [10.0, 25.0, 36.0, 50.0]);
check('Skun untuk breaker IC60N/H/L', $ref->skunTypesForSeries('IC60N/H/L'), ['Ferrules', 'Plate Terminal', 'Type Y', 'Insulated Pin Terminal']);
check('Skun untuk breaker CVSB/F/N/H', $ref->skunTypesForSeries('CVSB/F/N/H'), ['Type DTG', 'Type CE', 'Type SC', 'Type Y']);
check('Faktor breaker = 1; 1,1; 1,15; 1,2; 1,25; 1,3', array_map('floatval', $ref->rule('breaker_factors')), [1.0, 1.1, 1.15, 1.2, 1.25, 1.3]);
$bad = $calc->calculate(['module' => ['model' => 'TSM-NEG21C.20 720 Wp', 'qty' => 10], 'inverters' => [['model' => 'SG6.0RS', 'qty' => 1, 'length' => 5, 'core' => '4C']], 'interconnection' => ['length' => 5, 'core' => '4C']]);
check('Inti 4C pada sistem 1 fasa ditolak (2 error: inverter dan interconnection)', count(array_filter($bad['checks'], fn ($c) => $c['level'] === 'error' && str_contains($c['text'], 'tidak tersedia untuk sistem 1 fasa'))), 2);
$auto = $calc->calculate(['module' => ['model' => 'TSM-NEG21C.20 720 Wp', 'qty' => 170], 'inverters' => [['model' => 'SG50CX-P2', 'qty' => 2, 'length' => 25, 'core' => '1C']], 'interconnection' => ['length' => 40, 'core' => '1C', 'runs' => 2]]);
check('Skun otomatis kabel 35 mm² pada breaker iC60: Ferrules (tipe pertama yang diizinkan & tersedia)', str_contains(line($auto, 'Skun Cable AC — Inverter 1')['spec'] ?? '', 'Ferrules'), true);
check('Breaker inverter 3 fasa tanpa error produk (SG50CX-P2 = 125A)', str_contains(line($auto, 'Breaker Inverter 1')['spec'] ?? '', '125A'), true);

echo "== Tangga ukuran kabel (rumus Data Cabling kolom I) ==\n";
foreach ([[10, '1C', 2.5], [26, '1C', 2.5], [26.01, '1C', 4.0], [46, '1C', 4.0], [46.01, '1C', 6.0], [960, '1C', 500.0],
    [18, '4C', 1.5], [18.01, '4C', 2.5], [500, '4C', 300.0]] as [$amps, $core, $size]) {
    check("{$amps} A, {$core} => {$size} mm²", (float) ($ref->pickCable($core, (float) $amps)['attrs']['size'] ?? 0), $size);
}
check('960,01 A tidak ada ukuran (Excel: FALSE)', $ref->pickCable('1C', 960.01), null);

echo "== Rating breaker per inverter: tangga 1,3 x Imax vs kolom 'Breaker Selection' Excel ==\n";
$mismatch = [];
foreach ($ref->all('inverter') as $row) {
    $a = $row['attrs'];
    $auto = $ref->breakerRating((float) $a['imax'] * 1.3);
    if ($auto !== (float) $a['breaker_rating']) {
        $mismatch[] = $row['code'] . ' (Excel ' . $a['breaker_rating'] . 'A, tangga ' . $auto . 'A)';
    }
}
check('Semua ' . count($ref->all('inverter')) . ' inverter: rating tangga = rating Excel', $mismatch, []);
foreach ($mismatch as $m) {
    echo "        beda: {$m}\n";
}

echo "\n{$pass} lulus, {$fail} gagal\n";
exit($fail > 0 ? 1 : 0);
