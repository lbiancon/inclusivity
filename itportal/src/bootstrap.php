<?php

declare(strict_types=1);

use ItPortal\Auth\AuthService;
use ItPortal\Auth\Session;
use ItPortal\Config;
use ItPortal\Support\App;

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/autoload.php';
}

require_once __DIR__ . '/Support/helpers.php';

Config::loadEnv($root . '/config/.env');
Config::boot($root);

$session = Session::start((int) Config::get('app.session_timeout', 900));
$auth = new AuthService($session);
$app = App::boot($root, $session, $auth);

return [
    'root' => $root,
    'session' => $session,
    'auth' => $auth,
    'app' => $app,
];
