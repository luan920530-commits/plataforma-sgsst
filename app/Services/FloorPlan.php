<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;
use JsonException;

/** Valida y normaliza el formato de datos del editor SVG; nunca acepta markup del cliente. */
final class FloorPlan
{
    private const TOOLS = ['wall', 'door', 'route', 'exit', 'extinguisher', 'first_aid', 'alarm', 'assembly', 'text'];
    private const COLORS = ['#0f172a', '#334155', '#64748b', '#2563eb', '#15803d', '#16a34a', '#dc2626', '#f97316'];

    public static function defaults(): array
    {
        return ['format' => 1, 'width' => 1000, 'height' => 650, 'grid' => 20, 'elements' => []];
    }

    public static function tools(): array
    {
        return self::TOOLS;
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
        foreach ($design['elements'] as $element) {
            if (!is_array($element)) {
                throw new DomainException('Uno de los elementos del plano no es válido.');
            }
            $id = $element['id'] ?? '';
            $tool = $element['tool'] ?? '';
            $label = $element['label'] ?? '';
            $color = $element['color'] ?? '';
            if (!is_string($id) || !is_string($tool) || !is_string($label) || !is_string($color)
                || !preg_match('/^[A-Za-z0-9_-]{1,48}$/D', $id) || isset($ids[$id]) || !in_array($tool, self::TOOLS, true)) {
                throw new DomainException('El identificador o tipo de un elemento no es válido.');
            }
            $ids[$id] = true;
            $normalized = [
                'id' => $id, 'tool' => $tool,
                'x' => self::coordinate($element['x'] ?? null, $width, 'X'),
                'y' => self::coordinate($element['y'] ?? null, $height, 'Y'),
                'label' => text_substr(trim($label), 0, 160),
                'color' => in_array($color, self::COLORS, true) ? $color : '#334155',
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
