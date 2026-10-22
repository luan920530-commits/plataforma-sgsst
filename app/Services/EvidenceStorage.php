<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use RuntimeException;

final class EvidenceStorage
{
    public const MAX_BYTES = 10485760;

    public static function store(array $file, int $tenantId): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new DomainException(self::uploadError($error));
        }
        $size = (int) ($file['size'] ?? 0);
        $temporary = (string) ($file['tmp_name'] ?? '');
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('El archivo debe pesar entre 1 byte y 10 MB.');
        }
        if ($temporary === '' || !is_uploaded_file($temporary)) {
            throw new DomainException('La carga no es válida. Vuelve a seleccionar el archivo.');
        }
        $handle = fopen($temporary, 'rb');
        if ($handle === false) {
            throw new RuntimeException('No fue posible leer el archivo cargado.');
        }
        $magic = fread($handle, 16) ?: '';
        fclose($handle);
        $detected = self::detect($magic);
        if ($detected === null) {
            throw new DomainException('Formato no permitido. Carga un PDF, JPG o PNG válido.');
        }
        $original = basename(str_replace('\\', '/', (string) ($file['name'] ?? 'soporte')));
        $original = trim($original);
        if ($original === '' || text_length($original) > 250) {
            throw new DomainException('El nombre original del archivo no es válido.');
        }
        $originalExtension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $validExtensions = $detected === 'jpg' ? ['jpg', 'jpeg'] : [$detected];
        if (!in_array($originalExtension, $validExtensions, true)) {
            throw new DomainException('La extensión del archivo no coincide con su contenido real.');
        }
        $stored = bin2hex(random_bytes(16)) . '.' . $detected;
        $directory = self::directory($tenantId);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio privado de evidencias. Revisa los permisos de storage.');
        }
        $destination = $directory . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($temporary, $destination)) {
            throw new RuntimeException('No fue posible guardar el archivo. Revisa los permisos de storage.');
        }
        @chmod($destination, 0640);
        $hash = hash_file('sha256', $destination);
        if ($hash === false) {
            @unlink($destination);
            throw new RuntimeException('No fue posible verificar la integridad del archivo.');
        }
        return [
            'nombre_original' => $original,
            'nombre_almacenado' => $stored,
            'ruta_relativa' => 'tenant_' . $tenantId . '/' . $stored,
            'mime' => self::mime($detected),
            'extension' => $detected,
            'tamano' => $size,
            'hash_sha256' => $hash,
            'absolute_path' => $destination,
        ];
    }

    public static function path(int $tenantId, string $storedName): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
            throw new DomainException('La ubicación del soporte no es válida.');
        }
        $path = self::directory($tenantId) . DIRECTORY_SEPARATOR . $storedName;
        if (!is_file($path)) {
            throw new RuntimeException('El archivo físico no está disponible.');
        }
        return $path;
    }

    public static function delete(int $tenantId, string $storedName): void
    {
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|jpg|png)$/', $storedName)) {
            throw new RuntimeException('La ubicación del soporte no es válida.');
        }
        $path = self::directory($tenantId) . DIRECTORY_SEPARATOR . $storedName;
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('No fue posible eliminar el archivo físico.');
        }
    }

    public static function mime(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    private static function detect(string $magic): ?string
    {
        if (str_starts_with($magic, '%PDF-')) {
            return 'pdf';
        }
        if (str_starts_with($magic, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($magic, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return 'png';
        }
        return null;
    }

    private static function directory(int $tenantId): string
    {
        return BASE_PATH . '/storage/uploads/tenant_' . $tenantId;
    }

    private static function uploadError(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite permitido de 10 MB.',
            UPLOAD_ERR_PARTIAL => 'La carga quedó incompleta. Inténtalo nuevamente.',
            UPLOAD_ERR_NO_FILE => 'Selecciona un archivo PDF, JPG o PNG.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene un directorio temporal para cargas.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir la carga temporal.',
            default => 'No fue posible procesar la carga del archivo.',
        };
    }
}
