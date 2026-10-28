<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Indicator;

/** Calcula sólo con eventos y denominadores del periodo; nunca inventa HHT ni población. */
final class EventStatistics
{
    public static function calculate(array $period): array
    {
        $args = [(int) $period['tenant_id'], $period['fecha_inicio'], $period['fecha_fin']];
        $row = Database::first("SELECT
            SUM(CASE WHEN tipo='Accidente de trabajo' THEN 1 ELSE 0 END) accidentes,
            SUM(CASE WHEN tipo='Accidente de trabajo' AND gravedad='Mortal' THEN 1 ELSE 0 END) mortales,
            COALESCE(SUM(CASE WHEN tipo='Accidente de trabajo' THEN dias_perdidos+dias_cargados ELSE 0 END),0) dias,
            SUM(CASE WHEN tipo='Enfermedad laboral' THEN 1 ELSE 0 END) enfermedades_nuevas,
            SUM(CASE WHEN tipo='Incidente' THEN 1 ELSE 0 END) incidentes
            FROM eventos_sst WHERE tenant_id=? AND fecha_evento BETWEEN ? AND ?", $args) ?? [];
        $accidents = (int) ($row['accidentes'] ?? 0);
        $diseases = (int) ($row['enfermedades_nuevas'] ?? 0);
        $days = (int) ($row['dias'] ?? 0);
        $hht = (float) $period['horas_hombre_trabajadas'];
        $workers = (float) $period['trabajadores_promedio'];
        $programmed = (float) $period['dias_programados'];
        return [
            'accidentes' => $accidents, 'incidentes' => (int) ($row['incidentes'] ?? 0),
            'mortales' => (int) ($row['mortales'] ?? 0), 'dias' => $days,
            'enfermedades_nuevas' => $diseases,
            'frecuencia' => Indicator::calculate($accidents, $hht, 240000),
            'severidad' => Indicator::calculate($days, $hht, 240000),
            'ili' => Indicator::calculate($diseases, $workers, 100000),
            'accidentalidad' => Indicator::calculate($accidents, $workers, 100),
            'ausentismo' => $programmed > 0 ? Indicator::calculate((float) $period['dias_ausencia'], $programmed, 100) : null,
        ];
    }
}
