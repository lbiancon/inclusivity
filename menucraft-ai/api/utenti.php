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
    $azione = (string) ($body['azione'] ?? 'crea');
    if ($azione === 'elimina') {
        $anagrafica->eliminaUtente($aziendaId, $utente['id'], (int) ($body['utente_id'] ?? 0));
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => 'Utente eliminato.',
            'utenti' => $anagrafica->elencoUtenti($aziendaId),
        ]);
    }
    if ($azione === 'aggiorna') {
        $aggiornato = $anagrafica->aggiornaUtente($aziendaId, $utente['id'], $body);
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => $aggiornato['messaggio'],
            'utente' => $aggiornato,
            'utenti' => $anagrafica->elencoUtenti($aziendaId),
        ]);
    }
    $creato = $anagrafica->creaUtente($aziendaId, $body);
    JsonResponse::send(201, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => $creato['messaggio'],
        'utente' => $creato,
        'utenti' => $anagrafica->elencoUtenti($aziendaId),
    ]);
});
