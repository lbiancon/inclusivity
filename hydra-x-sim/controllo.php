<?php

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$dir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$path = $dir . DIRECTORY_SEPARATOR . 'sim.sqlite';
$lines = array();

$lines[] = 'PHP ' . PHP_VERSION . ' (' . PHP_SAPI . ')';
$ini = php_ini_loaded_file();
$lines[] = 'php.ini: ' . ($ini !== false ? $ini : 'non trovato');
$software = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'nessuno: la pagina non passa da Apache';
$lines[] = 'Server: ' . $software;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $lines[] = 'BLOCCATO: Banco X richiede PHP 8.1 o successivo. Con questo PHP il file sim.sqlite non viene creato. Non è la cartella: lo script si ferma prima.';
}

$lines[] = 'pdo_sqlite: ' . (extension_loaded('pdo_sqlite') ? 'attivo' : 'ASSENTE');
$lines[] = 'pdo_oci: ' . (extension_loaded('pdo_oci') ? 'attivo' : 'assente, si usa SQLite');

if (!is_dir($dir)) {
    $made = @mkdir($dir, 0775, true);
    $lines[] = $made ? 'Cartella data creata.' : 'Non creo la cartella data. ' . probe_error();
}

$probe = $dir . DIRECTORY_SEPARATOR . '.write-probe';
$wrote = @file_put_contents($probe, '1');
if ($wrote === false) {
    $lines[] = 'SCRITTURA NEGATA in ' . $dir . '. ' . probe_error();
    $lines[] = 'Il tuo utente vede la cartella scrivibile. Apache gira con un altro account e non può creare sim.sqlite.';
} else {
    @unlink($probe);
    $lines[] = 'Scrittura in data: ok';
}

if (extension_loaded('pdo_sqlite') && $wrote !== false) {
    try {
        $dsnPath = str_replace('\\', '/', $path);
        $pdo = new PDO('sqlite:' . $dsnPath, null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $pdo->query('SELECT 1');
        $lines[] = is_file($path) ? 'File creato: ' . $path : 'PDO ha risposto ma il file non è dove me lo aspetto: ' . $path;
    } catch (Exception $e) {
        $lines[] = 'SQLite ha rifiutato ' . $path . '. ' . $e->getMessage();
    }
} elseif (!extension_loaded('pdo_sqlite')) {
    $lines[] = 'Senza pdo_sqlite il file non può nascere. In C:\\xampp\\php\\php.ini togli il ; da extension=pdo_sqlite e da extension=sqlite3, salva, Stop e Start di Apache.';
}

$local = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (is_file($local)) {
    $lines[] = 'C\'è config.local.php. Se dentro c\'è driver oci e manca pdo_oci, le versioni precedenti non creavano sim.sqlite. Scarica di nuovo il banco, oppure rinomina quel file.';
}

echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Controllo Banco X</title>';
echo '<body style="font-family:Segoe UI,sans-serif;max-width:720px;margin:40px auto;line-height:1.45">';
echo '<h1>Controllo</h1><ul>';
foreach ($lines as $line) {
    echo '<li>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</li>';
}
echo '</ul>';
echo '<p>Se l\'ultima riga dice che il file è stato creato, apri <a href="index.php">index.php</a>.</p>';
echo '<p>Apri questa pagina come http://localhost/hydra-x-sim/controllo.php. Un doppio clic sul file in Esplora risorse non esegue PHP e non crea nulla.</p>';
echo '</body></html>';

function probe_error()
{
    $last = error_get_last();
    return isset($last['message']) ? $last['message'] : 'senza dettaglio';
}
