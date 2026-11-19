<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\Anagrafica;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['dipendente', 'caterer']);
    $auth->richiediCsrf();
    $body = JsonResponse::readJson();
    $destinatario = $utente['ruolo'] === 'dipendente'
        ? $utente['id']
        : (int) ($body['utente_id'] ?? 0);
    if ($destinatario < 1) {
        throw new MenuRuleException('UTENTE_SCONOSCIUTO', 'Indica la persona da segnalare.');
    }

    $testo = (new Anagrafica($pdo))->segnalaAllergie($utente['azienda_id'], $destinatario, (string) ($body['allergie'] ?? ''));
    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'allergie' => $testo,
        'messaggio' => $testo === ''
            ? 'Segnalazione tolta.'
            : 'Segnalazione registrata. In cucina la vedono sulla prenotazione.',
    ]);
});
