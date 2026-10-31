#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
php -r 'exit(PHP_VERSION_ID >= 80512 ? 0 : 1);' || {
  echo "Serve PHP 8.5.12 o successivo (correzioni del 21 ottobre 2026). Trovato: $(php -v | head -n 1)" >&2
  echo "Non reinstallare XAMPP sopra la cartella già in uso: htdocs e i database verrebbero sostituiti." >&2
  exit 1
}
php -r '
require "src/bootstrap.php";
try {
    Lumina\Db::connect();
} catch (Throwable $e) {
    fwrite(STDERR, "MySQL non raggiungibile con lumina@127.0.0.1.\n");
    fwrite(STDERR, "Crea database e utente, poi rilancia:\n");
    fwrite(STDERR, "CREATE DATABASE lumina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
    fwrite(STDERR, "CREATE USER \"lumina\"@\"127.0.0.1\" IDENTIFIED BY \"lumina_local\";\n");
    fwrite(STDERR, "GRANT ALL ON lumina.* TO \"lumina\"@\"127.0.0.1\";\n");
    exit(1);
}
'
php bin/seed.php
echo "Avvio: php bin/serve.sh"
