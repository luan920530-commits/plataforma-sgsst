<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

/** ZIP de entradas almacenadas, UTF-8 y sin ZipArchive; suficiente para paquetes documentales y DOCX. */
final class ZipBuilder
{
    private array $entries = [];

    public function add(string $name, string $content): void
    {
        $name = str_replace('\\', '/', ltrim($name, '/'));
        if ($name === '' || str_contains($name, '../') || str_contains($name, "\0")) {
            throw new InvalidArgumentException('La ruta interna del ZIP no es segura.');
        }
        if (isset($this->entries[$name])) {
            throw new InvalidArgumentException('La entrada ZIP está duplicada: ' . $name);
        }
        $this->entries[$name] = $content;
    }

    public function build(): string
    {
        if ($this->entries === [] || count($this->entries) > 65535) {
            throw new RuntimeException('El paquete ZIP está vacío o supera el número de entradas admitido.');
        }
        $binary = '';
        $central = '';
        $offset = 0;
        $dos = self::dosTime();
        foreach ($this->entries as $name => $content) {
            $size = strlen($content);
            $crc = crc32($content);
            $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x800, 0, $dos['time'], $dos['date'], $crc, $size, $size, $nameLength, 0);
            $binary .= $local . $name . $content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x800, 0, $dos['time'], $dos['date'], $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($local) + $nameLength + $size;
        }
        $centralOffset = strlen($binary);
        $count = count($this->entries);
        return $binary . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $centralOffset, 0);
    }

    private static function dosTime(): array
    {
        $year = max(1980, (int) date('Y'));
        return [
            'time' => ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2),
            'date' => (($year - 1980) << 9) | ((int) date('n') << 5) | (int) date('j'),
        ];
    }
}
