<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Documento
{
    public static function forTenant(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT d.*, u.nombre AS aprobado_por_nombre FROM documentos_sgsst d
                LEFT JOIN users u ON u.id = d.aprobado_por WHERE d.tenant_id = ?';
        $params = [$tenantId];
        if (!empty($filters['fase'])) {
            $sql .= ' AND d.fase_phva = ?';
            $params[] = $filters['fase'];
        }
        if (!empty($filters['estado'])) {
            $sql .= ' AND d.estado = ?';
            $params[] = $filters['estado'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (d.nombre_documento LIKE ? OR d.codigo_estandar LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term);
        }
        $sql .= ' ORDER BY d.fase_phva, d.codigo_estandar, d.id';
        return Database::query($sql, $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first(
            'SELECT d.*, u.nombre AS aprobado_por_nombre FROM documentos_sgsst d
             LEFT JOIN users u ON u.id = d.aprobado_por WHERE d.id = ? AND d.tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function stats(int $tenantId): array
    {
        return Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) AS aprobados,
                    SUM(CASE WHEN estado = 'Borrador' THEN 1 ELSE 0 END) AS borradores,
                    SUM(CASE WHEN fase_phva = 'Planear' THEN 1 ELSE 0 END) AS planear,
                    SUM(CASE WHEN fase_phva = 'Hacer' THEN 1 ELSE 0 END) AS hacer,
                    SUM(CASE WHEN fase_phva = 'Verificar' THEN 1 ELSE 0 END) AS verificar,
                    SUM(CASE WHEN fase_phva = 'Actuar' THEN 1 ELSE 0 END) AS actuar
             FROM documentos_sgsst WHERE tenant_id = ?",
            [$tenantId]
        ) ?? [];
    }

    public static function approvedPolicies(int $tenantId): array
    {
        return Database::query(
            "SELECT * FROM documentos_sgsst WHERE tenant_id = ? AND estado = 'Aprobado' AND tipo = 'Politica' ORDER BY nombre_documento",
            [$tenantId]
        );
    }

    public static function approve(int $id, int $tenantId, int $userId): void
    {
        Database::execute(
            "UPDATE documentos_sgsst SET estado = 'Aprobado', aprobado_por = ?, aprobado_at = CURRENT_TIMESTAMP,
                                            updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?",
            [$userId, $id, $tenantId]
        );
    }

    public static function saveDraft(int $id, int $tenantId, string $content): void
    {
        Database::execute(
            "UPDATE documentos_sgsst SET contenido_html = ?, estado = 'Borrador', version = version + 1,
                                            aprobado_por = NULL, aprobado_at = NULL, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?",
            [$content, $id, $tenantId]
        );
    }

    public static function setObsolete(int $id, int $tenantId): void
    {
        Database::execute("UPDATE documentos_sgsst SET estado = 'Obsoleto', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }
}
