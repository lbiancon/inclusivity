<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\Anagrafica;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;
use MenuCraft\Services\OrderService;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediUtente();
    $auth->richiediCsrf();

    $body = JsonResponse::readJson();
    $piatti = $body['piatti'] ?? [];
    if (!is_array($piatti)) {
        throw new MenuRuleException('PIATTO_NON_VALIDO', 'Elenco piatti non valido.');
    }

    if (array_key_exists('allergie', $body)) {
        (new Anagrafica($pdo))->segnalaAllergie($utente['azienda_id'], $utente['id'], (string) $body['allergie']);
    }

    $ospite = ($body['ospite'] ?? false) === true || (string) ($body['ospite'] ?? '') === '1';
    $servizio = new OrderService($pdo, new MenuValidator($pdo));
    $esito = $servizio->invia(
        $utente['id'],
        $utente['azienda_id'],
        (string) ($body['data'] ?? ''),
        array_values($piatti),
        null,
        $ospite,
        (string) ($body['ospite_nome'] ?? ''),
        (string) ($body['a_carico'] ?? ''),
    );

    JsonResponse::send(200, ['ok' => true] + $esito);
});
