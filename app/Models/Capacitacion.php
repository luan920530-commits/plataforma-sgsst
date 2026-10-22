<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Capacitacion
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT * FROM capacitaciones WHERE tenant_id = ? ORDER BY fecha_programada, id', [$tenantId]);
    }

    public static function upcoming(int $tenantId, int $limit = 4): array
    {
        return Database::query(
            "SELECT * FROM capacitaciones WHERE tenant_id = ? AND estado = 'Programada' ORDER BY fecha_programada LIMIT " . max(1, $limit),
            [$tenantId]
        );
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO capacitaciones (tenant_id, tema, descripcion, responsable, fecha_programada, duracion_horas, modalidad)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], trim($data['tema']), trim($data['descripcion'] ?? ''), trim($data['responsable']), $data['fecha_programada'], (float) $data['duracion_horas'], $data['modalidad']]
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE capacitaciones SET tema = ?, descripcion = ?, responsable = ?, fecha_programada = ?,
                                      duracion_horas = ?, modalidad = ? WHERE id = ? AND tenant_id = ?',
            [trim($data['tema']), trim($data['descripcion'] ?? ''), trim($data['responsable']), $data['fecha_programada'], (float) $data['duracion_horas'], $data['modalidad'], $id, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        Database::execute('UPDATE capacitaciones SET estado = ? WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
