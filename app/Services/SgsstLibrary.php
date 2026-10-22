<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SgsstLibrary
{
    private static ?array $manifest = null;
    private static array $byNumber = [];
    private static array $byId = [];

    public static function all(): array
    {
        return self::manifest()['templates'];
    }

    public static function find(int $number): ?array
    {
        self::index();
        return self::$byNumber[$number] ?? null;
    }

    public static function findById(string $id): ?array
    {
        self::index();
        return self::$byId[$id] ?? null;
    }

    public static function filter(array $filters): array
    {
        $query = text_lower(trim((string) ($filters['q'] ?? '')));
        $kind = (string) ($filters['tipo'] ?? '');
        $standard = (int) ($filters['estandar'] ?? 0);
        $items = array_filter(self::all(), static function (array $item) use ($query, $kind, $standard): bool {
            if ($kind !== '' && $item['kind'] !== $kind) {
                return false;
            }
            if ($standard > 0 && !in_array($standard, $item['standards'], true)) {
                return false;
            }
            if ($query !== '') {
                $haystack = text_lower(implode(' ', [$item['title'], $item['folder'], $item['module'], $item['code'], $item['id']]));
                if (!str_contains($haystack, $query)) {
                    return false;
                }
            }
            return true;
        });
        usort($items, static fn(array $a, array $b): int => [$a['primary_standard'], $a['title'], $a['number']] <=> [$b['primary_standard'], $b['title'], $b['number']]);
        return array_values($items);
    }

    public static function stats(): array
    {
        $manifest = self::manifest();
        $documents = count(array_filter($manifest['templates'], static fn(array $item): bool => $item['kind'] === 'document'));
        return [
            'total' => (int) $manifest['unique_templates'],
            'entries' => (int) $manifest['source_entries'],
            'duplicates' => (int) $manifest['duplicate_entries'],
            'ignored' => (int) $manifest['ignored_entries'],
            'documents' => $documents,
            'forms' => (int) $manifest['unique_templates'] - $documents,
            'standards' => count($manifest['standards_covered']),
            'warnings' => count(array_filter($manifest['templates'], static fn(array $item): bool => $item['warnings'] !== [])),
            'formulas' => array_sum(array_map(static fn(array $item): int => (int) ($item['stats']['formulas'] ?? 0), $manifest['templates'])),
        ];
    }

    public static function content(array $template): string
    {
        if ($template['kind'] !== 'document') {
            throw new RuntimeException('La plantilla solicitada es un formulario.');
        }
        return self::readResource($template['content']);
    }

    public static function form(array $template): array
    {
        if ($template['kind'] !== 'form') {
            throw new RuntimeException('La plantilla solicitada no es un formulario.');
        }
        $data = json_decode(self::readResource($template['content']), true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['sheets']) || !is_array($data['sheets'])) {
            throw new RuntimeException('La estructura del formulario no es válida.');
        }
        return $data;
    }

    public static function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)) . $name;
            $column = intdiv($column, 26);
        }
        return $name;
    }

    private static function readResource(string $relative): string
    {
        $base = BASE_PATH . '/resources/sgsst-library';
        $path = $base . '/' . ltrim($relative, '/');
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('No se encontró el contenido local de la plantilla.');
        }
        return $content;
    }

    private static function index(): void
    {
        if (self::$byNumber !== []) {
            return;
        }
        foreach (self::all() as $item) {
            self::$byNumber[(int) $item['number']] = $item;
            self::$byId[$item['id']] = $item;
        }
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $path = BASE_PATH . '/resources/sgsst-library/manifest.json';
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('La biblioteca SG-SST no está instalada.');
        }
        $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($manifest['templates']) || !is_array($manifest['templates'])) {
            throw new RuntimeException('El manifiesto de la biblioteca no es válido.');
        }
        self::$manifest = $manifest;
        return self::$manifest;
    }
}
