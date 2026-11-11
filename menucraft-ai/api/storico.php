<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\StoricoPrenotazioni;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $utente = (new AuthService($pdo))->richiediRuolo('dipendente');
    $prenotazioni = (new StoricoPrenotazioni($pdo))->perUtente($utente['azienda_id'], $utente['id']);

    JsonResponse::send(200, [
        'ok' => true,
        'prenotazioni' => $prenotazioni,
    ]);
});
