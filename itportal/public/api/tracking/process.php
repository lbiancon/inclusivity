<?php

declare(strict_types=1);

use ItPortal\View;

require dirname(__DIR__, 3) . '/src/bootstrap.php';

$serial = (string) ($_POST['part_sn'] ?? '');
$procType = (string) ($_POST['proc_type'] ?? '0');
$fields = (int) ($_POST['fields'] ?? 20);

/** @var \ItPortal\Tracking\ProcessSearchService $service */
$service = portal('tracking.process');
$result = $service->search($serial, $procType, $fields);

header('Content-Type: text/html; charset=utf-8');

if (!$result['ok']) {
    echo '<div class="alert alert--danger">' . View::e($result['message']) . '</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Serial</span><strong>' . View::e($result['serial']) . '</strong></div>';
echo '<div><span>Table</span><strong>' . View::e($result['table']) . '</strong></div>';
echo '<div><span>Mode</span><strong>' . View::e($result['meta']['mode'] ?? '') . '</strong></div>';
echo '<div><span>Rows</span><strong>' . count($result['rows']) . '</strong></div>';
echo '</div>';
echo '<p class="muted">' . View::e($result['message']) . '</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr>';
echo '<th>Line</th><th>Group</th><th>#</th><th>Type</th><th>Timestamp</th><th>Values</th>';
echo '</tr></thead><tbody>';
foreach ($result['rows'] as $row) {
    $vals = [];
    foreach ($row['values'] as $k => $v) {
        $vals[] = View::e($k) . '=' . View::e((string) $v);
    }
    echo '<tr>';
    echo '<td>' . View::e($row['wc_line_code']) . '</td>';
    echo '<td>' . View::e($row['wc_machine_group_code']) . '</td>';
    echo '<td>' . View::e($row['wc_machine_number']) . '</td>';
    echo '<td>' . View::e($row['machine_type']) . '</td>';
    echo '<td>' . View::e($row['timestamp']) . '</td>';
    echo '<td>' . implode('; ', $vals) . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
