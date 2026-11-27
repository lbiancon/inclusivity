<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/src/bootstrap.php';
$app['auth']->logout();

header('Location: /index.php');
exit;
