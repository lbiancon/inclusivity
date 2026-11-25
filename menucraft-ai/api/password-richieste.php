<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\PasswordRichieste;

JsonResponse::guard(static function (): void {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $body = $metodo === 'POST' ? JsonResponse::readJson() : $_GET;
    $aziendaId = $auth->aziendaOperativa($utente, $body);
    $servizio = new PasswordRichieste($pdo);

    if ($metodo === 'GET') {
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'richieste' => $servizio->elencoAperte($aziendaId),
        ]);
    }

    $auth->richiediCsrf();
    $azione = (string) ($body['azione'] ?? 'genera');
    $richiestaId = (int) ($body['richiesta_id'] ?? 0);

    if ($azione === 'annulla') {
        $servizio->annulla($aziendaId, $richiestaId, $utente['id']);
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'messaggio' => 'Richiesta di reset chiusa senza cambiare la password.',
            'richieste' => $servizio->elencoAperte($aziendaId),
        ]);
    }

    $esito = $servizio->generaPasswordTemporanea($aziendaId, $richiestaId, $utente['id']);
    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => $esito['messaggio'],
        'password_temporanea' => $esito['password_temporanea'],
        'matricola' => $esito['matricola'],
        'nome' => $esito['nome'],
        'richieste' => $servizio->elencoAperte($aziendaId),
    ]);
});
