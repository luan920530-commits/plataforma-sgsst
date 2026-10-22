<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class ClassificationService
{
    public static function groupFor(int $workers, string $risk): int
    {
        if ($workers < 1) {
            throw new InvalidArgumentException('El número de trabajadores debe ser mayor o igual a uno.');
        }
        if (!in_array($risk, ['I', 'II', 'III', 'IV', 'V'], true)) {
            throw new InvalidArgumentException('La clase de riesgo no es válida.');
        }
        if ($workers > 50 || in_array($risk, ['IV', 'V'], true)) {
            return 60;
        }
        return $workers <= 10 ? 7 : 21;
    }

    public static function explanation(int $workers, string $risk): array
    {
        $group = self::groupFor($workers, $risk);
        $reason = match ($group) {
            7 => 'Hasta 10 trabajadores y clase de riesgo I, II o III.',
            21 => 'Entre 11 y 50 trabajadores y clase de riesgo I, II o III.',
            default => in_array($risk, ['IV', 'V'], true)
                ? 'La clase de riesgo IV o V exige el esquema completo, sin importar el número de trabajadores.'
                : 'Las empresas con más de 50 trabajadores aplican el esquema completo.',
        };

        return [
            'grupo' => $group,
            'titulo' => $group . ' estándares mínimos',
            'razon' => $reason,
            'responsable' => match ($group) {
                7 => 'Verificar el perfil y las capacidades exigidas para diseñar y administrar el SG-SST en empresas de hasta 10 trabajadores.',
                21 => 'El diseño requiere el perfil, la experiencia, la licencia y la formación exigidos para este grupo.',
                default => 'El diseño e implementación debe estar a cargo de profesionales en SST o con posgrado, licencia vigente y curso de 50 horas.',
            },
        ];
    }
}
