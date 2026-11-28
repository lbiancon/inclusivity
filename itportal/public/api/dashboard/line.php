<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$dashId = (string) ($_POST['dash_id'] ?? '');
$wcLine = (string) ($_POST['wc_line'] ?? '%');

/** @var \ItPortal\Dashboard\DashboardLineService $service */
$result = portal('dashboard.line')->byId($dashId, $wcLine);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--warning">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? '') . '</strong></div>';
echo '<div><span>Source</span><strong>' . View::e($result['meta']['source'] ?? '') . '</strong></div>';
echo '<div><span>Rows</span><strong>' . count($result['rows']) . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr>';
echo '<th>ID</th><th>WC</th><th>Group</th><th>Machine</th><th>Start</th><th>End</th><th>Qty</th><th>Scrap_0</th><th>Note</th>';
echo '</tr></thead><tbody>';
foreach ($result['rows'] as $row) {
    echo '<tr>';
    echo '<td>' . View::e($row['id'] ?? '') . '</td>';
    echo '<td>' . View::e($row['wc_line_code'] ?? '') . '</td>';
    echo '<td>' . View::e($row['wc_machine_group_code'] ?? '') . '</td>';
    echo '<td>' . View::e($row['wc_machine_number'] ?? '') . '</td>';
    echo '<td>' . View::e($row['start'] ?? '') . '</td>';
    echo '<td>' . View::e($row['end'] ?? '') . '</td>';
    echo '<td>' . View::e($row['qty'] ?? '0') . '</td>';
    echo '<td>' . View::e($row['scrap_0'] ?? '0') . '</td>';
    echo '<td>' . View::e($row['note'] ?? '') . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
