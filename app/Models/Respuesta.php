<?php
namespace App\Models;

use App\Database;

class Respuesta
{
    public static function porAmenaza(int $amenazaId): array
    {
        return Database::query(
            'SELECT * FROM respuestas WHERE amenaza_id = ? ORDER BY formato, orden, id',
            [$amenazaId]
        );
    }

    public static function porAmenazaYFormato(int $amenazaId, int $formato): array
    {
        return Database::query(
            'SELECT * FROM respuestas WHERE amenaza_id = ? AND formato = ? ORDER BY orden, id',
            [$amenazaId, $formato]
        );
    }

    public static function crear(int $amenazaId, array $d): int
    {
        Database::execute(
            'INSERT INTO respuestas
             (amenaza_id, formato, categoria, pregunta, respuesta, calificacion, observacion, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $amenazaId,
                (int) $d['formato'],
                $d['categoria'],
                $d['pregunta'],
                $d['respuesta'] ?? null,
                isset($d['calificacion']) ? (float) $d['calificacion'] : null,
                $d['observacion'] ?? null,
                (int) ($d['orden'] ?? 0),
            ]
        );
        return Database::lastId();
    }

    public static function actualizar(int $id, array $d): void
    {
        Database::execute(
            'UPDATE respuestas SET respuesta = ?, calificacion = ?, observacion = ? WHERE id = ?',
            [
                $d['respuesta'] ?? null,
                isset($d['calificacion']) ? (float) $d['calificacion'] : null,
                $d['observacion'] ?? null,
                $id,
            ]
        );
    }

    public static function eliminarPorAmenaza(int $amenazaId): void
    {
        Database::execute('DELETE FROM respuestas WHERE amenaza_id = ?', [$amenazaId]);
    }
}
