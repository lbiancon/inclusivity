<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$lineId = (string) ($_POST['line_id'] ?? '');
$wcLine = (string) ($_POST['wc_line'] ?? '%');
$start = (string) ($_POST['start_ts'] ?? '');
$end = (string) ($_POST['end_ts'] ?? '');

/** @var \ItPortal\Engineering\ScrapReportService $service */
$result = portal('engineering.scrap')->summarize($lineId, $wcLine, $start, $end);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--warning">' . View::e($result['message']) . '</div>';
    exit;
}

$row = $result['rows'][0] ?? [];
echo '<div class="result-meta">';
echo '<div><span>Line</span><strong>' . View::e($result['meta']['line_id'] ?? $lineId) . '</strong></div>';
echo '<div><span>WC</span><strong>' . View::e($result['meta']['wc_line'] ?? $wcLine) . '</strong></div>';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? '') . '</strong></div>';
echo '<div><span>Qty</span><strong>' . View::e($row['qty'] ?? '0') . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr>';
echo '<th>QTY</th>';
for ($i = 0; $i <= 10; $i++) {
    echo '<th>SCRAP_' . $i . '</th>';
}
echo '</tr></thead><tbody><tr>';
echo '<td>' . View::e($row['qty'] ?? '0') . '</td>';
for ($i = 0; $i <= 10; $i++) {
    echo '<td>' . View::e($row['scrap_' . $i] ?? '0') . '</td>';
}
echo '</tr></tbody></table></div>';
