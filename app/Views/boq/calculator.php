<?php
/** @var array $assignment @var array|null $saved @var array $inputs @var array|null $result @var bool $editable @var \App\Services\Boq\BoqReference $ref @var array $notCovered */
$pageTitle = 'Kalkulator BoQ';
$dis = $editable ? '' : 'disabled';
$fmt = function ($v, int $dec = 2): string {
    $s = rtrim(rtrim(number_format((float) $v, $dec, ',', '.'), '0'), ',');

    return $s === '' ? '0' : $s;
};
$n = fn ($v) => ($v === null || $v === '' || (float) $v === 0.0) ? '' : e((string) $v);
$brands = $ref->inverterBrands();
$inverterData = [];
foreach ($ref->all('inverter') as $r) {
    $inverterData[] = ['code' => $r['code'], 'brand' => $r['attrs']['brand'], 'phase' => $r['attrs']['phase'] ?? 1, 'kw' => ($r['attrs']['power_w'] ?? 0) / 1000, 'w' => (float) ($r['attrs']['power_w'] ?? 0)];
}
$monitoringData = array_map(fn ($r) => ['code' => $r['code'], 'label' => $r['label'], 'brand' => $r['attrs']['brand'] ?? ''], $ref->all('monitoring'));
$meteoData = array_map(fn ($r) => ['code' => $r['code'], 'label' => $r['label'], 'brand' => $r['attrs']['brand'] ?? ''], $ref->all('meteo'));
$skunTypes = $ref->skunTypes();
$dcacLimits = [
    'HME' => [$ref->param('dcac_hme_min', 1.1), $ref->param('dcac_hme_max', 1.35)],
    'DEVELOPER' => [$ref->param('dcac_dev_min', 1.2), $ref->param('dcac_dev_max', 1.3)],
];
$modulesByBrand = [];
foreach ($ref->all('pv_module') as $r) {
    $modulesByBrand[$r['attrs']['brand'] ?? '-'][] = $r;
}
$ruleData = [
    'cores' => [1 => $ref->coresFor(1), 3 => $ref->coresFor(3)],
    'poles' => [1 => $ref->polesFor(1), 3 => $ref->polesFor(3)],
    'series' => [1 => $ref->seriesFor(1), 3 => $ref->seriesFor(3)],
    'ka' => [1 => $ref->kaFor(1), 3 => $ref->kaFor(3)],
    'factors' => $ref->rule('breaker_factors'),
    'spare' => $ref->rule('spare_options'),
    'skunBySeries' => array_column(array_map(fn ($r) => ['k' => $r['code'], 'v' => $r['attrs']['types'] ?? []], $ref->all('skun_rule')), 'v', 'k'),
    'skunAll' => $skunTypes,
    'compat' => array_column(array_map(fn ($r) => ['k' => $r['code'], 'v' => $r['attrs']['compatible_meteo'] ?? []], $ref->all('monitoring')), 'v', 'k'),
];
$levelIcon = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-triangle-fill text-warning', 'error' => 'bi-x-octagon-fill text-danger'];
$sum = $result['summary'] ?? null;
$sections = [];
foreach (($result['lines'] ?? []) as $l) {
    $sections[$l['section']][] = $l;
}
?>
<div class="page-header page-header-row">
    <div>
        <div class="lead-code-eyebrow"><?= e($assignment['assignment_code']) ?></div>
        <h2>Kalkulator BoQ On-Grid</h2>
        <p class="text-muted"><?= e($assignment['customer_name']) ?> &middot; <?= e($assignment['company_name'] ?: 'Tanpa perusahaan') ?>. Rumus mengikuti Excel &ldquo;Detailed Calculation BoQ&rdquo;; hasil dikirim ke Procurement bersama tugas ini.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($result['lines'])): ?>
        <a href="<?= url('/engineer/' . $assignment['id'] . '/boq/export') ?>" class="btn btn-light"><i class="bi bi-filetype-csv me-1"></i>Unduh CSV</a>
        <?php endif; ?>
        <a href="<?= url('/engineer/' . $assignment['id']) ?>" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Kembali ke Tugas</a>
    </div>
</div>

<?php if (!$editable): ?>
<div class="alert alert-info"><i class="bi bi-lock me-1"></i>Mode lihat saja. BoQ hanya dapat diubah oleh penerima tugas (atau pengelola) saat tugas aktif dan sebelum dikirim ke Procurement.</div>
<?php endif; ?>

<form method="POST" action="<?= url('/engineer/' . $assignment['id'] . '/boq') ?>" id="boq-form" novalidate>
    <?= csrf_field() ?>
    <fieldset <?= $dis ?>>

    <div class="card card-elevated">
        <div class="card-header"><h3>1. Sistem PV</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="b_module_model">Modul surya</label>
                    <select class="form-select" id="b_module_model" name="boq[module][model]">
                        <option value="">- Pilih modul -</option>
                        <?php foreach ($modulesByBrand as $brand => $mods): ?>
                        <optgroup label="<?= e($brand) ?>">
                            <?php foreach ($mods as $m): ?>
                            <option value="<?= e($m['code']) ?>" data-wp="<?= (int) ($m['attrs']['wp'] ?? 0) ?>" <?= $inputs['module']['model'] === $m['code'] ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_module_qty">Jumlah modul (pcs)</label>
                    <input type="number" min="0" class="form-control" id="b_module_qty" name="boq[module][qty]" value="<?= $n($inputs['module']['qty']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_cap_pv">Kapasitas PV total</label>
                    <input type="text" class="form-control boq-auto" id="b_cap_pv" readonly tabindex="-1" value="-">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_cap_inv">Kapasitas inverter total</label>
                    <input type="text" class="form-control boq-auto" id="b_cap_inv" readonly tabindex="-1" value="-">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_dcac">DC/AC rasio</label>
                    <input type="text" class="form-control boq-auto" id="b_dcac" readonly tabindex="-1" value="-">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="b_standard">Standar DC/AC</label>
                    <select class="form-select" id="b_standard" name="boq[standard]">
                        <option value="HME" <?= $inputs['standard'] === 'HME' ? 'selected' : '' ?>>HME (<?= e(number_format($dcacLimits['HME'][0], 2, ',', '')) ?> – <?= e(number_format($dcacLimits['HME'][1], 2, ',', '')) ?>)</option>
                        <option value="DEVELOPER" <?= $inputs['standard'] === 'DEVELOPER' ? 'selected' : '' ?>>Developer (<?= e(number_format($dcacLimits['DEVELOPER'][0], 2, ',', '')) ?> – <?= e(number_format($dcacLimits['DEVELOPER'][1], 2, ',', '')) ?>)</option>
                    </select>
                </div>
                <div class="col-12 col-md-8 d-flex align-items-end">
                    <span id="b_dcac_status" class="small text-muted pb-2">Isi jumlah modul dan inverter, kapasitas dan DC/AC dihitung otomatis.</span>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">Daya per modul diambil dari tipe modul yang dipilih. Kapasitas PV total = jumlah modul x daya modul; DC/AC = kapasitas PV / kapasitas inverter total.</p>
        </div>
    </div>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>2. Inverter &amp; Kabel AC</h3></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="b_brand">Merek inverter</label>
                    <select class="form-select" id="b_brand" name="boq[inverter_brand]">
                        <?php foreach ($brands as $b): ?><option value="<?= e($b) ?>" <?= $inputs['inverter_brand'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0 boq-input-table">
                    <thead><tr><th>#</th><th style="min-width:200px">Model inverter</th><th>Jumlah</th><th>Jarak kabel (m)</th><th>Inti</th><th>Skun AC</th><th>Skun PE</th></tr></thead>
                    <tbody>
                    <?php foreach ($inputs['inverters'] as $i => $inv): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td><select class="form-select form-select-sm js-inverter-model" name="boq[inverters][<?= $i ?>][model]" data-selected="<?= e($inv['model']) ?>"></select></td>
                        <td><input type="number" min="0" class="form-control form-control-sm" style="width:80px" name="boq[inverters][<?= $i ?>][qty]" value="<?= $n($inv['qty']) ?>"></td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[inverters][<?= $i ?>][length]" value="<?= $n($inv['length']) ?>"></td>
                        <td><select class="form-select form-select-sm js-core" style="width:80px" name="boq[inverters][<?= $i ?>][core]" data-selected="<?= e($inv['core']) ?>"></select></td>
                        <td><select class="form-select form-select-sm js-skun" style="min-width:150px" data-series="inv<?= $i ?>" name="boq[inverters][<?= $i ?>][skun_ac]" data-selected="<?= e($inv['skun_ac']) ?>"></select></td>
                        <td><select class="form-select form-select-sm js-skun" style="min-width:150px" data-series="inv<?= $i ?>" name="boq[inverters][<?= $i ?>][skun_pe]" data-selected="<?= e($inv['skun_pe']) ?>"></select></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h4 class="boq-subhead">Sisi interconnection (inverter &rarr; panel / titik sambung)</h4>
            <div class="row g-3">
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_ic_length">Jarak kabel (m)</label>
                    <input type="number" min="0" step="any" class="form-control" id="b_ic_length" name="boq[interconnection][length]" value="<?= $n($inputs['interconnection']['length']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_ic_runs">Jumlah run</label>
                    <input type="number" min="1" class="form-control" id="b_ic_runs" name="boq[interconnection][runs]" value="<?= e($inputs['interconnection']['runs']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_ic_core">Inti kabel</label>
                    <select class="form-select js-core" id="b_ic_core" name="boq[interconnection][core]" data-selected="<?= e($inputs['interconnection']['core']) ?>"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_ic_skun_ac">Skun AC</label>
                    <select class="form-select js-skun" data-series="main" id="b_ic_skun_ac" name="boq[interconnection][skun_ac]" data-selected="<?= e($inputs['interconnection']['skun_ac']) ?>"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_ic_skun_pe">Skun PE</label>
                    <select class="form-select js-skun" data-series="main" id="b_ic_skun_pe" name="boq[interconnection][skun_pe]" data-selected="<?= e($inputs['interconnection']['skun_pe']) ?>"></select>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">Ukuran kabel dipilih otomatis: arus x 1,7, ukuran terkecil yang cukup, lalu drop tegangan dicek terhadap batas 3%. Pilihan inti mengikuti fasa (1 fasa: 1C/3C, 3 fasa: 1C/4C); tipe skun mengikuti seri breaker di ujung kabel.</p>
        </div>
    </div>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>3. Proteksi AC (Breaker)</h3></div>
        <div class="card-body">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_f_inv">Faktor arus breaker inverter</label>
                    <select class="form-select js-factor" id="b_f_inv" name="boq[breaker_factor_inverter]" data-selected="<?= e($inputs['breaker_factor_inverter']) ?>"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_f_main">Faktor arus breaker utama</label>
                    <select class="form-select js-factor" id="b_f_main" name="boq[breaker_factor_main]" data-selected="<?= e($inputs['breaker_factor_main']) ?>"></select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_trafo">Trafo PLN (kVA) &rarr; kA breaker utama</label>
                    <select class="form-select" id="b_trafo" name="boq[transformer_kva]">
                        <?php foreach ($ref->all('transformer_ka') as $t): ?>
                        <option value="<?= e($t['attrs']['kva']) ?>" <?= abs((float) $inputs['transformer_kva'] - (float) $t['attrs']['kva']) < 1e-9 ? 'selected' : '' ?>><?= e($t['attrs']['kva']) ?> kVA &rarr; <?= e($t['attrs']['ka']) ?> kA</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0 boq-input-table">
                    <thead><tr><th>Breaker</th><th>Seri</th><th>Kutub</th><th>Kapasitas pemutus (kA)</th></tr></thead>
                    <tbody>
                    <?php foreach ($inputs['inverters'] as $i => $inv): ?>
                    <tr>
                        <td>Inverter <?= $i + 1 ?></td>
                        <td><select class="form-select form-select-sm js-bk-series" style="min-width:190px" data-key="inv<?= $i ?>" name="boq[inverters][<?= $i ?>][breaker_series]" data-selected="<?= e($inv['breaker_series']) ?>"></select></td>
                        <td><select class="form-select form-select-sm js-bk-poles" style="min-width:130px" name="boq[inverters][<?= $i ?>][breaker_poles]" data-selected="<?= e($inv['breaker_poles']) ?>"></select></td>
                        <td><select class="form-select form-select-sm js-bk-ka" style="width:100px" name="boq[inverters][<?= $i ?>][breaker_ka]" data-selected="<?= e($inv['breaker_ka']) ?>"></select></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td>Utama (main)</td>
                        <td><select class="form-select form-select-sm js-bk-series" style="min-width:190px" data-key="main" id="b_mb_series" name="boq[main_breaker][series]" data-selected="<?= e($inputs['main_breaker']['series']) ?>"></select></td>
                        <td><select class="form-select form-select-sm js-bk-poles" style="min-width:130px" id="b_mb_poles" name="boq[main_breaker][poles]" data-selected="<?= e($inputs['main_breaker']['poles']) ?>"></select></td>
                        <td class="text-muted small">mengikuti trafo</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2 mb-0">Rating = rating standar &ge; faktor x arus. Kode produk dipilih dengan kecocokan persis seri + kutub + rating + kA (seperti Excel); bila kA persis tidak ada, dipakai kA terdekat di atasnya. &ldquo;Otomatis&rdquo; mencoba seri yang diizinkan fasa secara berurutan.</p>
        </div>
    </div>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>4. Monitoring &amp; Komunikasi</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="b_mon_device">Perangkat monitoring</label>
                    <select class="form-select" id="b_mon_device" name="boq[monitoring][device]" data-selected="<?= e($inputs['monitoring']['device']) ?>"></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_mon_qty">Jumlah</label>
                    <input type="number" min="0" class="form-control" id="b_mon_qty" name="boq[monitoring][qty]" value="<?= e($inputs['monitoring']['qty']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_zero">Zero export (ls)</label>
                    <input type="number" min="0" class="form-control" id="b_zero" name="boq[monitoring][zero_export]" value="<?= $n($inputs['monitoring']['zero_export']) ?>">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <?php foreach ($inputs['monitoring']['meteo'] as $i => $m): ?>
                <div class="col-12 col-md-6">
                    <label class="form-label">Meteo station <?= $i + 1 ?></label>
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm js-meteo" name="boq[monitoring][meteo][<?= $i ?>][code]" data-selected="<?= e($m['code']) ?>"></select>
                        <input type="number" min="0" class="form-control form-control-sm" style="width:80px" name="boq[monitoring][meteo][<?= $i ?>][qty]" value="<?= $n($m['qty']) ?>" placeholder="jml">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_cat6">Kabel Cat 6 RJ45 (m)</label>
                    <input type="number" min="0" step="any" class="form-control" id="b_cat6" name="boq[comm][cat6_m]" value="<?= $n($inputs['comm']['cat6_m']) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_rs485">Kabel RS485 (m)</label>
                    <input type="number" min="0" step="any" class="form-control" id="b_rs485" name="boq[comm][rs485_m]" value="<?= $n($inputs['comm']['rs485_m']) ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>5. Kabel DC &amp; Grounding</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-modern mb-0 boq-input-table">
                    <thead><tr><th>Gedung / atap</th><th>Jumlah string</th><th>Atap &rarr; terjauh (m)</th><th>Tepi &rarr; shelter (m)</th><th>Home run (m)</th><th>Ukuran kabel DC</th><th>Grounding DC (m)</th><th>Grounding ke rod (m)</th></tr></thead>
                    <tbody>
                    <?php foreach ($inputs['dc']['buildings'] as $i => $b): ?>
                    <tr>
                        <td><input type="text" class="form-control form-control-sm" style="min-width:120px" name="boq[dc][buildings][<?= $i ?>][name]" value="<?= e($b['name']) ?>"></td>
                        <td><input type="number" min="0" class="form-control form-control-sm" style="width:80px" name="boq[dc][buildings][<?= $i ?>][strings]" value="<?= $n($b['strings']) ?>"></td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[dc][buildings][<?= $i ?>][roof_far_m]" value="<?= $n($b['roof_far_m']) ?>"></td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[dc][buildings][<?= $i ?>][edge_m]" value="<?= $n($b['edge_m']) ?>"></td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[dc][buildings][<?= $i ?>][home_run_m]" value="<?= $n($b['home_run_m']) ?>"></td>
                        <td>
                            <select class="form-select form-select-sm" name="boq[dc][buildings][<?= $i ?>][size]">
                                <?php foreach ($ref->all('cable_dc') as $c): ?><option value="<?= e($c['code']) ?>" <?= $b['size'] === $c['code'] ? 'selected' : '' ?>><?= e($c['code']) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[dc][buildings][<?= $i ?>][grounding_m]" value="<?= $n($b['grounding_m']) ?>"></td>
                        <td><input type="number" min="0" step="any" class="form-control form-control-sm" style="width:100px" name="boq[dc][buildings][<?= $i ?>][rod_m]" value="<?= $n($b['rod_m']) ?>"></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_dc_far">Faktor jarak terjauh</label>
                    <input type="number" min="0" step="0.05" class="form-control" id="b_dc_far" name="boq[dc][far_factor]" value="<?= e($inputs['dc']['far_factor']) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_dc_mod">Modul per string</label>
                    <input type="number" min="0" class="form-control" id="b_dc_mod" name="boq[dc][modules_in_series]" value="<?= e($inputs['dc']['modules_in_series']) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="b_dc_spare">Spare kabel DC (%)</label>
                    <select class="form-select js-spare" id="b_dc_spare" name="boq[dc][spare_percent]" data-selected="<?= e($inputs['dc']['spare_percent']) ?>"></select>
                </div>
            </div>
            <p class="small text-muted mt-2 mb-0">Panjang kabel DC = (atap terjauh x faktor + tepi + home run) x jumlah string x 2 kutub, ditambah spare. Excel memakai faktor 0,5 walau judul kolomnya tertulis 60%.</p>
        </div>
    </div>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>6. Lainnya</h3></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-2">
                    <label class="form-label" for="b_spare">Spare skun &amp; vinyl (%)</label>
                    <select class="form-select js-spare" id="b_spare" name="boq[skun_spare_percent]" data-selected="<?= e($inputs['skun_spare_percent']) ?>"></select>
                </div>
                
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="hidden" name="boq[jasa][include]" value="0">
                        <input class="form-check-input" type="checkbox" id="b_jasa" name="boq[jasa][include]" value="1" <?= !empty($inputs['jasa']['include']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="b_jasa">Sertakan jasa (engineering, training, ATK)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </fieldset>

    <?php if ($editable): ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><i class="bi bi-calculator me-1"></i>Hitung &amp; Simpan BoQ</button>
        <?php if ($saved): ?><span class="text-muted small">Terakhir dihitung <?= e(format_datetime($saved['updated_at'])) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
</form>

<?php if ($result): ?>
<div id="hasil" class="mt-4">
    <h3 class="boq-section-title">Hasil Perhitungan</h3>

    <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr))">
        <?php
        $tiles = [
            ['bi-brightness-high', 'primary', 'Kapasitas PV', $fmt($sum['system_kwp']) . ' kWp'],
            ['bi-lightning-charge', 'indigo', 'Inverter total', $fmt($sum['inverter_w'] / 1000) . ' kW'],
            ['bi-percent', ($sum['dc_ac_status'] ?? '') === 'OK' ? 'emerald' : 'amber', 'DC/AC', $sum['dc_ac_ratio'] !== null ? $fmt($sum['dc_ac_ratio']) : '-'],
            ['bi-diagram-3', 'muted', 'Fasa / arus total', $sum['phase'] . ' fasa / ' . $fmt($sum['total_current_a']) . ' A'],
            ['bi-toggles', 'muted', 'Breaker utama', $sum['main_breaker_a'] ? $fmt($sum['main_breaker_a']) . ' A' : '-'],
        ];
        foreach ($tiles as [$icon, $color, $label, $value]): ?>
        <div class="stat-card stat-card-compact">
            <div class="stat-icon stat-icon-<?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
            <div class="stat-body"><span class="stat-label"><?= e($label) ?></span><span class="stat-value" style="font-size:19px"><?= e($value) ?></span></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($result['checks'])): ?>
    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>Pemeriksaan</h3></div>
        <div class="card-body">
            <ul class="list-unstyled mb-0">
                <?php foreach ($result['checks'] as $c): ?>
                <li class="mb-1"><i class="bi <?= $levelIcon[$c['level']] ?? $levelIcon['warn'] ?> me-2"></i><?= e($c['text']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($result['cables'])): ?>
    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>Perhitungan Kabel &amp; Drop Tegangan</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead><tr><th>Kabel</th><th class="text-end">Jarak (m)</th><th class="text-end">Arus (A)</th><th class="text-end">Perlu (A)</th><th>Inti</th><th class="text-end">Ukuran</th><th class="text-end">PE</th><th class="text-end">Drop (V)</th><th class="text-end">Drop (%)</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($result['cables'] as $c): ?>
                    <tr>
                        <td><?= e($c['label']) ?></td>
                        <td class="text-end"><?= e($fmt($c['length'])) ?></td>
                        <td class="text-end"><?= e($fmt($c['current'])) ?></td>
                        <td class="text-end"><?= $c['required_a'] !== null ? e($fmt($c['required_a'])) : '-' ?></td>
                        <td><?= e($c['core']) ?></td>
                        <td class="text-end"><?= e($fmt($c['size'])) ?> mm²</td>
                        <td class="text-end"><?= $c['pe_size'] !== null ? e($fmt($c['pe_size'])) . ' mm²' : '-' ?></td>
                        <td class="text-end"><?= e($fmt($c['vd_v'])) ?></td>
                        <td class="text-end"><?= e($fmt($c['vd_pct'])) ?>%</td>
                        <td><?= $c['ok'] ? '<span class="badge-pill badge-pill-emerald">OK</span>' : '<span class="badge-pill badge-pill-danger">Tidak lolos</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card card-elevated mt-3">
        <div class="card-header"><h3>Daftar BoQ (<?= count($result['lines']) ?> baris)</h3></div>
        <div class="card-body p-0">
            <?php if (empty($result['lines'])): ?>
                <div class="empty-state"><i class="bi bi-list-check"></i><p>Belum ada baris BoQ. Lengkapi modul, inverter, dan jarak kabel.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead><tr><th style="width:48px">No</th><th>Item</th><th>Spesifikasi</th><th>Merek</th><th class="text-end">Jumlah</th><th>Satuan</th><th>Catatan</th></tr></thead>
                    <tbody>
                    <?php foreach ($sections as $name => $lines): ?>
                    <tr class="boq-section-row"><td colspan="7"><?= e($name) ?></td></tr>
                    <?php foreach ($lines as $l): ?>
                    <tr>
                        <td class="text-muted"><?= (int) $l['no'] ?></td>
                        <td><?= e($l['item']) ?></td>
                        <td><?= e($l['spec']) ?></td>
                        <td class="text-muted"><?= e($l['brand']) ?></td>
                        <td class="text-end mono"><?= e($fmt($l['qty'])) ?></td>
                        <td><?= e($l['unit']) ?></td>
                        <td class="text-muted small"><?= e($l['note']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info mt-3">
        <strong>Belum termasuk di kalkulator ini</strong> (masih diisi manual oleh Procurement/Engineer, dikerjakan di tahap berikutnya):
        <ul class="mb-0 mt-1">
            <?php foreach ($notCovered as $t): ?><li><?= e($t) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<style>
    .boq-subhead { font-size: 14px; font-weight: 600; margin: 22px 0 10px; }
    .boq-section-title { font-size: 18px; font-weight: 600; margin-bottom: 12px; }
    .boq-section-row td { background: #eaf1f7; font-weight: 600; font-size: 13px; }
    .boq-input-table td, .boq-input-table th { vertical-align: middle; white-space: nowrap; }
    .boq-auto { background: #f4f7fa; font-weight: 600; }
    #boq-form fieldset:disabled .form-control, #boq-form fieldset:disabled .form-select { background: #f4f7fa; }
</style>

<script>
(function () {
    const inverters = <?= json_encode($inverterData, JSON_UNESCAPED_UNICODE) ?>;
    const monitoring = <?= json_encode($monitoringData, JSON_UNESCAPED_UNICODE) ?>;
    const meteo = <?= json_encode($meteoData, JSON_UNESCAPED_UNICODE) ?>;
    const rules = <?= json_encode($ruleData, JSON_UNESCAPED_UNICODE) ?>;
    const limits = <?= json_encode($dcacLimits) ?>;
    const brandSel = document.getElementById('b_brand');
    const monSel = document.getElementById('b_mon_device');
    const form = document.getElementById('boq-form');
    const qa = function (sel) { return Array.prototype.slice.call(document.querySelectorAll(sel)); };
    const fmt = function (v, d) { return v.toLocaleString('id-ID', { maximumFractionDigits: d }); };

    // Isi ulang <select> dari daftar [{value,label}], pertahankan pilihan (data-selected / nilai saat ini).
    function fill(select, items, emptyLabel) {
        const keep = select.value !== '' && select.options.length ? select.value : (select.dataset.selected || '');
        select.innerHTML = '';
        if (emptyLabel !== null) {
            const o = document.createElement('option');
            o.value = ''; o.textContent = emptyLabel; select.appendChild(o);
        }
        items.forEach(function (it) {
            const opt = document.createElement('option');
            opt.value = it.value; opt.textContent = it.label;
            if (String(it.value) === String(keep) && keep !== '') opt.selected = true;
            select.appendChild(opt);
        });
        if (keep !== '' && !items.some(function (it) { return String(it.value) === String(keep); })) select.value = '';
        select.dataset.selected = '';
    }
    const plain = function (arr) { return arr.map(function (v) { return { value: v, label: String(v) }; }); };

    // Fasa sistem = fasa inverter 1 (model terpilih pertama), sama seperti Excel BoQ!C8.
    function systemPhase() {
        const sel = qa('.js-inverter-model').find(function (s) { return s.value; });
        const inv = sel ? inverters.find(function (i) { return i.code === sel.value; }) : null;
        return inv ? (parseInt(inv.phase, 10) === 3 ? 3 : 1) : 1;
    }

    function refreshDependent() {
        const ph = systemPhase();
        qa('.js-core').forEach(function (s) { fill(s, plain(rules.cores[ph]), null); });
        qa('.js-bk-series').forEach(function (s) { fill(s, plain(rules.series[ph]), 'Otomatis'); });
        qa('.js-bk-poles').forEach(function (s) {
            fill(s, plain(rules.poles[ph]), 'Otomatis (' + (s.id === 'b_mb_poles' ? (ph === 1 ? '2P' : '4P') : (ph === 1 ? '1P' : '3P')) + ')');
        });
        qa('.js-bk-ka').forEach(function (s) { fill(s, plain(rules.ka[ph]), null); if (!s.value) s.value = '10'; });
        qa('.js-factor').forEach(function (s) { fill(s, plain(rules.factors), null); });
        qa('.js-spare').forEach(function (s) { fill(s, rules.spare.map(function (v) { return { value: v, label: v + '%' }; }), null); });
        refreshSkun();
    }

    // Tipe skun yang boleh = menurut seri breaker di ujung kabel (belum dipilih seri => semua tipe, server memilih otomatis).
    function refreshSkun() {
        qa('.js-skun').forEach(function (s) {
            const key = s.dataset.series;
            const ser = (document.querySelector('.js-bk-series[data-key="' + key + '"]') || {}).value || '';
            const allowed = ser && rules.skunBySeries[ser] ? rules.skunBySeries[ser] : rules.skunAll;
            fill(s, plain(allowed), 'Otomatis');
        });
    }

    function refreshMeteo() {
        const mon = monSel ? monSel.value : '';
        const compat = mon && rules.compat[mon] ? rules.compat[mon] : null;
        const brand = brandSel ? brandSel.value : '';
        qa('.js-meteo').forEach(function (s) {
            let list = meteo.filter(function (m) { return !brand || m.brand === brand; });
            if (compat) list = list.filter(function (m) { return compat.indexOf(m.label) !== -1; });
            else if (mon) list = [];
            fill(s, list.map(function (m) { return { value: m.code, label: m.label }; }), mon && !compat ? '- tidak ada yang kompatibel -' : '- tidak dipakai -');
        });
    }

    function refreshBrand() {
        const brand = brandSel ? brandSel.value : '';
        qa('.js-inverter-model').forEach(function (s) {
            fill(s, inverters.filter(function (i) { return !brand || i.brand === brand; }).map(function (i) {
                return { value: i.code, label: i.code + ' (' + (i.kw % 1 ? i.kw.toFixed(2) : i.kw) + ' kW, ' + i.phase + ' fasa)' };
            }), '- tidak dipakai -');
        });
        fill(monSel, monitoring.filter(function (m) { return !brand || m.brand === brand; }).map(function (m) { return { value: m.code, label: m.label }; }), '- tidak dipakai -');
    }

    // Kapasitas PV, kapasitas inverter, dan DC/AC dihitung langsung di halaman (rumus sama dengan BoqCalculator).
    function updateCapacity() {
        const modSel = document.getElementById('b_module_model');
        const wp = parseFloat((modSel.options[modSel.selectedIndex] || {}).dataset.wp || '0') || 0;
        const modQty = parseFloat(document.getElementById('b_module_qty').value) || 0;
        const pvW = modQty * wp;
        let invW = 0;
        qa('.js-inverter-model').forEach(function (sel) {
            const qty = parseFloat(sel.closest('tr').querySelector('input[name$="[qty]"]').value) || 0;
            const inv = inverters.find(function (i) { return i.code === sel.value; });
            if (inv) invW += qty * inv.w;
        });
        document.getElementById('b_cap_pv').value = pvW > 0 ? fmt(pvW / 1000, 2) + ' kWp' : '-';
        document.getElementById('b_cap_inv').value = invW > 0 ? fmt(invW / 1000, 2) + ' kW' : '-';
        const ratio = invW > 0 ? pvW / invW : null;
        document.getElementById('b_dcac').value = ratio !== null ? fmt(ratio, 2) : '-';
        const st = document.getElementById('b_dcac_status');
        if (ratio === null || pvW <= 0) {
            st.className = 'small text-muted pb-2';
            st.textContent = 'Isi jumlah modul dan inverter, kapasitas dan DC/AC dihitung otomatis.';
            return;
        }
        const lim = limits[document.getElementById('b_standard').value] || limits.HME;
        const bad = ratio <= lim[0] || ratio >= lim[1];
        st.className = 'small pb-2 ' + (bad ? 'text-danger' : 'text-success');
        st.textContent = bad
            ? 'NEED TO ADJUST — rasio harus di atas ' + fmt(lim[0], 2) + ' dan di bawah ' + fmt(lim[1], 2) + '.'
            : 'OK — rasio berada di rentang standar (' + fmt(lim[0], 2) + ' – ' + fmt(lim[1], 2) + ').';
    }

    refreshBrand();
    refreshDependent();
    refreshMeteo();
    updateCapacity();

    brandSel.addEventListener('change', function () {
        qa('.js-inverter-model, .js-meteo, #b_mon_device').forEach(function (s) { s.value = ''; s.dataset.selected = ''; });
        refreshBrand(); refreshDependent(); refreshMeteo(); updateCapacity();
    });
    form.addEventListener('change', function (e) {
        const t = e.target;
        if (t.classList.contains('js-inverter-model')) { refreshDependent(); }
        else if (t.classList.contains('js-bk-series')) { refreshSkun(); }
        else if (t === monSel) { refreshMeteo(); }
        updateCapacity();
    });
    form.addEventListener('input', updateCapacity);
})();
</script>
