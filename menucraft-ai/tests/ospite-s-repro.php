<?php

declare(strict_types=1);

/**
 * Riproduzione mirata: ospite a carico azienda vs dipendente → colonna S.
 * Uso: php tests/ospite-s-repro.php
 */

putenv('MENUCRAFT_DB_HOST=127.0.0.1');
putenv('MENUCRAFT_DB_PORT=3306');
putenv('MENUCRAFT_DB_NAME=menucraft');
putenv('MENUCRAFT_DB_USER=menucraft');
putenv('MENUCRAFT_DB_PASS=menucraft');

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Services\ExportCucina;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\OrderService;

$pdo = menucraft_db();
$fuso = new DateTimeZone('Europe/Rome');
$adesso = new DateTimeImmutable('now', $fuso);
$dt = $adesso->modify('+1 day');
while ((int) $dt->format('N') >= 6) {
    $dt = $dt->modify('+1 day');
}
$data = $dt->format('Y-m-d');
$slug = 'qa-ospite-s-' . $dt->format('Ymd');

$pdo->prepare('DELETE FROM aziende WHERE slug = ?')->execute([$slug]);

$azienda = $pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (:nome, :slug, \'dipendente\', 5, 1, 1, 1, \'09:30:00\', \'23:59:00\', 0, \'sempre\')'
);
$azienda->execute(['nome' => $slug, 'slug' => $slug]);
$aziendaId = (int) $pdo->lastInsertId();
$hash = password_hash('MenuCraft!2026', PASSWORD_BCRYPT, ['cost' => 4]);
$utente = $pdo->prepare(
    'INSERT INTO utenti (azienda_id, matricola, nome, password_hash, ruolo) VALUES (?, ?, ?, ?, ?)'
);
$utente->execute([$aziendaId, 'DIP', 'Dipendente QA', $hash, 'dipendente']);
$dipendente = (int) $pdo->lastInsertId();
$utente->execute([$aziendaId, 'CAT', 'Caterer QA', $hash, 'caterer']);
$caterer = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO portafogli (azienda_id, utente_id, saldo_centesimi) VALUES (?, ?, ?)')
    ->execute([$aziendaId, $dipendente, 5000]);

$pdo->prepare(
    'INSERT INTO piatti (azienda_id, codice, nome, categoria, prezzo) VALUES (?, ?, ?, ?, ?)'
)->execute([$aziendaId, 'BE1', 'Acqua', 'bevanda', '0.80']);
$piattoId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO menu_pubblicazioni (azienda_id, pubblicato_il, visibile_dal, valido_dal, valido_al, caricato_da)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$aziendaId, $adesso->format('Y-m-d H:i:s'), $adesso->format('Y-m-d H:i:s'), $data, $data, $caterer]);
$pub = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO menu_voci (azienda_id, pubblicazione_id, piatto_id, data_pasto, lettera) VALUES (?, ?, ?, ?, ?)'
)->execute([$aziendaId, $pub, $piattoId, $data, 'A']);

$ordini = new OrderService($pdo, new MenuValidator($pdo));

echo "data=$data azienda=$aziendaId\n";

foreach (['azienda', 'dipendente'] as $carico) {
    // un solo ospite per utente/giorno: aggiorna lo stesso buono
    $esito = $ordini->invia($dipendente, $aziendaId, $data, [$piattoId], $adesso, true, 'Mario Ospite', $carico);
    $export = (new ExportCucina($pdo))->materializza($aziendaId, $data);

    $rigaExport = null;
    foreach ($export as $riga) {
        if (($riga['ospite'] ?? '') === 'S') {
            $rigaExport = $riga;
            break;
        }
    }

    $ordiniDb = $pdo->prepare(
        'SELECT ospite, ospite_nome, a_carico FROM ordini WHERE azienda_id = ? AND data_pasto = ? AND ospite = 1'
    );
    $ordiniDb->execute([$aziendaId, $data]);
    $ordine = $ordiniDb->fetch();

    $pren = $pdo->prepare(
        'SELECT CODE_EMPLOYEE, EMPLOYEE, EMPL_RIF, OSPITE, HOST, PAYMENT FROM prenotazioni
         WHERE azienda_id = ? AND data_pasto = ? AND CODE_EMPLOYEE = \'OSPITE\' LIMIT 1'
    );
    $pren->execute([$aziendaId, $data]);
    $prenotazione = $pren->fetch();

    $exportDb = $pdo->prepare(
        'SELECT badge, employee, ospite, host, payment FROM export_cucina
         WHERE azienda_id = ? AND data_pasto = ? AND ospite = \'S\' LIMIT 1'
    );
    $exportDb->execute([$aziendaId, $data]);
    $exportRiga = $exportDb->fetch();

    echo "--- a_carico={$carico} totale={$esito['ordine']['totale']} ---\n";
    echo 'ordini: ' . json_encode($ordine, JSON_UNESCAPED_UNICODE) . "\n";
    echo 'export_cucina S: ' . json_encode($exportRiga, JSON_UNESCAPED_UNICODE) . "\n";
    echo 'prenotazioni OSPITE: ' . json_encode($prenotazione, JSON_UNESCAPED_UNICODE) . "\n";
    echo 'materializza riga S: ' . json_encode($rigaExport ? [
        'ospite' => $rigaExport['ospite'],
        'employee' => $rigaExport['employee'],
        'payment' => $rigaExport['payment'],
        'host' => $rigaExport['host'],
    ] : null, JSON_UNESCAPED_UNICODE) . "\n";

    $ok = is_array($ordine) && (int) $ordine['ospite'] === 1
        && is_array($exportRiga) && $exportRiga['ospite'] === 'S'
        && is_array($prenotazione) && $prenotazione['CODE_EMPLOYEE'] === 'OSPITE'
        && ($prenotazione['OSPITE'] ?? '') === 'S'
        && ($prenotazione['HOST'] ?? '') !== ''
        && (($carico === 'dipendente' && $exportRiga['payment'] === 'P' && $prenotazione['PAYMENT'] === 'P')
            || ($carico === 'azienda' && $exportRiga['payment'] === '' && $prenotazione['PAYMENT'] === ''));
    echo $ok ? "OK\n" : "FAIL\n";
    if (!$ok) {
        exit(1);
    }
}

echo "tutti ok\n";
