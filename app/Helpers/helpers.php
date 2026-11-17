<?php

use App\Core\Csrf;
use App\Core\Session;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        [$file, $path] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset($cache[$file])) {
            $configFile = __DIR__ . "/../Config/{$file}.php";
            $cache[$file] = is_file($configFile) ? require $configFile : [];
        }

        if ($path === null) {
            return $cache[$file] ?? $default;
        }

        $value = $cache[$file];
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = config('app.url', '');

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $relative = ltrim($path, '/');
        $absolute = __DIR__ . '/../../public/assets/' . $relative;
        $version = is_file($absolute) ? filemtime($absolute) : null;

        return url('assets/' . $relative) . ($version !== null ? '?v=' . $version : '');
    }
}

if (!function_exists('redirect_to')) {
    function redirect_to(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return Session::getFlash($key, $default);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime, string $format = 'd M Y H:i'): string
    {
        if (empty($datetime)) {
            return '-';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return '-';
        }

        // Singkatan bulan berbahasa Indonesia (date() hanya menghasilkan bahasa Inggris).
        $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $format = str_replace('M', '\\' . implode('\\', str_split($bulan[(int) date('n', $timestamp) - 1])), $format);

        return date($format, $timestamp);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd M Y'): string
    {
        return format_datetime($date, $format);
    }
}

if (!function_exists('audit_label')) {
    /**
     * Nama aksi audit log dalam bahasa Indonesia (mis. "lead_marked_won" ->
     * "Lead ditandai menang"). Aksi yang belum dikenal diterjemahkan per kata.
     */
    function audit_label(string $action): string
    {
        static $explicit = [
            'login_success' => 'Masuk ke sistem',
            'login_failed' => 'Gagal masuk',
            'logout' => 'Keluar dari sistem',
            'lead_marked_won' => 'Lead ditandai Menang (Won)',
            'lead_marked_lost' => 'Lead ditandai Kalah (Lost)',
            'survey_gate_override' => 'Gerbang Survey di-override',
            'survey_report_submitted' => 'Survey Report diajukan untuk diperiksa',
            'survey_report_completed' => 'Survey Report disetujui',
            'survey_report_revision' => 'Survey Report perlu revisi',
            'proposal_status_changed' => 'Status Proposal diubah',
            'lead_status_changed' => 'Status Lead diubah',
        ];
        static $words = [
            'lead' => 'Lead', 'queue' => 'Antrian', 'engineer' => 'Engineer', 'procurement' => 'Procurement',
            'proposal' => 'Proposal', 'prelim' => 'Prelim', 'survey' => 'Survey', 'report' => 'Laporan',
            'followup' => 'Follow Up', 'user' => 'Pengguna', 'role' => 'Role', 'price' => 'Harga',
            'validation' => 'validasi', 'created' => 'dibuat', 'updated' => 'diperbarui', 'deleted' => 'dihapus',
            'restored' => 'dipulihkan', 'sent' => 'dikirim', 'approved' => 'disetujui', 'rejected' => 'ditolak',
            'submitted' => 'diajukan', 'requested' => 'diajukan', 'accepted' => 'diterima', 'completed' => 'selesai',
            'cancelled' => 'dibatalkan', 'scheduled' => 'dijadwalkan', 'rescheduled' => 'dijadwalkan ulang',
            'in' => '', 'progress' => 'sedang berlangsung', 'started' => 'dimulai', 'status' => 'status',
            'changed' => 'diubah', 'priority' => 'prioritas', 'assigned' => 'ditugaskan', 'reassigned' => 'ditugaskan ulang',
            'revision' => 'revisi', 'review' => 'pemeriksaan', 'note' => 'catatan', 'added' => 'ditambahkan',
            'logged' => 'dicatat', 'document' => 'dokumen', 'uploaded' => 'diunggah', 'download' => 'unduh',
            'won' => 'menang', 'lost' => 'kalah', 'reopened' => 'dibuka kembali', 'marked' => 'ditandai',
            'viewed' => 'dilihat', 'negotiation' => 'negosiasi', 'item' => 'item', 'password' => 'password',
            'toggled' => 'diubah', 'enqueued' => 'masuk antrian', 'auth' => 'Autentikasi',
        ];

        if (isset($explicit[$action])) {
            return $explicit[$action];
        }

        $parts = array_filter(array_map(fn ($w) => $words[$w] ?? $w, explode('_', strtolower($action))), fn ($w) => $w !== '');
        $text = implode(' ', $parts);

        return $text === '' ? $action : mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return \App\Core\Acl::can($permission);
    }
}

if (!function_exists('collect_first_error')) {
    /**
     * @param array<string, string[]> $errors Validator::errors() shape
     */
    function collect_first_error(array $errors, string $default = 'Data tidak valid.'): string
    {
        foreach ($errors as $field => $messages) {
            if (!empty($messages[0])) {
                return ucfirst(str_replace('_', ' ', $field)) . ' ' . $messages[0];
            }
        }

        return $default;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return \App\Core\Auth::user();
    }
}
