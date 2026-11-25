<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\Anagrafica;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuolo('piattaforma');
    $anagrafica = new Anagrafica($pdo);

    if ($metodo === 'GET') {
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'societa' => $anagrafica->elencoSocieta(),
        ]);
    }

    $auth->richiediCsrf();
    $body = JsonResponse::readJson();
    $azione = (string) ($body['azione'] ?? 'crea');

    if ($azione === 'aggiorna') {
        $aggiornata = $anagrafica->aggiornaSocieta($body);
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => $aggiornata['messaggio'],
            'societa' => $aggiornata,
            'elenco' => $anagrafica->elencoSocieta(),
        ]);
    }

    $creata = $anagrafica->creaSocieta($body);
    JsonResponse::send(201, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => $creata['messaggio'],
        'societa' => $creata,
        'elenco' => $anagrafica->elencoSocieta(),
    ]);
});
