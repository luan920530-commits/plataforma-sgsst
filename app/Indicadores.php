<?php
namespace App;

/**
 * Indicadores del SG-SST (Resolución 0312/2019), calculados por periodo mensual.
 * - Frecuencia de accidentalidad: (N.° accidentes / N.° horas-hombre trabajadas) × 240.000
 * - Severidad de accidentalidad:  ((días de incapacidad + días cargados) / HHT) × 240.000
 * - Proporción de accidentes mortales: (accidentes mortales / total accidentes) × 100
 * - Ausentismo por causa médica: (días de ausencia por incapacidad / días programados) × 100
 * - Índice de lesiones incapacitantes (ILI): IF × IS / 1.000 (referencia complementaria)
 * Los conteos de accidentes/enfermedades salen automáticamente de los registros del
 * módulo de gestión; las horas y días se capturan por periodo (nómina).
 */
class Indicadores
{
    public const K = 240000;

    public static function calcular(array $periodo, int $accidentes, int $enfermedades): array
    {
        $hht = (float)($periodo['horas_trabajadas'] ?? 0);
        $diasPerdidos = (int)($periodo['dias_perdidos'] ?? 0) + (int)($periodo['dias_cargados'] ?? 0);
        $mortales = (int)($periodo['accidentes_mortales'] ?? 0);
        $programados = (int)($periodo['dias_programados'] ?? 0);
        $ausencia = (int)($periodo['dias_ausencia_medica'] ?? 0);

        $if = $hht > 0 ? round($accidentes / $hht * self::K, 2) : 0.0;
        $is = $hht > 0 ? round($diasPerdidos / $hht * self::K, 2) : 0.0;
        return [
            'accidentes' => $accidentes,
            'enfermedades' => $enfermedades,
            'frecuencia' => $if,
            'severidad' => $is,
            'ili' => round($if * $is / 1000, 3),
            'mortalidad_pct' => $accidentes > 0 ? round($mortales / $accidentes * 100, 2) : 0.0,
            'ausentismo_pct' => $programados > 0 ? round($ausencia / $programados * 100, 2) : 0.0,
        ];
    }

    /** Periodos de la empresa con indicadores ya calculados. */
    public static function porEmpresa(int $empresaId): array
    {
        $periodos = Database::query(
            'SELECT * FROM sgsst_periodos WHERE empresa_id = ? ORDER BY periodo DESC LIMIT 24',
            [$empresaId]
        );
        foreach ($periodos as &$p) {
            $conteos = Database::first(
                "SELECT
                   SUM(CASE WHEN tipo='accidente' THEN 1 ELSE 0 END) AS acc,
                   SUM(CASE WHEN tipo='enfermedad' THEN 1 ELSE 0 END) AS enf
                 FROM sgsst_registros WHERE empresa_id = ? AND strftime('%Y-%m', fecha) = ?",
                [$empresaId, $p['periodo']]
            ) ?: [];
            $p['indicadores'] = self::calcular($p, (int)($conteos['acc'] ?? 0), (int)($conteos['enf'] ?? 0));
        }
        unset($p);
        return $periodos;
    }
}
