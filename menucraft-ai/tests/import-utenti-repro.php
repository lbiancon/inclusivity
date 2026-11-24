<?php

declare(strict_types=1);

putenv('MENUCRAFT_DB_HOST=127.0.0.1');
putenv('MENUCRAFT_DB_PORT=3306');
putenv('MENUCRAFT_DB_NAME=menucraft');
putenv('MENUCRAFT_DB_USER=menucraft');
putenv('MENUCRAFT_DB_PASS=menucraft');

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Services\AuthService;
use MenuCraft\Services\ImportUtenti;

$pdo = menucraft_db();
$slug = 'qa-import-utenti';
$pdo->prepare('DELETE FROM aziende WHERE slug = ?')->execute([$slug]);
$pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (?, ?, \'welfare\', 5, 1, 0, 0, \'09:30:00\', \'18:00:00\', 0, \'sempre\')'
)->execute([$slug, $slug]);
$aziendaId = (int) $pdo->lastInsertId();
$hash = password_hash('MenuCraft!2026', PASSWORD_BCRYPT, ['cost' => 4]);
$pdo->prepare(
    'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo) VALUES (?, ?, ?, ?, ?)'
)->execute([$aziendaId, 'CAT', 'Caterer', $hash, 'caterer']);

$csv = ImportUtenti::templateCsv();
$file = tempnam(sys_get_temp_dir(), 'utenti');
file_put_contents($file, $csv);

$esito = (new ImportUtenti($pdo))->importa($aziendaId, $file, 'utenti-import.csv');
@unlink($file);

echo json_encode([
    'creati' => $esito['creati'],
    'aggiornati' => $esito['aggiornati'],
    'saltati' => $esito['saltati'],
    'errori' => $esito['errori'],
], JSON_UNESCAPED_UNICODE) . "\n";

$ok = $esito['creati'] === 3 && $esito['saltati'] === 0;
$auth = new AuthService($pdo);
try {
    $auth->cambiaPassword(
        (int) $pdo->query("SELECT id FROM utenti WHERE azienda_id = {$aziendaId} AND matricola = 'A101'")->fetchColumn(),
        $aziendaId,
        'CambiaMe!2026',
        'NuovaPass!2026',
    );
    $login = $auth->login($slug, 'A101', 'NuovaPass!2026');
    $ok = $ok && (($login['utente']['matricola'] ?? '') === 'A101');
    echo "password_ok=1\n";
} catch (Throwable $e) {
    echo 'password_fail=' . $e->getMessage() . "\n";
    $ok = false;
}

echo $ok ? "OK\n" : "FAIL\n";
exit($ok ? 0 : 1);
