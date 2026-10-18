<?php
namespace App;

class Validador
{
    public static function validar(int $analisisId): array
    {
        $alertas = [];

        $analisis = Database::first(
            'SELECT a.*, e.id AS empresa_id, e.num_trabajadores
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE a.id = ?',
            [$analisisId]
        );

        if (!$analisis) return [['tipo' => 'error', 'norma' => '', 'mensaje' => 'Análisis no encontrado']];

        // Res. 256/2014 — Brigada ≥ 20%
        $brigadistas = \App\Models\Personal::contarBrigadistas((int) $analisis['empresa_id']);
        $minimo      = (int) ceil(((int) $analisis['num_trabajadores']) * 0.20);

        if ($brigadistas < $minimo) {
            $alertas[] = [
                'tipo'    => 'warning',
                'norma'   => 'Res. 256/2014',
                'mensaje' => "Brigada incompleta: {$brigadistas} brigadistas, mínimo {$minimo}.",
            ];
        }

        // Decreto 1072/2015 — Actualización anual
        $dias = (time() - strtotime($analisis['fecha_elaboracion'])) / 86400;
        if ($dias > 365) {
            $alertas[] = [
                'tipo'    => 'info',
                'norma'   => 'Decreto 1072/2015',
                'mensaje' => 'El plan tiene más de un año. Actualizar.',
            ];
        }

        return $alertas;
    }
}
