<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Empleado
{
    public static function forTenant(int $tenantId, string $search = ''): array
    {
        $sql = 'SELECT e.*, u.rol AS usuario_rol FROM empleados e LEFT JOIN users u ON u.id = e.user_id WHERE e.tenant_id = ?';
        $params = [$tenantId];
        if ($search !== '') {
            $sql .= ' AND (e.nombre LIKE ? OR e.documento LIKE ? OR e.cargo LIKE ? OR e.area LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        return Database::query($sql . ' ORDER BY e.activo DESC, e.nombre', $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM empleados WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function findByUser(int $userId, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM empleados WHERE user_id = ? AND tenant_id = ?', [$userId, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO empleados (tenant_id, user_id, nombre, documento, email, cargo, area, tipo_vinculacion, fecha_ingreso, telefono)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], !empty($data['user_id']) ? (int) $data['user_id'] : null,
                trim($data['nombre']), trim($data['documento']), text_lower(trim($data['email'] ?? '')),
                trim($data['cargo']), trim($data['area']), $data['tipo_vinculacion'],
                $data['fecha_ingreso'] ?: null, trim($data['telefono'] ?? ''),
            ]
        );
        return Database::lastId();
    }

    public static function linkUser(int $employeeId, int $tenantId, int $userId): void
    {
        Database::execute('UPDATE empleados SET user_id = ? WHERE id = ? AND tenant_id = ?', [$userId, $employeeId, $tenantId]);
    }

    public static function setActive(int $id, int $tenantId, bool $active): void
    {
        Database::execute('UPDATE empleados SET activo = ? WHERE id = ? AND tenant_id = ?', [$active ? 1 : 0, $id, $tenantId]);
    }

    public static function countActive(int $tenantId): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS total FROM empleados WHERE tenant_id = ? AND activo = 1', [$tenantId])['total'] ?? 0);
    }
}
