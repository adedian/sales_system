<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SurveyPhoto;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Membuat PDF Survey Report dari data terstruktur + foto memakai dompdf
 * (murni PHP, jalan di hosting biasa tanpa Python/Chrome). Foto dimasukkan
 * sebagai data URI versi web (maks 1600px) dengan rasio asli.
 */
class SurveyReportPdf
{
    /**
     * @param array<string,mixed> $survey  hasil Survey::withRelations()
     * @param array<string,mixed> $report  baris survey_reports
     * @param array<string,array<string,mixed>> $data data terstruktur (sudah digabung default)
     * @param array<int,array<string,mixed>> $photos baris survey_photos
     */
    public static function render(array $survey, array $report, array $data, array $photos, ?string $reporterName): Dompdf
    {
        $byCat = ['site_overview' => [], 'layout' => [], 'notes' => []];
        foreach ($photos as $p) {
            $uri = SurveyPhoto::dataUri($p);
            if ($uri !== null) {
                $byCat[$p['category']][] = ['uri' => $uri, 'w' => (int) $p['width'], 'h' => (int) $p['height'], 'caption' => $p['caption']];
            }
        }

        $meta = [
            'title' => Setting::get('survey_report_title', 'SURVEY REPORT'),
            'doc_code' => Setting::get('survey_report_doc_code', ''),
            'footer' => Setting::get('survey_report_footer', ''),
            'revision' => str_pad((string) ((int) ($report['revision_no'] ?? 0)), 2, '0', STR_PAD_LEFT),
            'surveyor' => (string) ($survey['surveyor_name'] ?? ''),
            'reporter' => (string) ($reporterName ?? ''),
            'date' => format_date($data['site']['survey_date'] ?? $survey['scheduled_date'] ?? null, 'd/m/Y'),
            'summary' => (string) ($report['summary'] ?? ''),
            'header_uri' => self::headerUri(),
        ];

        $html = (static function () use ($data, $byCat, $meta): string {
            $photos = $byCat;
            ob_start();
            include __DIR__ . '/../Views/surveys/report_pdf.php';

            return (string) ob_get_clean();
        })();

        $cache = dirname(__DIR__, 2) . '/storage/cache/dompdf';
        if (!is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        if (is_dir($cache) && is_writable($cache)) {
            $options->set('tempDir', $cache);
            $options->set('fontCache', $cache);
        }
        $options->set('chroot', dirname(__DIR__, 2));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Nomor halaman + kode dokumen/revisi di footer setiap halaman.
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $w = $canvas->get_width();
        $h = $canvas->get_height();
        $left = trim(($meta['doc_code'] !== '' ? $meta['doc_code'] . ' · ' : '') . 'Rev' . $meta['revision']
            . (($report['status'] ?? '') !== 'completed' ? ' · DRAFT (belum disetujui)' : '')
            . ($meta['footer'] !== '' ? ' · ' . $meta['footer'] : ''));
        $canvas->page_text(51, $h - 36, $left, $font, 7.5, [0.45, 0.45, 0.45]);
        $canvas->page_text($w - 100, $h - 36, 'Hal {PAGE_NUM} / {PAGE_COUNT}', $font, 7.5, [0.45, 0.45, 0.45]);

        return $dompdf;
    }

    /** Gambar header (kop) dari Pengaturan Template, bila ada. */
    private static function headerUri(): ?string
    {
        $rel = Setting::get('survey_report_header_image', '');
        if ($rel === null || $rel === '') {
            return null;
        }
        $path = SurveyPhoto::absolute($rel);
        if (!is_file($path)) {
            return null;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }
}
