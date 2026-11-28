<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$auth = $app['auth'];
$session = $app['session'];
$auth->requireLogin('/login.php');

$user = $session->user();
$allowed = ['SU', 'ITM', 'IT', 'D'];
if (!in_array($user['roles'], $allowed, true)) {
    header('Location: /index.php');
    exit;
}

View::layout('dashboard/line', $auth, $session, 'it', 'Dashboard Line · JSS IT Portal', [
    'user' => $user,
]);
