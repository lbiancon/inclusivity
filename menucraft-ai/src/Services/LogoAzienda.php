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
    private const LATO = 192;

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
        if (!in_array($tipo, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il logo è png, jpg, webp o gif.');
        }
        if (($info[0] ?? 0) > 4000 || ($info[1] ?? 0) > 4000) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Il logo è troppo grande: al massimo 4000 pixel per lato.');
        }

        $cartella = dirname(__DIR__, 2) . '/assets/loghi';
        if (!is_dir($cartella) && !mkdir($cartella, 0775, true) && !is_dir($cartella)) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a conservare il logo.', 500);
        }
        $estensione = match ($tipo) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            default => 'bin',
        };
        $usaGd = extension_loaded('gd') && function_exists('imagecreatetruecolor');
        $relativo = 'assets/loghi/' . $aziendaId . ($usaGd ? '.png' : '.' . $estensione);
        $destinazione = dirname(__DIR__, 2) . '/' . $relativo;
        foreach (glob($cartella . '/' . $aziendaId . '.*') ?: [] as $vecchio) {
            if (is_file($vecchio)) {
                unlink($vecchio);
            }
        }
        if ($usaGd) {
            $this->normalizza($temporaneo, $tipo, $destinazione);
        } else {
            $this->copia($temporaneo, $destinazione);
        }

        $stmt = $this->pdo->prepare('UPDATE aziende SET logo = :logo WHERE id = :id');
        $stmt->execute(['logo' => $relativo, 'id' => $aziendaId]);

        return $relativo;
    }

    private function copia(string $origine, string $destinazione): void
    {
        $copiato = is_uploaded_file($origine)
            ? move_uploaded_file($origine, $destinazione)
            : copy($origine, $destinazione);
        if ($copiato !== true) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a conservare il logo.', 500);
        }
    }

    private function normalizza(string $origine, int $tipo, string $destinazione): void
    {
        $sorgente = false;
        if ($tipo === IMAGETYPE_PNG && function_exists('imagecreatefrompng')) {
            $sorgente = @imagecreatefrompng($origine);
        } elseif ($tipo === IMAGETYPE_JPEG && function_exists('imagecreatefromjpeg')) {
            $sorgente = @imagecreatefromjpeg($origine);
        } elseif ($tipo === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $sorgente = @imagecreatefromwebp($origine);
        } elseif ($tipo === IMAGETYPE_GIF && function_exists('imagecreatefromgif')) {
            $sorgente = @imagecreatefromgif($origine);
        }
        if ($sorgente === false) {
            $this->copia($origine, $destinazione);

            return;
        }

        $larghezza = imagesx($sorgente);
        $altezza = imagesy($sorgente);
        $tela = imagecreatetruecolor(self::LATO, self::LATO);
        if ($tela === false) {
            imagedestroy($sorgente);
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a ridimensionare il logo.', 500);
        }
        imagealphablending($tela, false);
        imagesavealpha($tela, true);
        $trasparente = imagecolorallocatealpha($tela, 255, 255, 255, 127);
        imagefilledrectangle($tela, 0, 0, self::LATO, self::LATO, $trasparente);
        $scala = min(self::LATO / max(1, $larghezza), self::LATO / max(1, $altezza));
        $nuovaL = max(1, (int) round($larghezza * $scala));
        $nuovaH = max(1, (int) round($altezza * $scala));
        $x = (int) floor((self::LATO - $nuovaL) / 2);
        $y = (int) floor((self::LATO - $nuovaH) / 2);
        imagecopyresampled($tela, $sorgente, $x, $y, 0, 0, $nuovaL, $nuovaH, $larghezza, $altezza);
        imagedestroy($sorgente);
        $ok = imagepng($tela, $destinazione, 6);
        imagedestroy($tela);
        if ($ok !== true) {
            throw new MenuRuleException('LOGO_NON_VALIDO', 'Non riesco a conservare il logo.', 500);
        }
    }
}
