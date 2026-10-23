<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SoporteCumplimiento
{
    public static function forRequirement(int $tenantId, string $key): array
    {
        return Database::query(
            'SELECT s.*, u.nombre AS subido_por_nombre FROM soportes_cumplimiento s
             LEFT JOIN users u ON u.id = s.subido_por WHERE s.tenant_id = ? AND s.requisito_clave = ?
             ORDER BY s.created_at DESC, s.id DESC',
            [$tenantId, $key]
        );
    }

    public static function counts(int $tenantId): array
    {
        $rows = Database::query('SELECT requisito_clave, COUNT(*) AS total FROM soportes_cumplimiento WHERE tenant_id = ? GROUP BY requisito_clave', [$tenantId]);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['requisito_clave']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM soportes_cumplimiento WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO soportes_cumplimiento (tenant_id, requisito_clave, modulo, categoria, titulo, fecha_evidencia,
                entidad_tipo, entidad_id, entidad_referencia, nombre_original, nombre_almacenado,
                ruta_relativa, mime, extension, tamano, hash_sha256, descripcion, subido_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], $data['requisito_clave'], $data['modulo'] ?? 'sgsst',
                $data['categoria'] ?? 'Soporte de requisito',
                trim($data['titulo'] ?? (trim($data['descripcion'] ?? '') !== '' ? $data['descripcion'] : $data['nombre_original'])),
                $data['fecha_evidencia'] ?? date('Y-m-d'), 'requisito', $data['requisito_clave'],
                'Requisito: ' . $data['requisito_clave'], $data['nombre_original'], $data['nombre_almacenado'],
                $data['ruta_relativa'], $data['mime'], $data['extension'], (int) $data['tamano'], $data['hash_sha256'],
                trim($data['descripcion'] ?? ''), (int) $data['subido_por'],
            ]
        );
        return Database::lastId();
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute(
            "DELETE FROM soportes_cumplimiento WHERE id = ? AND tenant_id = ? AND estado_revision = 'Pendiente'",
            [$id, $tenantId]
        ) === 1;
    }
}
