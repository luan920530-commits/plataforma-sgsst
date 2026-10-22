<?php
namespace App;

/**
 * Metodología GTC 45 (Guía Técnica Colombiana) — valoración del riesgo:
 * NP = ND × NE · NR = NP × NC.
 * ND: 10 Muy alto, 6 Alto, 2 Medio, Bajo = sin valor (se trata como 0 en NP según GTC 45:
 * cuando ND es Bajo, NP se clasifica directamente en el nivel más bajo).
 */
class Gtc45
{
    public const DEFICIENCIA = [10 => 'Muy alto (10)', 6 => 'Alto (6)', 2 => 'Medio (2)', 0 => 'Bajo (sin valor asignado)'];
    public const EXPOSICION  = [4 => 'Continua (4)', 3 => 'Frecuente (3)', 2 => 'Ocasional (2)', 1 => 'Esporádica (1)'];
    public const CONSECUENCIA = [100 => 'Mortal o catastrófico (100)', 60 => 'Muy grave (60)', 25 => 'Grave (25)', 10 => 'Leve (10)'];

    public const CLASIFICACIONES = ['Biológico','Físico','Químico','Psicosocial','Biomecánico','Condiciones de seguridad','Fenómenos naturales','Otro'];

    public static function nivelProbabilidad(int $nd, int $ne): array
    {
        $np = $nd * $ne;
        // GTC 45: ND Bajo → NP Bajo directamente
        if ($nd === 0) return ['valor' => $np, 'nivel' => 'Bajo', 'rango' => 'Bajo'];
        $nivel = match(true) {
            $np >= 24 => 'Muy alto',
            $np >= 10 => 'Alto',
            $np >= 6  => 'Medio',
            default   => 'Bajo',
        };
        return ['valor' => $np, 'nivel' => $nivel, 'rango' => $nivel];
    }

    public static function nivelRiesgo(int $np, int $nc): array
    {
        $nr = $np * $nc;
        // Rangos GTC 45 (Tabla de determinación del nivel de riesgo)
        if ($nr >= 600)      { $nivel = 'I';   $aceptabilidad = 'No aceptable'; }
        elseif ($nr >= 150)  { $nivel = 'II';  $aceptabilidad = 'No aceptable o aceptable con control específico'; }
        elseif ($nr >= 40)   { $nivel = 'III'; $aceptabilidad = 'Mejorable'; }
        else                 { $nivel = 'IV';  $aceptabilidad = 'Aceptable'; }
        return ['valor' => $nr, 'nivel' => $nivel, 'aceptabilidad' => $aceptabilidad];
    }

    public static function evaluar(int $nd, int $ne, int $nc): array
    {
        $np = self::nivelProbabilidad($nd, $ne);
        $nr = self::nivelRiesgo($np['valor'], $nc);
        return [
            'np' => $np['valor'], 'np_nivel' => $np['nivel'],
            'nr' => $nr['valor'], 'nr_nivel' => $nr['nivel'],
            'aceptabilidad' => $nr['aceptabilidad'],
        ];
    }

    public static function color(string $nrNivel): string
    {
        return match($nrNivel) { 'I' => 'red', 'II' => 'amber', 'III' => 'yellow', default => 'green' };
    }
}
