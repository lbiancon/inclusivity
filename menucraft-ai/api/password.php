<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['dipendente', 'caterer', 'piattaforma']);
    $auth->richiediCsrf();
    $body = JsonResponse::readJson();

    $auth->cambiaPassword(
        $utente['id'],
        $utente['azienda_id'],
        (string) ($body['password_attuale'] ?? ''),
        (string) ($body['password_nuova'] ?? ''),
    );

    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => 'Password aggiornata. Al prossimo accesso usa la nuova password.',
    ]);
});
