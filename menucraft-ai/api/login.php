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

    $body = JsonResponse::readJson();
    $auth = new AuthService(menucraft_db());
    $esito = $auth->login(
        (string) ($body['slug'] ?? ''),
        (string) ($body['matricola'] ?? ''),
        (string) ($body['password'] ?? ''),
    );

    JsonResponse::send(200, ['ok' => true] + $esito);
});
