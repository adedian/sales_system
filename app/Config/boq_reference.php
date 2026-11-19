<?php

/**
 * Definisi kategori Data Referensi BoQ (tabel boq_ref_items) untuk menu "Data Referensi BoQ".
 *
 * label/description : tampilan kartu & judul
 * list              : kolom attrs yang ditampilkan di tabel daftar
 * fields            : kolom attrs yang bisa diubah: key => [label, tipe, opsi]
 *                     tipe: text | number | select (opsi = [nilai => label]) | lines (satu nilai per baris, disimpan sebagai array)
 * source            : sheet Excel asal data (untuk keterangan)
 */
return [
    'pv_module' => [
        'label' => 'Modul Surya', 'icon' => 'bi-brightness-high', 'source' => 'PV Module',
        'description' => 'Tipe modul PV beserta data elektrik dan dimensi.',
        'list' => ['brand', 'wp', 'vmpp', 'impp', 'voc', 'isc'],
        'fields' => [
            'brand' => ['Merek', 'text'], 'wp' => ['Daya (Wp)', 'number'], 'vmpp' => ['Vmpp (V)', 'number'], 'impp' => ['Impp (A)', 'number'],
            'voc' => ['Voc (V)', 'number'], 'isc' => ['Isc (A)', 'number'], 'length' => ['Panjang (mm)', 'number'], 'width' => ['Lebar (mm)', 'number'],
            'thickness' => ['Tebal (mm)', 'number'], 'weight' => ['Berat (kg)', 'number'],
        ],
    ],
    'inverter' => [
        'label' => 'Inverter', 'icon' => 'bi-lightning-charge', 'source' => 'INVERTER',
        'description' => 'Inverter 4 merek: arus maksimum, daya, fasa, dan rating breaker.',
        'list' => ['brand', 'phase', 'imax', 'power_w', 'breaker_rating'],
        'fields' => [
            'brand' => ['Merek', 'text'], 'phase' => ['Fasa', 'select', [1 => '1 fasa', 3 => '3 fasa']], 'imax' => ['I max (A)', 'number'],
            'power_w' => ['Daya (W)', 'number'], 'breaker_rating' => ['Rating breaker (A)', 'number'], 'breaker_code_excel' => ['Kode breaker di Excel (catatan)', 'text'],
        ],
    ],
    'monitoring' => [
        'label' => 'Monitoring', 'icon' => 'bi-broadcast', 'source' => 'INVERTER (kolom Monitoring & kompatibilitas)',
        'description' => 'Perangkat monitoring, apakah butuh panel komunikasi, dan meteo station yang kompatibel.',
        'list' => ['brand', 'needs_panel'],
        'fields' => [
            'brand' => ['Merek', 'text'], 'needs_panel' => ['Butuh panel komunikasi', 'select', [0 => 'Tidak', 1 => 'Ya']],
            'compatible_meteo' => ['Meteo station kompatibel (satu per baris)', 'lines'],
        ],
    ],
    'meteo' => [
        'label' => 'Meteo Station', 'icon' => 'bi-cloud-sun', 'source' => 'INVERTER (kolom Meteo Station)',
        'description' => 'Sensor/meteo station per merek.',
        'list' => ['brand'], 'fields' => ['brand' => ['Merek', 'text']],
    ],
    'breaker' => [
        'label' => 'Breaker (MCB/MCCB/ACB)', 'icon' => 'bi-toggles', 'source' => 'Breaker Selection & CT',
        'description' => 'Katalog breaker beserta kode produk, kapasitas pemutus, dan kutub.',
        'list' => ['kind', 'series', 'poles', 'rating', 'ka', 'phase'],
        'fields' => [
            'kind' => ['Jenis', 'select', ['MCB' => 'MCB', 'MCCB' => 'MCCB', 'ACB' => 'ACB']], 'series' => ['Seri', 'text'], 'subtype' => ['Sub-tipe', 'text'],
            'phase' => ['Fasa', 'select', [1 => '1 fasa', 3 => '3 fasa']], 'rating' => ['Rating (A)', 'number'], 'ka' => ['Kapasitas pemutus (kA)', 'number'],
            'poles' => ['Kutub', 'select', ['1P' => '1P', '2P' => '2P', '3P' => '3P', '4P' => '4P']], 'width' => ['Lebar (mm) / jumlah grup', 'number'],
        ],
    ],
    'ct' => [
        'label' => 'Current Transformer', 'icon' => 'bi-circle-square', 'source' => 'Breaker Selection & CT',
        'description' => 'Trafo arus (CT) untuk power meter.',
        'list' => ['ct_type', 'brand', 'ratio', 'class', 'burden_va'],
        'fields' => [
            'ct_type' => ['Tipe CT', 'text'], 'brand' => ['Merek', 'text'], 'model' => ['Model', 'text'], 'ratio' => ['Rasio', 'text'],
            'class' => ['Kelas', 'number'], 'burden_va' => ['Burden (VA)', 'number'], 'busbar_hole' => ['Lubang busbar', 'text'],
        ],
    ],
    'cable_1c' => [
        'label' => 'Kabel 1C', 'icon' => 'bi-plug', 'source' => '1C',
        'description' => 'Tabel kabel 1 inti: tahanan, ampacity, dan batas pemilihan ukuran. "Batas pemilihan" adalah arus maksimum yang boleh dipikul ukuran ini pada tangga pemilihan kabel.',
        'list' => ['size', 'r70', 'i_air', 'select_max_a', 'pe_size'],
        'fields' => [
            'size' => ['Ukuran (mm²)', 'number'], 'r20' => ['R @20°C (Ω/km)', 'number'], 'r70' => ['R @70°C (Ω/km)', 'number'], 'insulation' => ['Isolasi min (MΩ.km)', 'number'],
            'i_air' => ['Ampacity di udara (A)', 'number'], 'i_ground' => ['Ampacity di tanah (A)', 'number'], 'isc_ka' => ['Isc 1 detik (kA)', 'number'], 'od' => ['Diameter luar (mm)', 'number'],
            'select_max_a' => ['Batas pemilihan (A)', 'number'], 'pe_size' => ['Ukuran PE (mm²)', 'number'],
        ],
    ],
    'cable_multi' => [
        'label' => 'Kabel 3C & 4C', 'icon' => 'bi-plug-fill', 'source' => '3C & 4C',
        'description' => 'Tabel kabel multi-inti (3 dan 4 inti).',
        'list' => ['size', 'r70', 'i_air', 'select_max_a', 'pe_size'],
        'fields' => [
            'size' => ['Ukuran (mm²)', 'number'], 'r20' => ['R @20°C (Ω/km)', 'number'], 'r70' => ['R @70°C (Ω/km)', 'number'], 'insulation' => ['Isolasi min (MΩ.km)', 'number'],
            'i_air' => ['Ampacity di udara (A)', 'number'], 'i_ground' => ['Ampacity di tanah (A)', 'number'], 'isc_ka' => ['Isc 1 detik (kA)', 'number'],
            'od_3c' => ['Diameter luar 3C (mm)', 'number'], 'od_4c' => ['Diameter luar 4C (mm)', 'number'],
            'select_max_a' => ['Batas pemilihan (A)', 'number'], 'pe_size' => ['Ukuran PE (mm²)', 'number'],
        ],
    ],
    'cable_nya' => [
        'label' => 'Kabel NYA', 'icon' => 'bi-ethernet', 'source' => 'NYA',
        'description' => 'Diameter luar kabel NYA per ukuran.',
        'list' => ['size', 'od'], 'fields' => ['size' => ['Ukuran (mm²)', 'number'], 'od' => ['Diameter luar (mm)', 'number']],
    ],
    'cable_correction' => [
        'label' => 'Faktor Koreksi Suhu Kabel', 'icon' => 'bi-thermometer-half', 'source' => 'Cable Sizing Correction Factor',
        'description' => 'Faktor koreksi ampacity menurut suhu ambient (PVC dan XLPE).',
        'list' => ['ambient_c', 'pvc', 'xlpe'], 'fields' => ['ambient_c' => ['Suhu ambient (°C)', 'number'], 'pvc' => ['PVC', 'number'], 'xlpe' => ['XLPE', 'number']],
    ],
    'cable_dc' => [
        'label' => 'Kabel DC (Solar Cable)', 'icon' => 'bi-lightning', 'source' => 'Cable DC',
        'description' => 'Kabel DC 1C: tahanan @20°C, kemampuan arus, dan diameter luar.',
        'list' => ['size', 'r20', 'current_a', 'od'],
        'fields' => ['size' => ['Ukuran (mm²)', 'number'], 'r20' => ['R @20°C (Ω/km)', 'number'], 'current_a' => ['Arus (A)', 'number'], 'od' => ['Diameter luar (mm)', 'number']],
    ],
    'skun' => [
        'label' => 'Skun (Lug & Ferrule)', 'icon' => 'bi-pin-angle', 'source' => 'Skun & Vinyl & Gland & Conduit',
        'description' => 'Kode skun per tipe dan ukuran kabel.',
        'list' => ['skun_type', 'size'], 'fields' => ['skun_type' => ['Tipe skun', 'text'], 'size' => ['Untuk kabel (mm²)', 'number']],
    ],
    'vinyl' => [
        'label' => 'Vinyl Insulation', 'icon' => 'bi-circle', 'source' => 'Skun & Vinyl & Gland & Conduit',
        'description' => 'Isolasi vinyl untuk skun per ukuran kabel.',
        'list' => ['size', 'diameter', 'color'], 'fields' => ['diameter' => ['Diameter (mm)', 'number'], 'size' => ['Untuk skun kabel (mm²)', 'number'], 'color' => ['Warna', 'text']],
    ],
    'gland' => [
        'label' => 'Cable Gland', 'icon' => 'bi-record-circle', 'source' => 'Skun & Vinyl & Gland & Conduit',
        'description' => 'Cable gland (BPG Brass, PG Nylon, CG/MG Nylon, M Brass).',
        'list' => ['gland_type', 'gland_code', 'cable_range', 'inch'],
        'fields' => ['gland_type' => ['Tipe', 'text'], 'gland_code' => ['Kode', 'text'], 'cable_range' => ['Rentang kabel', 'text'], 'min_od' => ['OD min (mm)', 'number'], 'max_od' => ['OD maks (mm)', 'number'], 'inch' => ['Inci', 'text']],
    ],
    'conduit' => [
        'label' => 'Conduit', 'icon' => 'bi-distribute-vertical', 'source' => 'Skun & Vinyl & Gland & Conduit',
        'description' => 'Ukuran flexible metal conduit.',
        'list' => ['inch', 'mm', 'use'], 'fields' => ['inch' => ['Inci', 'text'], 'mm' => ['mm', 'number'], 'use' => ['Pemakaian', 'text']],
    ],
    'busbar' => [
        'label' => 'Busbar', 'icon' => 'bi-bar-chart-steps', 'source' => 'Busbar & Box Panel',
        'description' => 'Ukuran busbar tembaga dan kuat hantar arus (KHA) per jumlah lapis.',
        'list' => ['width', 'thickness', 'kha_1', 'kha_2', 'kha_3', 'kha_4'],
        'fields' => [
            'width' => ['Lebar (mm)', 'number'], 'thickness' => ['Tebal (mm)', 'number'], 'area_mm2' => ['Luas (mm²)', 'number'],
            'kha_1' => ['KHA 1 lapis (A)', 'number'], 'kha_2' => ['KHA 2 lapis (A)', 'number'], 'kha_3' => ['KHA 3 lapis (A)', 'number'], 'kha_4' => ['KHA 4 lapis (A)', 'number'],
        ],
    ],
    'jasa_tier' => [
        'label' => 'Tarif Jasa per Tier kWp', 'icon' => 'bi-cash-coin', 'source' => 'DATA Jasa',
        'description' => 'Tarif jasa engineering, training & commissioning, dan ATK per tier kapasitas. Total engineering dan training dihitung otomatis saat disimpan.',
        'list' => ['kwp', 'engineering_total', 'training_total', 'atk'],
        'fields' => [
            'kwp' => ['Tier (kWp)', 'number'], 'survey' => ['List survey (Rp)', 'number'], 'drawing' => ['Drawing (Rp)', 'number'], 'blueprint' => ['Cetak blueprint (Rp)', 'number'],
            'om' => ['O&M (Rp)', 'number'], 'troubleshooting' => ['Troubleshooting instalasi (Rp)', 'number'], 'training_day' => ['Training: hari', 'number'],
            'training_man' => ['Training: orang', 'number'], 'training_rate' => ['Training: tarif per orang-hari (Rp)', 'number'], 'atk' => ['ATK (Rp)', 'number'],
        ],
    ],
    'rule' => [
        'label' => 'Aturan Pilihan', 'icon' => 'bi-funnel', 'source' => 'Breaker Selection & CT, INVERTER, data validation',
        'description' => 'Daftar pilihan yang diizinkan Excel per fasa: jenis inti kabel, seri breaker, kutub, kA, faktor arus, dan pilihan spare. Satu nilai per baris.',
        'list' => ['values'], 'fields' => ['values' => ['Nilai yang diizinkan (satu per baris)', 'lines']],
    ],
    'transformer_ka' => [
        'label' => 'Trafo → kA Breaker Utama', 'icon' => 'bi-diagram-2', 'source' => 'Breaker Selection & CT (AN:AO)',
        'description' => 'Kapasitas trafo PLN (kVA) menentukan kapasitas pemutus (kA) breaker utama.',
        'list' => ['kva', 'ka'], 'fields' => ['kva' => ['Kapasitas trafo (kVA)', 'number'], 'ka' => ['kA breaker utama', 'number']],
    ],
    'skun_rule' => [
        'label' => 'Skun menurut Seri Breaker', 'icon' => 'bi-link-45deg', 'source' => 'Skun & Vinyl (O26:P81)',
        'description' => 'Tipe skun yang boleh dipakai di ujung kabel untuk tiap seri breaker (iC60/C120N memakai ferrule/lug kecil; MCCB/ACB memakai DTG/CE/SC).',
        'list' => ['types'], 'fields' => ['types' => ['Tipe skun yang diizinkan (satu per baris)', 'lines']],
    ],
    'param' => [
        'label' => 'Parameter Perhitungan', 'icon' => 'bi-sliders', 'source' => 'berbagai sheet',
        'description' => 'Faktor dan batas yang dipakai kalkulator (faktor ampacity 1,7, batas drop tegangan, rentang DC/AC, dll.).',
        'list' => ['value', 'source'], 'fields' => ['value' => ['Nilai', 'number'], 'source' => ['Asal di Excel', 'text']],
    ],
];
