<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$auth = $app['auth'];
$session = $app['session'];

$auth->requireLogin('/login.php');

$user = $session->user();
$allowed = ['SU', 'D', 'QI', 'QA', 'CI', 'CA', 'EI', 'MAA', 'MAI'];
if (!in_array($user['roles'], $allowed, true)) {
    header('Location: /index.php');
    exit;
}

$canPickServer = in_array($user['roles'], ['SU', 'D'], true);

View::layout('tracking/pallet', $auth, $session, 'tracking', 'Pallet Track · JSS IT Portal', [
    'user' => $user,
    'canPickServer' => $canPickServer,
]);
