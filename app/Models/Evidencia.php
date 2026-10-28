<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ModuleAccess;

/** Archivo documental privado unificado; reutiliza los soportes existentes sin duplicar archivos. */
final class Evidencia
{
    public static function categories(): array
    {
        return [
            'Reporte o informe', 'Formulario diligenciado', 'Lista de asistencia', 'Acta o minuta',
            'Registro fotográfico', 'Plano de emergencia', 'Certificado o diploma', 'Inspección',
            'Simulacro', 'Documento SG-SST', 'Soporte PESV', 'Soporte de requisito', 'Otro',
        ];
    }

    public static function forTenant(int $tenantId, string $module, array $filters = []): array
    {
        [$scope, $scopeParams] = self::scope($module);
        $sql = self::select() . ' WHERE s.tenant_id = ?' . $scope;
        $params = array_merge([$tenantId], $scopeParams);
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (s.titulo LIKE ? OR s.descripcion LIKE ? OR s.nombre_original LIKE ? OR s.entidad_referencia LIKE ?)';
            $term = '%' . $filters['q'] . '%';
            array_push($params, $term, $term, $term, $term);
        }
        if (in_array($filters['categoria'] ?? '', self::categories(), true)) {
            $sql .= ' AND s.categoria = ?';
            $params[] = $filters['categoria'];
        }
        if (in_array($filters['revision'] ?? '', ['Pendiente', 'Revisado'], true)) {
            $sql .= ' AND s.estado_revision = ?';
            $params[] = $filters['revision'];
        }
        foreach (['desde' => 'fecha_evidencia >=', 'hasta' => 'fecha_evidencia <='] as $filter => $condition) {
            if (($filters[$filter] ?? '') !== '') {
                $sql .= ' AND s.' . $condition . ' ?';
                $params[] = $filters[$filter];
            }
        }
        $sql .= ' ORDER BY COALESCE(s.fecha_evidencia, date(s.created_at)) DESC, s.id DESC';
        return Database::query($sql, $params);
    }

    public static function findForTenant(int $id, int $tenantId, string $module): ?array
    {
        [$scope, $params] = self::scope($module);
        return Database::first(
            self::select() . ' WHERE s.id = ? AND s.tenant_id = ?' . $scope,
            array_merge([$id, $tenantId], $params)
        );
    }

    public static function stats(int $tenantId, string $module): array
    {
        [$scope, $params] = self::scope($module);
        return Database::first(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN s.estado_revision = 'Pendiente' THEN 1 ELSE 0 END), 0) AS pendientes,
                    COALESCE(SUM(CASE WHEN s.estado_revision = 'Revisado' THEN 1 ELSE 0 END), 0) AS revisados,
                    COALESCE(SUM(s.tamano), 0) AS bytes
             FROM soportes_cumplimiento s WHERE s.tenant_id = ?" . $scope,
            array_merge([$tenantId], $params)
        ) ?? ['total' => 0, 'pendientes' => 0, 'revisados' => 0, 'bytes' => 0];
    }

    public static function countForEntity(int $tenantId, string $type, string $entityId): int
    {
        return (int) (Database::first(
            'SELECT COUNT(*) AS total FROM soportes_cumplimiento WHERE tenant_id = ? AND entidad_tipo = ? AND entidad_id = ?',
            [$tenantId, $type, $entityId]
        )['total'] ?? 0);
    }

    public static function reviewedCountForEntity(int $tenantId, string $type, string $entityId): int
    {
        return (int) (Database::first(
            "SELECT COUNT(*) AS total FROM soportes_cumplimiento WHERE tenant_id = ? AND entidad_tipo = ? AND entidad_id = ? AND estado_revision = 'Revisado'",
            [$tenantId, $type, (string) $entityId]
        )['total'] ?? 0);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO soportes_cumplimiento (
                tenant_id, requisito_clave, modulo, categoria, titulo, fecha_evidencia,
                entidad_tipo, entidad_id, entidad_referencia, nombre_original, nombre_almacenado,
                ruta_relativa, mime, extension, tamano, hash_sha256, descripcion,
                estado_revision, observaciones_revision, subido_por
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], (string) ($data['requisito_clave'] ?? ''), $data['modulo'], $data['categoria'],
                trim($data['titulo']), $data['fecha_evidencia'] ?: null, $data['entidad_tipo'], (string) $data['entidad_id'],
                trim($data['entidad_referencia']), $data['nombre_original'], $data['nombre_almacenado'], $data['ruta_relativa'],
                $data['mime'], $data['extension'], (int) $data['tamano'], $data['hash_sha256'], trim($data['descripcion'] ?? ''),
                $data['estado_revision'] ?? 'Pendiente', trim($data['observaciones_revision'] ?? ''), (int) $data['subido_por'],
            ]
        );
        return Database::lastId();
    }

    public static function review(int $id, int $tenantId, string $status, string $notes, int $userId): void
    {
        Database::execute(
            'UPDATE soportes_cumplimiento SET estado_revision = ?, observaciones_revision = ?, revisado_por = ?,
                revisado_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [
                $status, trim($notes), $status === 'Revisado' ? $userId : null,
                $status === 'Revisado' ? date('Y-m-d H:i:s') : null, $id, $tenantId,
            ]
        );
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute(
            "DELETE FROM soportes_cumplimiento WHERE id = ? AND tenant_id = ? AND estado_revision = 'Pendiente'",
            [$id, $tenantId]
        ) === 1;
    }

    private static function select(): string
    {
        return 'SELECT s.*, u.nombre AS subido_por_nombre, r.nombre AS revisado_por_nombre
                FROM soportes_cumplimiento s
                LEFT JOIN users u ON u.id = s.subido_por
                LEFT JOIN users r ON r.id = s.revisado_por';
    }

    /** @return array{0:string,1:array} */
    private static function scope(string $module): array
    {
        if ($module === ModuleAccess::SGST) {
            return ['', []];
        }
        if (in_array($module, [ModuleAccess::PESV, ModuleAccess::EMERGENCIAS], true)) {
            return [' AND s.modulo = ?', [$module]];
        }
        return [' AND 1 = 0', []];
    }
}
