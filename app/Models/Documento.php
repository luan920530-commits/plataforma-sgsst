<?php
namespace App\Models;

use App\Database;

class Documento
{
    public static function porAnalisis(int $analisisId): array
    {
        return Database::query(
            'SELECT * FROM documentos WHERE analisis_id = ? ORDER BY created_at DESC',
            [$analisisId]
        );
    }

    public static function crear(int $analisisId, array $d): int
    {
        Database::execute(
            'INSERT INTO documentos
             (analisis_id, tipo, nombre_archivo, ruta, hash_sha256, generado_por)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $analisisId,
                $d['tipo'],
                $d['nombre_archivo'],
                $d['ruta'],
                $d['hash_sha256'] ?? hash_file('sha256', $d['ruta']),
                $d['generado_por'] ?? null,
            ]
        );
        return Database::lastId();
    }
}
