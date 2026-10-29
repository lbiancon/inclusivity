<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Lumina\Db;

if (PHP_OS_FAMILY !== 'Windows') {
    echo 'PHP ' . PHP_VERSION . " è aggiornato.\n";
    echo "Su Windows, bin\\xampp.bat lascia le app di htdocs su http://localhost e apre Lumina su http://127.0.0.1:8080.\n";
    echo "Qui usa php bin/setup.sh e php bin/serve.sh.\n";
    exit(0);
}

putenv('LUMINA_DB_HOST=127.0.0.1');
putenv('LUMINA_DB_PORT=3306');
putenv('LUMINA_DB_NAME=lumina');
putenv('LUMINA_DB_USER=lumina');
putenv('LUMINA_DB_PASS=lumina_local');

lumina_xampp_database();

echo "Le app già in htdocs restano su http://localhost\n";
echo "Lumina usa solo http://127.0.0.1:8080 e non modifica Apache.\n";
echo "Dal pannello XAMPP tieni avviati Apache e MySQL.\n";

require __DIR__ . '/seed.php';

function lumina_xampp_database(): void
{
    $password = getenv('XAMPP_MYSQL_ROOT_PASSWORD');
    $password = is_string($password) ? $password : '';
    $pdo = null;
    $last = null;
    foreach (['127.0.0.1', 'localhost'] as $host) {
        try {
            $pdo = new PDO("mysql:host={$host};port=3306;charset=utf8mb4", 'root', $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            break;
        } catch (PDOException $exception) {
            $last = $exception;
        }
    }
    if (!$pdo instanceof PDO) {
        $denied = $last instanceof PDOException && str_contains($last->getMessage(), '[1045]');
        if ($denied) {
            fwrite(STDERR, "MariaDB risponde, ma root non entra con questa password.\n");
            fwrite(STDERR, "Se root ha una password: set XAMPP_MYSQL_ROOT_PASSWORD=... e rilancia.\n");
            fwrite(STDERR, "Altrimenti esegui in phpMyAdmin le istruzioni qui sotto, poi php bin\\seed.php\n\n");
            fwrite(STDERR, Db::localAccountHelp() . "\n");
        } else {
            fwrite(STDERR, "MariaDB di XAMPP non risponde. Avvia MySQL dal pannello e rilancia bin\\xampp.bat\n");
        }
        if ($last instanceof PDOException) {
            fwrite(STDERR, $last->getMessage() . "\n");
        }
        exit(1);
    }
    Db::installLocal($pdo);
    Db::connect();
}
