<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Indicador
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT * FROM indicadores WHERE tenant_id = ? ORDER BY fecha_corte DESC, id DESC', [$tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM indicadores WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function latest(int $tenantId, int $limit = 6): array
    {
        return Database::query('SELECT * FROM indicadores WHERE tenant_id = ? ORDER BY fecha_corte DESC, id DESC LIMIT ' . max(1, $limit), [$tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO indicadores (tenant_id, tipo, nombre, periodo, numerador, denominador, factor, resultado, unidad, meta, fecha_corte)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], $data['tipo'], trim($data['nombre']), trim($data['periodo']),
                (float) $data['numerador'], (float) $data['denominador'], (float) $data['factor'],
                (float) $data['resultado'], $data['unidad'], $data['meta'] === null || $data['meta'] === '' ? null : (float) $data['meta'],
                $data['fecha_corte'],
            ]
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): bool
    {
        return Database::execute(
            'UPDATE indicadores SET tipo = ?, nombre = ?, periodo = ?, numerador = ?, denominador = ?, factor = ?,
                                    resultado = ?, unidad = ?, meta = ?, fecha_corte = ?
             WHERE id = ? AND tenant_id = ?',
            [
                $data['tipo'], trim($data['nombre']), trim($data['periodo']), (float) $data['numerador'],
                (float) $data['denominador'], (float) $data['factor'], (float) $data['resultado'], $data['unidad'],
                $data['meta'] === null || $data['meta'] === '' ? null : (float) $data['meta'], $data['fecha_corte'], $id, $tenantId,
            ]
        ) === 1;
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute('DELETE FROM indicadores WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;
    }
}
