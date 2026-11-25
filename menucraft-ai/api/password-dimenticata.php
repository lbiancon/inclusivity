<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\PasswordRichieste;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $body = JsonResponse::readJson();
    $esito = (new PasswordRichieste(menucraft_db()))->richiedi(
        (string) ($body['slug'] ?? ''),
        (string) ($body['matricola'] ?? ''),
    );

    JsonResponse::send(200, [
        'ok' => true,
        'messaggio' => $esito['messaggio'],
        'contatto' => $esito['contatto'],
    ]);
});
