<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Contenido técnico diferenciado para cada estándar mínimo; no usa un desarrollo genérico de respaldo. */
final class StandardDocumentTemplate
{
    private static ?array $resource = null;

    public static function render(array $tenant, array $standard): string
    {
        $code = (string) $standard['codigo'];
        $resource = self::resource();
        $topicKey = $resource['standard_map'][$code] ?? null;
        if (!is_string($topicKey) || !isset($resource['topics'][$topicKey])) {
            throw new RuntimeException('El estándar ' . $code . ' no tiene contenido técnico asociado.');
        }
        $topic = $resource['topics'][$topicKey];
        $activity = trim((string) ($tenant['actividad'] ?? 'Por caracterizar'));
        $ciiu = trim((string) ($tenant['codigo_ciuu'] ?? 'Por validar'));
        $body = '<h2>1. Objeto y criterio de aplicación</h2>'
            . '<p>' . e($topic['focus']) . ' Este lineamiento se aplica en ' . $tenant['razonsocial_html'] . ' dentro del régimen de <strong>' . (int) $tenant['grupo_estandares'] . ' estándares</strong>, sin limitar otras obligaciones que resulten aplicables.</p>'
            . '<h2>2. Contexto que debe validarse</h2>'
            . '<table><tbody><tr><th>Actividad registrada</th><td>' . e($activity) . ' · CIIU ' . e($ciiu) . '</td><th>Población registrada</th><td>' . (int) $tenant['numero_trabajadores'] . ' trabajadores</td></tr>'
            . '<tr><th>Clase de riesgo registrada</th><td>' . e((string) $tenant['nivel_riesgo']) . '</td><th>Alcance por completar</th><td>Centros, cargos, turnos, contratistas y condiciones reales</td></tr></tbody></table>'
            . '<p>La clasificación del asistente orienta la selección documental. Antes de aprobar este estándar se deben contrastar procesos, peligros, fuentes y evidencias de la operación.</p>'
            . '<h2>3. Procedimiento de implementación</h2><ol>' . self::items($topic['actions'], 'li') . '</ol>'
            . '<h2>4. Controles y verificación</h2><ul>' . self::items($topic['checks'], 'li') . '</ul>'
            . '<h2>5. Evidencias que la empresa debe producir o aportar</h2><ul>' . self::items($topic['evidence'], 'li') . '</ul>'
            . '<p><strong>Estado inicial:</strong> no se han precargado ejecuciones, firmas, certificados, resultados, conceptos ni cierres. Los soportes se incorporan únicamente después de realizar y revisar la actividad correspondiente.</p>'
            . '<h2>6. Referente y revisión</h2><p>' . e($topic['reference']) . '</p>'
            . '<p>Revisar al menos anualmente y cuando cambien la normatividad, los procesos, la población, los peligros o los resultados de seguimiento. Las referencias deben contrastarse con sus modificaciones y con requisitos sectoriales adicionales.</p>';
        return DocumentTemplate::layout($tenant, $code, (string) $standard['nombre'], (string) $standard['fase_phva'], $body);
    }

    public static function resource(): array
    {
        if (self::$resource !== null) {
            return self::$resource;
        }
        $path = BASE_PATH . '/resources/starter-package/standards.json';
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException('No se encontró la fuente de contenido de estándares.');
        }
        $resource = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($resource['topics'], $resource['standard_map']) || !is_array($resource['topics']) || !is_array($resource['standard_map'])) {
            throw new RuntimeException('La fuente de contenido de estándares no es válida.');
        }
        self::$resource = $resource;
        return self::$resource;
    }

    private static function items(array $items, string $tag): string
    {
        $html = '';
        foreach ($items as $item) {
            $html .= '<' . $tag . '>' . e((string) $item) . '</' . $tag . '>';
        }
        return $html;
    }
}
