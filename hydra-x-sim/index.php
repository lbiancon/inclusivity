<?php

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    http_response_code(500);
    $version = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="it"><meta charset="utf-8"><title>Banco X</title>';
    echo '<body style="font-family:Segoe UI,sans-serif;max-width:640px;margin:40px auto;line-height:1.45">';
    echo '<h1>PHP ' . $version . ' è troppo vecchio</h1>';
    echo '<p>Banco X richiede PHP 8.1 o successivo. Lo script si interrompe prima di creare <code>data\\sim.sqlite</code>: non è un problema di permessi della cartella.</p>';
    echo '<p>In XAMPP il PHP di Apache è quello del pannello. Serve una installazione con PHP 8.1 o successivo. Poi Stop e Start su Apache.</p>';
    echo '<p>Apri anche <a href="controllo.php">controllo.php</a>: funziona anche con questo PHP e dice cosa vede il server.</p>';
    echo '</body></html>';
    exit;
}

require __DIR__ . '/lib/app.php';
