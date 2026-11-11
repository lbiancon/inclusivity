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
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $anagrafica = new Anagrafica($pdo);
    $body = $metodo === 'POST' ? JsonResponse::readJson() : $_GET;
    $aziendaId = $auth->aziendaOperativa($utente, $body);

    if ($metodo === 'GET') {
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'utenti' => $anagrafica->elencoUtenti($aziendaId),
        ]);
    }

    $auth->richiediCsrf();
    $creato = $anagrafica->creaUtente($aziendaId, $body);
    JsonResponse::send(201, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => $creato['messaggio'],
        'utente' => $creato,
        'utenti' => $anagrafica->elencoUtenti($aziendaId),
    ]);
});
