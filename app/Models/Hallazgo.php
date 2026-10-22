<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Hallazgo
{
    public static function forTenant(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM hallazgos_acpa WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND estado = ?';
            $params[] = $status;
        }
        return Database::query($sql . " ORDER BY CASE WHEN estado = 'Abierto' THEN 0 ELSE 1 END, fecha_limite, id DESC", $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM hallazgos_acpa WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO hallazgos_acpa (tenant_id, origen, descripcion, accion_correctiva, responsable, fecha_limite)
             VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], $data['origen'], trim($data['descripcion']), trim($data['accion_correctiva']), trim($data['responsable']), $data['fecha_limite']]
        );
        return Database::lastId();
    }

    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        Database::execute('UPDATE hallazgos_acpa SET estado = ?, fecha_cierre = ? WHERE id = ? AND tenant_id = ?', [$status, $status === 'Cerrado' ? date('Y-m-d') : null, $id, $tenantId]);
    }

    public static function openCount(int $tenantId): int
    {
        return (int) (Database::first("SELECT COUNT(*) AS total FROM hallazgos_acpa WHERE tenant_id = ? AND estado = 'Abierto'", [$tenantId])['total'] ?? 0);
    }
}
