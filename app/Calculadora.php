<?php
namespace App;

class Calculadora
{
    public static function calificar(?string $r): float
    {
        return match($r) {
            'SI' => 1.0, 'NO' => 0.0, 'PARCIAL' => 0.5, 'N/A' => 1.0, default => 0.0,
        };
    }

    public static function promedio(array $vals): float
    {
        if (empty($vals)) return 0.0;
        return round(array_sum($vals) / count($vals), 2);
    }

    public static function colorRombo(float $p): string
    {
        if ($p <= 0.5) return 'ROJO';
        if ($p <= 0.7) return 'AMARILLO';
        return 'VERDE';
    }

    public static function nivelRiesgo(array $rombos): string
    {
        $rojos = count(array_filter($rombos, fn($r) => $r === 'ROJO'));
        if ($rojos >= 3) return 'ALTO';
        if ($rojos >= 1) return 'MEDIO';
        return 'BAJO';
    }

    public static function recalcularAmenaza(int $amenazaId): void
    {
    $promedios = [];
    foreach ([2, 3, 4] as $formato) {
        $rows = Database::query(
            'SELECT calificacion FROM respuestas
             WHERE amenaza_id = ? AND formato = ? AND calificacion IS NOT NULL',
            [$amenazaId, $formato]
        );
        $promedios[$formato] = self::promedio(array_column($rows, 'calificacion'));
    }

    $colores = [
        self::colorRombo($promedios[2]),
        self::colorRombo($promedios[3]),
        self::colorRombo($promedios[4]),
        'AMARILLO', // placeholder para amenaza
    ];
    $nivel = self::nivelRiesgo($colores);

    Database::execute(
        'UPDATE amenazas SET
         promedio_personas = ?, promedio_recursos = ?, promedio_sistemas = ?,
         total_personas = ?, total_recursos = ?, total_sistemas = ?,
         color_personas = ?, color_recursos = ?, color_sistemas = ?,
         nivel_riesgo = ?
         WHERE id = ?',
        [
            $promedios[2], $promedios[3], $promedios[4],
            round($promedios[2] * 3, 2),
            round($promedios[3] * 3, 2),
            round($promedios[4] * 3, 2),
            $colores[0], $colores[1], $colores[2], $nivel,
            $amenazaId,
        ]
    );
}
}
