<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\PrenotazioniAzienda;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $aziendaId = $auth->aziendaOperativa($utente, $_GET);

    $servizio = new PrenotazioniAzienda($pdo);
    $prenotazioni = $servizio->delGiorno($aziendaId, (string) ($_GET['data'] ?? ''));
    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'data' => (string) ($_GET['data'] ?? ''),
        'totale' => count($prenotazioni),
        'conteggi' => $servizio->conteggi($prenotazioni),
        'prenotazioni' => $prenotazioni,
    ]);
});
