<?php
declare(strict_types=1);

namespace App\Support;

final class Indicator
{
    public static function definition(string $type): array
    {
        return match ($type) {
            'Frecuencia' => ['factor' => 240000, 'unidad' => 'AT por 240.000 HHT', 'formula' => 'Accidentes de trabajo × 240.000 ÷ horas-hombre trabajadas'],
            'Severidad' => ['factor' => 240000, 'unidad' => 'días por 240.000 HHT', 'formula' => 'Días de incapacidad y días cargados × 240.000 ÷ horas-hombre trabajadas'],
            'Mortalidad' => ['factor' => 100, 'unidad' => '%', 'formula' => 'Accidentes mortales × 100 ÷ total de accidentes de trabajo'],
            'Prevalencia' => ['factor' => 100000, 'unidad' => 'por 100.000 trabajadores', 'formula' => 'Enfermedades laborales existentes × 100.000 ÷ promedio de trabajadores'],
            'Incidencia' => ['factor' => 100000, 'unidad' => 'por 100.000 trabajadores', 'formula' => 'Enfermedades laborales nuevas × 100.000 ÷ promedio de trabajadores'],
            'Ausentismo' => ['factor' => 100, 'unidad' => '%', 'formula' => 'Días de ausencia por incapacidad × 100 ÷ días de trabajo programados'],
            default => ['factor' => 1, 'unidad' => '', 'formula' => 'Numerador × factor ÷ denominador'],
        };
    }

    public static function calculate(float $numerator, float $denominator, float $factor): float
    {
        return $denominator > 0 ? round(($numerator * $factor) / $denominator, 2) : 0.0;
    }
}
