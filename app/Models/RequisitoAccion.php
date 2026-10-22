<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RequisitoAccion
{
    public static function forRequirement(int $tenantId, string $key): array
    {
        return Database::query(
            'SELECT a.*, u.nombre AS responsable_nombre, u.email AS responsable_email,
                    c.nombre AS creado_por_nombre
             FROM requisito_acciones a
             JOIN users u ON u.id = a.responsable_id
             LEFT JOIN users c ON c.id = a.created_by
             WHERE a.tenant_id = ? AND a.requisito_clave = ?
             ORDER BY CASE a.estado WHEN \'Pendiente\' THEN 1 WHEN \'En Proceso\' THEN 2 ELSE 3 END,
                      a.fecha_compromiso IS NULL, a.fecha_compromiso, a.id DESC',
            [$tenantId, $key]
        );
    }

    public static function counts(int $tenantId): array
    {
        $rows = Database::query(
            "SELECT requisito_clave, COUNT(*) AS total,
                    SUM(CASE WHEN estado <> 'Completado' THEN 1 ELSE 0 END) AS abiertas
             FROM requisito_acciones WHERE tenant_id = ? GROUP BY requisito_clave",
            [$tenantId]
        );
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['requisito_clave']] = ['total' => (int) $row['total'], 'abiertas' => (int) $row['abiertas']];
        }
        return $counts;
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM requisito_acciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO requisito_acciones
             (tenant_id, requisito_clave, titulo, descripcion, responsable_id, recursos, fecha_compromiso, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], $data['requisito_clave'], trim($data['titulo']), trim($data['descripcion'] ?? ''),
                (int) $data['responsable_id'], trim($data['recursos'] ?? ''), $data['fecha_compromiso'] ?: null,
                isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]
        );
        return Database::lastId();
    }

    public static function updateManagement(int $id, int $tenantId, string $status, string $evidence): void
    {
        Database::execute(
            'UPDATE requisito_acciones SET estado = ?, evidencia = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [$status, trim($evidence), $id, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM requisito_acciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
