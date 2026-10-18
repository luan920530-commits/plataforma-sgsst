<?php
namespace App\Models;

use App\Database;

class Amenaza
{
    public static function porAnalisis(int $analisisId): array
    {
        return Database::query(
            'SELECT * FROM amenazas WHERE analisis_id = ? AND aplica = 1 ORDER BY id',
            [$analisisId]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::first('SELECT * FROM amenazas WHERE id = ?', [$id]);
    }

    public static function crear(int $analisisId, array $d): int
    {
        Database::execute(
            'INSERT INTO amenazas (analisis_id, tipo, nombre, origen, calificacion_color)
             VALUES (?, ?, ?, ?, ?)',
            [
                $analisisId,
                $d['tipo'],
                $d['nombre'],
                $d['origen'] ?? 'externa',
                $d['calificacion_color'] ?? 'AMARILLO',
            ]
        );
        return Database::lastId();
    }

    public static function actualizarPromedios(int $id, array $d): void
    {
        Database::execute(
            'UPDATE amenazas SET
             promedio_personas = ?, promedio_recursos = ?, promedio_sistemas = ?,
             total_personas = ?, total_recursos = ?, total_sistemas = ?,
             color_personas = ?, color_recursos = ?, color_sistemas = ?,
             nivel_riesgo = ?
             WHERE id = ?',
            [
                $d['promedio_personas'], $d['promedio_recursos'], $d['promedio_sistemas'],
                $d['total_personas'], $d['total_recursos'], $d['total_sistemas'],
                $d['color_personas'], $d['color_recursos'], $d['color_sistemas'],
                $d['nivel_riesgo'], $id,
            ]
        );
    }
}
