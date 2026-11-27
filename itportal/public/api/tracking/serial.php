<?php

declare(strict_types=1);

use ItPortal\Tracking\SerialTracker;

$app = require dirname(__DIR__, 3) . '/src/bootstrap.php';

$serial = (string) ($_POST['part_sn'] ?? '');
$server = (string) ($_POST['server'] ?? 'CFP1');
$parent = isset($_POST['searchplant']) && $_POST['searchplant'] === 'y';

$result = (new SerialTracker())->search($serial, $server, $parent);

header('Content-Type: text/html; charset=utf-8');
require dirname(__DIR__, 3) . '/templates/tracking/_serial_result.php';
