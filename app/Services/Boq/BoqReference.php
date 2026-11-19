<?php

namespace App\Services\Boq;

use App\Core\Database;

/**
 * Data referensi BoQ (katalog dari Excel "Detailed Calculation BoQ") sebagai objek tanpa
 * ketergantungan pada request. Bisa dimuat dari database (produksi) atau dari
 * database/seeders/boq_reference.json (verifikasi/CLI), sehingga BoqCalculator dapat
 * diuji tanpa database.
 */
class BoqReference
{
    /** Urutan rating standar breaker (A). Excel memakai tangga yang sama di kolom Breaker Selection. */
    public const BREAKER_RATINGS = [1, 2, 4, 6, 10, 16, 20, 25, 32, 40, 50, 63, 80, 100, 125, 160, 200, 250, 320, 400, 630, 800, 1000, 1250, 1600, 2000, 2500, 3200, 4000, 5000, 6000];

    /** @var array<string,array<int,array{code:string,label:string,attrs:array<string,mixed>}>> */
    private array $data;

    /** @var array<string,array<string,array>> indeks code => baris per kategori */
    private array $index = [];

    /** @param array<string,array<int,array>> $data kategori => baris [code,label,attrs] */
    public function __construct(array $data)
    {
        $this->data = $data;
        foreach ($data as $category => $rows) {
            foreach ($rows as $i => $row) {
                $this->data[$category][$i]['attrs'] = $row['attrs'] ?? [];
                $this->index[$category][(string) $row['code']] = $this->data[$category][$i];
            }
        }
    }

    public static function fromDatabase(): self
    {
        $rows = Database::fetchAll('SELECT category, code, label, attrs FROM boq_ref_items WHERE is_active = 1 ORDER BY category, sort_order, id');
        $data = [];
        foreach ($rows as $row) {
            $data[$row['category']][] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'attrs' => json_decode((string) $row['attrs'], true) ?: [],
            ];
        }

        return new self($data);
    }

    public static function fromJsonFile(string $path): self
    {
        $json = json_decode((string) file_get_contents($path), true);
        if (!is_array($json)) {
            throw new \RuntimeException('File referensi BoQ tidak terbaca: ' . $path);
        }

        return new self($json);
    }

    /** @return array<int,array{code:string,label:string,attrs:array}> */
    public function all(string $category): array
    {
        return $this->data[$category] ?? [];
    }

    public function get(string $category, string $code): ?array
    {
        return $this->index[$category][$code] ?? null;
    }

    public function param(string $code, float $default): float
    {
        $row = $this->get('param', $code);

        return $row !== null && isset($row['attrs']['value']) ? (float) $row['attrs']['value'] : $default;
    }

    // ------------------------------------------------------------------ inverter / modul

    /** Cari inverter menurut model (kode unik di katalog). */
    public function inverter(string $model): ?array
    {
        return $this->get('inverter', $model);
    }

    /** @return string[] merek inverter yang ada di katalog, berurutan. */
    public function inverterBrands(): array
    {
        $brands = [];
        foreach ($this->all('inverter') as $row) {
            $brands[$row['attrs']['brand'] ?? ''] = true;
        }
        unset($brands['']);

        return array_keys($brands);
    }

    public function pvModule(string $model): ?array
    {
        return $this->get('pv_module', $model);
    }

    /** Rating breaker terkecil dari tangga standar yang >= arus (null bila di luar tabel). */
    public function breakerRating(float $amps): ?float
    {
        foreach (self::BREAKER_RATINGS as $rating) {
            if ($amps <= $rating) {
                return (float) $rating;
            }
        }

        return null;
    }

    /**
     * Cari produk breaker. Urutan: seri yang diminta (bila ada) lalu kapasitas pemutus (kA) paling kecil.
     *
     * @return array<string,mixed>|null baris katalog [code,label,attrs]
     */
    public function findBreaker(string $poles, float $rating, ?string $series = null, ?string $kind = null): ?array
    {
        $best = null;
        foreach ($this->all('breaker') as $row) {
            $a = $row['attrs'];
            if (($a['poles'] ?? null) !== $poles || abs((float) ($a['rating'] ?? -1) - $rating) > 1e-9) {
                continue;
            }
            if ($series !== null && $series !== '' && ($a['series'] ?? '') !== $series) {
                continue;
            }
            if ($kind !== null && $kind !== '' && ($a['kind'] ?? '') !== $kind) {
                continue;
            }
            if ($best === null || (float) ($a['ka'] ?? 0) < (float) ($best['attrs']['ka'] ?? 0)) {
                $best = $row;
            }
        }

        return $best;
    }

    // ------------------------------------------------------- aturan pilihan (dropdown Excel)

    /** Cadangan bila tabel `rule` belum terisi (sama dengan isi Excel). */
    private const DEFAULT_RULES = [
        'cores_phase_1' => ['1C', '3C'], 'cores_phase_3' => ['1C', '4C'],
        'poles_phase_1' => ['1P', '2P'], 'poles_phase_3' => ['3P', '4P'],
        'series_phase_1' => ['IC60N/H/L', 'C120N'],
        'series_phase_3' => ['IC60N/H/L', 'C120N', 'EZC100B/F/N/H', 'CVSB/F/N/H', 'NSXF/N/H', 'NS N/H/L', 'NSX VIGI', 'MVS T/H/N', 'MTZ H1', 'MTZ H2', 'NSXF/N/H Micrologic 2.2'],
        'ka_phase_1' => [6, 10], 'ka_phase_3' => [6, 10, 15, 25, 30, 36, 50, 55, 65, 80, 100, 150],
        'breaker_factors' => [1, 1.1, 1.15, 1.2, 1.25, 1.3],
        'spare_options' => [0, 2, 4, 5, 6, 8, 10],
    ];

    /** @return array<int,mixed> */
    public function rule(string $code): array
    {
        $row = $this->get('rule', $code);
        $values = $row['attrs']['values'] ?? null;

        return is_array($values) && $values !== [] ? $values : (self::DEFAULT_RULES[$code] ?? []);
    }

    /** Jenis inti kabel yang boleh: 1 fasa = 1C/3C, 3 fasa = 1C/4C. */
    public function coresFor(int $phase): array
    {
        return $this->rule('cores_phase_' . ($phase === 3 ? 3 : 1));
    }

    public function polesFor(int $phase): array
    {
        return $this->rule('poles_phase_' . ($phase === 3 ? 3 : 1));
    }

    public function seriesFor(int $phase): array
    {
        return $this->rule('series_phase_' . ($phase === 3 ? 3 : 1));
    }

    public function kaFor(int $phase): array
    {
        return $this->rule('ka_phase_' . ($phase === 3 ? 3 : 1));
    }

    /**
     * kA breaker utama menurut kapasitas trafo (Breaker Selection AN:AO, VLOOKUP persis di Excel).
     * Bila kVA tidak ada persis di tabel, dipakai tier terdekat di atasnya (exact = false).
     *
     * @return array{kva:float,ka:float,exact:bool}
     */
    public function transformerKa(float $kva): array
    {
        $rows = $this->all('transformer_ka');
        usort($rows, fn ($a, $b) => $a['attrs']['kva'] <=> $b['attrs']['kva']);
        foreach ($rows as $row) {
            if (abs((float) $row['attrs']['kva'] - $kva) < 1e-9) {
                return ['kva' => (float) $row['attrs']['kva'], 'ka' => (float) $row['attrs']['ka'], 'exact' => true];
            }
        }
        foreach ($rows as $row) {
            if ((float) $row['attrs']['kva'] >= $kva) {
                return ['kva' => (float) $row['attrs']['kva'], 'ka' => (float) $row['attrs']['ka'], 'exact' => false];
            }
        }
        $last = $rows ? end($rows)['attrs'] : ['kva' => 0, 'ka' => 10];

        return ['kva' => (float) $last['kva'], 'ka' => (float) $last['ka'], 'exact' => false];
    }

    /** @return string[] tipe skun yang boleh untuk breaker seri tsb (kosong = tidak dibatasi). */
    public function skunTypesForSeries(string $series): array
    {
        return $this->get('skun_rule', $series)['attrs']['types'] ?? [];
    }

    /**
     * Pilih produk breaker seperti rumus Excel (AC COMB H14: kecocokan PERSIS seri + kutub + rating + kA).
     * Bila kA persis tidak ada, dipakai kA terkecil yang >= kA diminta dan ditandai ka_adjusted.
     * Seri dicoba berurutan sesuai daftar kandidat.
     *
     * @param string[] $seriesCandidates
     * @return array{row:array,ka_adjusted:bool}|null
     */
    public function pickBreaker(string $poles, float $rating, float $ka, array $seriesCandidates): ?array
    {
        foreach ($seriesCandidates as $series) {
            foreach ($this->all('breaker') as $row) {
                $a = $row['attrs'];
                if (($a['series'] ?? '') === $series && ($a['poles'] ?? '') === $poles
                    && abs((float) ($a['rating'] ?? -1) - $rating) < 1e-9 && abs((float) ($a['ka'] ?? -1) - $ka) < 1e-9) {
                    return ['row' => $row, 'ka_adjusted' => false];
                }
            }
        }
        foreach ($seriesCandidates as $series) {
            $best = null;
            foreach ($this->all('breaker') as $row) {
                $a = $row['attrs'];
                if (($a['series'] ?? '') !== $series || ($a['poles'] ?? '') !== $poles
                    || abs((float) ($a['rating'] ?? -1) - $rating) > 1e-9 || (float) ($a['ka'] ?? 0) < $ka) {
                    continue;
                }
                if ($best === null || (float) $a['ka'] < (float) $best['attrs']['ka']) {
                    $best = $row;
                }
            }
            if ($best !== null) {
                return ['row' => $best, 'ka_adjusted' => true];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------------- kabel

    /** Tabel kabel menurut jenis inti: '1C' => cable_1c, selain itu (3C/4C) => cable_multi. */
    public function cableTable(string $core): array
    {
        return $this->all($core === '1C' ? 'cable_1c' : 'cable_multi');
    }

    /**
     * Ukuran kabel terkecil yang batas ampacity-nya >= kebutuhan (tangga di Data Cabling kolom I).
     * Mengembalikan baris katalog, atau null bila kebutuhan melebihi ukuran terbesar.
     */
    public function pickCable(string $core, float $requiredAmps): ?array
    {
        $rows = array_values(array_filter($this->cableTable($core), fn ($r) => isset($r['attrs']['select_max_a'])));
        usort($rows, fn ($a, $b) => $a['attrs']['size'] <=> $b['attrs']['size']);
        foreach ($rows as $row) {
            if ($requiredAmps <= (float) $row['attrs']['select_max_a']) {
                return $row;
            }
        }

        return null;
    }

    public function cableBySize(string $core, float $size): ?array
    {
        foreach ($this->cableTable($core) as $row) {
            if (abs((float) $row['attrs']['size'] - $size) < 1e-9) {
                return $row;
            }
        }

        return null;
    }

    // ------------------------------------------------- skun / vinyl / gland / jasa

    /** @return string|null kode skun, atau null bila ukuran tidak ada di tabel tipe tsb. */
    public function skunCode(string $type, float $size): ?string
    {
        foreach ($this->all('skun') as $row) {
            if (($row['attrs']['skun_type'] ?? '') === $type && abs((float) ($row['attrs']['size'] ?? -1) - $size) < 1e-9) {
                return $row['label'];
            }
        }

        return null;
    }

    /** @return string[] tipe skun yang tersedia. */
    public function skunTypes(): array
    {
        $types = [];
        foreach ($this->all('skun') as $row) {
            $types[$row['attrs']['skun_type'] ?? ''] = true;
        }
        unset($types['']);

        return array_keys($types);
    }

    public function vinylCode(float $size): ?string
    {
        foreach ($this->all('vinyl') as $row) {
            if (abs((float) ($row['attrs']['size'] ?? -1) - $size) < 1e-9) {
                return $row['label'];
            }
        }

        return null;
    }

    /**
     * Tier tarif jasa: kWp dibulatkan ke atas kelipatan 100 (CEILING.MATH(kWp,100) di DATA Jasa K1),
     * lalu dicari persis; bila tier itu tidak ada dipakai tier terdekat di atasnya.
     */
    public function jasaTier(float $kwp): ?array
    {
        $target = max(100, (int) (ceil($kwp / 100) * 100));
        $rows = $this->all('jasa_tier');
        usort($rows, fn ($a, $b) => $a['attrs']['kwp'] <=> $b['attrs']['kwp']);
        foreach ($rows as $row) {
            if ($row['attrs']['kwp'] >= $target) {
                return $row;
            }
        }

        return $rows ? end($rows) : null;
    }
}
