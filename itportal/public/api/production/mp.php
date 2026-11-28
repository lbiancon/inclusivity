<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$plant = (string) ($_POST['plant'] ?? 'IF');

/** @var \ItPortal\Production\ManpowerService $service */
$result = portal('production.mp')->roster($plant);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--warning">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Plant</span><strong>' . View::e($result['meta']['plant'] ?? $plant) . '</strong></div>';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? '') . '</strong></div>';
echo '<div><span>Source</span><strong>' . View::e($result['meta']['source'] ?? '') . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr>';
echo '<th>Empl ID</th><th>Name</th><th>Center</th>';
echo '</tr></thead><tbody>';
foreach ($result['rows'] as $row) {
    echo '<tr>';
    echo '<td>' . View::e($row['empl_id'] ?? '') . '</td>';
    echo '<td>' . View::e($row['fullname'] ?? '') . '</td>';
    echo '<td>' . View::e($row['center'] ?? '') . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
