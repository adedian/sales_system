<?php
$isEdit = $lead !== null;
$val = fn (string $field, $default = '') => $isEdit ? ($lead[$field] ?? $default) : old($field, $default);
?>
<div class="page-header">
    <h2><?= $isEdit ? 'Ubah Lead' : 'Tambah Lead' ?></h2>
    <p class="text-muted"><?= $isEdit ? 'Perbarui data ' . e($lead['customer_name']) . ' (' . e($lead['lead_code']) . ').' : 'Catat lead baru yang masuk.' ?></p>
</div>

<form method="POST" action="<?= $isEdit ? url('/leads/' . $lead['id']) : url('/leads') ?>" novalidate>
    <?= csrf_field() ?>

    <div class="detail-section">
        <h3 class="detail-section-title">Informasi Customer</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="customer_name">Nama Customer</label>
                <input type="text" class="form-control" id="customer_name" name="customer_name" value="<?= e($val('customer_name')) ?>" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="company_name">Perusahaan</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?= e($val('company_name')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="phone">Telepon</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($val('phone')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e($val('email')) ?>">
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Klasifikasi Lead</h3>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="source_id">Sumber Lead</label>
                <select class="form-select" id="source_id" name="source_id">
                    <option value="">Pilih sumber</option>
                    <?php foreach ($sources as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('source_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="category_id">Kategori</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">Pilih kategori</option>
                    <?php foreach ($categories as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('category_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="need_type_id">Jenis Kebutuhan</label>
                <select class="form-select" id="need_type_id" name="need_type_id">
                    <option value="">Pilih jenis kebutuhan</option>
                    <?php foreach ($needTypes as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('need_type_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="type_id">Type</label>
                <select class="form-select" id="type_id" name="type_id">
                    <option value="">Pilih tipe</option>
                    <?php foreach ($leadTypes as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('type_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="system_id">System</label>
                <select class="form-select" id="system_id" name="system_id">
                    <option value="">Pilih sistem</option>
                    <?php foreach ($leadSystems as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('system_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="funding_id">Funding</label>
                <select class="form-select" id="funding_id" name="funding_id">
                    <option value="">Pilih funding</option>
                    <?php foreach ($fundingSources as $code => $row): ?>
                        <option value="<?= (int) $row['id'] ?>" <?= (string) $val('funding_id') === (string) $row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?php if ($isEdit): ?>
    <div class="detail-section">
        <h3 class="detail-section-title">Teknis &amp; Kebutuhan</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="estimated_value">Estimasi Nilai (Rp)</label>
                <input type="number" step="0.01" class="form-control" id="estimated_value" name="estimated_value" value="<?= e($val('estimated_value')) ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="size_kwp">Size (KWp)</label>
                <input type="number" step="0.01" class="form-control" id="size_kwp" name="size_kwp" value="<?= e($val('size_kwp')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="needs_description">Deskripsi Kebutuhan</label>
                <textarea class="form-control" id="needs_description" name="needs_description" rows="2"><?= e($val('needs_description')) ?></textarea>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="detail-section">
        <h3 class="detail-section-title">Data Awal (syarat Prelim)</h3>
        <p class="text-muted small mb-2">4 data ini wajib lengkap sebelum Prelim dapat dibuat: ID PLN, Tagihan Listrik, Model System (di atas), dan Lokasi (di bawah).</p>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="pln_id">ID PLN</label>
                <input type="text" class="form-control" id="pln_id" name="pln_id" value="<?= e($val('pln_id')) ?>" placeholder="Nomor ID Pelanggan PLN">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="electricity_bill">Tagihan Listrik (Rp/bulan)</label>
                <input type="number" step="0.01" min="0" class="form-control" id="electricity_bill" name="electricity_bill" value="<?= e($val('electricity_bill')) ?>">
            </div>
        </div>
    </div>

    <div class="detail-section" id="locationSection">
        <h3 class="detail-section-title">Instalasi</h3>
        <input type="hidden" name="latitude" id="loc_lat" value="<?= e($val('latitude')) ?>">
        <input type="hidden" name="longitude" id="loc_lng" value="<?= e($val('longitude')) ?>">
        <input type="hidden" name="maps_url" id="loc_url" value="<?= e($val('maps_url')) ?>">
        <?php
            $hasCoord = $val('latitude') !== '' && $val('longitude') !== '';
            $queryValue = $val('maps_url') !== '' ? $val('maps_url') : ($hasCoord ? $val('latitude') . ', ' . $val('longitude') : '');
        ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="loc_query">Koordinat / Link Google Maps</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="text" class="form-control" id="loc_query" value="<?= e($queryValue) ?>" placeholder="Tempel link Google Maps atau koordinat, mis. -7.2575, 112.7521" autocomplete="off">
                    <button type="button" class="btn btn-light" id="loc_btn">Cari Lokasi</button>
                </div>
                <div class="form-text">Di Google Maps: tekan lama titik lokasi lalu salin koordinatnya, atau pilih <em>Bagikan → Salin link</em>. Detail lokasi muncul otomatis.</div>
                <div class="small mt-2 d-none" id="loc_status"></div>
            </div>

            <div class="col-12 <?= $hasCoord ? '' : 'd-none' ?>" id="loc_card">
                <div class="loc-card">
                    <iframe id="loc_map" class="loc-map" loading="lazy" referrerpolicy="no-referrer" title="Peta lokasi"
                            src="<?= $hasCoord ? e('https://maps.google.com/maps?q=' . $val('latitude') . ',' . $val('longitude') . '&z=17&output=embed') : 'about:blank' ?>"></iframe>
                    <div class="loc-info">
                        <div class="loc-info-row d-none" id="loc_place_row"><span>Nama tempat di Google Maps</span><strong id="loc_place_txt">—</strong></div>
                        <div class="loc-info-row"><span>Kota / Kabupaten</span><strong id="loc_city_txt"><?= e($val('site_location') ?: '—') ?></strong></div>
                        <div class="loc-info-row"><span>Provinsi</span><strong id="loc_prov_txt">—</strong></div>
                        <div class="loc-info-row"><span>Koordinat</span><strong id="loc_coord_txt"><?= $hasCoord ? e($val('latitude') . ', ' . $val('longitude')) : '—' ?></strong></div>
                        <a href="<?= e($val('maps_url') ?: '#') ?>" target="_blank" rel="noopener" class="btn btn-light btn-sm mt-2" id="loc_open"><i class="bi bi-box-arrow-up-right me-1"></i>Buka di Google Maps</a>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <label class="form-label" for="site_location">Kota / Kabupaten</label>
                <input type="text" class="form-control" id="site_location" name="site_location" value="<?= e($val('site_location')) ?>" placeholder="Terisi otomatis dari titik peta">
            </div>
            <div class="col-12 col-md-7">
                <label class="form-label" for="address">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Ketik alamat lengkap (ruko, blok, jalan, kecamatan). Tidak diisi otomatis dari link Maps."><?= e($val('address')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Proses</h3>
        <div class="row g-3">
            <?php if ($showSalesField && count($salesUsers)): ?>
            <div class="col-12">
                <label class="form-label">Sales</label>
                <div class="checkbox-list">
                    <?php foreach ($salesUsers as $row): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="sales_<?= (int) $row['id'] ?>" name="sales_ids[]" value="<?= (int) $row['id'] ?>" <?= in_array((int) $row['id'], $assignedSalesIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sales_<?= (int) $row['id'] ?>"><?= e($row['name']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="text-muted">Bisa pilih lebih dari satu. Yang teratas dalam daftar (sesuai urutan di atas) jadi Sales utama.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-section">
        <h3 class="detail-section-title">Catatan</h3>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="notes">Catatan 1</label>
                <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($val('notes')) ?></textarea>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="note2">Catatan 2</label>
                <textarea class="form-control" id="note2" name="note2" rows="2"><?= e($val('note2')) ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Buat Lead' ?></button>
        <a href="<?= $isEdit ? url('/leads/' . $lead['id']) : url('/leads') ?>" class="btn btn-light">Batal</a>
    </div>
</form>

<script>
(function () {
    // Lokasi dua arah: (1) koordinat/link Maps -> kota & alamat; (2) alamat -> koordinat & link Maps.
    var base = (window.APP_BASE_URL || '').replace(/\/+$/, '');
    var q = document.getElementById('loc_query');
    var btn = document.getElementById('loc_btn');
    var status = document.getElementById('loc_status');
    var card = document.getElementById('loc_card');
    var address = document.getElementById('address');
    var city = document.getElementById('site_location');
    if (!q || !btn) return;

    var lastQuery = q.value.trim();       // nilai kolom Maps yang terakhir diproses
    var lastAddress = address.value.trim(); // nilai alamat yang terakhir diproses / diisi otomatis
    var lastCity = city.value.trim();
    if (address.value.trim()) address.dataset.touched = '1';
    // Pin dari link/koordinat yang ditempel pengguna dianggap tepat: mengetik alamat tidak boleh menimpanya.
    // Kosongkan kolom Maps bila ingin titik dicari ulang dari alamat.
    var pinFromLink = document.getElementById('loc_lat').value !== '';
    var seq = 0;                           // hanya hasil permintaan terbaru yang dipakai
    var qTimer = null, aTimer = null;

    function setStatus(html, cls) {
        status.className = 'small mt-2 ' + (cls || 'text-muted');
        status.innerHTML = html;
    }

    function getJson(params) {
        var url = base + '/api/leads/locate?' + params;
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); });
    }

    function show(loc, opts) {
        document.getElementById('loc_lat').value = loc.lat;
        document.getElementById('loc_lng').value = loc.lng;
        document.getElementById('loc_url').value = loc.maps_url;
        document.getElementById('loc_map').src = 'https://maps.google.com/maps?q=' + loc.lat + ',' + loc.lng + '&z=17&output=embed';
        document.getElementById('loc_city_txt').textContent = loc.city || city.value || '—';
        document.getElementById('loc_prov_txt').textContent = loc.province || '—';
        document.getElementById('loc_coord_txt').textContent = loc.lat + ', ' + loc.lng;
        document.getElementById('loc_open').href = loc.maps_url;
        card.classList.remove('d-none');
        if (opts.fillMaps) { q.value = loc.maps_url; lastQuery = loc.maps_url; }
        if (opts.fillCity && loc.city) { city.value = loc.city; lastCity = loc.city; }
        var place = document.getElementById('loc_place_row');
        document.getElementById('loc_place_txt').textContent = loc.place_name || '';
        place.classList.toggle('d-none', !loc.place_name);
        // alamat hanya diisi bila masih kosong / hasil isian otomatis — alamat yang diketik pengguna tidak ditimpa
        var keptAddress = false;
        if (opts.fillAddress && loc.address) {
            if (!address.value.trim() || address.dataset.touched !== '1') { address.value = loc.address; lastAddress = loc.address.trim(); }
            else { keptAddress = true; }
        }
        return keptAddress;
    }

    // Arah 1: koordinat / link Google Maps -> kota & alamat
    function fromMaps() {
        var value = q.value.trim();
        if (!value) { setStatus(''); return; }
        lastQuery = value;
        var my = ++seq;
        btn.disabled = true;
        setStatus('<span class="spinner-border spinner-border-sm me-2"></span>Mencari lokasi…');
        getJson('q=' + encodeURIComponent(value)).then(function (r) {
            if (my !== seq) return;
            if (!r.ok || !r.d.location) { setStatus('<i class="bi bi-exclamation-circle me-1"></i>' + ((r.d && r.d.error) || 'Lokasi tidak ditemukan.'), 'text-danger'); return; }
            var loc = r.d.location;
            // kota mengikuti titik; alamat diisi hanya bila kosong
            show(loc, { fillCity: true, fillAddress: false, fillMaps: false }); // alamat tidak pernah diisi dari link
            pinFromLink = true;
            var who = loc.place_name ? ' <strong>' + String(loc.place_name).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }) + '</strong>' : '';
            setStatus(loc.city
                ? '<i class="bi bi-check-circle-fill me-1"></i>Titik ditemukan' + who + ' — kota terisi dari peta. ' + (address.value.trim() ? '' : 'Isi alamat lengkap secara manual.')
                : '<i class="bi bi-info-circle me-1"></i>Koordinat valid, tetapi nama kota tidak ditemukan — isi kota/kabupaten secara manual.', loc.city ? 'text-success' : 'text-warning');
        }).catch(function () { if (my === seq) setStatus('<i class="bi bi-exclamation-circle me-1"></i>Tidak dapat menghubungi server.', 'text-danger'); })
          .finally(function () { if (my === seq) btn.disabled = false; });
    }

    // Arah 2: alamat -> koordinat & link Google Maps
    function fromAddress() {
        var value = address.value.trim();
        var cityValue = city.value.trim();
        if (value.length < 8 || (value === lastAddress && cityValue === lastCity)) return;
        if (pinFromLink) { lastAddress = value; lastCity = cityValue; return; } // pin dari link dipertahankan
        lastAddress = value; lastCity = cityValue;
        var my = ++seq;
        setStatus('<span class="spinner-border spinner-border-sm me-2"></span>Mencari titik dari alamat…');
        getJson('address=' + encodeURIComponent(value) + '&city=' + encodeURIComponent(cityValue)).then(function (r) {
            if (my !== seq) return;
            if (!r.ok || !r.d.location) { setStatus('<i class="bi bi-exclamation-circle me-1"></i>' + ((r.d && r.d.error) || 'Alamat tidak ditemukan.') + ' <span class="text-muted">Link Google Maps bisa ditempel manual di atas.</span>', 'text-warning'); return; }
            var loc = r.d.location;
            // teks alamat ketikan pengguna dipertahankan; kota hanya diisi bila masih kosong
            show(loc, { fillCity: !cityValue, fillAddress: false, fillMaps: true });
            pinFromLink = false;
            var matched = String(loc.matched || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
            var tail = ' <span class="text-muted">Cek pada peta; tempel link Google Maps bila perlu titik yang tepat.</span>';
            var msg = {
                exact: ['text-success', 'check-circle-fill', 'Titik ditemukan dari alamat — link Google Maps terisi otomatis.'],
                poi: ['text-warning', 'info-circle', 'Ditemukan kompleks/gedung yang sama (<em>' + matched + '</em>) — titik mendekati, belum tentu tepat di unit Anda.' + tail],
                street: ['text-warning', 'info-circle', 'Jalan ditemukan, tetapi nomor/unit tidak dikenal peta — titik berada di jalan tersebut (<em>' + matched + '</em>).' + tail],
                area: ['text-warning', 'info-circle', 'Hanya kawasan yang dikenali (<em>' + matched + '</em>) — titik masih perkiraan kasar.' + tail]
            }[loc.precision] || ['text-warning', 'info-circle', 'Titik perkiraan.' + tail];
            setStatus('<i class="bi bi-' + msg[1] + ' me-1"></i>' + msg[2], msg[0]);
        }).catch(function () { if (my === seq) setStatus('<i class="bi bi-exclamation-circle me-1"></i>Tidak dapat menghubungi server.', 'text-danger'); });
    }

    function clearPin() {
        ['loc_lat', 'loc_lng', 'loc_url'].forEach(function (id) { document.getElementById(id).value = ''; });
        card.classList.add('d-none');
        pinFromLink = false; lastQuery = ''; lastAddress = ''; // izinkan pencarian ulang dari alamat
        setStatus('');
    }
    q.addEventListener('input', function () { if (q.value.trim() === '' && document.getElementById('loc_lat').value !== '') { clearPin(); fromAddress(); } });
    btn.addEventListener('click', fromMaps);
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fromMaps(); } });
    q.addEventListener('paste', function () { clearTimeout(qTimer); qTimer = setTimeout(function () { if (q.value.trim() !== lastQuery) fromMaps(); }, 150); });
    q.addEventListener('blur', function () { if (q.value.trim() && q.value.trim() !== lastQuery) fromMaps(); });

    // alamat: cari setelah berhenti mengetik ±1,2 detik, atau saat kolom ditinggalkan
    function scheduleAddress() { clearTimeout(aTimer); aTimer = setTimeout(fromAddress, 1200); }
    address.addEventListener('input', function () { address.dataset.touched = '1'; scheduleAddress(); });
    address.addEventListener('blur', function () { clearTimeout(aTimer); fromAddress(); });
    city.addEventListener('change', function () { clearTimeout(aTimer); fromAddress(); });
})();
</script>
