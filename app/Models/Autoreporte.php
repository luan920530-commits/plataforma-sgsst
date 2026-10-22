<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Autoreporte
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query(
            'SELECT r.*, u.nombre AS reportante, u.email FROM autoreportes r
             JOIN users u ON u.id = r.user_id WHERE r.tenant_id = ?
             ORDER BY CASE r.estado WHEN \'Recibido\' THEN 1 WHEN \'En revision\' THEN 2 ELSE 3 END, r.created_at DESC',
            [$tenantId]
        );
    }

    public static function forUser(int $tenantId, int $userId): array
    {
        return Database::query('SELECT * FROM autoreportes WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC', [$tenantId, $userId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM autoreportes WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO autoreportes (tenant_id, user_id, tipo, descripcion, ubicacion, fecha_evento) VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], (int) $data['user_id'], $data['tipo'], trim($data['descripcion']), trim($data['ubicacion'] ?? ''), $data['fecha_evento']]
        );
        return Database::lastId();
    }

    public static function updateManagement(int $id, int $tenantId, string $status, string $response): void
    {
        Database::execute('UPDATE autoreportes SET estado = ?, respuesta = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, trim($response), $id, $tenantId]);
    }

    public static function openCount(int $tenantId): int
    {
        return (int) (Database::first("SELECT COUNT(*) AS total FROM autoreportes WHERE tenant_id = ? AND estado <> 'Gestionado'", [$tenantId])['total'] ?? 0);
    }
}
