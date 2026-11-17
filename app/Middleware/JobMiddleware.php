<?php

namespace App\Middleware;

use App\Core\Jobs;
use App\Core\View;

/**
 * Pembatas akses berdasarkan JOB (bukan hanya role). Dipakai untuk pekerjaan
 * yang dimiliki satu divisi, mis. `job:surveyor` untuk pengerjaan Survey
 * lapangan. Super Admin selalu lolos. Contoh di route: 'job:surveyor'.
 */
class JobMiddleware
{
    public function handle(?string $jobs = null): bool
    {
        $allowed = $jobs === null ? [] : array_filter(array_map('trim', explode(',', $jobs)));

        if ($allowed === [] || Jobs::has('super_admin') || Jobs::has(...$allowed)) {
            return true;
        }

        http_response_code(403);
        View::render('errors/403');

        return false;
    }
}
