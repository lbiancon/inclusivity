<?php

declare(strict_types=1);

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$line = (string) ($_POST['line'] ?? '');
$badge = (string) ($_POST['badge'] ?? '');
$checkname = (string) ($_POST['check_part'] ?? 'csl');
$rev = (string) ($_POST['rev'] ?? '1');

/** @var \ItPortal\Quality\ChecklistService $service */
$service = portal('quality.checklist');
$result = $service->loginOperator($line, $badge, $checkname, $rev);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
