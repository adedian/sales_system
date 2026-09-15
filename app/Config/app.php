<?php

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Sistem Internal Sales'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::get('APP_DEBUG', false),
    'url' => rtrim(Env::get('APP_URL', 'http://localhost'), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Jakarta'),

    'session' => [
        'name' => Env::get('SESSION_NAME', 'sales_system_session'),
        'lifetime_minutes' => (int) Env::get('SESSION_LIFETIME', 120),
    ],

    'login' => [
        'max_attempts' => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
        'remember_days' => (int) Env::get('REMEMBER_LIFETIME_DAYS', 30),
        'remember_cookie' => Env::get('REMEMBER_COOKIE_NAME', 'sales_system_remember'),
    ],

    'dashboard' => [
        'poll_interval_ms' => (int) Env::get('DASHBOARD_POLL_INTERVAL_MS', 30000),
    ],
];
