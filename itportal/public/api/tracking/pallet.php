<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';
$app['auth']->requireLogin('/login.php');

$pallet = strtoupper(trim((string) ($_POST['pallet_sn'] ?? '')));
$server = (string) ($_POST['server'] ?? 'CFP1');

header('Content-Type: text/html; charset=utf-8');

if ($pallet === '') {
    echo '<div class="alert alert--warning">Enter a pallet / container id.</div>';
    exit;
}

echo '<div class="result-meta">';
echo '<div><span>Pallet</span><strong>' . View::e($pallet) . '</strong></div>';
echo '<div><span>Server</span><strong>' . View::e($server) . '</strong></div>';
echo '<div><span>Mode</span><strong>mock</strong></div>';
echo '</div>';
echo '<p class="muted">Demo pallet result. Production will call the legacy pallet_sn branch in tables.php / Oracle packout tables.</p>';
echo '<div class="table-wrap"><table class="data-table"><thead><tr><th>Cont ID</th><th>Part SN</th><th>Part</th><th>Qty</th><th>Timestamp</th></tr></thead><tbody>';
for ($i = 1; $i <= 5; $i++) {
    echo '<tr>';
    echo '<td>' . View::e($pallet) . '</td>';
    echo '<td>SN' . View::e(substr($pallet, 0, 8)) . str_pad((string) $i, 3, '0', STR_PAD_LEFT) . '</td>';
    echo '<td>DEMO-PART</td>';
    echo '<td>1</td>';
    echo '<td>' . date('Y-m-d H:i:s', time() - $i * 120) . '</td>';
    echo '</tr>';
}
echo '</tbody></table></div>';
