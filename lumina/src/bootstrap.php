<?php

declare(strict_types=1);

define('LUMINA_ROOT', dirname(__DIR__));

if (PHP_VERSION_ID < 80512) {
    $message = 'PHP ' . PHP_VERSION . " è precedente a 8.5.12.\n"
        . "Il 21 ottobre 2026 sono state corrette due falle critiche, CVE-2026-109618 e CVE-2026-109619: un visitatore può eseguire codice sul server prima ancora che parta l'applicazione. Non esiste una configurazione che le chiuda del tutto.\n"
        . "Non reinstallare XAMPP sopra la cartella che usi già: perderesti htdocs e i database. Le build 8.2.35, 8.3.36 e 8.4.27 sono corrette, ma Lumina usa PHP 8.5.\n";
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, $message);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit(1);
}

$luminaExtensions = array_values(array_filter(
    ['pdo_mysql', 'mbstring', 'intl'],
    static fn (string $name): bool => !extension_loaded($name),
));
if ($luminaExtensions !== []) {
    $message = "A PHP mancano queste estensioni: " . implode(', ', $luminaExtensions) . ".\n"
        . "Nel php.ini di PHP 8.5 togli il punto e virgola davanti a extension=pdo_mysql, extension=mbstring ed extension=intl.\n"
        . "Controlla anche extension_dir = \"C:\\xampp\\php\\ext\" e che in quella cartella ci siano i file .dll.\n"
        . "Poi riavvia Apache.\n";
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, $message);
    } else {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
    exit(1);
}

date_default_timezone_set('UTC');

require LUMINA_ROOT . '/src/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lumina\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = LUMINA_ROOT . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});
