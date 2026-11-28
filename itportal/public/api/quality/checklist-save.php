<?php

declare(strict_types=1);

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

/** @var \ItPortal\Quality\ChecklistService $service */
$service = portal('quality.checklist');
$result = $service->saveAnswer(
    line: (string) ($_POST['line'] ?? ''),
    idQuestion: (string) ($_POST['id_question'] ?? ''),
    checkValue: (string) ($_POST['check_value'] ?? ''),
    question: (string) ($_POST['question'] ?? ''),
    userId: (string) ($_POST['user_id'] ?? ''),
    checkPart: (string) ($_POST['check_part'] ?? 'csl'),
    rev: (string) ($_POST['rev'] ?? '1'),
    note: (string) ($_POST['note'] ?? ''),
    status: (string) ($_POST['status'] ?? 'Y'),
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
