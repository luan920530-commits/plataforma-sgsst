<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use Throwable;

final class Audit
{
    public static function record(
        string $action,
        string $entity,
        ?int $entityId = null,
        array $details = [],
        ?int $tenantId = null,
        ?int $userId = null
    ): void {
        try {
            $tenantId ??= Auth::tenantId();
            $userId ??= Auth::id();
            Database::execute(
                'INSERT INTO auditoria_saas (tenant_id, user_id, accion, entidad, entidad_id, detalles_json, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $tenantId,
                    $userId,
                    $action,
                    $entity,
                    $entityId,
                    json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45),
                ]
            );
        } catch (Throwable $exception) {
            error_log('No fue posible registrar auditoría: ' . $exception->getMessage());
        }
    }
}
