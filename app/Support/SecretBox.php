<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/** Cifrado autenticado para secretos de aplicación; la clave no se guarda en SQLite. */
final class SecretBox
{
    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') return '';
        $nonce = random_bytes(12);$tag = '';
        $cipher = openssl_encrypt($plainText, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('No fue posible cifrar el secreto con AES-256-GCM.');
        return 'v1.' . base64_encode($nonce . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') return '';
        if (!str_starts_with($payload, 'v1.')) throw new RuntimeException('Formato de secreto cifrado no reconocido.');
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) throw new RuntimeException('El secreto cifrado está incompleto.');
        $nonce = substr($raw, 0, 12);$tag = substr($raw, 12, 16);$cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plain === false) throw new RuntimeException('No fue posible verificar el secreto cifrado; revise la clave de la instalación.');
        return $plain;
    }

    private static function key(): string
    {
        if (!extension_loaded('openssl')) throw new RuntimeException('La extensión OpenSSL es necesaria para cifrar secretos.');
        $configured = trim((string) getenv('APP_DATA_KEY'));
        if ($configured !== '') {
            $key = preg_match('/^[0-9a-f]{64}$/i', $configured) ? hex2bin($configured) : base64_decode($configured, true);
            if ($key === false || strlen($key) !== 32) throw new RuntimeException('APP_DATA_KEY debe contener 32 bytes en Base64 o 64 caracteres hexadecimales.');
            return $key;
        }
        $path = BASE_PATH . '/storage/security/app-data.key';
        if (is_file($path)) {
            $key = base64_decode(trim((string) file_get_contents($path)), true);
            if ($key === false || strlen($key) !== 32) throw new RuntimeException('La clave local de cifrado no es válida.');
            return $key;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('No se pudo crear el directorio privado de seguridad.');
        $key = random_bytes(32);
        if (file_put_contents($path, base64_encode($key), LOCK_EX) === false) throw new RuntimeException('No se pudo guardar la clave privada de cifrado.');
        @chmod($path, 0600);
        return $key;
    }
}
