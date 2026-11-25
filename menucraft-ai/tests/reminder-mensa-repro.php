<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Services\Anagrafica;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\ReminderMensa;

$pdo = menucraft_db();
(new \MenuCraft\Schema\Installer($pdo))->install();

$slug = 'qa-reminder-mensa';
$pdo->prepare('DELETE FROM aziende WHERE slug = :s')->execute(['s' => $slug]);
$pdo->prepare(
    'INSERT INTO aziende (nome, slug, tipo_pagamento, max_scelte, min_scelte, permetti_doppio_primo, permetti_doppio_contorno, limite_oggi, limite_domani, bevanda_obbligatoria, caricamento_menu)
     VALUES (\'QA Reminder\', :s, \'welfare\', 5, 5, 0, 0, \'09:30:00\', \'18:00:00\', 1, \'sempre\')'
)->execute(['s' => $slug]);
$aziendaId = (int) $pdo->lastInsertId();

$ana = new Anagrafica($pdo);
$u1 = $ana->creaUtente($aziendaId, [
    'nome' => 'Senza Prenota',
    'matricola' => 'R001',
    'password' => 'CambiaMe!2026',
    'pagamento' => 'eredita',
    'email' => 'senza.prenota@example.com',
    'mail_reminder' => 'Y',
]);
$u2 = $ana->creaUtente($aziendaId, [
    'nome' => 'Gia Prenotato',
    'matricola' => 'R002',
    'password' => 'CambiaMe!2026',
    'pagamento' => 'eredita',
    'email' => 'gia.prenotato@example.com',
    'mail_reminder' => 'Y',
]);
$ana->creaUtente($aziendaId, [
    'nome' => 'No Reminder',
    'matricola' => 'R003',
    'password' => 'CambiaMe!2026',
    'pagamento' => 'eredita',
    'email' => 'no.reminder@example.com',
    'mail_reminder' => 'N',
]);

$validator = new MenuValidator($pdo);
$oggi = $validator->giornoCivile($validator->adesso());
// Se oggi chiuso, sposta il riferimento a un lunedì aperto recente.
$rif = $oggi;
for ($i = 0; $i < 14; $i++) {
    if ($validator->motivoCalendario($aziendaId, $rif) === null) {
        break;
    }
    $rif = $rif->modify('-1 day');
}

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
$pdo->prepare(
    'INSERT INTO ordini (azienda_id, utente_id, data_pasto, totale, creato_il, aggiornato_il, ospite)
     VALUES (:a, :u, :d, 0, :c, :g, 0)'
)->execute([
    'a' => $aziendaId,
    'u' => $u2['id'],
    'd' => $rif->format('Y-m-d'),
    'c' => $now,
    'g' => $now,
]);

$svc = new ReminderMensa($pdo);
$mattina = $svc->elencoEmail('mattina', $aziendaId, $rif);
$domani = $validator->prossimoGiornoExport($aziendaId, $rif);
$pdo->prepare(
    'INSERT INTO ordini (azienda_id, utente_id, data_pasto, totale, creato_il, aggiornato_il, ospite)
     VALUES (:a, :u, :d, 0, :c, :g, 0)'
)->execute([
    'a' => $aziendaId,
    'u' => $u1['id'],
    'd' => $domani->format('Y-m-d'),
    'c' => $now,
    'g' => $now,
]);
$pomeriggio = $svc->elencoEmail('pomeriggio', $aziendaId, $rif);
$limiti = $svc->limiti($aziendaId);

$ok = in_array('senza.prenota@example.com', $mattina, true)
    && !in_array('gia.prenotato@example.com', $mattina, true)
    && !in_array('no.reminder@example.com', $mattina, true)
    && in_array('gia.prenotato@example.com', $pomeriggio, true)
    && !in_array('senza.prenota@example.com', $pomeriggio, true)
    && $limiti['limite_domani'] === '18:00';

$log = [
    'rif' => $rif->format('Y-m-d'),
    'mattina' => $mattina,
    'pomeriggio_target' => $domani->format('Y-m-d'),
    'pomeriggio' => $pomeriggio,
    'limiti' => $limiti,
    'ok' => $ok,
];
file_put_contents('/opt/cursor/artifacts/reminder-mensa-repro.log', json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($ok ? 0 : 1);
