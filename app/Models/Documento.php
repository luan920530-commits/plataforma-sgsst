<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Auth;
use RuntimeException;

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

    public static function createOperational(array $data): int
    {
        Database::execute(
            "INSERT INTO documentos_sgsst (tenant_id, codigo_estandar, nombre_documento, fase_phva, contenido_html, tipo, biblioteca_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [(int) $data['tenant_id'], $data['codigo'], $data['nombre'], $data['fase'] ?? 'Hacer', $data['contenido'], $data['tipo'], $data['biblioteca_id'] ?? null]
        );
        return Database::lastId();
    }

    public static function createOperationalIfMissing(array $data): ?int
    {
        $created = Database::execute(
            'INSERT INTO documentos_sgsst (tenant_id, codigo_estandar, nombre_documento, fase_phva, contenido_html, tipo, biblioteca_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(tenant_id, codigo_estandar) DO NOTHING',
            [(int) $data['tenant_id'], $data['codigo'], $data['nombre'], $data['fase'] ?? 'Hacer', $data['contenido'], $data['tipo'], $data['biblioteca_id'] ?? null]
        );
        return $created === 1 ? Database::lastId() : null;
    }

    public static function findByLibrary(string $libraryId, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM documentos_sgsst WHERE tenant_id = ? AND biblioteca_id = ?', [$tenantId, $libraryId]);
    }

    public static function libraryCounts(int $tenantId): array
    {
        $rows = Database::query('SELECT biblioteca_id, COUNT(*) AS total FROM documentos_sgsst WHERE tenant_id = ? AND biblioteca_id IS NOT NULL GROUP BY biblioteca_id', [$tenantId]);
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['biblioteca_id']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function saveDraft(int $id, int $tenantId, string $content): void
    {
        Database::transaction(static function () use ($id, $tenantId, $content): void {
            $current = self::findForTenant($id, $tenantId);
            if ($current === null) {
                throw new RuntimeException('El documento no existe en esta empresa.');
            }
            Database::execute(
                'INSERT INTO documento_versiones (tenant_id, documento_id, version, contenido_html, estado, aprobado_por, aprobado_at, archivado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$tenantId, $id, (int) $current['version'], $current['contenido_html'], $current['estado'], $current['aprobado_por'], $current['aprobado_at'], Auth::id()]
            );
            Database::execute(
                "UPDATE documentos_sgsst SET contenido_html = ?, estado = 'Borrador', version = version + 1,
                                                aprobado_por = NULL, aprobado_at = NULL, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND tenant_id = ?",
                [$content, $id, $tenantId]
            );
        });
    }

    public static function versions(int $documentId, int $tenantId): array
    {
        return Database::query(
            'SELECT v.*, u.nombre AS archivado_por_nombre, a.nombre AS aprobado_por_nombre
             FROM documento_versiones v
             LEFT JOIN users u ON u.id = v.archivado_por
             LEFT JOIN users a ON a.id = v.aprobado_por
             WHERE v.documento_id = ? AND v.tenant_id = ? ORDER BY v.version DESC',
            [$documentId, $tenantId]
        );
    }

    public static function findVersionForTenant(int $versionId, int $documentId, int $tenantId): ?array
    {
        return Database::first(
            'SELECT v.*, d.nombre_documento, d.codigo_estandar, d.fase_phva, d.tipo,
                    u.nombre AS archivado_por_nombre, a.nombre AS aprobado_por_nombre
             FROM documento_versiones v JOIN documentos_sgsst d ON d.id = v.documento_id
             LEFT JOIN users u ON u.id = v.archivado_por LEFT JOIN users a ON a.id = v.aprobado_por
             WHERE v.id = ? AND v.documento_id = ? AND v.tenant_id = ?',
            [$versionId, $documentId, $tenantId]
        );
    }

    public static function deleteForTenant(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM documentos_sgsst WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function setObsolete(int $id, int $tenantId): void
    {
        Database::execute("UPDATE documentos_sgsst SET estado = 'Obsoleto', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
    }
}
