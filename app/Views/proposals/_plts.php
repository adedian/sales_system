<?php
/** Kartu "Dokumen Penawaran PLTS (PDF)" — memakai generator HME lokal, tanpa API. Butuh $proposal, $canOperate. */
use App\Services\PltsProposalBuilder;

if (!PltsProposalBuilder::isAvailable()) {
    return;
}
$pf = PltsProposalBuilder::defaults($proposal);
$pltsSaved = PltsProposalBuilder::saved($proposal);
$pdfReady = is_file(PltsProposalBuilder::pdfPath($proposal));
$derived = $pltsSaved['derived'] ?? [];
$pltsWarnings = $pltsSaved['warnings'] ?? [];
$sheets = PltsProposalBuilder::datasheets();
$chosen = array_flip($pf['datasheets']);
$fnum = fn ($v) => e((string) $v);
$ro = $canOperate ? '' : 'disabled';
?>
<div class="card card-elevated mt-3" id="plts">
    <div class="card-header">
        <div>
            <h3>Dokumen Penawaran PLTS (PDF)</h3>
            <div class="sub">Dibuat otomatis oleh generator HME dari data di bawah — hemat listrik, payback, dan IRR dihitung sistem, tanpa AI.</div>
        </div>
        <?php if ($pdfReady): ?>
        <div class="d-flex gap-2">
            <a href="<?= url('/proposals/' . $proposal['id'] . '/plts-pdf') ?>" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-eye me-1"></i>Lihat PDF</a>
            <a href="<?= url('/proposals/' . $proposal['id'] . '/plts-pdf') ?>?download=1" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Unduh</a>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($pdfReady && !empty($pltsSaved['built_at'])): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="delta-chip"><i class="bi bi-check-lg"></i>PDF dibuat <?= e(format_datetime($pltsSaved['built_at'])) ?></span>
            <?php if (!empty($derived['payback_years'])): ?><span class="delta-chip flat">Payback <?= e(number_format($derived['payback_years'], 1, ',', '.')) ?> tahun</span><?php endif; ?>
            <?php if (!empty($derived['roi_pct'])): ?><span class="delta-chip flat">ROI <?= (int) $derived['roi_pct'] ?>%</span><?php endif; ?>
            <?php if (!empty($derived['peak_sun_hours'])): ?><span class="delta-chip flat">Iradiasi <?= e(number_format($derived['peak_sun_hours'], 2, ',', '.')) ?> kWh/m²/hari</span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($pltsWarnings): ?>
        <div class="notify-bar" style="align-items:flex-start">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div><strong>Perlu dicek:</strong>
                <ul class="mb-0 ps-3"><?php foreach ($pltsWarnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= url('/proposals/' . $proposal['id'] . '/plts-build') ?>" class="row g-3" id="pltsForm">
            <?= csrf_field() ?>

            <div class="col-12"><div class="detail-section-title mb-0">1. Dokumen &amp; klien</div></div>
            <div class="col-12 col-md-4"><label class="form-label">Nomor penawaran</label><input type="text" name="plts[number]" class="form-control" value="<?= $fnum($pf['number']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-2"><label class="form-label">Revisi</label><input type="text" name="plts[revision]" class="form-control" value="<?= $fnum($pf['revision']) ?>" <?= $ro ?>></div>
            <div class="col-12 col-md-6"><label class="form-label">Nama singkat klien</label><input type="text" name="plts[short_name]" class="form-control" value="<?= $fnum($pf['short_name']) ?>" <?= $ro ?>></div>
            <div class="col-12 col-md-5"><label class="form-label">Lokasi (tampil di dokumen)</label><input type="text" name="plts[location_id]" class="form-control" placeholder="mis. Gresik, Jawa Timur" value="<?= $fnum($pf['location_id']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Kota (untuk data iradiasi)</label><input type="text" name="plts[city]" class="form-control" placeholder="mis. Gresik" value="<?= $fnum($pf['city']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-2"><label class="form-label">Segmen</label>
                <select name="plts[segment]" class="form-select" <?= $ro ?>><?php foreach (['Industrial', 'Commercial', 'Residential'] as $s): ?><option <?= $pf['segment'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-6 col-md-2"><label class="form-label">Tipe sistem</label>
                <select name="plts[type]" class="form-select" <?= $ro ?>><?php foreach (['On-Grid', 'Hybrid', 'Off-Grid'] as $s): ?><option <?= $pf['type'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>

            <div class="col-12 mt-3"><div class="detail-section-title mb-0">2. Peralatan utama</div></div>
            <div class="col-6 col-md-2"><label class="form-label">Modul — jumlah</label><input type="number" min="1" name="plts[panel_qty]" class="form-control" value="<?= $fnum($pf['panel_qty']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-2"><label class="form-label">Modul — Wp</label><input type="number" min="1" name="plts[panel_wp]" class="form-control" value="<?= $fnum($pf['panel_wp']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Modul — merek</label><input type="text" name="plts[panel_brand]" class="form-control" placeholder="mis. Trina Solar" value="<?= $fnum($pf['panel_brand']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-5"><label class="form-label">Modul — model</label><input type="text" name="plts[panel_model]" class="form-control" placeholder="mis. TSM-NEG21C.20" value="<?= $fnum($pf['panel_model']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-2"><label class="form-label">Inverter — jumlah</label><input type="number" min="1" name="plts[inv_qty]" class="form-control" value="<?= $fnum($pf['inv_qty']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-2"><label class="form-label">Inverter — kVA/unit</label><input type="number" step="0.1" min="0" name="plts[inv_kva]" class="form-control" value="<?= $fnum($pf['inv_kva']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Inverter — merek</label><input type="text" name="plts[inv_brand]" class="form-control" placeholder="mis. SolaX" value="<?= $fnum($pf['inv_brand']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-5"><label class="form-label">Inverter — model</label><input type="text" name="plts[inv_model]" class="form-control" placeholder="mis. X3-FTH-125K" value="<?= $fnum($pf['inv_model']) ?>" <?= $ro ?>></div>
            <div class="col-12 col-md-4"><label class="form-label">Harga inverter (Rp, untuk biaya penggantian)</label><input type="number" min="0" name="plts[inv_price_rp]" class="form-control" value="<?= $fnum($pf['inv_price_rp']) ?>" <?= $ro ?>></div>
            <div class="col-12 col-md-4"><label class="form-label">Monitoring</label><input type="text" name="plts[monitoring]" class="form-control" placeholder="mis. Pocket WiFi 3.0" value="<?= $fnum($pf['monitoring']) ?>" <?= $ro ?>></div>
            <div class="col-12 col-md-4"><label class="form-label">Pemasangan</label>
                <select name="plts[mounting]" class="form-select" <?= $ro ?>><?php foreach (['Rooftop', 'Ground', 'Floating'] as $s): ?><option <?= $pf['mounting'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>

            <div class="col-12 mt-3"><div class="detail-section-title mb-0">3. Pemakaian listrik klien <span class="text-muted fw-normal small">(dasar perhitungan hemat)</span></div></div>
            <div class="col-6 col-md-3"><label class="form-label">Pemakaian (kWh/bulan)</label><input type="number" min="0" name="plts[monthly_kwh]" class="form-control" value="<?= $fnum($pf['monthly_kwh']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Tagihan (Rp/bulan)</label><input type="number" min="0" name="plts[monthly_bill_rp]" class="form-control" value="<?= $fnum($pf['monthly_bill_rp']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Tarif PLN efektif (Rp/kWh)</label><input type="number" step="0.01" min="0" name="plts[tariff_rp_kwh]" class="form-control" value="<?= $fnum($pf['tariff_rp_kwh']) ?>" <?= $ro ?>></div>
            <div class="col-6 col-md-3"><label class="form-label">Jam operasi</label>
                <div class="input-group"><input type="number" min="0" max="24" name="plts[work_start]" class="form-control" value="<?= $fnum($pf['work_start']) ?>" <?= $ro ?>><span class="input-group-text">–</span><input type="number" min="0" max="24" name="plts[work_end]" class="form-control" value="<?= $fnum($pf['work_end']) ?>" <?= $ro ?>></div></div>
            <div class="col-12 d-flex gap-4">
                <div class="form-check"><input class="form-check-input" type="checkbox" name="plts[saturday]" value="1" id="pltsSat" <?= $pf['saturday'] ? 'checked' : '' ?> <?= $ro ?>><label class="form-check-label" for="pltsSat">Beroperasi hari Sabtu</label></div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="plts[sunday]" value="1" id="pltsSun" <?= $pf['sunday'] ? 'checked' : '' ?> <?= $ro ?>><label class="form-check-label" for="pltsSun">Beroperasi hari Minggu</label></div>
            </div>

            <div class="col-12 mt-3"><div class="detail-section-title mb-0">4. Lampiran datasheet <span class="text-muted fw-normal small">(opsional)</span></div></div>
            <div class="col-12">
                <?php if (empty($sheets)): ?>
                    <p class="text-muted small mb-0">Belum ada datasheet di <code>tools/hme-proposal-plts/datasheet</code>.</p>
                <?php else: ?>
                <div class="checkbox-list" style="max-height:200px">
                    <?php foreach ($sheets as $brand => $files): ?>
                        <div class="permission-group-label mt-2"><?= e($brand) ?></div>
                        <?php foreach ($files as $rel => $label): ?>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="plts[datasheets][]" value="<?= e($rel) ?>" id="ds<?= md5($rel) ?>" <?= isset($chosen[$rel]) ? 'checked' : '' ?> <?= $ro ?>><label class="form-check-label" for="ds<?= md5($rel) ?>"><?= e($label) ?></label></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-12 d-flex flex-wrap align-items-center gap-3">
                <?php if ($canOperate): ?>
                <button type="submit" class="btn btn-primary" id="pltsBuildBtn"><i class="bi bi-file-earmark-pdf me-1"></i><?= $pdfReady ? 'Perbarui PDF' : 'Buat PDF Penawaran PLTS' ?></button>
                <span class="small text-muted">Harga dan BOQ diambil dari tabel <strong>Item Penawaran</strong> di bawah (harga sebelum PPN, setelah diskon). Proses ± 10–20 detik.</span>
                <?php else: ?>
                <span class="small text-muted"><i class="bi bi-lock"></i> Hanya pemilik proposal yang dapat membuat PDF.</span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<script>
    (function () {
        var form = document.getElementById('pltsForm');
        var btn = document.getElementById('pltsBuildBtn');
        if (!form || !btn) return;
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Membuat PDF…';
        });
    })();
</script>
<?php if ($pdfReady && ($_GET['plts_download'] ?? '') === '1'): ?>
<script>
    // PDF baru saja dibuat: unduh otomatis lewat iframe tersembunyi (halaman tidak berpindah), lalu bersihkan
    // parameter agar refresh tidak mengunduh ulang.
    (function () {
        var frame = document.createElement('iframe');
        frame.style.display = 'none';
        frame.src = <?= json_encode(url('/proposals/' . $proposal['id'] . '/plts-pdf') . '?download=1', JSON_UNESCAPED_SLASHES) ?>;
        document.body.appendChild(frame);
        if (window.history && history.replaceState) {
            history.replaceState(null, '', location.pathname + '#plts');
        }
    })();
</script>
<?php endif; ?>
