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
(new \MenuCraft\Schema\Installer($pdo))->install();

$slug = 'qa-import-utenti';
$slug2 = 'qa-import-utenti-b';
foreach ([$slug, $slug2] as $s) {
    $pdo->prepare('DELETE FROM aziende WHERE slug = ?')->execute([$s]);
}
$pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (?, ?, \'welfare\', 5, 1, 0, 0, \'09:30:00\', \'18:00:00\', 0, \'sempre\')'
)->execute([$slug, $slug]);
$aziendaId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (?, ?, \'welfare\', 5, 1, 0, 0, \'09:30:00\', \'18:00:00\', 0, \'sempre\')'
)->execute([$slug2, $slug2]);
$aziendaId2 = (int) $pdo->lastInsertId();

$hash = password_hash('MenuCraft!2026', PASSWORD_BCRYPT, ['cost' => 4]);
$pdo->prepare(
    'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo) VALUES (?, ?, ?, ?, ?)'
)->execute([$aziendaId, 'CAT', 'Caterer', $hash, 'caterer']);

$import = new ImportUtenti($pdo);
$csv = $import->generaTemplateCsv($aziendaId, false, [$slug]);
$file = tempnam(sys_get_temp_dir(), 'utenti');
file_put_contents($file, $csv);

$attoreCaterer = ['ruolo' => 'caterer', 'azienda_id' => $aziendaId, 'id' => 0];
$esito = $import->importa($aziendaId, $file, 'utenti-import.csv', $attoreCaterer);
@unlink($file);

// Re-export + update email without password
$export = $import->generaTemplateCsv($aziendaId, true);
$rows = array_map('str_getcsv', explode("\n", str_replace("\r", '', preg_replace('/^\xEF\xBB\xBF/', '', $export))), array_fill(0, 20, ';'));
$header = array_shift($rows);
$idxEmail = array_search('email', $header, true);
$idxMail = array_search('mail_reminder', $header, true);
$idxMat = array_search('matricola', $header, true);
$aggiornato = false;
foreach ($rows as &$riga) {
    if (($riga[$idxMat] ?? '') === 'A101') {
        $riga[$idxEmail] = 'aggiornato@example.com';
        $riga[$idxMail] = 'Y';
        $aggiornato = true;
    }
}
unset($riga);
$righeOut = [$header];
foreach ($rows as $riga) {
    if ($riga === [null] || $riga === false || $riga === []) {
        continue;
    }
    if (count(array_filter($riga, static fn ($c) => trim((string) $c) !== '')) === 0) {
        continue;
    }
    $righeOut[] = $riga;
}
$csvUpdate = "\xEF\xBB\xBF";
foreach ($righeOut as $riga) {
    $csvUpdate .= implode(';', $riga) . "\r\n";
}
$file2 = tempnam(sys_get_temp_dir(), 'utenti2');
file_put_contents($file2, $csvUpdate);
$esito2 = $import->importa($aziendaId, $file2, 'update.csv', $attoreCaterer);
@unlink($file2);

// Multi-azienda da piattaforma
$csvMulti = "azienda;matricola;nome;password;pagamento;allergie;saldo;email;mail_reminder\r\n"
    . "{$slug};M001;Multi Uno;CambiaMe!2026;eredita;;;;N\r\n"
    . "{$slug2};M002;Multi Due;CambiaMe!2026;eredita;;;;N\r\n";
$file3 = tempnam(sys_get_temp_dir(), 'utenti3');
file_put_contents($file3, "\xEF\xBB\xBF" . $csvMulti);
$piattaformaId = (int) $pdo->query("SELECT id FROM utenti WHERE ruolo = 'piattaforma' LIMIT 1")->fetchColumn();
$esito3 = $import->importa(null, $file3, 'multi.csv', [
    'ruolo' => 'piattaforma',
    'azienda_id' => (int) $pdo->query("SELECT azienda_id FROM utenti WHERE id = {$piattaformaId}")->fetchColumn(),
    'id' => $piattaformaId,
]);
@unlink($file3);

$emailA101 = $pdo->query("SELECT email FROM utenti WHERE azienda_id = {$aziendaId} AND matricola = 'A101'")->fetchColumn();
$countM2 = (int) $pdo->query("SELECT COUNT(*) FROM utenti WHERE azienda_id = {$aziendaId2} AND matricola = 'M002'")->fetchColumn();

$auth = new AuthService($pdo);
$passwordOk = false;
try {
    $auth->cambiaPassword(
        (int) $pdo->query("SELECT id FROM utenti WHERE azienda_id = {$aziendaId} AND matricola = 'A101'")->fetchColumn(),
        $aziendaId,
        'CambiaMe!2026',
        'NuovaPass!2026',
    );
    $login = $auth->login($slug, 'A101', 'NuovaPass!2026');
    $passwordOk = (($login['utente']['matricola'] ?? '') === 'A101');
} catch (Throwable $e) {
    echo 'password_fail=' . $e->getMessage() . "\n";
}

$ok = $esito['creati'] === 3
    && $esito['saltati'] === 0
    && $aggiornato
    && $esito2['aggiornati'] >= 1
    && $emailA101 === 'aggiornato@example.com'
    && $esito3['creati'] === 2
    && $countM2 === 1
    && $passwordOk;

$log = [
    'creati' => $esito['creati'],
    'aggiornati_reimport' => $esito2['aggiornati'],
    'email_a101' => $emailA101,
    'multi' => $esito3['per_azienda'],
    'password_ok' => $passwordOk,
    'ok' => $ok,
];
file_put_contents('/opt/cursor/artifacts/import-utenti-repro.log', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo $ok ? "OK\n" : "FAIL\n";
exit($ok ? 0 : 1);
