<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class BibliotecaRespuesta
{
    public static function forTenant(int $tenantId, ?string $templateId = null): array
    {
        $sql = 'SELECT r.*, u.nombre AS creado_por_nombre FROM biblioteca_respuestas r
                LEFT JOIN users u ON u.id = r.created_by WHERE r.tenant_id = ?';
        $params = [$tenantId];
        if ($templateId !== null) {
            $sql .= ' AND r.plantilla_id = ?';
            $params[] = $templateId;
        }
        $sql .= ' ORDER BY r.updated_at DESC, r.id DESC';
        return Database::query($sql, $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM biblioteca_respuestas WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function counts(int $tenantId): array
    {
        $rows = Database::query('SELECT plantilla_id, COUNT(*) AS total FROM biblioteca_respuestas WHERE tenant_id = ? GROUP BY plantilla_id', [$tenantId]);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['plantilla_id']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO biblioteca_respuestas (tenant_id, plantilla_id, titulo, datos_json, estado, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], $data['plantilla_id'], $data['titulo'], $data['datos_json'], $data['estado'], $data['created_by']]
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE biblioteca_respuestas SET titulo = ?, datos_json = ?, estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [$data['titulo'], $data['datos_json'], $data['estado'], $id, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM biblioteca_respuestas WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
