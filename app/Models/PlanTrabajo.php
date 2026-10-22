<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PlanTrabajo
{
    public static function forTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM plan_trabajo WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND estado = ?';
            $params[] = $status;
        }
        return Database::query($sql . ' ORDER BY fecha_programada, id', $params);
    }

    public static function upcoming(int $tenantId, int $limit = 5): array
    {
        return Database::query(
            "SELECT * FROM plan_trabajo WHERE tenant_id = ? AND estado <> 'Completado' ORDER BY fecha_programada LIMIT " . max(1, $limit),
            [$tenantId]
        );
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM plan_trabajo WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO plan_trabajo (tenant_id, actividad, responsable, fecha_programada, estado, presupuesto, fase_phva)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], trim($data['actividad']), trim($data['responsable']), $data['fecha_programada'], $data['estado'] ?? 'Pendiente', (float) ($data['presupuesto'] ?? 0), $data['fase_phva'] ?? 'Planear']
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        $status = $data['estado'];
        $executionDate = $status === 'Completado' ? ($data['fecha_ejecucion'] ?? null) : null;
        Database::execute(
            'UPDATE plan_trabajo
             SET actividad = ?, responsable = ?, fecha_programada = ?, presupuesto = ?, fase_phva = ?, estado = ?, fecha_ejecucion = ?
             WHERE id = ? AND tenant_id = ?',
            [trim($data['actividad']), trim($data['responsable']), $data['fecha_programada'], (float) ($data['presupuesto'] ?? 0), $data['fase_phva'], $status, $executionDate, $id, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM plan_trabajo WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function updateStatus(int $id, int $tenantId, string $status, ?string $executionDate = null): void
    {
        $current = self::findForTenant($id, $tenantId);
        if ($current === null) return;
        if ($status === 'Completado') {
            $executionDate = $executionDate
                ?: ($current['estado'] === 'Completado' && $current['fecha_ejecucion'] ? $current['fecha_ejecucion'] : date('Y-m-d'));
        } else {
            $executionDate = null;
        }
        Database::execute('UPDATE plan_trabajo SET estado = ?, fecha_ejecucion = ? WHERE id = ? AND tenant_id = ?', [$status, $executionDate, $id, $tenantId]);
    }

    public static function stats(int $tenantId): array
    {
        return Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'Completado' THEN 1 ELSE 0 END) AS completadas,
                    SUM(CASE WHEN estado <> 'Completado' AND fecha_programada < date('now') THEN 1 ELSE 0 END) AS vencidas,
                    SUM(presupuesto) AS presupuesto
             FROM plan_trabajo WHERE tenant_id = ?",
            [$tenantId]
        ) ?? [];
    }
}
