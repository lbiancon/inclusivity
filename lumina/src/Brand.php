<?php

declare(strict_types=1);

namespace Lumina;

final class Brand
{
    /** @return array{ext: string, bytes: string}|null */
    public static function uploaded(): ?array
    {
        $file = $_FILES['logo'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
            throw new DomainException('validation', 'Il logo non è arrivato. Riprova con un PNG o un JPG fino a 2 MB.');
        }
        if ((int) ($file['size'] ?? 0) > 2_000_000) {
            throw new DomainException('validation', 'Il logo supera 2 MB.');
        }
        $info = @getimagesize($file['tmp_name']);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => '',
        };
        if ($ext === '' || (int) ($info[0] ?? 0) < 16 || (int) ($info[1] ?? 0) < 16 || (int) $info[0] > 4000 || (int) $info[1] > 4000) {
            throw new DomainException('validation', 'Il logo deve essere un PNG o un JPG, tra 16 e 4000 pixel.');
        }
        $bytes = file_get_contents($file['tmp_name']);
        if (!is_string($bytes) || $bytes === '') {
            throw new DomainException('validation', 'Il logo non è leggibile.');
        }

        return ['ext' => $ext, 'bytes' => $bytes];
    }

    public static function store(int $businessId, string $ext, string $bytes): void
    {
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new DomainException('validation', 'Non riesco a conservare il logo.');
        }
        self::deleteFiles($businessId);
        $path = $dir . '/' . $businessId . '.' . $ext;
        if (file_put_contents($path, $bytes) === false) {
            throw new DomainException('validation', 'Non riesco a conservare il logo.');
        }
    }

    public static function delete(int $businessId): void
    {
        self::deleteFiles($businessId);
    }

    public static function path(int $businessId, string $ext): ?string
    {
        if ($ext !== 'jpg' && $ext !== 'png') {
            return null;
        }
        $path = self::directory() . '/' . $businessId . '.' . $ext;

        return is_file($path) ? $path : null;
    }

    private static function directory(): string
    {
        return LUMINA_ROOT . '/storage/logos';
    }

    private static function deleteFiles(int $businessId): void
    {
        foreach (['jpg', 'png'] as $ext) {
            $path = self::directory() . '/' . $businessId . '.' . $ext;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
