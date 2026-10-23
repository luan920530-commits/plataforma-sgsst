<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;
use RuntimeException;

final class PlanoEmergencia
{
    public static function forPlan(int $planId, int $tenantId): array
    {
        return Database::query(self::select() . ' WHERE p.plan_id = ? AND p.tenant_id = ? ORDER BY p.updated_at DESC, p.id DESC', [$planId, $tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId, ?int $planId = null): ?array
    {
        $sql = self::select() . ' WHERE p.id = ? AND p.tenant_id = ?';
        $params = [$id, $tenantId];
        if ($planId !== null) {
            $sql .= ' AND p.plan_id = ?';
            $params[] = $planId;
        }
        return Database::first($sql, $params);
    }

    public static function create(array $data): int
    {
        return Database::transaction(static function () use ($data): int {
        Database::execute(
            'INSERT INTO planos_emergencia (tenant_id, plan_id, nombre, escala, descripcion, datos_json, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], (int) $data['plan_id'], trim($data['nombre']), trim($data['escala']),
                trim($data['descripcion'] ?? ''), $data['datos_json'], (int) $data['created_by'], (int) $data['created_by'],
            ]
        );
        $id = Database::lastId();
        self::insertVersion($id, $data + ['version' => 1]);
        return $id;
        });
    }

    public static function update(int $id, int $tenantId, array $data): int
    {
        return Database::transaction(static function () use ($id, $tenantId, $data): int {
        $current = self::findForTenant($id, $tenantId);
        if ($current === null) {
            throw new RuntimeException('El plano que intentas actualizar no existe.');
        }
        $expectedVersion = (int) ($data['expected_version'] ?? 0);
        if ($expectedVersion < 1 || $expectedVersion !== (int) $current['version']) {
            throw new DomainException('Otra sesión guardó una versión mientras editabas. Tu diseño fue conservado; revisa la versión actual antes de volver a guardar.');
        }
        $version = $expectedVersion + 1;
        $updated = Database::execute(
            'UPDATE planos_emergencia SET nombre = ?, escala = ?, descripcion = ?, datos_json = ?, version = ?,
                updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ? AND version = ?',
            [trim($data['nombre']), trim($data['escala']), trim($data['descripcion'] ?? ''), $data['datos_json'], $version, (int) $data['created_by'], $id, $tenantId, $expectedVersion]
        );
        if ($updated !== 1) {
            throw new DomainException('El plano cambió antes de completar el guardado. Tu diseño fue conservado; revisa la versión actual antes de volver a guardar.');
        }
        self::insertVersion($id, $data + ['tenant_id' => $tenantId, 'version' => $version]);
        return $version;
        });
    }

    public static function versions(int $planoId, int $tenantId): array
    {
        return Database::query(
            'SELECT v.*, u.nombre AS created_by_nombre FROM planos_emergencia_versiones v
             LEFT JOIN users u ON u.id = v.created_by WHERE v.plano_id = ? AND v.tenant_id = ?
             ORDER BY v.version DESC',
            [$planoId, $tenantId]
        );
    }

    public static function findVersion(int $versionId, int $planoId, int $tenantId): ?array
    {
        return Database::first(
            'SELECT v.*, p.plan_id, u.nombre AS created_by_nombre FROM planos_emergencia_versiones v
             JOIN planos_emergencia p ON p.id = v.plano_id LEFT JOIN users u ON u.id = v.created_by
             WHERE v.id = ? AND v.plano_id = ? AND v.tenant_id = ?',
            [$versionId, $planoId, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM planos_emergencia WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    private static function insertVersion(int $planoId, array $data): void
    {
        Database::execute(
            'INSERT INTO planos_emergencia_versiones (tenant_id, plano_id, version, nombre, escala, descripcion, datos_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], $planoId, (int) $data['version'], trim($data['nombre']), trim($data['escala']),
                trim($data['descripcion'] ?? ''), $data['datos_json'], (int) $data['created_by'],
            ]
        );
    }

    private static function select(): string
    {
        return 'SELECT p.*, u.nombre AS created_by_nombre, a.nombre AS updated_by_nombre
                FROM planos_emergencia p LEFT JOIN users u ON u.id = p.created_by
                LEFT JOIN users a ON a.id = p.updated_by';
    }
}
