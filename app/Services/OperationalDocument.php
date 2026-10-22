<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Documento;
use App\Models\PlanEmergencia;
use App\Models\ProgramaActividad;
use App\Models\ProgramaPrevencion;
use RuntimeException;

final class OperationalDocument
{
    public static function createEmergency(int $planId, int $tenantId): int
    {
        $plan = PlanEmergencia::findForTenant($planId, $tenantId);
        if ($plan === null) {
            throw new RuntimeException('No se encontró el plan de emergencias.');
        }
        return Documento::createOperational([
            'tenant_id' => $tenantId,
            'codigo' => 'EMG-PLAN-' . $planId,
            'nombre' => $plan['titulo'],
            'fase' => 'Hacer',
            'tipo' => 'Plan',
            'contenido' => self::emergency($plan),
        ]);
    }

    public static function refreshEmergency(int $planId, int $tenantId): void
    {
        $plan = PlanEmergencia::findForTenant($planId, $tenantId);
        if ($plan === null || empty($plan['documento_id'])) {
            throw new RuntimeException('El plan no tiene un documento asociado.');
        }
        Documento::saveDraft((int) $plan['documento_id'], $tenantId, self::emergency($plan));
    }

    public static function createProgram(int $programId, int $tenantId): int
    {
        $program = ProgramaPrevencion::findForTenant($programId, $tenantId);
        if ($program === null) {
            throw new RuntimeException('No se encontró el programa preventivo.');
        }
        return Documento::createOperational([
            'tenant_id' => $tenantId,
            'codigo' => 'PYP-DOC-' . $programId,
            'nombre' => $program['nombre'] . ' · ' . $program['anio'],
            'fase' => 'Hacer',
            'tipo' => 'Programa',
            'contenido' => self::program($program, []),
        ]);
    }

    public static function refreshProgram(int $programId, int $tenantId): void
    {
        $program = ProgramaPrevencion::findForTenant($programId, $tenantId);
        if ($program === null || empty($program['documento_id'])) {
            throw new RuntimeException('El programa no tiene un documento asociado.');
        }
        $activities = ProgramaActividad::forProgram($programId, $tenantId);
        Documento::saveDraft((int) $program['documento_id'], $tenantId, self::program($program, $activities));
    }

    public static function emergency(array $plan): string
    {
        $html = '<h1>' . e($plan['titulo']) . '</h1>'
            . '<p><strong>Código:</strong> ' . e($plan['codigo']) . ' · <strong>Centro:</strong> ' . e($plan['centro_trabajo']) . '</p>'
            . '<p><strong>Dirección:</strong> ' . e($plan['direccion'] ?: 'Por confirmar por la empresa') . '</p>'
            . '<blockquote>Borrador estructurado con los datos registrados. Debe ser revisado, aprobado, divulgado e implementado por la empresa. La redacción no acredita por sí sola la preparación ni la capacidad de respuesta.</blockquote>'
            . '<h2>1. Alcance y responsabilidad</h2>' . self::paragraphs($plan['alcance'])
            . '<p><strong>Responsable del plan:</strong> ' . e($plan['responsable_plan']) . '</p>'
            . '<p><strong>Señal o número interno de alarma:</strong> ' . e($plan['telefono_alarma'] ?: 'Por definir') . '</p>'
            . '<h2>2. Amenazas, vulnerabilidad y controles</h2>'
            . self::table(['Amenaza', 'Probabilidad', 'Impacto', 'Vulnerabilidad / controles existentes'], self::rows($plan['amenazas_json'], ['nombre', 'probabilidad', 'impacto', 'controles']))
            . '<h2>3. Contactos y redes de apoyo</h2>'
            . self::table(['Contacto o entidad', 'Tipo', 'Teléfono / medio'], self::rows($plan['contactos_json'], ['nombre', 'tipo', 'telefono']))
            . '<h2>4. Brigada y roles</h2>'
            . self::table(['Persona / equipo', 'Rol', 'Contacto'], self::rows($plan['brigada_json'], ['nombre', 'rol', 'contacto']))
            . '<h2>5. Recursos para la respuesta</h2>'
            . self::table(['Recurso', 'Cantidad', 'Ubicación', 'Estado', 'Inspección'], self::rows($plan['recursos_json'], ['nombre', 'cantidad', 'ubicacion', 'estado', 'inspeccion']))
            . '<h2>6. Procedimientos de prevención, preparación y respuesta</h2>' . self::paragraphs($plan['procedimientos'])
            . '<h2>7. Evacuación y puntos de encuentro</h2>' . self::paragraphs($plan['rutas_evacuacion'])
            . '<h3>Puntos de encuentro</h3>' . self::paragraphs($plan['puntos_encuentro'])
            . '<h2>8. Rescate y atención</h2>' . self::paragraphs($plan['plan_rescate'])
            . '<h2>9. Ayuda mutua y articulación externa</h2>' . self::paragraphs($plan['ayuda_mutua'])
            . '<h2>10. Entrenamiento, mantenimiento y revisión</h2>'
            . '<p><strong>Próximo simulacro programado:</strong> ' . e(format_date($plan['proximo_simulacro'] ?: null, true)) . '</p>'
            . '<p><strong>Fecha prevista de revisión:</strong> ' . e(format_date($plan['fecha_revision'] ?: null, true)) . '</p>'
            . '<p>La empresa debe realizar simulacros como mínimo una vez al año, capacitar y dotar la brigada según sus riesgos e inspeccionar los equipos con la periodicidad definida en el SG-SST.</p>'
            . '<h2>11. Soportes que la empresa debe aportar y mantener</h2>'
            . '<ul><li>Plano actualizado de instalaciones, rutas de evacuación, salidas, equipos y puntos de encuentro.</li>'
            . '<li>Verificación de números de emergencia, directorio interno y acuerdos de ayuda mutua.</li>'
            . '<li>Registros de conformación, formación, entrenamiento y dotación de la brigada.</li>'
            . '<li>Inspecciones y mantenimiento de alarmas, señalización, extintores, botiquines y demás equipos.</li>'
            . '<li>Informes, listas de participación y acciones de mejora de simulacros.</li></ul>'
            . '<p><strong>Referencia de diseño:</strong> Decreto 1072 de 2015, artículo 2.2.4.6.25. La aplicabilidad de otras disposiciones depende de las amenazas, la actividad y el contexto de cada centro de trabajo.</p>';
        return $html;
    }

    public static function program(array $program, array $activities): string
    {
        $rows = [];
        foreach ($activities as $activity) {
            $rows[] = [$activity['actividad'], $activity['responsable'], $activity['fecha_programada'], $activity['indicador'], $activity['meta'], $activity['estado']];
        }
        return '<h1>' . e($program['nombre']) . '</h1>'
            . '<p><strong>Código:</strong> ' . e($program['codigo']) . ' · <strong>Categoría:</strong> ' . e($program['categoria']) . ' · <strong>Año:</strong> ' . (int) $program['anio'] . '</p>'
            . '<blockquote>Documento generado como borrador editable. Debe sustentarse en el diagnóstico y los riesgos reales, contar con recursos y responsables, ejecutarse y evaluarse. No acredita cumplimiento por su sola emisión.</blockquote>'
            . '<h2>1. Diagnóstico y justificación</h2>' . self::paragraphs($program['diagnostico'])
            . '<h2>2. Población y peligros relacionados</h2>' . self::paragraphs($program['poblacion']) . self::paragraphs($program['peligros_relacionados'])
            . '<h2>3. Objetivo general</h2>' . self::paragraphs($program['objetivo_general'])
            . '<h2>4. Objetivos específicos</h2>' . self::paragraphs($program['objetivos_especificos'])
            . '<h2>5. Metas e indicadores</h2>' . self::paragraphs($program['metas_indicadores'])
            . '<h2>6. Cronograma de actividades</h2>' . self::table(['Actividad', 'Responsable', 'Fecha', 'Indicador', 'Meta', 'Estado'], $rows)
            . '<h2>7. Responsables y recursos</h2><p><strong>Responsable:</strong> ' . e($program['responsable']) . '</p>'
            . self::paragraphs($program['recursos']) . '<p><strong>Presupuesto de referencia:</strong> ' . e(money($program['presupuesto'])) . '</p>'
            . '<p><strong>Vigencia:</strong> ' . e(format_date($program['fecha_inicio'])) . ' a ' . e(format_date($program['fecha_fin'])) . '</p>'
            . '<h2>8. Seguimiento y evidencias</h2><p>Registrar la ejecución, participantes, resultados de indicadores, soportes y acciones de mejora. Las actividades marcadas como completadas deben contar con evidencia verificable.</p>'
            . '<p><strong>Referencias de orientación:</strong> Decreto 1072 de 2015, artículos 2.2.4.6.11, 2.2.4.6.17, 2.2.4.6.24 y los que resulten aplicables al riesgo intervenido; Resolución 0312 de 2019 según el grupo de la empresa.</p>';
    }

    private static function rows(?string $json, array $keys): array
    {
        $decoded = json_decode($json ?: '[]', true);
        if (!is_array($decoded)) {
            return [];
        }
        $rows = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = [];
            foreach ($keys as $key) {
                $row[] = (string) ($item[$key] ?? '');
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function table(array $headers, array $rows): string
    {
        if ($rows === []) {
            return '<p><strong>Pendiente:</strong> la empresa debe registrar y validar esta información.</p>';
        }
        $html = '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . e($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }

    private static function paragraphs(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '<p><strong>Pendiente de diligenciamiento y validación por la empresa.</strong></p>';
        }
        $parts = preg_split('/\R{2,}|\R/', $text) ?: [];
        $html = '';
        foreach ($parts as $part) {
            if (trim($part) !== '') {
                $html .= '<p>' . e(trim($part)) . '</p>';
            }
        }
        return $html;
    }
}
