<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MenuCraft\Http\JsonResponse;
use MenuCraft\Services\AuthService;
use MenuCraft\Services\LogoAzienda;
use MenuCraft\Services\MenuRuleException;

JsonResponse::guard(static function (): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new MenuRuleException('METODO', 'Metodo non consentito.', 405);
    }

    $pdo = menucraft_db();
    $auth = new AuthService($pdo);
    $utente = $auth->richiediRuoli(['caterer', 'piattaforma']);
    $auth->richiediCsrf();

    $aziendaId = $utente['azienda_id'];
    if ($utente['ruolo'] === 'piattaforma') {
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $cerca = $pdo->prepare('SELECT id FROM aziende WHERE slug = :slug');
        $cerca->execute(['slug' => $slug]);
        $trovato = $cerca->fetchColumn();
        if ($trovato === false) {
            throw new MenuRuleException('AZIENDA_ASSENTE', 'Azienda non trovata.', 404);
        }
        $aziendaId = (int) $trovato;
    }

    $file = $_FILES['logo'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new MenuRuleException('LOGO_NON_VALIDO', 'Scegli un\'immagine per il logo.');
    }

    $percorso = (new LogoAzienda($pdo))->salva($aziendaId, (string) $file['tmp_name']);
    JsonResponse::send(200, [
        'ok' => true,
        'csrf' => $utente['csrf'],
        'logo' => $percorso,
        'messaggio' => 'Logo aggiornato.',
    ]);
});
