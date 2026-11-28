<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$line = (string) ($_POST['line'] ?? '');
$checkPart = (string) ($_POST['check_part'] ?? 'csl');
$rev = (string) ($_POST['rev'] ?? '1');

/** @var \ItPortal\Quality\ChecklistService $service */
$service = portal('quality.checklist');
$result = $service->config($line, $checkPart, $rev);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--warning">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Line</span><strong>' . View::e($result['meta']['line'] ?? $line) . '</strong></div>';
echo '<div><span>Part</span><strong>' . View::e($result['meta']['check_part'] ?? $checkPart) . '</strong></div>';
echo '<div><span>Rev</span><strong>' . View::e($result['meta']['rev'] ?? $rev) . '</strong></div>';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? '') . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Ctrl</th><th>Question</th><th>Type</th><th>Status</th></tr></thead><tbody>';
foreach ($result['items'] as $item) {
    echo '<tr>';
    echo '<td>' . View::e((string) ($item['check_ctrl'] ?? $item[4] ?? '')) . '</td>';
    echo '<td>' . View::e((string) ($item['question'] ?? $item['QUESTION'] ?? '')) . '</td>';
    echo '<td>' . View::e((string) ($item['check_type'] ?? $item['CHECK_TYPE'] ?? '')) . '</td>';
    echo '<td>' . View::e((string) ($item['status'] ?? $item['STATUS'] ?? '')) . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
