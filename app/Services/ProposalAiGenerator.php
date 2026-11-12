<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Models\ProposalItem;
use App\Models\Setting;

/**
 * Menyusun DRAFT teks proposal (ruang lingkup, timeline, syarat pembayaran,
 * garansi, syarat & ketentuan) dengan Claude dari data lead + item proposal.
 *
 * - Hasilnya TIDAK disimpan otomatis: controller hanya mengembalikannya ke
 *   form agar Sales meninjau/mengubah lalu menyimpan lewat tombol biasa.
 * - Data pribadi customer (nama kontak, telepon, email, alamat, ID PLN) dan
 *   harga/biaya TIDAK dikirim ke layanan luar; hanya data teknis proyek.
 * - Memakai HTTP langsung (cURL) karena SDK resmi PHP Anthropic butuh PHP 8.1+,
 *   sedangkan XAMPP di sini PHP 8.0.
 */
class ProposalAiGenerator
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2026-10-21';
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public static function isConfigured(): bool
    {
        return (string) Env::get('ANTHROPIC_API_KEY', '') !== '';
    }

    /**
     * @return array{scope_description:string,timeline_text:string,payment_terms:string,warranty:string,terms_conditions:string}
     * @throws \RuntimeException pesan aman ditampilkan ke pengguna
     */
    public static function generate(array $proposal): array
    {
        $apiKey = (string) Env::get('ANTHROPIC_API_KEY', '');
        if ($apiKey === '') {
            throw new \RuntimeException('Fitur AI belum diaktifkan: ANTHROPIC_API_KEY belum diisi di file .env.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Ekstensi cURL PHP belum aktif.');
        }

        $payload = [
            'model' => (string) Env::get('AI_PROPOSAL_MODEL', 'claude-opus-5-5'),
            'max_tokens' => 4000,
            'fallbacks' => 'default',
            'output_config' => [
                'effort' => 'medium',
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
            ],
            'system' => self::systemPrompt(),
            'messages' => [['role' => 'user', 'content' => self::buildBrief($proposal)]],
        ];

        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: ' . self::API_VERSION,
                'anthropic-beta: ' . self::FALLBACK_BETA,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log('ProposalAiGenerator curl: ' . $curlError);
            throw new \RuntimeException('Tidak dapat terhubung ke layanan AI. Periksa koneksi internet server.');
        }

        $body = json_decode((string) $raw, true);
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('API key AI ditolak. Periksa ANTHROPIC_API_KEY di file .env.');
        }
        if ($status === 429 || $status === 529) {
            throw new \RuntimeException('Layanan AI sedang sibuk atau batas pemakaian tercapai. Coba lagi sebentar lagi.');
        }
        if ($status !== 200 || !is_array($body)) {
            error_log('ProposalAiGenerator HTTP ' . $status . ': ' . substr((string) $raw, 0, 500));
            throw new \RuntimeException('Layanan AI mengembalikan error (' . $status . ').');
        }
        if (($body['stop_reason'] ?? '') === 'refusal') {
            throw new \RuntimeException('Permintaan ditolak oleh layanan AI. Isi manual atau ubah data proyek.');
        }

        $text = '';
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Respons AI tidak dapat dibaca. Coba generate ulang.');
        }

        $clean = static fn ($v) => trim((string) ($v ?? ''));

        return [
            'scope_description' => $clean($data['scope_description'] ?? ''),
            'timeline_text' => mb_substr($clean($data['timeline_text'] ?? ''), 0, 255),
            'payment_terms' => $clean($data['payment_terms'] ?? ''),
            'warranty' => mb_substr($clean($data['warranty'] ?? ''), 0, 255),
            'terms_conditions' => $clean($data['terms_conditions'] ?? ''),
        ];
    }

    private static function systemPrompt(): string
    {
        return <<<'TXT'
Anda adalah staf penawaran (pre-sales) berpengalaman di perusahaan EPC PLTS (tenaga surya) di Indonesia.
Tugas: menyusun teks bagian-bagian proposal penawaran dalam Bahasa Indonesia formal, ringkas, dan profesional,
berdasarkan data proyek yang diberikan.

Aturan:
- Gunakan hanya fakta dari data yang diberikan. Jangan mengarang angka (kapasitas, harga, tanggal, nama merek).
  Jika suatu data tidak tersedia, tulis secara umum atau tandai dengan "[isi manual]".
- Ruang lingkup: uraikan pekerjaan secara berpoin (gunakan "- " di awal baris), mengikuti item penawaran bila ada
  (pengadaan material, instalasi, commissioning, pengurusan dokumen, pelatihan/serah terima).
- Timeline: satu kalimat singkat (maks 200 karakter), misalnya estimasi durasi kerja sejak down payment.
  Bila tidak ada dasar, gunakan estimasi wajar dan beri tanda "(estimasi)".
- Syarat pembayaran: skema bertahap yang lazim (mis. DP, progres, pelunasan) dengan persentase wajar.
- Garansi: satu baris ringkas (maks 200 karakter) dan hanya hal umum, tanpa janji spesifik pada merek.
- Syarat & ketentuan: 4-7 poin berpoin ("- "), mencakup masa berlaku penawaran, perubahan lingkup, dan force majeure.
- Jangan menyebut harga atau total biaya; tabel harga ditangani sistem terpisah.
TXT;
    }

    private static function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'scope_description' => $str + ['description' => 'Ruang lingkup pekerjaan, berpoin'],
                'timeline_text' => $str + ['description' => 'Timeline pengerjaan, satu kalimat'],
                'payment_terms' => $str + ['description' => 'Syarat pembayaran bertahap'],
                'warranty' => $str + ['description' => 'Garansi, satu baris'],
                'terms_conditions' => $str + ['description' => 'Syarat dan ketentuan, berpoin'],
            ],
            'required' => ['scope_description', 'timeline_text', 'payment_terms', 'warranty', 'terms_conditions'],
            'additionalProperties' => false,
        ];
    }

    /** Ringkasan proyek untuk model — tanpa data kontak pribadi dan tanpa harga. */
    private static function buildBrief(array $proposal): string
    {
        $lead = Database::fetch(
            'SELECT l.company_name, l.site_location, l.size_kwp, l.electricity_bill, l.needs_description,
                    lt.name AS type_name, ls.name AS system_name, fs.name AS funding_name
             FROM leads l
             LEFT JOIN lead_types lt ON lt.id = l.type_id
             LEFT JOIN lead_systems ls ON ls.id = l.system_id
             LEFT JOIN funding_sources fs ON fs.id = l.funding_id
             WHERE l.id = ?',
            [(int) $proposal['lead_id']]
        ) ?: [];

        $lines = ['Data proyek:'];
        $add = static function (string $label, $value) use (&$lines): void {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "- {$label}: {$value}";
            }
        };

        $add('Perusahaan penyedia', Setting::get('company_name', ''));
        $add('Nama proyek', $proposal['project_name'] ?? '');
        $add('Customer (perusahaan)', $lead['company_name'] ?? '');
        $add('Tipe', $lead['type_name'] ?? '');
        $add('Sistem', $lead['system_name'] ?? '');
        $add('Kapasitas (kWp)', $lead['size_kwp'] ?? '');
        $add('Lokasi proyek', $lead['site_location'] ?? '');
        $add('Skema pendanaan', $lead['funding_name'] ?? '');
        $add('Kebutuhan customer', mb_substr((string) ($lead['needs_description'] ?? ''), 0, 1500));
        $add('Timeline yang sudah diisi Sales', $proposal['timeline_text'] ?? '');

        $items = ProposalItem::forProposal((int) $proposal['id']);
        if ($items) {
            $lines[] = '';
            $lines[] = 'Item penawaran (tanpa harga):';
            foreach (array_slice($items, 0, 40) as $i => $it) {
                $qty = rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.');
                $spec = trim((string) ($it['specification'] ?? ''));
                $lines[] = sprintf('%d. %s — %s %s%s', $i + 1, $it['item_name'], $qty, (string) $it['unit'], $spec !== '' ? ' (' . mb_substr($spec, 0, 160) . ')' : '');
            }
        } else {
            $lines[] = '';
            $lines[] = 'Belum ada item penawaran; susun ruang lingkup umum PLTS sesuai tipe dan sistem di atas.';
        }

        return implode("\n", $lines);
    }
}
