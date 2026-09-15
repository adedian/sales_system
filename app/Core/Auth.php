<?php

namespace App\Core;

use App\Models\LoginAttempt;
use App\Models\RememberToken;
use App\Models\User;

class Auth
{
    private const SESSION_KEY = '_auth_user_id';

    public static function attempt(string $username, string $password, bool $remember = false): array
    {
        $config = require __DIR__ . '/../Config/app.php';
        $maxAttempts = $config['login']['max_attempts'];
        $lockoutMinutes = $config['login']['lockout_minutes'];

        $identifier = strtolower($username);

        if (LoginAttempt::isLocked($identifier)) {
            $lockedUntil = LoginAttempt::lockedUntil($identifier);

            return [
                'success' => false,
                'locked' => true,
                'message' => 'Akun terkunci karena terlalu banyak percobaan gagal. Coba lagi setelah ' .
                    date('H:i', strtotime($lockedUntil)) . '.',
            ];
        }

        $user = User::findByUsername($username);

        if ($user === null || !password_verify($password, $user['password'])) {
            LoginAttempt::registerFailure($identifier, $maxAttempts, $lockoutMinutes);
            AuditLogger::log($user['id'] ?? null, 'login_failed', 'auth', null, null, ['username' => $username]);

            return ['success' => false, 'locked' => false, 'message' => 'Username atau password salah.'];
        }

        if ((int) $user['is_active'] !== 1) {
            return ['success' => false, 'locked' => false, 'message' => 'Akun tidak aktif. Hubungi administrator.'];
        }

        LoginAttempt::clear($identifier);
        self::login((int) $user['id']);

        if ($remember) {
            self::rememberUser((int) $user['id']);
        }

        User::update((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log((int) $user['id'], 'login_success', 'auth');

        return ['success' => true, 'locked' => false, 'user' => $user];
    }

    private static function login(int $userId): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, $userId);
        Acl::forgetCache();
    }

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId !== null) {
            AuditLogger::log($userId, 'logout', 'auth');
        }

        self::forgetRememberCookie();
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        return Session::get(self::SESSION_KEY);
    }

    public static function user(): ?array
    {
        static $cached = null;
        static $loaded = false;

        if ($loaded) {
            return $cached;
        }

        $id = self::id();
        $cached = $id !== null ? User::withRole($id) : null;
        $loaded = true;

        return $cached;
    }

    /**
     * Selector/validator persistent-login cookie (OWASP pattern): the
     * selector identifies the row, the validator is hashed before storage
     * so a stolen database dump alone can't be replayed as a cookie.
     */
    private static function rememberUser(int $userId): void
    {
        $config = require __DIR__ . '/../Config/app.php';
        $days = max(1, $config['login']['remember_days']);
        $cookieName = $config['login']['remember_cookie'];

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 86400));

        RememberToken::insert([
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        self::setRememberCookie($cookieName, $selector . ':' . $validator, time() + ($days * 86400));
    }

    /**
     * Called once per request (see bootstrap.php) before routing, so a
     * visitor with a valid remember cookie but no active session is
     * silently logged back in.
     */
    public static function attemptRememberLogin(): void
    {
        if (self::check()) {
            return;
        }

        $config = require __DIR__ . '/../Config/app.php';
        $cookieName = $config['login']['remember_cookie'];
        $cookie = $_COOKIE[$cookieName] ?? null;

        if (!is_string($cookie) || !str_contains($cookie, ':')) {
            return;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        $token = RememberToken::findBySelector($selector);

        if ($token === null || strtotime($token['expires_at']) < time()) {
            self::forgetRememberCookie();

            return;
        }

        if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Selector matched but validator didn't: possible theft/replay — burn the token.
            RememberToken::deleteBySelector($selector);
            self::forgetRememberCookie();

            return;
        }

        $user = User::find((int) $token['user_id']);
        if ($user === null || (int) $user['is_active'] !== 1) {
            RememberToken::deleteBySelector($selector);
            self::forgetRememberCookie();

            return;
        }

        // Rotate the token on every use so a captured cookie stops working once the real owner returns.
        RememberToken::deleteBySelector($selector);
        self::login((int) $user['id']);
        self::rememberUser((int) $user['id']);
    }

    private static function forgetRememberCookie(): void
    {
        $config = require __DIR__ . '/../Config/app.php';
        $cookieName = $config['login']['remember_cookie'];
        $cookie = $_COOKIE[$cookieName] ?? null;

        if (is_string($cookie) && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            RememberToken::deleteBySelector($selector);
        }

        if (isset($_COOKIE[$cookieName])) {
            self::setRememberCookie($cookieName, '', time() - 3600);
            unset($_COOKIE[$cookieName]);
        }
    }

    private static function setRememberCookie(string $name, string $value, int $expires): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
