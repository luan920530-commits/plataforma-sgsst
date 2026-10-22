<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PlanSaaS
{
    public static function all(bool $onlyActive = false): array
    {
        return Database::query(
            'SELECT p.*, (SELECT COUNT(*) FROM tenants t WHERE t.plan_id = p.id) AS total_tenants
             FROM planes_saas p ' . ($onlyActive ? 'WHERE p.activo = 1 ' : '') . 'ORDER BY p.precio_mensual'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM planes_saas WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO planes_saas (nombre, descripcion, precio_mensual, max_usuarios, max_empleados) VALUES (?, ?, ?, ?, ?)',
            [trim($data['nombre']), trim($data['descripcion']), (float) $data['precio_mensual'], (int) $data['max_usuarios'], (int) $data['max_empleados']]
        );
        return Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE planes_saas SET descripcion = ?, precio_mensual = ?, max_usuarios = ?, max_empleados = ?, activo = ? WHERE id = ?',
            [trim($data['descripcion']), (float) $data['precio_mensual'], (int) $data['max_usuarios'], (int) $data['max_empleados'], !empty($data['activo']) ? 1 : 0, $id]
        );
    }
}
