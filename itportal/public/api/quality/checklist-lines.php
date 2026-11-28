<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

/** @var \ItPortal\Quality\ChecklistService $service */
$service = portal('quality.checklist');
$result = $service->listLines();

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--danger">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<p class="muted">' . View::e($result['message']) . ' · mode ' . View::e($result['meta']['mode'] ?? '') . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Line</th><th>Rev</th><th></th></tr></thead><tbody>';
foreach ($result['lines'] as $line) {
    $lineId = View::e($line['line']);
    echo '<tr>';
    echo '<td>' . $lineId . '</td>';
    echo '<td>' . View::e($line['rev']) . '</td>';
    echo '<td><button type="button" class="btn btn--primary btn-load-config" data-line="' . $lineId . '" data-rev="' . View::e($line['rev']) . '">Open</button></td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
