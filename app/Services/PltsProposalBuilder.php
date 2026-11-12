<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Models\ProposalItem;
use App\Models\Setting;

/**
 * Membuat dokumen penawaran PLTS (PDF, template HME v2) dari data lead +
 * proposal + item di sistem ini, memakai generator `tools/hme-proposal-plts`
 * (Python + Jinja2 + Chrome headless). 100% lokal, TANPA API/AI: semua angka
 * (produksi energi, penghematan, payback, IRR) dihitung deterministik oleh
 * build.py dari data yang diisi di form.
 *
 * Alur: form -> inputs.json -> assemble.py (gabung dengan _base + validasi)
 *       -> build.py (hitung + render PDF) -> output/<id>-v2.pdf
 */
class PltsProposalBuilder
{
    private const MONTHS_ID = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    private const ROMAN = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    private const SEGMENTS = ['Industrial', 'Commercial', 'Residential'];
    private const SERVICE_PATTERN = '/jasa|instalasi|commissioning|pengiriman|izin|perizinan|slo|nidi|akomodasi|scaffolding|engineering|pelatihan/i';

    public static function generatorDir(): string
    {
        $dir = (string) Env::get('PLTS_GENERATOR_DIR', '');

        return rtrim($dir !== '' ? $dir : dirname(__DIR__, 2) . '/tools/hme-proposal-plts', '/\\');
    }

    public static function isAvailable(): bool
    {
        return is_file(self::generatorDir() . '/proposal-generator/build.py');
    }

    public static function projectId(array $proposal): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $proposal['proposal_code']));
    }

    public static function pdfPath(array $proposal): string
    {
        return self::generatorDir() . '/proposal-generator/output/' . self::projectId($proposal) . '-v2.pdf';
    }

    /** Isian terakhir yang tersimpan: ['form' => [...], 'built_at' => ..., 'warnings' => [...], 'derived' => [...]]. */
    public static function saved(array $proposal): array
    {
        $data = json_decode((string) ($proposal['plts_data'] ?? ''), true);

        return is_array($data) ? $data : [];
    }

    /** Daftar datasheet PDF yang bisa dilampirkan: ['Trina' => ['Trina/xxx.pdf' => 'xxx', ...], ...]. */
    public static function datasheets(): array
    {
        $root = self::generatorDir() . '/datasheet';
        if (!is_dir($root)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (strtolower($file->getExtension()) !== 'pdf' || preg_match('/pricebook|manual|promotion/i', $file->getFilename())) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $brand = explode('/', $rel)[0];
            $out[$brand][$rel] = $file->getBasename('.pdf');
        }
        ksort($out);
        foreach ($out as &$list) {
            asort($list);
        }

        return $out;
    }

    /**
     * Nilai awal form: dari data lead + proposal + item (modul/inverter dikenali
     * dari nama & spesifikasi item), lalu ditimpa isian terakhir yang tersimpan.
     */
    public static function defaults(array $proposal): array
    {
        $lead = self::lead((int) $proposal['lead_id']);
        $items = ProposalItem::forProposal((int) $proposal['id']);
        $company = trim((string) ($lead['company_name'] ?: $lead['customer_name']));
        $site = trim((string) ($lead['site_location'] ?? ''));
        $now = time();
        $seq = (int) preg_replace('/\D/', '', (string) $proposal['proposal_code']);

        $panel = ['qty' => '', 'wp' => ''];
        $inverter = ['qty' => '', 'kva' => ''];
        foreach ($items as $it) {
            $text = $it['item_name'] . ' ' . ($it['specification'] ?? '');
            $qty = (float) $it['quantity'];
            if ($panel['qty'] === '' && preg_match('/modul|panel surya|solar (panel|module)|pv module/i', $it['item_name']) && !preg_match('/proteksi|combiner|distribusi/i', $it['item_name'])) {
                $panel['qty'] = (int) round($qty);
                if (preg_match('/(\d{3,4})\s*wp/i', $text, $m)) {
                    $panel['wp'] = (int) $m[1];
                }
            }
            if ($inverter['qty'] === '' && preg_match('/inverter/i', $it['item_name'])) {
                $inverter['qty'] = (int) round($qty);
                if (preg_match('/(\d+(?:[.,]\d+)?)\s*k(?:w|va)\b/i', $text, $m)) {
                    $inverter['kva'] = (float) str_replace(',', '.', $m[1]);
                }
            }
        }

        $segment = in_array($lead['type_name'] ?? '', self::SEGMENTS, true) ? $lead['type_name'] : 'Commercial';
        $defaults = [
            'number' => sprintf('%d/Q.PLTS/HME/%s/%d', $seq, self::ROMAN[(int) date('n', $now)], (int) date('Y', $now)),
            'revision' => '00',
            'short_name' => $company,
            'location_id' => $site,
            'city' => preg_split('/[,\/]/', $site)[0] ?? '',
            'segment' => $segment,
            'type' => $lead['system_name'] ?: 'On-Grid',
            'mounting' => 'Rooftop',
            'monitoring' => '',
            'panel_qty' => $panel['qty'], 'panel_brand' => '', 'panel_model' => '', 'panel_wp' => $panel['wp'],
            'inv_qty' => $inverter['qty'], 'inv_brand' => '', 'inv_model' => '', 'inv_kva' => $inverter['kva'], 'inv_price_rp' => '',
            'monthly_kwh' => '',
            'monthly_bill_rp' => $lead['electricity_bill'] !== null ? (string) round((float) $lead['electricity_bill']) : '',
            'tariff_rp_kwh' => '1444.70',
            'work_start' => 0, 'work_end' => 24, 'saturday' => 1, 'sunday' => 1,
            'datasheets' => [],
        ];

        $saved = self::saved($proposal)['form'] ?? [];

        return array_merge($defaults, array_intersect_key($saved, $defaults));
    }

    /** Ambil & rapikan isian form dari request (hanya kunci yang dikenal). */
    public static function sanitizeForm(array $input, array $defaults): array
    {
        $form = [];
        foreach ($defaults as $key => $default) {
            if ($key === 'datasheets') {
                $form[$key] = array_values(array_filter((array) ($input[$key] ?? []), fn ($v) => is_string($v) && preg_match('/^[\w\-. ()\/]+\.pdf$/i', $v) && !str_contains($v, '..')));
            } elseif (in_array($key, ['saturday', 'sunday'], true)) {
                $form[$key] = empty($input[$key]) ? 0 : 1;
            } else {
                $form[$key] = trim((string) ($input[$key] ?? ''));
            }
        }
        if (!in_array($form['segment'], self::SEGMENTS, true)) {
            $form['segment'] = 'Commercial';
        }

        return $form;
    }

    /**
     * @return array{pdf:string,warnings:string[],derived:array,missing:string[]}
     * @throws \RuntimeException pesan aman ditampilkan ke pengguna
     */
    public static function build(array $proposal, array $form): array
    {
        if (!self::isAvailable()) {
            throw new \RuntimeException('Generator PLTS belum terpasang di tools/hme-proposal-plts.');
        }
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('Fungsi proc_open dinonaktifkan di PHP server.');
        }

        $gen = self::generatorDir() . '/proposal-generator';
        $pid = self::projectId($proposal);
        $pdir = $gen . '/projects/' . $pid;
        if (!is_dir($pdir) && !mkdir($pdir, 0775, true) && !is_dir($pdir)) {
            throw new \RuntimeException('Tidak dapat membuat folder proyek generator.');
        }
        $inputsPath = $pdir . '/inputs.json';
        file_put_contents($inputsPath, json_encode(self::toInputs($proposal, $form), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 1) gabung dengan _base + validasi
        $assemble = self::run([self::python(), '-I', '-X', 'utf8', self::generatorDir() . '/skills/buat-proposal-cni/scripts/assemble.py', $gen, $pid, $inputsPath], self::generatorDir(), 60);
        $report = json_decode($assemble['out'], true);
        if (!is_array($report)) {
            error_log('PltsProposalBuilder assemble: ' . $assemble['out'] . $assemble['err']);
            throw new \RuntimeException('Gagal memvalidasi data proyek (assemble). Periksa PYTHON_BIN dan library Python (jinja2).');
        }
        if (empty($report['ok'])) {
            return ['pdf' => '', 'warnings' => $report['warnings'] ?? [], 'derived' => [], 'missing' => $report['missing'] ?? ['(tidak diketahui)']];
        }

        // 2) hitung + render PDF (Chrome headless)
        $pdf = self::pdfPath($proposal);
        @unlink($pdf);
        $build = self::run([self::python(), '-X', 'utf8', 'build.py', 'projects/' . $pid], $gen, 240);
        if (!is_file($pdf)) {
            error_log('PltsProposalBuilder build: ' . $build['out'] . $build['err']);
            $tail = trim(implode("\n", array_slice(preg_split('/\R/', trim($build['out'] . "\n" . $build['err'])), -3)));
            throw new \RuntimeException('Gagal membuat PDF. ' . $tail);
        }

        $warnings = $report['warnings'] ?? [];
        foreach (preg_split('/\R/', $build['out']) as $line) {
            if (preg_match('/^\s*!|OVERFLOW/i', $line)) {
                $warnings[] = trim($line, " !\t");
            }
        }
        preg_match('/Payback ([\d.]+) yr · ROI (\d+)% · LCOE Rp (\d+)/u', $build['out'], $m);
        $derived = ($report['derived'] ?? []) + ($m ? ['payback_years' => (float) $m[1], 'roi_pct' => (int) $m[2], 'lcoe_rp_kwh' => (int) $m[3]] : []);

        return ['pdf' => $pdf, 'warnings' => array_values(array_unique($warnings)), 'derived' => $derived, 'missing' => []];
    }

    // ------------------------------------------------------------------

    private static function python(): string
    {
        return (string) Env::get('PYTHON_BIN', 'python');
    }

    /** @return array{out:string,err:string,code:int} */
    private static function run(array $cmd, string $cwd, int $timeout): array
    {
        @set_time_limit($timeout + 30);
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Tidak dapat menjalankan Python. Periksa PYTHON_BIN di .env.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $start = time();
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            if (time() - $start > $timeout) {
                proc_terminate($proc);
                throw new \RuntimeException('Pembuatan PDF melebihi batas waktu.');
            }
            usleep(100000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return ['out' => $out, 'err' => $err, 'code' => $code];
    }

    private static function lead(int $leadId): array
    {
        return Database::fetch(
            'SELECT l.customer_name, l.company_name, l.site_location, l.size_kwp, l.electricity_bill,
                    lt.name AS type_name, ls.name AS system_name
             FROM leads l
             LEFT JOIN lead_types lt ON lt.id = l.type_id
             LEFT JOIN lead_systems ls ON ls.id = l.system_id
             WHERE l.id = ?',
            [$leadId]
        ) ?: ['customer_name' => '', 'company_name' => '', 'site_location' => '', 'size_kwp' => null, 'electricity_bill' => null, 'type_name' => null, 'system_name' => null];
    }

    /** Peta data sistem ini -> inputs.json generator. Field kosong dibiarkan agar assemble.py melaporkannya. */
    private static function toInputs(array $proposal, array $f): array
    {
        $lead = self::lead((int) $proposal['lead_id']);
        $name = trim((string) ($lead['company_name'] ?: $lead['customer_name']));
        $ts = time();
        $num = static fn ($v) => $v === '' || $v === null ? null : (float) str_replace(',', '.', (string) $v);
        $int = static fn ($v) => $v === '' || $v === null ? null : (int) round((float) str_replace(',', '.', (string) $v));

        $panelQty = $int($f['panel_qty']);
        $panelWp = $int($f['panel_wp']);
        $invQty = $int($f['inv_qty']);
        $invKva = $num($f['inv_kva']);
        $kwp = ($panelQty && $panelWp) ? round($panelQty * $panelWp / 1000, 2) : ($lead['size_kwp'] !== null ? (float) $lead['size_kwp'] : null);
        $kva = ($invQty && $invKva) ? $invQty * $invKva : null;
        $subtotal = (float) $proposal['subtotal'] - (float) $proposal['discount_amount'];

        $material = $service = [];
        foreach (ProposalItem::forProposal((int) $proposal['id']) as $it) {
            $row = [$it['item_name'], (float) $it['quantity'] == (int) $it['quantity'] ? (int) $it['quantity'] : (float) $it['quantity'], (string) ($it['unit'] ?: 'ls'), trim((string) ($it['specification'] ?? ''))];
            if (preg_match(self::SERVICE_PATTERN, $it['item_name'])) {
                $service[] = $row;
            } else {
                $material[] = $row;
            }
        }
        $boq = [];
        if ($material) {
            $boq[] = ['section' => 'Material Utama', 'items' => $material];
        }
        if ($service) {
            $boq[] = ['section' => 'Jasa, Pengiriman & Perizinan', 'items' => $service];
        }

        $datasheets = [];
        foreach ($f['datasheets'] as $rel) {
            $parts = explode('/', $rel);
            $datasheets[] = ['title' => 'Datasheet · ' . $parts[0] . ' · ' . pathinfo($rel, PATHINFO_FILENAME), 'file' => '../../../datasheet/' . $rel];
        }

        $inputs = [
            'meta' => [
                'number' => $f['number'], 'date' => date('j F Y', $ts),
                'date_id' => date('j', $ts) . ' ' . self::MONTHS_ID[(int) date('n', $ts)] . ' ' . date('Y', $ts),
                'revision' => $f['revision'] ?: '00', 'prepared_by' => (string) (Auth::user()['name'] ?? Setting::get('company_name', 'HME')), 'language' => 'id',
            ],
            'client' => [
                'name' => $name, 'short_name' => $f['short_name'] ?: $name,
                'location' => $f['location_id'], 'location_id' => $f['location_id'],
                'city' => $f['city'], 'segment' => $f['segment'],
            ],
            'system' => [
                'type' => $f['type'], 'mounting' => $f['mounting'] ?: 'Rooftop',
                'capacity_kwp' => $kwp, 'capacity_kva' => $kva,
                'panel' => ['qty' => $panelQty, 'brand' => $f['panel_brand'], 'model' => $f['panel_model'], 'wp' => $panelWp],
                'inverter' => ['qty' => $invQty, 'brand' => $f['inv_brand'], 'model' => $f['inv_model'], 'kva' => $invKva, 'price_rp' => $int($f['inv_price_rp'])],
                'battery' => null, 'monitoring' => $f['monitoring'],
            ],
            'price' => ['total' => (int) round($subtotal), 'vat_pct' => $proposal['tax_percent'] !== null ? (float) $proposal['tax_percent'] : 11],
            'load' => [
                'monthly_kwh' => $int($f['monthly_kwh']), 'monthly_bill_rp' => $int($f['monthly_bill_rp']),
                'work_hours' => [$int($f['work_start']) ?? 0, $int($f['work_end']) ?? 24],
                'saturday' => (bool) $f['saturday'], 'sunday' => (bool) $f['sunday'], 'base_load_pct' => 100,
            ],
            'financial' => ['pln_tariff_rp_kwh' => $num($f['tariff_rp_kwh']), 'tariff_note' => 'Tarif PLN Rp ' . number_format((float) $num($f['tariff_rp_kwh']), 2, ',', '.') . '/kWh (termasuk PPJ)'],
            'datasheets' => $datasheets,
            'boq' => $boq,
        ];
        return $inputs;
    }
}
