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
        return url('assets/' . ltrim($path, '/'));
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

        return $timestamp === false ? '-' : date($format, $timestamp);
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
