<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$auth = $app['auth'];
$session = $app['session'];

$auth->requireLogin('/login.php');

$user = $session->user();
$allowed = ['SU', 'D', 'QS', 'QI', 'QA', 'QAI'];
if (!in_array($user['roles'], $allowed, true)) {
    header('Location: /index.php');
    exit;
}

View::layout('quality/checklist', $auth, $session, 'quality', 'Checklist · JSS IT Portal', [
    'user' => $user,
]);
