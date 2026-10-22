<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProgramaActividad
{
    public static function forProgram(int $programId, int $tenantId): array
    {
        return Database::query('SELECT * FROM programa_actividades WHERE programa_id = ? AND tenant_id = ? ORDER BY fecha_programada, id', [$programId, $tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM programa_actividades WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO programa_actividades (tenant_id, programa_id, actividad, responsable, fecha_programada, indicador, meta, recursos)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], (int) $data['programa_id'], trim($data['actividad']), trim($data['responsable']), $data['fecha_programada'], trim($data['indicador'] ?? ''), trim($data['meta'] ?? ''), trim($data['recursos'] ?? '')]
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE programa_actividades SET actividad = ?, responsable = ?, fecha_programada = ?, indicador = ?, meta = ?, recursos = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [trim($data['actividad']), trim($data['responsable']), $data['fecha_programada'], trim($data['indicador'] ?? ''), trim($data['meta'] ?? ''), trim($data['recursos'] ?? ''), $id, $tenantId]
        );
    }

    public static function setStatus(int $id, int $tenantId, string $status, string $evidence): void
    {
        Database::execute('UPDATE programa_actividades SET estado = ?, evidencia = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, trim($evidence), $id, $tenantId]);
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM programa_actividades WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
