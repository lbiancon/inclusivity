<?php

declare(strict_types=1);

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$line = (string) ($_POST['line'] ?? '');
$checkname = (string) ($_POST['check_part'] ?? 'csl');

/** @var \ItPortal\Quality\ChecklistService $service */
$result = portal('quality.checklist')->logoutOperators($line, $checkname);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
