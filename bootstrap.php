<?php

use App\Core\Auth;
use App\Core\Env;
use App\Core\Session;

require_once __DIR__ . '/vendor/autoload.php';

Env::load(__DIR__ . '/.env');

date_default_timezone_set(config('app.timezone', 'Asia/Jakarta'));

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/storage/logs/error.log');

Session::start();
Auth::attemptRememberLogin();
