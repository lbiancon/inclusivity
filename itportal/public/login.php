<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__) . '/src/bootstrap.php';
/** @var \ItPortal\Auth\AuthService $auth */
$auth = $app['auth'];
$session = $app['session'];

if ($auth->check() && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

$error = null;
$errorType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->attemptFromRequest($_POST);

    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($result->ok) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'redirect' => $result->redirect]);
            exit;
        }
        $session->flash('auth_message', $result->message);
        $session->flash('auth_type', 'success');
        header('Location: ' . $result->redirect);
        exit;
    }

    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: text/html; charset=utf-8');
        echo htmlspecialchars($result->message, ENT_QUOTES, 'UTF-8');
        exit;
    }

    $error = $result->message;
    $errorType = $result->type;
}

View::layout('login', $auth, $session, 'login', 'Login · JSS IT Portal', [
    'error' => $error,
    'errorType' => $errorType,
]);
