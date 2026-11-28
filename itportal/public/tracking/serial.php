<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$session = $app['session'];
$user = $session->user();
$canPickServer = in_array($user['roles'], ['SU', 'D'], true);

View::layout('tracking/serial', $app['auth'], $session, 'tracking', 'Serial Track · JSS IT Portal', [
    'user' => $user,
    'canPickServer' => $canPickServer,
    'result' => null,
]);
