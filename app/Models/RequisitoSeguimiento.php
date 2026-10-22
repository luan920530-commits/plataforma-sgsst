<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RequisitoSeguimiento
{
    public static function forTenant(int $tenantId): array
    {
        $rows = Database::query('SELECT * FROM requisito_seguimiento WHERE tenant_id = ? ORDER BY requisito_clave', [$tenantId]);
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['requisito_clave']] = $row;
        }
        return $indexed;
    }

    public static function save(int $tenantId, string $key, array $data, ?int $userId): void
    {
        $verified = $data['estado'] === 'Verificado';
        Database::execute(
            'INSERT INTO requisito_seguimiento (tenant_id, requisito_clave, estado, responsable, fecha_limite, observaciones, verificado_por, verificado_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(tenant_id, requisito_clave) DO UPDATE SET estado = excluded.estado,
                responsable = excluded.responsable, fecha_limite = excluded.fecha_limite,
                observaciones = excluded.observaciones, verificado_por = excluded.verificado_por,
                verificado_at = excluded.verificado_at, updated_at = CURRENT_TIMESTAMP',
            [$tenantId, $key, $data['estado'], trim($data['responsable'] ?? ''), $data['fecha_limite'] ?: null, trim($data['observaciones'] ?? ''), $verified ? $userId : null, $verified ? date('Y-m-d H:i:s') : null]
        );
    }
}
