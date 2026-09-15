<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require __DIR__ . '/../Config/app.php';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_name($config['session']['name']);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::enforceIdleTimeout($config['session']['lifetime_minutes']);
        self::ageOldInput();
    }

    private static function enforceIdleTimeout(int $lifetimeMinutes): void
    {
        $lifetimeSeconds = $lifetimeMinutes * 60;
        $lastActivity = $_SESSION['_last_activity'] ?? null;

        if ($lastActivity !== null && (time() - $lastActivity) > $lifetimeSeconds) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            session_start();
        }

        $_SESSION['_last_activity'] = time();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Old-input is aged like a mini flash bag (see ageOldInput()): whatever
     * is flashed during this request becomes readable via old() on the
     * very next request only, then it's gone — so unrelated forms sharing
     * a field name (e.g. "username" on both the login and user-create
     * forms) never bleed into each other beyond that one redirect hop.
     */
    public static function flashOld(array $input): void
    {
        $_SESSION['_old_next'] = $input;
    }

    public static function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old'][$key] ?? $default;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old'], $_SESSION['_old_next']);
    }

    private static function ageOldInput(): void
    {
        $_SESSION['_old'] = $_SESSION['_old_next'] ?? [];
        $_SESSION['_old_next'] = [];
    }
}
