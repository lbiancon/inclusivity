<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$lines = array();

$lines[] = 'I dati del banco non stanno in questa cartella. Senza config.local.php stanno in MySQL, database hxs, su 127.0.0.1 porta 3306, utente root.';
$lines[] = 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')';
$ini = php_ini_loaded_file();
$lines[] = 'php.ini: ' . ($ini !== false ? $ini : 'non trovato');
$software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'nessuno: la pagina non passa da Apache';
$lines[] = 'Server: ' . $software;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $lines[] = 'BLOCCATO: Banco X richiede PHP 8.1 o successivo. Con questo PHP il database hxs non viene aperto.';
}

$lines[] = 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'attivo' : 'ASSENTE');
$lines[] = 'pdo_oci: ' . (extension_loaded('pdo_oci') ? 'attivo' : 'assente');

if (extension_loaded('pdo_mysql')) {
    try {
        $mysql = new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4', 'root', '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $mysql->exec('CREATE DATABASE IF NOT EXISTS `hxs` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $mysql->exec('USE `hxs`');
        $lines[] = 'MySQL risponde. Il database di lavoro è hxs su 127.0.0.1:3306. Spostare la cartella del sito non sposta queste tabelle.';
    } catch (Exception $e) {
        $lines[] = 'MySQL non risponde. Avvia MySQL dal pannello XAMPP. ' . $e->getMessage();
    }
} else {
    $lines[] = 'Senza pdo_mysql il database hxs non si apre. In C:\\xampp\\php\\php.ini togli il ; da extension=pdo_mysql, salva, Stop e Start di Apache.';
}

$local = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($local)) {
    $over = require $local;
    $driver = is_array($over) && isset($over['driver']) ? (string) $over['driver'] : 'mysql';
    $lines[] = 'C\'è config.local.php con driver ' . $driver . '. mysql usa il database hxs. oci usa lo schema Oracle HXSIM. sqlite è l\'unico driver che crea data/sim.sqlite.';
}

echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Controllo Banco X</title>';
echo '<body style="font-family:Segoe UI,sans-serif;max-width:720px;margin:40px auto;line-height:1.45">';
echo '<h1>Controllo</h1><ul>';
foreach ($lines as $line) {
    echo '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
}
echo '</ul>';
echo '<p>Se MySQL risponde, apri <a href="index.php">index.php</a>. La riga in alto deve nominare il database hxs, non un file dentro la cartella.</p>';
echo '<p>Apri questa pagina come http://localhost/hydra-x-sim/controllo.php. Un doppio clic sul file in Esplora risorse non esegue PHP.</p>';
echo '</body></html>';
