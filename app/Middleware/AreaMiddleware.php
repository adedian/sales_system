<?php

namespace App\Middleware;

use App\Core\Acl;
use App\Core\View;

/**
 * Pembatas "wilayah kerja" berdasarkan ROLE, di atas izin (permission).
 * Izin bisa diubah lewat menu Role & Hak Akses; batas ini tidak bisa
 * dilonggarkan lewat izin sehingga pembagian tanggung jawab tetap terjaga:
 *
 *   area:lead    -> hanya Sales (dan pengawas: Super Admin/Admin Sales/Manager)
 *   area:survey  -> Sales, Surveyor, Engineer, pengawas; TIDAK Sales Engineer
 *
 * Berlaku untuk halaman maupun API (JSON untuk /api/*).
 */
class AreaMiddleware
{
    public function handle(?string $area = null): bool
    {
        $allowed = match ($area) {
            'lead' => Acl::managesLeads(),
            'survey' => Acl::touchesSurvey(),
            default => false,
        };

        if ($allowed) {
            return true;
        }

        http_response_code(403);
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (str_contains($path, '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Akses ditolak: wilayah kerja role Anda tidak mencakup modul ini.']);

            return false;
        }

        View::render('errors/403');

        return false;
    }
}
