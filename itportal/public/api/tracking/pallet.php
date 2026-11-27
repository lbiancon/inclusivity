<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$pallet = (string) ($_POST['pallet_sn'] ?? '');
$server = (string) ($_POST['server'] ?? 'CFP1');

/** @var \ItPortal\Tracking\PalletSearchService $service */
$service = portal('tracking.pallet');
$result = $service->search($pallet, $server);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--warning">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Pallet</span><strong>' . View::e($result['pallet']) . '</strong></div>';
echo '<div><span>Server</span><strong>' . View::e($result['meta']['server'] ?? 'CFP1') . '</strong></div>';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? 'live') . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Cont ID</th><th>Part SN</th><th>Part</th><th>Qty</th><th>Timestamp</th></tr></thead><tbody>';
foreach ($result['rows'] as $row) {
    echo '<tr>';
    echo '<td>' . View::e($row['cont_id']) . '</td>';
    echo '<td>' . View::e($row['part_sn']) . '</td>';
    echo '<td>' . View::e($row['part']) . '</td>';
    echo '<td>' . View::e($row['qty']) . '</td>';
    echo '<td>' . View::e($row['timestamp']) . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
