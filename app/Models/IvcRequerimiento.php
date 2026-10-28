<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class IvcRequerimiento
{
    public const ESTADOS = ['Pendiente', 'En curso', 'Por verificar', 'Cerrado', 'No aplica'];
    public const ORIGENES = ['Requerimiento IVC', 'Brecha documental', 'Seguimiento interno'];

    public static function forActa(int $actaId, int $tenantId): array
    {
        return Database::query(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM soportes_cumplimiento s
                      WHERE s.tenant_id = r.tenant_id AND s.entidad_tipo = 'ivc_requerimiento'
                        AND s.entidad_id = CAST(r.id AS TEXT)) AS evidencias,
                    (SELECT COUNT(*) FROM soportes_cumplimiento s
                      WHERE s.tenant_id = r.tenant_id AND s.entidad_tipo = 'ivc_requerimiento'
                        AND s.entidad_id = CAST(r.id AS TEXT) AND s.estado_revision = 'Revisado') AS evidencias_revisadas,
                    (SELECT COUNT(*) FROM sanitario_registros sr WHERE sr.tenant_id = r.tenant_id AND sr.requerimiento_id = r.id) AS registros
             FROM ivc_requerimientos r
             WHERE r.acta_id = ? AND r.tenant_id = ?
             ORDER BY CASE WHEN r.codigo LIKE 'RD-%' THEN 1 ELSE 0 END,
                      CAST(CASE WHEN instr(r.codigo, '.') > 0 THEN substr(r.codigo, 1, instr(r.codigo, '.') - 1) ELSE r.codigo END AS INTEGER),
                      length(r.codigo), r.codigo, r.id",
            [$actaId, $tenantId]
        );
    }

    public static function forTenant(int $tenantId): array
    {
        return Database::query(
            'SELECT r.*, a.numero_acta FROM ivc_requerimientos r
             JOIN ivc_actas a ON a.id = r.acta_id AND a.tenant_id = r.tenant_id
             WHERE r.tenant_id = ? ORDER BY a.fecha_visita DESC, r.codigo, r.id',
            [$tenantId]
        );
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM ivc_requerimientos WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO ivc_requerimientos (tenant_id, acta_id, codigo, origen, tema, hallazgo, accion, responsable,
                                             fecha_compromiso, estado, evidencia_requerida, verificacion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::parameters($data)
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): bool
    {
        $parameters = self::parameters($data, false);
        $parameters[] = $id;
        $parameters[] = $tenantId;
        return Database::execute(
            'UPDATE ivc_requerimientos SET codigo = ?, origen = ?, tema = ?, hallazgo = ?, accion = ?, responsable = ?,
                                             fecha_compromiso = ?, estado = ?, evidencia_requerida = ?, verificacion = ?,
                                             updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            $parameters
        ) === 1;
    }

    public static function reviewedEvidenceCount(int $id, int $tenantId): int
    {
        return (int) (Database::first(
            "SELECT COUNT(*) AS total FROM soportes_cumplimiento
             WHERE tenant_id = ? AND entidad_tipo = 'ivc_requerimiento' AND entidad_id = ? AND estado_revision = 'Revisado'",
            [$tenantId, (string) $id]
        )['total'] ?? 0);
    }

    public static function dependencies(int $id, int $tenantId): array
    {
        return Database::first(
            "SELECT (SELECT COUNT(*) FROM soportes_cumplimiento
                      WHERE tenant_id = ? AND entidad_tipo = 'ivc_requerimiento' AND entidad_id = ?) AS evidencias,
                    (SELECT COUNT(*) FROM sanitario_registros WHERE tenant_id = ? AND requerimiento_id = ?) AS registros",
            [$tenantId, (string) $id, $tenantId, $id]
        ) ?? ['evidencias' => 0, 'registros' => 0];
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute('DELETE FROM ivc_requerimientos WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;
    }

    private static function parameters(array $data, bool $withActa = true): array
    {
        $parameters = [
            trim($data['codigo']), $data['origen'], $data['tema'], trim($data['hallazgo']), trim($data['accion']),
            trim($data['responsable']), $data['fecha_compromiso'] ?: null, $data['estado'],
            trim($data['evidencia_requerida']), trim($data['verificacion']),
        ];
        if ($withActa) {
            array_unshift($parameters, (int) $data['tenant_id'], (int) $data['acta_id']);
        }
        return $parameters;
    }
}
