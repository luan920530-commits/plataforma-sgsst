<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Auditoria
{
    public static function all(?int $tenantId = null, string $action = '', int $limit = 150): array
    {
        $sql = 'SELECT a.*, u.nombre AS usuario, u.email, t.razonsocial FROM auditoria_saas a
                LEFT JOIN users u ON u.id = a.user_id LEFT JOIN tenants t ON t.id = a.tenant_id WHERE 1 = 1';
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND a.tenant_id = ?';
            $params[] = $tenantId;
        }
        if ($action !== '') {
            $sql .= ' AND a.accion = ?';
            $params[] = $action;
        }
        $sql .= ' ORDER BY a.id DESC LIMIT ' . min(500, max(1, $limit));
        return Database::query($sql, $params);
    }

    public static function actions(): array
    {
        return array_column(Database::query('SELECT DISTINCT accion FROM auditoria_saas ORDER BY accion'), 'accion');
    }

    public static function recent(int $limit = 8): array
    {
        return self::all(null, '', $limit);
    }
}
