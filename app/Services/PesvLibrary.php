<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Recursos PESV importados del paquete aportado por el usuario.
 * Los formularios se abren en el editor local; nunca se ejecutan macros ni se consultan vínculos externos.
 */
final class PesvLibrary
{
    public const PLAN_CODE = 'PESV-INDICE';
    private static ?array $manifest = null;
    private static ?array $byNumber = null;
    private static ?array $byId = null;

    public static function all(): array
    {
        $items = self::manifest()['templates'];
        usort($items, static fn(array $a, array $b): int => [$a['step'], $a['title'], $a['number']] <=> [$b['step'], $b['title'], $b['number']]);
        return $items;
    }

    public static function steps(): array
    {
        $items = self::all();
        return array_map(static function (array $step) use ($items): array {
            $step['step'] = (int) $step['number'];
            $step['templates'] = count(array_filter($items, static fn(array $item): bool => in_array((int) $step['step'], $item['steps'], true)));
            return $step;
        }, self::manifest()['steps']);
    }

    public static function find(int $number): ?array
    {
        if (self::$byNumber === null) {
            self::$byNumber = [];
            foreach (self::manifest()['templates'] as $item) {
                self::$byNumber[(int) $item['number']] = $item;
            }
        }
        return self::$byNumber[$number] ?? null;
    }

    public static function findById(string $id): ?array
    {
        if (self::$byId === null) {
            self::$byId = [];
            foreach (self::manifest()['templates'] as $item) {
                self::$byId[$item['id']] = $item;
            }
        }
        return self::$byId[$id] ?? null;
    }

    public static function filter(array $filters): array
    {
        $query = text_lower(trim((string) ($filters['q'] ?? '')));
        $type = trim((string) ($filters['tipo'] ?? ''));
        $step = (int) ($filters['paso'] ?? 0);
        return array_values(array_filter(self::all(), static function (array $item) use ($query, $type, $step): bool {
            $haystack = text_lower(implode(' ', [$item['id'], $item['title'], $item['module'], $item['code'], $item['folder'], $item['step_name']]));
            return ($query === '' || str_contains($haystack, $query))
                && ($type === '' || $item['kind'] === $type)
                && ($step <= 0 || in_array($step, $item['steps'], true));
        }));
    }

    public static function stats(): array
    {
        $items = self::all();
        $manifest = self::manifest();
        return [
            'templates' => count($items),
            'source_entries' => (int) $manifest['source_entries'],
            'duplicate_entries' => (int) $manifest['duplicate_entries'],
            'documents' => count(array_filter($items, static fn(array $item): bool => $item['kind'] === 'document')),
            'forms' => count(array_filter($items, static fn(array $item): bool => $item['kind'] === 'form')),
            'steps' => count($manifest['steps']),
            'formulas' => array_sum(array_map(static fn(array $item): int => (int) ($item['stats']['formulas'] ?? 0), $items)),
            'residual_format_cells' => array_sum(array_map(static fn(array $item): int => (int) ($item['stats']['residual_format_cells_excluded'] ?? 0), $items)),
            'security' => $manifest['security'],
        ];
    }

    public static function isRecommended(array $tenant, ?array $activity = null): bool
    {
        $code = trim((string) ($activity['codigo_ciuu'] ?? $tenant['codigo_ciuu'] ?? ''));
        $sector = text_lower(trim(implode(' ', [
            (string) ($tenant['sector_economico'] ?? ''),
            (string) ($tenant['actividad'] ?? ''),
            (string) ($activity['descripcion'] ?? ''),
            (string) ($activity['sector'] ?? ''),
        ])));
        return str_contains($sector, 'transporte') || in_array($code, ['4921', '4922', '4923', '5011', '5012', '5021', '5022', '5110', '5121', '5122', '5210', '5221', '5222', '5223', '5224', '5229'], true);
    }

    public static function content(array $template): string
    {
        if (($template['kind'] ?? '') !== 'document') {
            throw new RuntimeException('Esta plantilla PESV requiere el editor de formularios.');
        }
        $path = self::basePath() . DIRECTORY_SEPARATOR . $template['content'];
        $content = is_file($path) ? file_get_contents($path) : false;
        if ($content === false || trim($content) === '') {
            throw new RuntimeException('El contenido PESV no está disponible. Reinstala los recursos de la aplicación.');
        }
        return $content;
    }

    public static function form(array $template): array
    {
        if (($template['kind'] ?? '') !== 'form') {
            throw new RuntimeException('Esta plantilla PESV requiere el editor de documentos.');
        }
        $path = self::basePath() . DIRECTORY_SEPARATOR . $template['content'];
        $raw = is_file($path) ? file_get_contents($path) : false;
        $form = $raw === false ? null : json_decode($raw, true);
        if (!is_array($form) || !isset($form['sheets']) || !is_array($form['sheets'])) {
            throw new RuntimeException('El formulario PESV no tiene una estructura válida.');
        }
        return $form;
    }

    public static function planDraft(array $tenant): string
    {
        $body = '<p>Este borrador organiza el paquete PESV aportado para su diligenciamiento en ' . e((string) ($tenant['razonsocial'] ?? 'la empresa')) . '. La estructura conserva los 24 pasos identificados en el archivo de origen; no sustituye el análisis de aplicabilidad, la verificación de la flota, los desplazamientos ni la aprobación de la alta dirección.</p>';
        $body .= '<div class="document-notice"><strong>Antes de aprobar:</strong> completa los formatos con evidencias reales, revisa las advertencias de conversión y define responsables, metas, indicadores y fecha de revisión. Las referencias externas de los Excel fueron excluidas y no se consultan desde la aplicación.</div>';
        $body .= '<h2>Estructura documental recuperada</h2><ol>';
        foreach (self::steps() as $step) {
            $items = array_values(array_filter(self::all(), static fn(array $item): bool => in_array((int) $step['step'], $item['steps'], true)));
            $titles = implode('; ', array_map(static fn(array $item): string => e((string) $item['title']), $items));
            $body .= '<li><strong>Paso ' . (int) $step['step'] . ' — ' . e((string) $step['name']) . '.</strong> ' . count($items) . ' plantilla(s) única(s)';
            if ($titles !== '') {
                $body .= ': ' . $titles;
            }
            $body .= '.</li>';
        }
        $body .= '</ol><h2>Uso y trazabilidad</h2><p>Cada plantilla se abre desde la biblioteca PESV, se guarda con identificación de empresa y conserva su versión, autor y fecha. Las respuestas no son evidencia de cumplimiento por sí solas: requieren revisión humana y validación frente a las condiciones reales de la organización.</p>';
        return DocumentTemplate::layout(DocumentTemplate::prepareTenant($tenant), self::PLAN_CODE, 'Plan Estratégico de Seguridad Vial (PESV)', 'Planear', $body);
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $path = self::basePath() . DIRECTORY_SEPARATOR . 'manifest.json';
        $raw = is_file($path) ? file_get_contents($path) : false;
        $manifest = $raw === false ? null : json_decode($raw, true);
        if (!is_array($manifest) || !isset($manifest['templates'], $manifest['steps']) || !is_array($manifest['templates'])) {
            throw new RuntimeException('La biblioteca PESV no está instalada correctamente.');
        }
        self::$manifest = $manifest;
        return self::$manifest;
    }

    private static function basePath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'pesv-library';
    }
}
