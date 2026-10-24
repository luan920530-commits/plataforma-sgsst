<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use JsonException;
use RuntimeException;

/** Valida el formato del editor; solo permite pictogramas ISO y geometría CAD de los catálogos locales. */
final class FloorPlan
{
    private const BASE_TOOLS = ['wall', 'door', 'route', 'exit', 'extinguisher', 'first_aid', 'alarm', 'assembly', 'text'];
    private const LEGACY_SIGNS = [
        'exit' => 'E002', 'extinguisher' => 'F001', 'first_aid' => 'E003',
        'alarm' => 'F005', 'assembly' => 'E007',
    ];
    private const COLORS = [
        '#0f172a', '#334155', '#475569', '#64748b', '#2563eb', '#15803d', '#16a34a', '#dc2626', '#f97316',
        '#237f52', '#b71f2e', '#005387', '#f9a800', '#ca8a04', '#b45309',
    ];
    private const CATEGORY_COLORS = [
        'emergency' => '#237f52', 'fire' => '#b71f2e', 'mandatory' => '#005387',
        'prohibition' => '#b71f2e', 'warning' => '#f9a800',
    ];
    private static ?array $library = null;
    private static ?array $tools = null;
    private static ?array $toolCatalog = null;
    private static ?array $cadLibrary = null;

    public static function defaults(): array
    {
        return ['format' => 1, 'width' => 1000, 'height' => 650, 'grid' => 20, 'elements' => []];
    }

    /** Incluye los tipos heredados para abrir versiones antiguas sin migrar ni reescribir su diseño. */
    public static function tools(): array
    {
        if (self::$tools === null) {
            self::$tools = array_merge(
                self::BASE_TOOLS,
                array_map(static fn(string $code): string => 'iso_' . strtolower($code), array_keys(self::library()['signs'])),
                array_keys(self::cadCatalog())
            );
        }
        return self::$tools;
    }

    /** Catálogo visible: herramientas de dibujo y las 332 señales, sin duplicar alias heredados. */
    public static function toolCatalog(): array
    {
        if (self::$toolCatalog !== null) {
            return self::$toolCatalog;
        }
        $catalog = [
            'select' => ['name' => 'Seleccionar y mover', 'category' => 'draw', 'description' => 'Selecciona, mueve o ajusta los extremos de muros y rutas.', 'reference' => 'Herramienta'],
            'wall' => ['name' => 'Muro', 'category' => 'draw', 'description' => 'Arrastra para delimitar espacios y recorridos del plano.', 'reference' => 'Dibujo'],
            'door' => ['name' => 'Puerta', 'category' => 'draw', 'description' => 'Ubica una puerta y ajusta su orientación según el levantamiento.', 'reference' => 'Dibujo'],
            'route' => ['name' => 'Ruta de evacuación', 'category' => 'draw', 'description' => 'Arrastra para trazar el recorrido; la flecha marca el sentido.', 'reference' => 'Dibujo'],
            'text' => ['name' => 'Texto y datos', 'category' => 'draw', 'description' => 'Añade pisos, áreas, norte, escala u observaciones útiles.', 'reference' => 'Dibujo'],
        ];
        foreach (self::library()['signs'] as $code => $sign) {
            $catalog['iso_' . strtolower($code)] = [
                'name' => $sign['name'], 'category' => $sign['category'],
                'description' => $sign['description'], 'reference' => $code,
            ];
        }
        self::$toolCatalog = $catalog;
        return $catalog;
    }

    public static function categories(): array
    {
        return self::library()['categories'];
    }

    public static function cadCatalog(): array
    {
        return self::cadLibrary()['objects'];
    }

    public static function cadCategories(): array
    {
        return self::cadLibrary()['categories'];
    }

    /** Devuelve el código ISO tanto para tipos nuevos como para alias de versiones anteriores. */
    public static function signCode(string $tool): ?string
    {
        if (isset(self::LEGACY_SIGNS[$tool])) {
            return self::LEGACY_SIGNS[$tool];
        }
        if (preg_match('/^iso_([efmpw][0-9]{3})$/D', $tool, $matches)) {
            $code = strtoupper($matches[1]);
            return isset(self::library()['signs'][$code]) ? $code : null;
        }
        return null;
    }

    public static function normalize(string $json): array
    {
        if ($json === '' || strlen($json) > 1000000) {
            throw new DomainException('El diseño está vacío o supera el tamaño permitido.');
        }
        try {
            $design = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DomainException('El diseño no contiene un JSON válido.');
        }
        if (!is_array($design) || !isset($design['elements']) || !is_array($design['elements'])) {
            throw new DomainException('La estructura del plano no es válida.');
        }
        $width = self::integer($design['width'] ?? 0, 400, 2400, 'ancho');
        $height = self::integer($design['height'] ?? 0, 300, 1600, 'alto');
        $grid = self::integer($design['grid'] ?? 20, 5, 100, 'cuadrícula');
        if (count($design['elements']) < 1 || count($design['elements']) > 1000) {
            throw new DomainException('Añade al menos un elemento al plano. El máximo por diseño es 1.000 elementos.');
        }
        $elements = [];
        $ids = [];
        $allowedTools = self::tools();
        foreach ($design['elements'] as $element) {
            if (!is_array($element)) {
                throw new DomainException('Uno de los elementos del plano no es válido.');
            }
            $id = $element['id'] ?? '';
            $tool = $element['tool'] ?? '';
            $label = $element['label'] ?? '';
            $color = $element['color'] ?? '';
            if (!is_string($id) || !is_string($tool) || !is_string($label) || !is_string($color)
                || !preg_match('/^[A-Za-z0-9_-]{1,48}$/D', $id) || isset($ids[$id]) || !in_array($tool, $allowedTools, true)) {
                throw new DomainException('El identificador o tipo de un elemento no es válido.');
            }
            $ids[$id] = true;
            $isSign = self::signCode($tool) !== null;
            $normalized = [
                'id' => $id, 'tool' => $tool,
                'x' => self::coordinate($element['x'] ?? null, $width, 'X'),
                'y' => self::coordinate($element['y'] ?? null, $height, 'Y'),
                'label' => text_substr(trim($label), 0, 160),
                'color' => $isSign ? self::defaultColor($tool) : (in_array($color, self::COLORS, true) ? $color : '#334155'),
                'rotation' => self::number($element['rotation'] ?? 0, -360, 360),
                'stroke' => self::number($element['stroke'] ?? 8, 2, 30),
            ];
            if ($tool === 'text' && $normalized['label'] === '') {
                throw new DomainException('Los textos del plano no pueden quedar vacíos.');
            }
            if (in_array($tool, ['wall', 'route'], true)) {
                $normalized['x2'] = self::coordinate($element['x2'] ?? null, $width, 'X final');
                $normalized['y2'] = self::coordinate($element['y2'] ?? null, $height, 'Y final');
                if (hypot($normalized['x2'] - $normalized['x'], $normalized['y2'] - $normalized['y']) < 1) {
                    throw new DomainException('Los muros y las rutas deben tener una longitud visible.');
                }
            }
            $elements[] = $normalized;
        }
        return ['format' => 1, 'width' => $width, 'height' => $height, 'grid' => $grid, 'elements' => $elements];
    }

    public static function encode(array $design): string
    {
        return json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function library(): array
    {
        if (self::$library !== null) {
            return self::$library;
        }
        $path = BASE_PATH . '/resources/iso7010-catalog.json';
        if (!is_file($path)) {
            throw new RuntimeException('Falta el catálogo local de señalización ISO 7010.');
        }
        try {
            $library = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El catálogo local de señalización no es válido.', 0, $exception);
        }
        if (!isset($library['signs'], $library['categories']) || !is_array($library['signs'])
            || !is_array($library['categories']) || count($library['signs']) !== 332) {
            throw new RuntimeException('El catálogo local debe contener las 332 señales registradas.');
        }
        self::$library = $library;
        return $library;
    }

    private static function cadLibrary(): array
    {
        if (self::$cadLibrary !== null) {
            return self::$cadLibrary;
        }
        $path = BASE_PATH . '/resources/cad-objects.json';
        if (!is_file($path)) {
            throw new RuntimeException('Falta el catálogo local de objetos CAD.');
        }
        try {
            $library = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('El catálogo local de objetos CAD no es válido.', 0, $exception);
        }
        if (!isset($library['objects'], $library['categories']) || !is_array($library['objects'])
            || !is_array($library['categories']) || count($library['objects']) !== 32) {
            throw new RuntimeException('El catálogo CAD debe contener 32 objetos y sus familias.');
        }
        foreach ($library['objects'] as $tool => $object) {
            if (!is_array($object) || !is_string($tool) || !preg_match('/^cad_[a-z][a-z0-9_]{2,30}$/D', $tool)
                || !isset($library['categories'][$object['category'] ?? ''], $object['name'], $object['description'], $object['color'], $object['svg'])
                || !in_array($object['color'], self::COLORS, true) || !str_contains($object['svg'], '<svg')) {
                throw new RuntimeException('El catálogo local contiene un objeto CAD inválido.');
            }
        }
        self::$cadLibrary = $library;
        return $library;
    }

    private static function defaultColor(string $tool): string
    {
        $code = self::signCode($tool);
        if ($code !== null) {
            $category = self::library()['signs'][$code]['category'];
            return self::CATEGORY_COLORS[$category] ?? '#334155';
        }
        return $tool === 'route' ? '#237f52' : '#334155';
    }

    private static function integer(mixed $value, int $minimum, int $maximum, string $label): int
    {
        if (!is_numeric($value) || (int) $value != $value || (int) $value < $minimum || (int) $value > $maximum) {
            throw new DomainException("El {$label} del lienzo debe estar entre {$minimum} y {$maximum}.");
        }
        return (int) $value;
    }

    private static function coordinate(mixed $value, int $maximum, string $axis): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || (float) $value > $maximum) {
            throw new DomainException("La coordenada {$axis} está fuera del lienzo.");
        }
        return round((float) $value, 2);
    }

    private static function number(mixed $value, float $minimum, float $maximum): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < $minimum || (float) $value > $maximum) {
            throw new DomainException('Una propiedad numérica del plano está fuera de rango.');
        }
        return round((float) $value, 2);
    }
}
