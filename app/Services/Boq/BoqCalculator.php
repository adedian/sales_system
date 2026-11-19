<?php

namespace App\Services\Boq;

/**
 * Kalkulator BoQ On-Grid — port logika Excel "Detailed Calculation BoQ Copper Residensial
 * Industrial" ke PHP. Sumber rumus (sheet Excel):
 *
 *   - BoQ On Grid (PV-INVERTER-AC): ringkasan sistem, daftar material, kabel, skun, vinyl
 *   - Data Cabling ON GRID: arus x 1,7 -> ukuran kabel -> drop tegangan; ukuran PE
 *   - Breaker Selection & CT: rating breaker utama (arus total x 1,2)
 *   - Cable DC / BoQ On Grid (DC Cable): kabel DC per gedung + grounding
 *   - DATA Jasa: tarif jasa per tier kWp
 *
 * Murni fungsi: input array + BoqReference -> hasil array, tanpa database/request.
 * Bagian Excel yang belum dibawa (tray, conduit, gland, signage, panel AC combiner lengkap,
 * water system, mounting/shelter/ladder/cable bridge/skylight) dicatat di `result.not_covered`.
 */
class BoqCalculator
{
    public const MAX_INVERTERS = 4;
    public const MAX_BUILDINGS = 4;
    public const MAX_METEO = 5;

    public function __construct(private BoqReference $ref)
    {
    }

    // ----------------------------------------------------------------- input

    /** Nilai awal formulir (kosong, siap diisi). */
    public static function defaults(): array
    {
        $inverters = [];
        for ($i = 0; $i < self::MAX_INVERTERS; $i++) {
            $inverters[] = ['model' => '', 'qty' => 0, 'length' => 0, 'core' => '1C', 'skun_ac' => '', 'skun_pe' => '', 'breaker_series' => '', 'breaker_poles' => '', 'breaker_ka' => 10];
        }
        $meteo = [];
        for ($i = 0; $i < self::MAX_METEO; $i++) {
            $meteo[] = ['code' => '', 'qty' => 0];
        }
        $buildings = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $buildings[] = ['name' => 'Building ' . $name, 'strings' => 0, 'roof_far_m' => 0, 'edge_m' => 0, 'home_run_m' => 0,
                'size' => '1C x 6', 'grounding_m' => 0, 'rod_m' => 0];
        }

        return [
            'standard' => 'HME',
            'module' => ['model' => '', 'qty' => 0, 'wp' => 0],
            'inverter_brand' => '',
            'inverters' => $inverters,
            'interconnection' => ['length' => 0, 'core' => '1C', 'runs' => 1, 'skun_ac' => '', 'skun_pe' => ''],
            'monitoring' => ['device' => '', 'qty' => 1, 'zero_export' => 0, 'meteo' => $meteo],
            'comm' => ['cat6_m' => 0, 'rs485_m' => 0],
            'skun_spare_percent' => 5,
            'breaker_factor_inverter' => 1.3,
            'breaker_factor_main' => 1.2,
            'transformer_kva' => 0,
            'main_breaker' => ['series' => '', 'poles' => ''],
            'dc' => ['far_factor' => 0.5, 'modules_in_series' => 18, 'spare_percent' => 10, 'grounding_spare_percent' => 10, 'rod_spare_percent' => 5, 'buildings' => $buildings],
            'jasa' => ['include' => 1],
        ];
    }

    /** Menormalkan input mentah (mis. dari form POST) menjadi tipe yang pasti. */
    public static function normalize(array $raw): array
    {
        $d = self::defaults();
        $in = $d;

        $in['standard'] = strtoupper((string) ($raw['standard'] ?? 'HME')) === 'DEVELOPER' ? 'DEVELOPER' : 'HME';
        $in['module'] = [
            'model' => trim((string) ($raw['module']['model'] ?? '')),
            'qty' => (int) self::num($raw['module']['qty'] ?? 0),
            'wp' => self::num($raw['module']['wp'] ?? 0),
        ];
        $in['inverter_brand'] = trim((string) ($raw['inverter_brand'] ?? ''));

        for ($i = 0; $i < self::MAX_INVERTERS; $i++) {
            $r = $raw['inverters'][$i] ?? [];
            $in['inverters'][$i] = [
                'model' => trim((string) ($r['model'] ?? '')),
                'qty' => max(0, (int) self::num($r['qty'] ?? 0)),
                'length' => max(0, self::num($r['length'] ?? 0)),
                'core' => self::core($r['core'] ?? '1C'),
                'skun_ac' => trim((string) ($r['skun_ac'] ?? '')),
                'skun_pe' => trim((string) ($r['skun_pe'] ?? '')),
                'breaker_series' => trim((string) ($r['breaker_series'] ?? '')),
                'breaker_poles' => strtoupper(trim((string) ($r['breaker_poles'] ?? ''))),
                'breaker_ka' => self::num($r['breaker_ka'] ?? 0) > 0 ? self::num($r['breaker_ka']) : 10.0,
            ];
        }

        $ic = $raw['interconnection'] ?? [];
        $in['interconnection'] = [
            'length' => max(0, self::num($ic['length'] ?? 0)),
            'core' => self::core($ic['core'] ?? '1C'),
            'runs' => max(1, (int) self::num($ic['runs'] ?? 1)),
            'skun_ac' => trim((string) ($ic['skun_ac'] ?? '')),
            'skun_pe' => trim((string) ($ic['skun_pe'] ?? '')),
        ];

        $mon = $raw['monitoring'] ?? [];
        $in['monitoring'] = ['device' => trim((string) ($mon['device'] ?? '')), 'qty' => max(0, (int) self::num($mon['qty'] ?? 1)),
            'zero_export' => max(0, (int) self::num($mon['zero_export'] ?? 0)), 'meteo' => []];
        for ($i = 0; $i < self::MAX_METEO; $i++) {
            $m = $mon['meteo'][$i] ?? [];
            $in['monitoring']['meteo'][] = ['code' => trim((string) ($m['code'] ?? '')), 'qty' => max(0, (int) self::num($m['qty'] ?? 0))];
        }

        $in['comm'] = ['cat6_m' => max(0, self::num($raw['comm']['cat6_m'] ?? 0)), 'rs485_m' => max(0, self::num($raw['comm']['rs485_m'] ?? 0))];
        $in['skun_spare_percent'] = max(0, self::num($raw['skun_spare_percent'] ?? 5));
        $in['breaker_factor_inverter'] = self::num($raw['breaker_factor_inverter'] ?? 1.3) ?: 1.3;
        $in['breaker_factor_main'] = self::num($raw['breaker_factor_main'] ?? 1.2) ?: 1.2;
        $in['transformer_kva'] = max(0, self::num($raw['transformer_kva'] ?? 0));
        $in['main_breaker'] = ['series' => trim((string) ($raw['main_breaker']['series'] ?? '')), 'poles' => strtoupper(trim((string) ($raw['main_breaker']['poles'] ?? '')))];

        $dc = $raw['dc'] ?? [];
        $in['dc'] = [
            'far_factor' => self::num($dc['far_factor'] ?? 0.5) ?: 0.5,
            'modules_in_series' => max(0, (int) self::num($dc['modules_in_series'] ?? 18)),
            'spare_percent' => max(0, self::num($dc['spare_percent'] ?? 10)),
            'grounding_spare_percent' => max(0, self::num($dc['grounding_spare_percent'] ?? 10)),
            'rod_spare_percent' => max(0, self::num($dc['rod_spare_percent'] ?? 5)),
            'buildings' => [],
        ];
        for ($i = 0; $i < self::MAX_BUILDINGS; $i++) {
            $b = $dc['buildings'][$i] ?? [];
            $in['dc']['buildings'][] = [
                'name' => trim((string) ($b['name'] ?? '')) ?: $d['dc']['buildings'][$i]['name'],
                'strings' => max(0, (int) self::num($b['strings'] ?? 0)),
                'roof_far_m' => max(0, self::num($b['roof_far_m'] ?? 0)),
                'edge_m' => max(0, self::num($b['edge_m'] ?? 0)),
                'home_run_m' => max(0, self::num($b['home_run_m'] ?? 0)),
                'size' => trim((string) ($b['size'] ?? '')) ?: '1C x 6',
                'grounding_m' => max(0, self::num($b['grounding_m'] ?? 0)),
                'rod_m' => max(0, self::num($b['rod_m'] ?? 0)),
            ];
        }

        $in['jasa'] = ['include' => !empty($raw['jasa']['include']) ? 1 : (array_key_exists('jasa', $raw) ? 0 : 1)];

        return $in;
    }

    private static function core(mixed $v): string
    {
        $v = strtoupper(trim((string) $v));

        return in_array($v, ['1C', '3C', '4C'], true) ? $v : '1C';
    }

    private static function num(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }
        if (str_contains($s, ',') && !str_contains($s, '.')) {
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }

        return is_numeric($s) ? (float) $s : 0.0;
    }

    // ------------------------------------------------------------- perhitungan

    /** @return array<string,mixed> */
    public function calculate(array $raw): array
    {
        $in = self::normalize($raw);
        $ref = $this->ref;

        $ampFactor = $ref->param('ampacity_factor', 1.7);
        $mainFactor = $in['breaker_factor_main'];       // AC COMB!C13 (pilihan 1 / 1,1 / 1,15 / 1,2 / 1,25 / 1,3)
        $currentFactor = $in['breaker_factor_inverter']; // AC COMB!C14
        $vNom = $ref->param('nominal_voltage', 400);
        $vdLimit = $ref->param('vd_limit_percent', 3);
        $spare = $in['skun_spare_percent'] / 100;

        $checks = [];
        $lines = [];
        $cables = [];
        $add = function (string $section, string $item, string $spec, string $brand, float $qty, string $unit, string $note = '') use (&$lines): void {
            $lines[] = ['section' => $section, 'item' => $item, 'spec' => $spec, 'brand' => $brand, 'qty' => $qty, 'unit' => $unit, 'note' => $note];
        };

        // ---- Modul
        $mod = $in['module']['model'] !== '' ? $ref->pvModule($in['module']['model']) : null;
        if ($in['module']['qty'] > 0 && $mod === null) {
            $checks[] = ['level' => 'error', 'text' => 'Modul surya "' . $in['module']['model'] . '" tidak ada di Data Referensi.'];
        }
        $wp = $in['module']['wp'] > 0 ? $in['module']['wp'] : (float) ($mod['attrs']['wp'] ?? 0);
        $systemWp = $in['module']['qty'] * $wp;

        // ---- Inverter
        $brand = $in['inverter_brand'];
        $inverters = [];
        $inverterW = 0.0;
        $totalCurrent = 0.0;
        $phase = 1;
        foreach ($in['inverters'] as $i => $inv) {
            if ($inv['model'] === '' || $inv['qty'] <= 0) {
                continue;
            }
            $row = $ref->inverter($inv['model']);
            if ($row === null) {
                $checks[] = ['level' => 'error', 'text' => 'Inverter ' . ($i + 1) . ' "' . $inv['model'] . '" tidak ada di Data Referensi.'];
                continue;
            }
            if ($brand === '') {
                $brand = (string) $row['attrs']['brand'];
            }
            if (($row['attrs']['brand'] ?? '') !== $brand) {
                $checks[] = ['level' => 'warn', 'text' => 'Inverter ' . ($i + 1) . ' bermerek ' . $row['attrs']['brand'] . ', berbeda dari merek inverter 1 (' . $brand . '). Excel mengasumsikan satu merek.'];
            }
            if ($inverters === []) {
                $phase = (int) ($row['attrs']['phase'] ?? 1);
            }
            $inverters[$i] = ['in' => $inv, 'row' => $row];
            $inverterW += $inv['qty'] * (float) ($row['attrs']['power_w'] ?? 0);
            $totalCurrent += $inv['qty'] * (float) ($row['attrs']['imax'] ?? 0);
        }
        if ($inverters === []) {
            $checks[] = ['level' => 'error', 'text' => 'Belum ada inverter yang diisi (model dan jumlah).'];
        }

        // ---- DC/AC
        $dcAc = $inverterW > 0 ? $systemWp / $inverterW : null;
        $dcAcStatus = null;
        if ($dcAc !== null) {
            [$lo, $hi] = $in['standard'] === 'DEVELOPER'
                ? [$ref->param('dcac_dev_min', 1.2), $ref->param('dcac_dev_max', 1.3)]
                : [$ref->param('dcac_hme_min', 1.1), $ref->param('dcac_hme_max', 1.35)];
            // Excel: <= min atau >= max => "NEED TO ADJUST".
            $dcAcStatus = ($dcAc <= $lo || $dcAc >= $hi) ? 'NEED TO ADJUST' : 'OK';
            $checks[] = ['level' => $dcAcStatus === 'OK' ? 'ok' : 'warn',
                'text' => sprintf('DC/AC ratio %.2f (standar %s: di atas %s dan di bawah %s) — %s.', $dcAc, $in['standard'], self::fmt($lo), self::fmt($hi), $dcAcStatus)];
        }

        // ---- Aturan pilihan Excel (data validation dinamis)
        $inList = function (float $v, array $list): bool {
            foreach ($list as $x) {
                if (abs((float) $x - $v) < 1e-9) {
                    return true;
                }
            }

            return false;
        };
        foreach ($inverters as $i => $x) {
            $p = (int) ($x['row']['attrs']['phase'] ?? 1);
            if ($p !== $phase) {
                $checks[] = ['level' => 'error', 'text' => 'Inverter ' . ($i + 1) . ' berfasa ' . $p . ' sedangkan inverter 1 berfasa ' . $phase . '. Semua inverter dalam satu sistem harus satu fasa (fasa sistem mengikuti inverter 1).'];
            }
            if (!in_array($x['in']['core'], $ref->coresFor($phase), true)) {
                $checks[] = ['level' => 'error', 'text' => 'Inti kabel ' . $x['in']['core'] . ' pada inverter ' . ($i + 1) . ' tidak tersedia untuk sistem ' . $phase . ' fasa (pilihan: ' . implode(', ', $ref->coresFor($phase)) . ').'];
            }
        }
        if ($inverters !== [] && !in_array($in['interconnection']['core'], $ref->coresFor($phase), true)) {
            $checks[] = ['level' => 'error', 'text' => 'Inti kabel interconnection ' . $in['interconnection']['core'] . ' tidak tersedia untuk sistem ' . $phase . ' fasa (pilihan: ' . implode(', ', $ref->coresFor($phase)) . ').'];
        }
        $factors = $ref->rule('breaker_factors');
        foreach ([['inverter', $currentFactor], ['utama', $mainFactor]] as [$lbl, $f]) {
            if (!$inList($f, $factors)) {
                $checks[] = ['level' => 'warn', 'text' => 'Faktor arus breaker ' . $lbl . ' ' . self::fmt($f) . ' di luar pilihan Excel (' . implode(' / ', array_map(fn ($v) => self::fmt((float) $v), $factors)) . ').'];
            }
        }
        $spareOptions = $ref->rule('spare_options');
        foreach ([['skun & vinyl', $in['skun_spare_percent']], ['kabel DC', $in['dc']['spare_percent']], ['grounding DC', $in['dc']['grounding_spare_percent']], ['grounding ke rod', $in['dc']['rod_spare_percent']]] as [$lbl, $sp]) {
            if ($spareOptions !== [] && !$inList($sp, $spareOptions)) {
                $checks[] = ['level' => 'warn', 'text' => 'Spare ' . $lbl . ' ' . self::fmt($sp) . '% di luar pilihan Excel (' . implode('%, ', $spareOptions) . '%).'];
            }
        }

        // ---- Breaker (dihitung lebih dulu: seri breaker menentukan tipe skun yang boleh)
        $phaseSeries = $ref->seriesFor($phase);
        $phasePoles = $ref->polesFor($phase);
        $phaseKa = $ref->kaFor($phase);
        $invBk = [];
        foreach ($inverters as $i => $x) {
            $n = $i + 1;
            $imax = (float) $x['row']['attrs']['imax'];
            $rating = $ref->breakerRating($imax * $currentFactor);
            $poles = $x['in']['breaker_poles'] !== '' ? $x['in']['breaker_poles'] : ($phase === 1 ? '1P' : '3P');
            $ka = (float) $x['in']['breaker_ka'];
            $series = $x['in']['breaker_series'];
            if (!in_array($poles, $phasePoles, true)) {
                $checks[] = ['level' => 'error', 'text' => "Breaker inverter {$n}: kutub {$poles} tidak tersedia untuk sistem {$phase} fasa (pilihan: " . implode(', ', $phasePoles) . ').'];
            }
            if (!$inList($ka, $phaseKa)) {
                $checks[] = ['level' => 'warn', 'text' => "Breaker inverter {$n}: kapasitas pemutus " . self::fmt($ka) . ' kA di luar pilihan Excel (' . implode(', ', $phaseKa) . ').'];
            }
            if ($series !== '' && !in_array($series, $phaseSeries, true)) {
                $checks[] = ['level' => 'error', 'text' => "Breaker inverter {$n}: seri {$series} tidak tersedia untuk sistem {$phase} fasa."];
            }
            if ($rating === null) {
                $checks[] = ['level' => 'error', 'text' => "Breaker inverter {$n}: arus " . self::fmt($imax * $currentFactor) . ' A melebihi rating breaker terbesar.'];
                continue;
            }
            if ($phase === 1 && $rating > 125) {
                $checks[] = ['level' => 'error', 'text' => "Breaker inverter {$n}: rating " . self::fmt($rating) . 'A melebihi batas breaker 1 fasa (125A) — gunakan inverter 3 fasa.'];
            }
            $cands = $series !== '' ? [$series] : $phaseSeries;
            $pick = $ref->pickBreaker($poles, $rating, $ka, $cands);
            if ($pick === null) {
                $checks[] = ['level' => 'warn', 'text' => "Breaker inverter {$n}: produk {$poles} " . self::fmt($rating) . 'A ' . self::fmt($ka) . ' kA' . ($series !== '' ? " seri {$series}" : '') . ' tidak ada di katalog breaker — ubah seri/kutub/kA atau tambahkan di Data Referensi.'];
            } elseif ($pick['ka_adjusted']) {
                $checks[] = ['level' => 'warn', 'text' => "Breaker inverter {$n}: kA " . self::fmt($ka) . ' tidak ada persis di katalog, dipakai ' . self::fmt((float) $pick['row']['attrs']['ka']) . ' kA (kode ' . trim($pick['row']['code']) . ').'];
            }
            $invBk[$i] = ['rating' => $rating, 'poles' => $poles, 'ka' => $pick ? (float) $pick['row']['attrs']['ka'] : $ka, 'imax' => $imax,
                'row' => $pick['row'] ?? null, 'series' => $pick ? (string) $pick['row']['attrs']['series'] : ($series !== '' ? $series : ($cands[0] ?? ''))];
        }

        $mainAmps = $totalCurrent * $mainFactor;
        $mainRating = $mainAmps > 0 ? $ref->breakerRating($mainAmps) : null;
        $mainBk = null;
        if ($mainRating !== null) {
            $tk = $ref->transformerKa($in['transformer_kva']);
            if (!$tk['exact']) {
                $checks[] = ['level' => 'warn', 'text' => 'Kapasitas trafo ' . self::fmt($in['transformer_kva']) . ' kVA tidak ada di tabel; dipakai tier ' . self::fmt($tk['kva']) . ' kVA (' . self::fmt($tk['ka']) . ' kA).'];
            }
            $mb = $in['main_breaker'];
            $poles = $mb['poles'] !== '' ? $mb['poles'] : ($phase === 1 ? '2P' : '4P');
            if (!in_array($poles, $phasePoles, true)) {
                $checks[] = ['level' => 'error', 'text' => "Breaker utama: kutub {$poles} tidak tersedia untuk sistem {$phase} fasa (pilihan: " . implode(', ', $phasePoles) . ').'];
            }
            if ($mb['series'] !== '' && !in_array($mb['series'], $phaseSeries, true)) {
                $checks[] = ['level' => 'error', 'text' => 'Breaker utama: seri ' . $mb['series'] . " tidak tersedia untuk sistem {$phase} fasa."];
            }
            if ($phase === 1 && $mainRating > 125) {
                $checks[] = ['level' => 'error', 'text' => 'Breaker utama ' . self::fmt($mainRating) . 'A melebihi batas breaker 1 fasa (125A; Excel: "1P >125A") — pertimbangkan sistem 3 fasa.'];
            }
            $cands = $mb['series'] !== '' ? [$mb['series']] : array_values(array_unique(array_merge(['C120N'], $phaseSeries))); // Excel D13 default C120N
            $pick = $ref->pickBreaker($poles, $mainRating, $tk['ka'], $cands);
            if ($pick === null) {
                $checks[] = ['level' => 'warn', 'text' => 'Breaker utama ' . $poles . ' ' . self::fmt($mainRating) . 'A ' . self::fmt($tk['ka']) . ' kA tidak ada di katalog breaker (Excel: #N/A) — pilih seri/kutub lain atau tambahkan di Data Referensi.'];
            } elseif ($pick['ka_adjusted']) {
                $checks[] = ['level' => 'warn', 'text' => 'Breaker utama: kA ' . self::fmt($tk['ka']) . ' tidak ada persis di katalog, dipakai ' . self::fmt((float) $pick['row']['attrs']['ka']) . ' kA.'];
            }
            $mainBk = ['rating' => $mainRating, 'poles' => $poles, 'ka' => $pick ? (float) $pick['row']['attrs']['ka'] : $tk['ka'], 'row' => $pick['row'] ?? null,
                'series' => $pick ? (string) $pick['row']['attrs']['series'] : ($cands[0] ?? '')];
        }

        // ---- 1. Main Material
        $S = 'Main Material';
        $add($S, 'Modul Solar', $in['module']['model'], (string) ($mod['attrs']['brand'] ?? ''), $in['module']['qty'], 'pcs', $wp > 0 ? 'Daya per modul ' . self::fmt($wp) . ' Wp' : '');
        foreach ($inverters as $i => $x) {
            $add($S, 'Inverter ' . ($i + 1), $x['in']['model'], (string) $x['row']['attrs']['brand'], $x['in']['qty'], 'pcs',
                self::fmt((float) $x['row']['attrs']['power_w'] / 1000) . ' kW, ' . ((int) ($x['row']['attrs']['phase'] ?? 1)) . ' fasa');
        }

        // ---- 2. Monitoring
        $S = 'Accessories Monitoring';
        $needsPanel = 0;
        $mon = $in['monitoring']['device'] !== '' ? $ref->get('monitoring', $in['monitoring']['device']) : null;
        if ($in['monitoring']['device'] !== '' && $mon === null) {
            $checks[] = ['level' => 'error', 'text' => 'Perangkat monitoring "' . $in['monitoring']['device'] . '" tidak ada di Data Referensi.'];
        }
        if ($mon !== null && $in['monitoring']['qty'] > 0) {
            $needsPanel = (int) ($mon['attrs']['needs_panel'] ?? 0);
            if ($brand !== '' && ($mon['attrs']['brand'] ?? '') !== $brand) {
                $checks[] = ['level' => 'warn', 'text' => 'Monitoring ' . $mon['label'] . ' bermerek ' . $mon['attrs']['brand'] . ', inverter ' . $brand . '.'];
            }
            $add($S, 'Monitoring', $mon['label'], (string) $mon['attrs']['brand'], $in['monitoring']['qty'], 'pcs');
            foreach ($in['monitoring']['meteo'] as $k => $m) {
                if ($m['code'] === '' || $m['qty'] <= 0) {
                    continue;
                }
                $meteo = $ref->get('meteo', $m['code']);
                $name = $meteo['label'] ?? $m['code'];
                if (!in_array($name, $mon['attrs']['compatible_meteo'] ?? [], true)) {
                    $checks[] = ['level' => 'error', 'text' => 'Meteo Station ' . ($k + 1) . ' "' . $name . '" TIDAK KOMPATIBEL dengan ' . $mon['label'] . ' (tidak dimasukkan ke BoQ).'];
                    continue;
                }
                $add($S, 'Meteo Station ' . ($k + 1), $name, (string) ($meteo['attrs']['brand'] ?? ''), $m['qty'], 'pcs');
            }
            if ($needsPanel) {
                $add($S, 'Panel Komunikasi', 'Panel komunikasi untuk ' . $mon['label'], '', 1, 'pcs', 'Perangkat ini membutuhkan panel (Excel: INVERTER!AX).');
                $add($S, 'Panel Sensor', 'Panel sensor untuk ' . $mon['label'], '', 1, 'pcs');
            }
        }
        if ($in['monitoring']['zero_export'] > 0) {
            $add($S, 'Zero Export', 'Limiter / zero export', '', $in['monitoring']['zero_export'], 'ls');
        }

        // ---- 3. Kabel AC per inverter + interconnection
        $S = 'Cable';
        $mult = fn (string $core): int => $core === '1C' ? ($phase === 1 ? 3 : 4) : 1;
        $coreCount = fn (string $core): int => $core === '1C' ? 4 : (int) rtrim($core, 'C');
        $skunRows = [];

        foreach ($inverters as $i => $x) {
            $n = $i + 1;
            $core = $x['in']['core'];
            $amps = (float) $x['row']['attrs']['imax'];
            $req = $amps * $ampFactor;
            $pick = $ref->pickCable($core, $req);
            if ($pick === null) {
                $checks[] = ['level' => 'error', 'text' => "Kabel AC inverter {$n}: kebutuhan " . self::fmt($req) . ' A melebihi ukuran terbesar di tabel kabel ' . $core . '.'];
                continue;
            }
            $size = (float) $pick['attrs']['size'];
            $vd = $amps * (float) $pick['attrs']['r70'] * ($x['in']['length'] / 1000);
            $vdPct = $vd / $vNom * 100;
            $okVd = $vdPct < $vdLimit;
            $cables[] = ['label' => "Kabel AC Inverter {$n}", 'length' => $x['in']['length'], 'current' => $amps, 'required_a' => $req, 'core' => $core,
                'size' => $size, 'pe_size' => $pick['attrs']['pe_size'] ?? null, 'ampacity' => $pick['attrs']['i_air'], 'vd_v' => $vd, 'vd_pct' => $vdPct, 'ok' => $okVd];
            if (!$okVd) {
                $checks[] = ['level' => 'error', 'text' => sprintf('Kabel AC inverter %d: drop tegangan %.2f%% melebihi batas %s%% — perbesar ukuran kabel atau pendekkan jarak.', $n, $vdPct, self::fmt($vdLimit))];
            }
            $q = $x['in']['qty'];
            $add($S, "Cable AC — Inverter {$n} Side", 'NYY ' . $q . 'Rx' . $core . 'x' . self::size($size) . 'mm²', 'Supreme', $x['in']['length'] * $mult($core) * $q, 'm',
                'Jarak ' . self::fmt($x['in']['length']) . ' m x ' . $mult($core) . ' kawat x ' . $q . ' inverter');
            $peSize = (float) ($pick['attrs']['pe_size'] ?? $size);
            if ($core !== '3C') { // 3C sudah termasuk PE
                $add($S, "Cable Grounding AC — Inverter {$n} Side", 'NYA ' . $q . 'Rx1Cx' . self::size($peSize) . 'mm²', 'Supreme', $x['in']['length'] * $q, 'm');
            }
            $skunRows[] = ['label' => "Inverter {$n}", 'count_core' => $coreCount($core), 'qty' => $q, 'size' => $size, 'pe_size' => $peSize,
                'skun_ac' => $x['in']['skun_ac'], 'skun_pe' => $x['in']['skun_pe'], 'has_pe' => $core !== '3C', 'series' => $invBk[$i]['series'] ?? ''];
        }

        // Interconnection
        $icIn = $in['interconnection'];
        if ($totalCurrent > 0 && $icIn['length'] > 0) {
            $runs = $icIn['runs'];
            $core = $icIn['core'];
            $req = $totalCurrent * $ampFactor / $runs;
            $pick = $ref->pickCable($core, $req);
            if ($pick === null) {
                $checks[] = ['level' => 'error', 'text' => 'Kabel interconnection: kebutuhan ' . self::fmt($req) . ' A per run melebihi ukuran terbesar di tabel kabel ' . $core . '.'];
            } else {
                $size = (float) $pick['attrs']['size'];
                // Excel (Data Cabling L34): arus TOTAL x R x jarak — tidak dibagi jumlah run.
                $vd = $totalCurrent * (float) $pick['attrs']['r70'] * ($icIn['length'] / 1000);
                $vdPct = $vd / $vNom * 100;
                $okVd = $vdPct < $vdLimit;
                $cables[] = ['label' => 'Kabel AC Interconnection', 'length' => $icIn['length'], 'current' => $totalCurrent, 'required_a' => $req, 'core' => $core,
                    'size' => $size, 'pe_size' => $pick['attrs']['pe_size'] ?? null, 'ampacity' => $pick['attrs']['i_air'], 'vd_v' => $vd, 'vd_pct' => $vdPct, 'ok' => $okVd];
                if (!$okVd) {
                    $checks[] = ['level' => 'error', 'text' => sprintf('Kabel interconnection: drop tegangan %.2f%% melebihi batas %s%%.', $vdPct, self::fmt($vdLimit))];
                }
                $add($S, 'Cable AC — Interconnection Side', 'NYY ' . $runs . 'Rx' . $core . 'x' . self::size($size) . 'mm²', 'Supreme', $icIn['length'] * $mult($core) * $runs, 'm',
                    'Jarak ' . self::fmt($icIn['length']) . ' m x ' . $mult($core) . ' kawat x ' . $runs . ' run');
                $peSize = (float) ($pick['attrs']['pe_size'] ?? $size);
                if ($core !== '3C') {
                    $add($S, 'Cable Grounding AC — Interconnection Side', 'NYA ' . $runs . 'Rx1Cx' . self::size($peSize) . 'mm²', 'Supreme', $icIn['length'] * $runs, 'm');
                }
                $skunRows[] = ['label' => 'Interconnection', 'count_core' => $coreCount($core), 'qty' => $runs, 'size' => $size, 'pe_size' => $peSize,
                    'skun_ac' => $icIn['skun_ac'], 'skun_pe' => $icIn['skun_pe'], 'has_pe' => $core !== '3C', 'series' => $mainBk['series'] ?? ''];
            }
        } elseif ($inverters !== []) {
            $checks[] = ['level' => 'warn', 'text' => 'Jarak kabel interconnection belum diisi, kabel sisi interconnection tidak dihitung.'];
        }

        // Komunikasi
        if ($in['comm']['cat6_m'] > 0) {
            $add($S, 'Cable Comunication', 'Cat 6 RJ45', 'Belden', $in['comm']['cat6_m'], 'm');
        }
        if ($in['comm']['rs485_m'] > 0) {
            $add($S, 'Cable Comunication', 'RS 485 AWG18 1Pair Shileded 9841A', 'Belden', $in['comm']['rs485_m'], 'm');
        }
        if ($inverters !== []) {
            $invQty = array_sum(array_map(fn ($x) => $x['in']['qty'], $inverters));
            $est = $invQty * $ref->param('socket_per_inverter', 50000) + $in['monitoring']['qty'] * $ref->param('socket_per_monitoring', 35000);
            $add($S, 'Socket', 'Socket RJ45, Accessories Communication Cable', '', 1, 'ls', 'Estimasi Excel: Rp ' . number_format($est, 0, ',', '.'));
        }

        // Kabel DC
        $dcCfg = $in['dc'];
        $dcCableRows = [];
        foreach ($dcCfg['buildings'] as $b) {
            if ($b['strings'] <= 0) {
                continue;
            }
            // Cable DC!X17: (50% terjauh + edge + home run) x jumlah string x 2 (kutub +/-).
            $oneWay = $b['roof_far_m'] * $dcCfg['far_factor'] + $b['edge_m'] + $b['home_run_m'];
            $total = $oneWay * $b['strings'] * 2;
            $withSpare = $total * (1 + $dcCfg['spare_percent'] / 100);
            if ($withSpare > 0) {
                $add($S, 'Cable DC PV — ' . $b['name'], $b['size'], '', round($withSpare, 2), 'm',
                    $b['strings'] . ' string, spare ' . self::fmt($dcCfg['spare_percent']) . '%');
            }
            $dcCableRows[] = ['name' => $b['name'], 'strings' => $b['strings'], 'one_way_m' => $oneWay, 'total_m' => $withSpare, 'size' => $b['size']];
            if ($b['grounding_m'] > 0) {
                $add($S, 'Cable Grounding DC — ' . $b['name'], 'NYA 1x6 mm²', '', round($b['grounding_m'] * (1 + $dcCfg['grounding_spare_percent'] / 100), 2), 'm');
            }
            if ($b['rod_m'] > 0) {
                $add($S, 'Grounding Roof to Rod — ' . $b['name'], 'NYA 1x25 mm²', '', round($b['rod_m'] * (1 + $dcCfg['rod_spare_percent'] / 100), 2), 'm');
            }
        }
        // Drop tegangan DC (dihitung benar: arus string x resistansi kabel / tegangan string).
        if ($mod !== null && $dcCfg['modules_in_series'] > 0) {
            foreach ($dcCableRows as $r) {
                $cab = $ref->get('cable_dc', $r['size']);
                if ($cab === null || $r['strings'] <= 0) {
                    continue;
                }
                $rOhmKm = (float) $cab['attrs']['r20'] * (1 + 0.004041 * (50 - 20)); // R @50 °C (Cable DC!F15)
                $lenRoundTrip = $r['one_way_m'] * 2 * (1 + $dcCfg['spare_percent'] / 100);
                $vd = (float) ($mod['attrs']['impp'] ?? 0) * $rOhmKm * $lenRoundTrip / 1000;
                $vString = (float) ($mod['attrs']['vmpp'] ?? 0) * $dcCfg['modules_in_series'];
                $pct = $vString > 0 ? $vd / $vString * 100 : 0;
                $cables[] = ['label' => 'Kabel DC ' . $r['name'], 'length' => $r['one_way_m'], 'current' => (float) ($mod['attrs']['impp'] ?? 0), 'required_a' => null,
                    'core' => '1C', 'size' => (float) $cab['attrs']['size'], 'pe_size' => null, 'ampacity' => $cab['attrs']['current_a'], 'vd_v' => $vd, 'vd_pct' => $pct, 'ok' => $pct < $vdLimit];
                if ($pct >= $vdLimit) {
                    $checks[] = ['level' => 'error', 'text' => sprintf('Kabel DC %s: drop tegangan %.2f%% melebihi batas %s%%.', $r['name'], $pct, self::fmt($vdLimit))];
                }
            }
        }

        // ---- 4. Skun & Vinyl
        // Tipe skun yang boleh mengikuti seri breaker di ujung kabel (Skun & Vinyl sheet O26:P81, dropdown C53:C66):
        // breaker iC60/C120N => Ferrules / Plate Terminal / Type Y / Insulated Pin; breaker MCCB/ACB => DTG / CE / SC / Type Y.
        $S = 'Skun & Vinyl';
        foreach ($skunRows as $r) {
            $allowed = $r['series'] !== '' ? $ref->skunTypesForSeries($r['series']) : [];
            $items = [['AC', $r['skun_ac'], $r['size'], (int) ceil(round($r['qty'] * $r['count_core'] * 2 * (1 + $spare), 9))]];
            if ($r['has_pe']) {
                $items[] = ['PE', $r['skun_pe'], $r['pe_size'], (int) ceil(round($r['qty'] * 2 * (1 + $spare), 9))];
            }
            foreach ($items as [$kind, $type, $size, $count]) {
                $pool = array_values(array_unique(array_merge($allowed, $ref->skunTypes())));
                if ($type === '') { // otomatis: tipe pertama yang diizinkan breaker dan tersedia untuk ukuran ini
                    foreach ($pool as $t) {
                        if ($ref->skunCode($t, $size) !== null) {
                            $type = $t;
                            break;
                        }
                    }
                    $type = $type !== '' ? $type : ($pool[0] ?? '');
                } elseif ($allowed !== [] && !in_array($type, $allowed, true)) {
                    $checks[] = ['level' => 'warn', 'text' => sprintf('Skun %s %s: tipe "%s" tidak sesuai breaker %s di ujung kabel (boleh: %s).', $r['label'], $kind, $type, $r['series'], implode(', ', $allowed))];
                }
                $code = $ref->skunCode($type, $size);
                if ($code === null) {
                    // Tipe pilihan tidak punya ukuran ini: pakai tipe pertama yang tersedia (Excel hanya menulis "Tidak Tersedia").
                    foreach ($pool as $alt) {
                        if ($alt !== $type && ($altCode = $ref->skunCode($alt, $size)) !== null) {
                            $checks[] = ['level' => 'warn', 'text' => sprintf('Skun %s %s: tipe "%s" tidak tersedia untuk kabel %s mm², otomatis diganti "%s". Ubah tipe di form bila perlu.', $r['label'], $kind, $type, self::size($size), $alt)];
                            $type = $alt;
                            $code = $altCode;
                            break;
                        }
                    }
                }
                if ($code === null) {
                    $checks[] = ['level' => 'warn', 'text' => sprintf('Skun %s %s: tidak ada skun untuk kabel %s mm² di Data Referensi.', $r['label'], $kind, self::size($size))];
                }
                $add($S, ($kind === 'AC' ? 'Skun Cable AC — ' : 'Skun Cable Grounding — ') . $r['label'], $type . ' ' . ($code ?? 'Tidak Tersedia') . ' ' . self::size($size) . 'mm sq', '', $count, 'pcs');
                $vin = $ref->vinylCode($size);
                $add($S, ($kind === 'AC' ? 'Vinyl Insulation AC — ' : 'Vinyl Insulation Grounding — ') . $r['label'], 'Vinyl Insulation ' . ($vin ?? 'Tidak Tersedia'), '', $count, 'pcs');
            }
        }

        // ---- 5. Proteksi AC (breaker per inverter + breaker utama)
        $S = 'Proteksi AC (Breaker)';
        $bkSpec = function (array $b): string {
            $a = $b['row']['attrs'] ?? [];

            return ($a ? $a['kind'] . ' ' . $a['series'] . ' ' : '') . $b['poles'] . ' ' . self::fmt($b['rating']) . 'A ' . self::fmt($b['ka']) . 'kA'
                . ($b['row'] ? ' — ' . trim($b['row']['code']) : ' (kode belum ada di katalog)');
        };
        foreach ($inverters as $i => $x) {
            if (!isset($invBk[$i])) {
                continue;
            }
            $b = $invBk[$i];
            $add($S, 'Breaker Inverter ' . ($i + 1), $bkSpec($b), $b['row'] ? 'Schneider' : '', $x['in']['qty'], 'pcs',
                'Rating = rating standar >= ' . self::fmt($currentFactor) . ' x Imax (' . self::fmt($b['imax']) . ' A)');
        }
        if ($mainBk !== null) {
            $add($S, 'Breaker Main', $bkSpec($mainBk), $mainBk['row'] ? 'Schneider' : '', 1, 'pcs',
                'Arus total ' . self::fmt($totalCurrent) . ' A x ' . self::fmt($mainFactor) . ' = ' . self::fmt($mainAmps) . ' A');
        }

        // ---- 6. Jasa
        $jasa = null;
        if ($in['jasa']['include'] && $systemWp > 0) {
            $tier = $ref->jasaTier($systemWp / 1000);
            if ($tier !== null) {
                $t = $tier['attrs'];
                $eng = (float) $t['engineering_total'];
                $train = (float) $t['training_total'];
                $atkRound = $ref->param('jasa_atk_round', 1000000);
                $atk = $atkRound > 0 ? ceil(((float) $t['atk']) / $atkRound) * $atkRound : (float) $t['atk'];
                $contingency = ($eng + $train + $atk) * $ref->param('jasa_contingency', 0.05);
                $jasa = ['tier_kwp' => $t['kwp'], 'items' => [
                    ['item' => 'Engineering (Survey, Drawing, Cetak Blueprint A3/A2, O&M, Troubleshooting)', 'price' => $eng],
                    ['item' => 'Training & Commissioning (' . $t['training_man'] . ' MAN ' . $t['training_day'] . ' DAY)', 'price' => $train],
                    ['item' => 'ATK', 'price' => $atk],
                    ['item' => 'Contingency ' . self::fmt($ref->param('jasa_contingency', 0.05) * 100) . '%', 'price' => $contingency],
                ], 'total' => $eng + $train + $atk + $contingency];
                $S = 'Jasa';
                foreach ($jasa['items'] as $it) {
                    $add($S, $it['item'], 'Tier ' . self::fmt((float) $t['kwp']) . ' kWp', '', 1, 'ls', 'Tarif Excel: Rp ' . number_format($it['price'], 0, ',', '.'));
                }
            }
        }

        $no = 0;
        foreach ($lines as &$l) {
            $l['no'] = ++$no;
        }
        unset($l);

        return [
            'summary' => [
                'standard' => $in['standard'],
                'phase' => $phase,
                'brand' => $brand,
                'module_wp' => $wp,
                'module_qty' => $in['module']['qty'],
                'system_wp' => $systemWp,
                'system_kwp' => $systemWp / 1000,
                'inverter_w' => $inverterW,
                'dc_ac_ratio' => $dcAc,
                'dc_ac_status' => $dcAcStatus,
                'total_current_a' => $totalCurrent,
                'main_breaker_a' => $mainRating,
                'jasa_total' => $jasa['total'] ?? null,
            ],
            'checks' => $checks,
            'cables' => $cables,
            'lines' => $lines,
            'jasa' => $jasa,
            'not_covered' => self::notCovered(),
        ];
    }

    /** Bagian Excel yang belum dibawa ke sistem (tahap berikutnya). */
    public static function notCovered(): array
    {
        return [
            'Tray DC & AC (BoQ On Grid (Tray DC & AC), Cable Line Route)',
            'Conduit, cable gland, signage, pipa (bagian bawah BoQ PV-INVERTER-AC)',
            'Panel AC Combiner lengkap (busbar, CT, power meter, MCH, aksesori panel)',
            'ATS, COS, AC Combiner Hybrid, Limiter/Zero Export detail',
            'Water System, Mounting Dak, perhitungan shelter standing, Access Ladder, Cable Bridge, Cover Skylight',
        ];
    }

    // ------------------------------------------------------------------ format

    /** 2.5 => "2,5" (tulisan Excel/Indonesia pada spesifikasi kabel). */
    private static function size(float $v): string
    {
        return str_replace('.', ',', self::fmt($v));
    }

    private static function fmt(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

        return $s === '' ? '0' : $s;
    }
}
