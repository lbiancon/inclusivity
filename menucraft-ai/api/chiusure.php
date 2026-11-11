<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use DateTimeImmutable;
use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\CalendarioChiusure;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuolo('caterer');
    $calendario = new CalendarioChiusure($pdo);

    if ($metodo === 'GET') {
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'chiusure' => $calendario->elenco($utente['azienda_id']),
        ]);
    }

    $auth->richiediCsrf();
    $body = JsonResponse::readJson();
    $azione = (string) ($body['azione'] ?? '');

    if ($azione === 'aggiungi') {
        $voce = $calendario->aggiungi($utente['azienda_id'], $body);
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => 'Chiusura segnata per il ' . (new DateTimeImmutable($voce['data']))->format('d/m/Y') . '.',
            'voce' => $voce,
            'chiusure' => $calendario->elenco($utente['azienda_id']),
        ]);
    }

    if ($azione === 'togli') {
        $calendario->togli($utente['azienda_id'], (int) ($body['id'] ?? 0));
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => 'Chiusura tolta dal calendario del servizio.',
            'chiusure' => $calendario->elenco($utente['azienda_id']),
        ]);
    }

    throw new MenuRuleException('AZIONE', 'Azione chiusura non riconosciuta.');
});
