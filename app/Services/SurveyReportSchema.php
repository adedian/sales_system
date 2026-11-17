<?php

namespace App\Services;

/**
 * Skema isi Survey Report — mengikuti template perusahaan
 * "Survey Report Bravo Tuban Rev00" (SITE INFORMATION, BUILDING INFORMATION,
 * ELECTRICAL OVERVIEW, PROTECTIONS, SPECIFIC CONCERN, plus SITE OVERVIEW,
 * LAYOUT, dan NOTES yang berisi foto).
 *
 * Satu skema dipakai bersama oleh form isian (label Indonesia) dan PDF
 * (label asli template, bahasa Inggris). Nilai pilihan disimpan sebagai
 * teks asli template (mis. "Trapezoid") sehingga PDF menampilkannya apa
 * adanya. Data disimpan sebagai JSON di survey_reports.data.
 *
 * Tipe baris: text, textarea, date, choice (satu pilihan), multi (beberapa
 * pilihan), parts (beberapa sub-isian dalam satu baris), dim (dimensi A-H),
 * panels (tabel panel listrik 3 kolom). `other` = pilihan yang disertai
 * isian teks bebas (mis. "Manufacture: ____", "Other: ____").
 */
class SurveyReportSchema
{
    public const PHOTO_CATEGORIES = [
        'site_overview' => 'Site Overview (citra satelit/foto lokasi)',
        'layout' => 'Layout (denah penempatan)',
        'notes' => 'Catatan lapangan & dokumentasi',
    ];

    public const MARKED = '☒';
    public const EMPTY_BOX = '☐';

    /** @return array<int,array<string,mixed>> */
    public static function sections(): array
    {
        $yesNo = ['Yes' => 'Ya', 'No' => 'Tidak'];
        $hours = ['8 hr' => '8 jam', '16 hr' => '16 jam', '24 hr' => '24 jam', '13 hr' => '13 jam'];
        $load = ['Full load' => 'Beban penuh', 'Partial load' => 'Beban sebagian'];

        return [
            [
                'key' => 'site', 'title' => 'SITE INFORMATION', 'title_id' => 'Informasi Lokasi',
                'rows' => [
                    ['key' => 'site', 'label' => 'Site', 'label_id' => 'Nama lokasi / site', 'type' => 'text', 'required' => true],
                    ['key' => 'survey_date', 'label' => 'Date of Survey', 'label_id' => 'Tanggal survey', 'type' => 'date', 'required' => true],
                    ['key' => 'sector', 'label' => 'Business Sector', 'label_id' => 'Sektor usaha', 'type' => 'choice', 'columns' => 2,
                        'options' => ['Agriculture' => 'Pertanian', 'Food and Beverage' => 'Makanan & Minuman', 'Pulp and Paper' => 'Pulp & Kertas', 'Textile / Garment' => 'Tekstil / Garmen', 'Energy and Mining' => 'Energi & Pertambangan', 'Manufacture' => 'Manufaktur', 'Logistics' => 'Logistik', 'Other' => 'Lainnya'],
                        'other' => ['Manufacture', 'Other']],
                    ['key' => 'address', 'label' => 'Address', 'label_id' => 'Alamat', 'type' => 'textarea', 'required' => true],
                    ['key' => 'coordinates', 'label' => 'Coordinates', 'label_id' => 'Koordinat (lat, long)', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'building', 'title' => 'BUILDING INFORMATION', 'title_id' => 'Informasi Bangunan',
                'rows' => [
                    ['key' => 'building_name', 'label' => 'Building name', 'label_id' => 'Nama bangunan', 'type' => 'text'],
                    ['key' => 'building_purpose', 'label' => 'Building purpose', 'label_id' => 'Peruntukan bangunan', 'type' => 'choice', 'columns' => 3,
                        'options' => ['Industrial' => 'Industrial', 'Commercial' => 'Komersial', 'Residential' => 'Residensial']],
                    ['key' => 'roof_material', 'label' => 'Roof material', 'label_id' => 'Material atap', 'type' => 'choice', 'columns' => 3,
                        'options' => ['Zincalume' => 'Zincalume', 'Alderon' => 'Alderon', 'Concrete' => 'Beton', 'Galvalume' => 'Galvalume', 'Other' => 'Lainnya'], 'other' => ['Other']],
                    ['key' => 'roof_topology', 'label' => 'Roof topology', 'label_id' => 'Tipe profil atap', 'type' => 'choice', 'columns' => 3,
                        'options' => ['Kliplok' => 'Kliplok', 'Corrugated' => 'Corrugated (gelombang)', 'Trapezoid' => 'Trapezoid', 'Other' => 'Lainnya'], 'other' => ['Other']],
                    ['key' => 'roof_tilt', 'label' => 'Roof tilt', 'label_id' => 'Kemiringan atap (derajat)', 'type' => 'text', 'suffix' => '°'],
                    ['key' => 'roof_dim', 'label' => 'Roof dimension', 'label_id' => 'Dimensi profil atap (mm)', 'type' => 'dim', 'letters' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], 'suffix' => 'mm'],
                    ['key' => 'roof_access', 'label' => 'Roof access', 'label_id' => 'Akses ke atap', 'type' => 'parts', 'parts' => [
                        ['key' => 'availability', 'label' => 'Availability', 'label_id' => 'Tersedia', 'type' => 'choice', 'options' => $yesNo],
                        ['key' => 'access_type', 'label' => 'Access Type', 'label_id' => 'Jenis akses', 'type' => 'choice', 'options' => ['Staircase' => 'Tangga permanen', 'Ladder' => 'Tangga portabel']],
                        ['key' => 'condition', 'label' => 'Condition', 'label_id' => 'Kondisi', 'type' => 'choice', 'options' => ['Good' => 'Baik', 'Bad' => 'Buruk']],
                    ]],
                    ['key' => 'skylight_width', 'label' => 'Skylight width', 'label_id' => 'Lebar skylight', 'type' => 'text'],
                    ['key' => 'jackroof_width', 'label' => 'Jackroof width', 'label_id' => 'Lebar jackroof', 'type' => 'text'],
                    ['key' => 'gording_dist', 'label' => 'Gording Dist', 'label_id' => 'Jarak gording', 'type' => 'text'],
                ],
            ],
            [
                'key' => 'electrical', 'title' => 'ELECTRICAL OVERVIEW', 'title_id' => 'Gambaran Kelistrikan',
                'rows' => [
                    ['key' => 'grid_provider', 'label' => 'Grid Provider', 'label_id' => 'Penyedia listrik', 'type' => 'choice', 'columns' => 2,
                        'options' => ['PLN' => 'PLN', 'Other' => 'Lainnya'], 'other' => ['Other']],
                    ['key' => 'grid_capacity', 'label' => 'Grid Capacity', 'label_id' => 'Kapasitas (kVA)', 'type' => 'text', 'suffix' => 'kVA'],
                    ['key' => 'grid_id', 'label' => 'Grid ID', 'label_id' => 'ID pelanggan PLN', 'type' => 'text'],
                    ['key' => 'operating', 'label' => 'Factory operating Hours', 'label_id' => 'Jam operasional', 'type' => 'parts', 'parts' => [
                        ['key' => 'operating_hour', 'label' => 'Operating hour', 'label_id' => 'Jam operasi (mis. 07:00 - 22:00)', 'type' => 'text'],
                        ['key' => 'weekday', 'label' => 'Weekday', 'label_id' => 'Hari kerja (Sen-Jum)', 'type' => 'choice', 'options' => $hours],
                        ['key' => 'saturday', 'label' => 'Saturday', 'label_id' => 'Sabtu', 'type' => 'choice', 'options' => $hours],
                        ['key' => 'saturday_load', 'label' => 'Saturday load', 'label_id' => 'Beban Sabtu', 'type' => 'choice', 'options' => $load],
                        ['key' => 'sunday', 'label' => 'Sunday', 'label_id' => 'Minggu', 'type' => 'choice', 'options' => $hours],
                        ['key' => 'sunday_load', 'label' => 'Sunday load', 'label_id' => 'Beban Minggu', 'type' => 'choice', 'options' => $load],
                    ]],
                    ['key' => 'downtime', 'label' => 'Power Downtime', 'label_id' => 'Pemadaman listrik', 'type' => 'parts', 'parts' => [
                        ['key' => 'frequency', 'label' => 'Estimated Frequency', 'label_id' => 'Perkiraan frekuensi', 'type' => 'text'],
                        ['key' => 'duration', 'label' => 'Estimated Duration', 'label_id' => 'Perkiraan durasi', 'type' => 'text'],
                    ]],
                    ['key' => 'generator', 'label' => 'Back-up Generators', 'label_id' => 'Generator cadangan', 'type' => 'parts', 'parts' => [
                        ['key' => 'availability', 'label' => 'Availability', 'label_id' => 'Tersedia', 'type' => 'choice', 'options' => $yesNo],
                        ['key' => 'type', 'label' => 'Type', 'label_id' => 'Jenis', 'type' => 'choice', 'options' => ['Diesel Generators' => 'Genset diesel', 'Gas Engines' => 'Mesin gas', 'Other' => 'Lainnya'], 'other' => ['Other']],
                        ['key' => 'amount', 'label' => 'Amount', 'label_id' => 'Jumlah unit', 'type' => 'text'],
                        ['key' => 'capacity', 'label' => 'Capacity', 'label_id' => 'Kapasitas', 'type' => 'text'],
                    ]],
                    ['key' => 'notes', 'label' => 'Notes', 'label_id' => 'Catatan kelistrikan', 'type' => 'textarea'],
                ],
            ],
            [
                'key' => 'protection', 'title' => 'PROTECTIONS', 'title_id' => 'Proteksi & Panel Listrik',
                'rows' => [
                    ['key' => 'lightning', 'label' => 'Lightning arrester', 'label_id' => 'Penangkal petir', 'type' => 'parts', 'parts' => [
                        ['key' => 'availability', 'label' => 'Availability', 'label_id' => 'Tersedia', 'type' => 'choice', 'options' => $yesNo],
                        ['key' => 'coverage', 'label' => 'Coverage area', 'label_id' => 'Area jangkauan', 'type' => 'text'],
                    ]],
                    ['key' => 'grounding', 'label' => 'Grounding rod', 'label_id' => 'Grounding', 'type' => 'parts', 'parts' => [
                        ['key' => 'depth', 'label' => 'Depth', 'label_id' => 'Kedalaman', 'type' => 'text'],
                        ['key' => 'resistance', 'label' => 'Resistance', 'label_id' => 'Tahanan', 'type' => 'text'],
                    ]],
                    ['key' => 'panels', 'label' => 'Electrical Panels', 'label_id' => 'Panel listrik (isi sesuai yang ada, maks. 3)', 'type' => 'panels', 'columns' => ['1', '2', '3'],
                        'rows' => ['name' => 'Name', 'voltage' => 'Voltage', 'transformator' => 'Transformator', 'breaker' => 'Breaker rating', 'cable' => 'Cable Routing'],
                        'rows_id' => ['name' => 'Nama (mis. LVMDP)', 'voltage' => 'Tegangan', 'transformator' => 'Transformator', 'breaker' => 'Rating breaker', 'cable' => 'Jalur kabel'],
                        'flag' => ['key' => 'interconnection', 'label' => 'Interconnection', 'label_id' => 'Titik interkoneksi']],
                    ['key' => 'notes', 'label' => 'Notes', 'label_id' => 'Catatan proteksi', 'type' => 'textarea'],
                    ['key' => 'space', 'label' => 'Space for Combiner and Inverters', 'label_id' => 'Ruang combiner & inverter', 'type' => 'parts', 'parts' => [
                        ['key' => 'related_to', 'label' => 'Related to', 'label_id' => 'Terkait panel', 'type' => 'text'],
                        ['key' => 'distance', 'label' => 'Distance to interconnection', 'label_id' => 'Jarak ke interkoneksi', 'type' => 'text'],
                        ['key' => 'location', 'label' => 'Location', 'label_id' => 'Lokasi', 'type' => 'choice', 'options' => ['Indoor' => 'Dalam ruangan', 'Outdoor' => 'Luar ruangan']],
                    ]],
                ],
            ],
            [
                'key' => 'concern', 'title' => 'SPECIFIC CONCERN', 'title_id' => 'Hal yang Perlu Diperhatikan',
                'rows' => [
                    ['key' => 'heat', 'label' => 'Heat', 'label_id' => 'Panas', 'type' => 'choice', 'options' => ['None' => 'Tidak ada', 'Must-avoided heat sources exist' => 'Ada sumber panas yang harus dihindari'], 'note' => 'heat_note'],
                    ['key' => 'soiling', 'label' => 'Soiling', 'label_id' => 'Kotoran/debu', 'type' => 'choice', 'options' => ['Standard' => 'Standar', 'Higher than average in specific areas' => 'Lebih tinggi di area tertentu', 'Higher than average in all areas' => 'Lebih tinggi di semua area'], 'note' => 'soiling_note'],
                    ['key' => 'shading', 'label' => 'Shading', 'label_id' => 'Bayangan', 'type' => 'multi', 'options' => ['None' => 'Tidak ada', 'Multilevel roofs' => 'Atap bertingkat', 'Higher buildings' => 'Bangunan lebih tinggi', 'Trees' => 'Pohon', 'Other factors' => 'Faktor lain'], 'note' => 'shading_note'],
                    ['key' => 'chemical', 'label' => 'Chemical', 'label_id' => 'Bahan kimia', 'type' => 'multi', 'options' => ['None' => 'Tidak ada', 'Corrosive' => 'Korosif', 'Flammable' => 'Mudah terbakar', 'Explosive' => 'Mudah meledak', 'Other' => 'Lainnya'], 'note' => 'chemical_note'],
                ],
            ],
        ];
    }

    /**
     * Nilai awal dari data yang sudah ada (Lead & Survey) supaya Surveyor tidak
     * mengetik ulang.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function defaults(array $survey, array $lead): array
    {
        $type = (string) ($lead['type_name'] ?? '');
        $purpose = in_array($type, ['Industrial', 'Commercial', 'Residential'], true) ? $type : '';
        $coords = (!empty($lead['latitude']) && !empty($lead['longitude'])) ? ($lead['latitude'] . ', ' . $lead['longitude']) : '';

        return [
            'site' => [
                'site' => trim((string) ($lead['company_name'] ?: $lead['customer_name'])),
                'survey_date' => (string) ($survey['scheduled_date'] ?? ''),
                'address' => trim((string) (($lead['address'] ?? '') ?: ($lead['site_location'] ?? ''))),
                'coordinates' => $coords,
            ],
            'building' => ['building_purpose' => $purpose, 'building_name' => trim((string) ($lead['company_name'] ?? ''))],
            'electrical' => ['grid_provider' => 'PLN', 'grid_id' => (string) ($lead['pln_id'] ?? '')],
        ];
    }

    /** Gabungkan default dengan data tersimpan (data tersimpan menang). */
    public static function merged(array $survey, array $lead, ?array $saved): array
    {
        $out = self::defaults($survey, $lead);
        foreach ($saved ?? [] as $section => $values) {
            if (is_array($values)) {
                $out[$section] = array_replace($out[$section] ?? [], $values);
            }
        }

        return $out;
    }

    /**
     * Membersihkan input form ($_POST['d']) menurut skema: hanya kunci yang
     * dikenal, pilihan harus salah satu opsi, panjang dibatasi.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function sanitize(array $input): array
    {
        $clean = [];
        foreach (self::sections() as $section) {
            $sk = $section['key'];
            $in = is_array($input[$sk] ?? null) ? $input[$sk] : [];
            foreach ($section['rows'] as $row) {
                self::sanitizeRow($row, $row['key'], $in, $clean[$sk]);
            }
        }

        return $clean;
    }

    /** @param array<string,mixed> $row */
    private static function sanitizeRow(array $row, string $prefix, array $in, ?array &$out): void
    {
        $out ??= [];
        $type = $row['type'];

        if ($type === 'parts') {
            foreach ($row['parts'] as $part) {
                self::sanitizeRow($part, $row['key'] . '_' . $part['key'], $in, $out);
            }

            return;
        }

        $val = $in[$prefix] ?? null;

        switch ($type) {
            case 'text':
                $out[$prefix] = self::str($val, 200);
                break;
            case 'textarea':
                $out[$prefix] = self::str($val, 2000);
                break;
            case 'date':
                $d = is_string($val) ? $val : '';
                $out[$prefix] = ($d !== '' && date('Y-m-d', (int) strtotime($d)) === $d) ? $d : '';
                break;
            case 'choice':
                $v = is_string($val) ? $val : '';
                $out[$prefix] = isset($row['options'][$v]) ? $v : '';
                if (!empty($row['other'])) {
                    $out[$prefix . '_other'] = in_array($out[$prefix], $row['other'], true) ? self::str($in[$prefix . '_other'] ?? '', 150) : '';
                }
                if (!empty($row['note'])) {
                    $out[$row['note']] = self::str($in[$row['note']] ?? '', 500);
                }
                break;
            case 'multi':
                $picked = [];
                foreach ((array) $val as $v) {
                    if (is_string($v) && isset($row['options'][$v])) {
                        $picked[] = $v;
                    }
                }
                $out[$prefix] = array_values(array_unique($picked));
                if (!empty($row['note'])) {
                    $out[$row['note']] = self::str($in[$row['note']] ?? '', 500);
                }
                break;
            case 'dim':
                $dims = [];
                foreach ($row['letters'] as $l) {
                    $dims[$l] = self::str(is_array($val) ? ($val[$l] ?? '') : '', 20);
                }
                $out[$prefix] = $dims;
                break;
            case 'panels':
                $cells = [];
                foreach ($row['columns'] as $col) {
                    foreach (array_keys($row['rows']) as $rk) {
                        $cells[$col][$rk] = self::str(is_array($val) ? ($val[$col][$rk] ?? '') : '', 100);
                    }
                    $cells[$col][$row['flag']['key']] = !empty($val[$col][$row['flag']['key']]) ? '1' : '';
                }
                $out[$prefix] = $cells;
                break;
        }
    }

    private static function str(mixed $v, int $max): string
    {
        return mb_substr(trim(is_string($v) ? $v : ''), 0, $max);
    }

    /** Daftar isian wajib yang masih kosong (untuk pengajuan laporan). @return string[] */
    public static function missingRequired(array $data): array
    {
        $missing = [];
        foreach (self::sections() as $section) {
            foreach ($section['rows'] as $row) {
                if (!empty($row['required']) && trim((string) ($data[$section['key']][$row['key']] ?? '')) === '') {
                    $missing[] = $row['label_id'];
                }
            }
        }

        return $missing;
    }

    /** Teks tanda centang untuk PDF. */
    public static function box(bool $checked): string
    {
        return $checked ? self::MARKED : self::EMPTY_BOX;
    }
}
