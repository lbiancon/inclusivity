<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\MenuBanco;
use MenuCraft\Services\MenuRuleException;
use MenuCraft\Services\MenuValidator;

JsonResponse::guard(static function (): void {
    $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($metodo, ['GET', 'POST'], true)) {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $banco = new MenuBanco($pdo, new MenuValidator($pdo));

    if ($metodo === 'GET') {
        $data = (string) ($_GET['data'] ?? '');
        JsonResponse::send(200, [
            'ok' => true,
            'csrf' => $utente['csrf'],
            'menu' => $banco->giorno($utente['azienda_id'], $data),
        ]);
    }

    $auth->richiediCsrf();
    $body = JsonResponse::readJson();
    $azione = (string) ($body['azione'] ?? '');
    $menu = match ($azione) {
        'aggiungi' => $banco->aggiungi($utente['azienda_id'], $utente['id'], $body),
        'crea' => $banco->crea($utente['azienda_id'], $utente['id'], $body),
        'togli' => $banco->togli($utente['azienda_id'], $utente['id'], (string) ($body['data'] ?? ''), (int) ($body['piatto_id'] ?? 0)),
        default => throw new MenuRuleException('AZIONE', 'Azione menu non riconosciuta.'),
    };
    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'messaggio' => 'Menu aggiornato.',
        'menu' => $menu,
    ]);
});
