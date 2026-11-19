<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

menucraft_db();
fwrite(STDOUT, "Schema MenuCraft pronto.\n");
