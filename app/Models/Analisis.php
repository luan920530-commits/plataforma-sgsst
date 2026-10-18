<?php
namespace App\Models;

use App\Database;

class Analisis
{
    public static function porEmpresa(int $empresaId): array
    {
        return Database::query(
            'SELECT * FROM analisis WHERE empresa_id = ? ORDER BY created_at DESC',
            [$empresaId]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::first('SELECT * FROM analisis WHERE id = ?', [$id]);
    }

    public static function crear(int $empresaId, array $d = []): int
    {
        Database::execute(
            'INSERT INTO analisis (empresa_id, codigo_documento, version, fecha_elaboracion, estado)
             VALUES (?, ?, ?, ?, ?)',
            [
                $empresaId,
                $d['codigo_documento'] ?? 'SST-F-1',
                $d['version'] ?? '1',
                $d['fecha_elaboracion'] ?? date('Y-m-d'),
                $d['estado'] ?? 'borrador',
            ]
        );
        return Database::lastId();
    }

    public static function actualizarEstado(int $id, string $estado): void
    {
        Database::execute(
            'UPDATE analisis SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$estado, $id]
        );
    }

    public static function ultimoPorEmpresa(int $empresaId): ?array
    {
        return Database::first(
            'SELECT * FROM analisis WHERE empresa_id = ? ORDER BY created_at DESC LIMIT 1',
            [$empresaId]
        );
    }
}
