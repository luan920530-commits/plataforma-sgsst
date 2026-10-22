<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class AccesoReporte
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query(
            'SELECT a.*, u.nombre AS creado_por_nombre, t.estado AS tenant_estado
             FROM accesos_reporte a LEFT JOIN users u ON u.id = a.created_by
             JOIN tenants t ON t.id = a.tenant_id
             WHERE a.tenant_id = ? ORDER BY a.activo DESC, a.created_at DESC',
            [$tenantId]
        );
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM accesos_reporte WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function findByToken(string $token): ?array
    {
        if (strlen($token) < 40 || strlen($token) > 120) {
            return null;
        }
        return Database::first(
            'SELECT a.*, t.razonsocial, t.estado AS tenant_estado, u.nombre AS creado_por_nombre
             FROM accesos_reporte a
             JOIN tenants t ON t.id = a.tenant_id
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.token_hash = ?',
            [hash('sha256', $token)]
        );
    }

    public static function isUsable(?array $access): bool
    {
        if ($access === null || !(bool) $access['activo'] || $access['tenant_estado'] !== 'Activa' || empty($access['created_by'])) {
            return false;
        }
        if ((int) $access['usos'] >= (int) $access['max_usos'] && (int) $access['max_usos'] > 0) {
            return false;
        }
        $expires = strtotime((string) $access['expira_at']);
        return $expires !== false && $expires >= time();
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO accesos_reporte (tenant_id, nombre, token_hash, token_hint, expira_at, max_usos, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], trim($data['nombre']), $data['token_hash'], $data['token_hint'],
                $data['expira_at'], (int) $data['max_usos'], (int) $data['created_by'],
            ]
        );
        return Database::lastId();
    }

    public static function consume(int $id): bool
    {
        return Database::execute(
            'UPDATE accesos_reporte SET usos = usos + 1, last_used_at = CURRENT_TIMESTAMP
             WHERE id = ? AND activo = 1 AND expira_at >= ? AND (max_usos = 0 OR usos < max_usos)',
            [$id, date('Y-m-d H:i:s')]
        ) === 1;
    }

    public static function revoke(int $id, int $tenantId): void
    {
        Database::execute(
            'UPDATE accesos_reporte SET activo = 0, revoked_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ? AND activo = 1',
            [$id, $tenantId]
        );
    }

    public static function recentUseCount(int $id, int $seconds = 3600): int
    {
        $since = date('Y-m-d H:i:s', time() - max(60, $seconds));
        return (int) (Database::first(
            "SELECT COUNT(*) AS total FROM autoreportes WHERE acceso_reporte_id = ? AND origen = 'Enlace externo' AND created_at >= ?",
            [$id, $since]
        )['total'] ?? 0);
    }
}
