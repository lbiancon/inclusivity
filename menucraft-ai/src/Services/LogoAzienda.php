<?php

declare(strict_types=1);

namespace MenuCraft\Services;

use PDO;

if (!defined('MENUCRAFT_APP')) {
    http_response_code(404);
    exit;
}

final class LogoAzienda
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function salva(int $aziendaId, string $temporaneo): string
    {
        $azienda = $this->pdo->prepare('SELECT id, slug FROM aziende WHERE id = :id');
        $azienda->execute(['id' => $aziendaId]);
        if ($azienda->fetch() === false) {
            throw new MenuRuleException('AZIENDA_ASSENTE', 'Azienda non trovata.', 404);
        }
        if (!is_uploaded_file($temporaneo) && !is_file($temporaneo)) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il file del logo non è arrivato.');
        }
        $byte = filesize($temporaneo);
        if ($byte === false || $byte < 32 || $byte > 1_500_000) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il logo deve pesare meno di 1,5 MB.');
        }
        $info = @getimagesize($temporaneo);
        if ($info === false) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il file non è un\'immagine.');
        }
        $tipo = $info[2] ?? 0;
        $estensione = match ($tipo) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            default => null,
        };
        if ($estensione === null) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il logo è png, jpg, webp o gif.');
        }
        if (($info[0] ?? 0) > 4000 || ($info[1] ?? 0) > 4000) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il logo è troppo grande: al massimo 4000 pixel per lato.');
        }

        $cartella = dirname(__DIR__, 2) . '/assets/loghi';
        if (!is_dir($cartella) && !mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a conservare il logo.', 500);
        }
        $relativo = 'assets/loghi/' . $aziendaId . '.' . $estensione;
        $destinazione = dirname(__DIR__, 2) . '/' . $relativo;
        foreach (glob($cartella . '/' . $aziendaId . '.*') ?: [] as $vecchio) {
            if (is_file($vecchio)) {
                unlink($vecchio);
            }
        }
        $copiato = is_uploaded_file($temporaneo)
            ? move_uploaded_file($temporaneo, $destinazione)
            : copy($temporaneo, $destinazione);
        if ($copiato !== true) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a conservare il logo.', 500);
        }

        $stmt = $this->pdo->prepare('UPDATE aziende SET logo = :logo WHERE id = :id');
        $stmt->execute(['logo' => $relativo, 'id' => $aziendaId]);

        return $relativo;
    }
}
