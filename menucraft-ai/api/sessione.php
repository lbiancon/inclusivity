<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Models\AziendaConfig;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $auth = new AuthService(menucraft_db());
    $utente = $auth->richiediUtente();
    $azienda = AziendaConfig::trova(menucraft_db(), $utente['azienda_id']);

    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'utente' => [
            'id' => $utente['id'],
            'nome' => $utente['nome'],
            'matricola' => $utente['matricola'],
            'ruolo' => $utente['ruolo'],
        ],
        'azienda' => $azienda->toArray(),
    ]);
});
