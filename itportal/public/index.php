<?php

declare(strict_types=1);

use ItPortal\View;

$app = require dirname(__DIR__) . '/src/bootstrap.php';

View::layout('home', $app['auth'], $app['session'], 'home', 'JSS IT Portal · Colleferro');
