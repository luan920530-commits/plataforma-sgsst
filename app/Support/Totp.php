<?php
declare(strict_types=1);

namespace App\Support;

/** TOTP RFC 6238 de 6 dígitos y 30 segundos, sin dependencias externas. */
final class Totp
{
    public static function secret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function provisioningUri(string $secret, string $email, string $issuer = 'Normia SST'): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), 30);
        $binary = pack('N2', ($counter >> 32) & 0xffffffff, $counter & 0xffffffff);
        $hash = hash_hmac('sha1', $binary, self::base32Decode($secret), true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24) | ((ord($hash[$offset + 1]) & 0xff) << 16) | ((ord($hash[$offset + 2]) & 0xff) << 8) | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) return false;
        $time = $timestamp ?? time();
        foreach ([-30, 0, 30] as $offset) if (hash_equals(self::code($secret, $time + $offset), $code)) return true;
        return false;
    }

    public static function recoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) $codes[] = strtoupper(substr(bin2hex(random_bytes(6)), 0, 5) . '-' . substr(bin2hex(random_bytes(6)), 0, 5));
        return $codes;
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits = '';$output = '';
        foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        foreach (str_split($bits, 5) as $chunk) $output .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        return $output;
    }

    private static function base32Decode(string $value): string
    {
        $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));$bits = '';$output = '';
        $value = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $value) ?? '');
        foreach (str_split($value) as $character) { if (!isset($alphabet[$character])) return ''; $bits .= str_pad(decbin($alphabet[$character]), 5, '0', STR_PAD_LEFT); }
        foreach (str_split($bits, 8) as $chunk) if (strlen($chunk) === 8) $output .= chr(bindec($chunk));
        if ($output === '') throw new \InvalidArgumentException('Secreto TOTP no válido.');
        return $output;
    }
}
