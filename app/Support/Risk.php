<?php
declare(strict_types=1);

namespace App\Support;

final class Risk
{
    public static function calculate(?int $deficiency, ?int $exposure, ?int $consequence, string $fallback = 'Medio'): array
    {
        if ($deficiency === null || $exposure === null || $consequence === null) {
            return ['probability' => null, 'score' => null, 'level' => $fallback, ...self::interpretation($fallback)];
        }
        $probability = $deficiency * $exposure;
        $score = $probability * $consequence;
        $level = match (true) {
            $score >= 600 => 'Critico',
            $score >= 150 => 'Alto',
            $score >= 40 => 'Medio',
            default => 'Bajo',
        };
        return ['probability' => $probability, 'score' => $score, 'level' => $level, ...self::interpretation($level)];
    }

    /** Interpretación de NR de GTC 45; no reemplaza la intervención ni la verificación humana. */
    public static function interpretation(string $level): array
    {
        return match ($level) {
            'Critico' => ['acceptability' => 'No aceptable', 'intervention' => 'Suspender o controlar de inmediato hasta reducir el riesgo.'],
            'Alto' => ['acceptability' => 'No aceptable / control específico', 'intervention' => 'Corregir y adoptar medidas de control con seguimiento prioritario.'],
            'Medio' => ['acceptability' => 'Mejorable', 'intervention' => 'Mejorar el control existente y justificar la vigilancia aplicable.'],
            default => ['acceptability' => 'Aceptable', 'intervention' => 'Mantener los controles y verificar periódicamente que sigan siendo eficaces.'],
        };
    }
}
