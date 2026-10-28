<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class IvcActa
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query(
            "SELECT a.*,
                    (SELECT COUNT(*) FROM ivc_requerimientos r WHERE r.acta_id = a.id AND r.tenant_id = a.tenant_id) AS total_requerimientos,
                    (SELECT COUNT(*) FROM ivc_requerimientos r WHERE r.acta_id = a.id AND r.tenant_id = a.tenant_id AND r.estado = 'Cerrado') AS cerrados,
                    (SELECT COUNT(*) FROM ivc_requerimientos r WHERE r.acta_id = a.id AND r.tenant_id = a.tenant_id AND r.estado NOT IN ('Cerrado','No aplica')) AS pendientes
             FROM ivc_actas a WHERE a.tenant_id = ? ORDER BY a.fecha_visita DESC, a.id DESC",
            [$tenantId]
        );
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM ivc_actas WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function stats(int $tenantId): array
    {
        return Database::first(
            "SELECT (SELECT COUNT(*) FROM ivc_actas WHERE tenant_id = ?) AS actas,
                    (SELECT COUNT(*) FROM ivc_requerimientos WHERE tenant_id = ?) AS requerimientos,
                    (SELECT COUNT(*) FROM ivc_requerimientos WHERE tenant_id = ? AND estado = 'Cerrado') AS cerrados,
                    (SELECT COUNT(*) FROM ivc_requerimientos WHERE tenant_id = ? AND estado = 'Por verificar') AS por_verificar",
            [$tenantId, $tenantId, $tenantId, $tenantId]
        ) ?? ['actas' => 0, 'requerimientos' => 0, 'cerrados' => 0, 'por_verificar' => 0];
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO ivc_actas (tenant_id, numero_acta, autoridad, fecha_visita, fecha_notificacion, fecha_limite,
                                    concepto, porcentaje, direccion_visita, fuente_nombre, fuente_sha256, observaciones)
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
            'UPDATE ivc_actas SET numero_acta = ?, autoridad = ?, fecha_visita = ?, fecha_notificacion = ?, fecha_limite = ?,
                                    concepto = ?, porcentaje = ?, direccion_visita = ?, fuente_nombre = ?, fuente_sha256 = ?,
                                    observaciones = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            $parameters
        ) === 1;
    }

    public static function dependencies(int $id, int $tenantId): array
    {
        return Database::first(
            "SELECT (SELECT COUNT(*) FROM soportes_cumplimiento s
                      WHERE s.tenant_id = ? AND s.entidad_tipo = 'ivc_requerimiento'
                        AND s.entidad_id IN (SELECT CAST(r.id AS TEXT) FROM ivc_requerimientos r WHERE r.acta_id = ? AND r.tenant_id = ?)) AS evidencias,
                    (SELECT COUNT(*) FROM sanitario_registros sr
                      WHERE sr.tenant_id = ? AND sr.requerimiento_id IN
                        (SELECT r.id FROM ivc_requerimientos r WHERE r.acta_id = ? AND r.tenant_id = ?)) AS registros",
            [$tenantId, $id, $tenantId, $tenantId, $id, $tenantId]
        ) ?? ['evidencias' => 0, 'registros' => 0];
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute('DELETE FROM ivc_actas WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;
    }

    private static function parameters(array $data, bool $withTenant = true): array
    {
        $parameters = [
            trim($data['numero_acta']), trim($data['autoridad']), $data['fecha_visita'],
            $data['fecha_notificacion'] ?: null, $data['fecha_limite'] ?: null, trim($data['concepto']),
            $data['porcentaje'] === '' || $data['porcentaje'] === null ? null : (float) $data['porcentaje'],
            trim($data['direccion_visita']), trim($data['fuente_nombre']), text_lower(trim($data['fuente_sha256'])),
            trim($data['observaciones']),
        ];
        if ($withTenant) {
            array_unshift($parameters, (int) $data['tenant_id']);
        }
        return $parameters;
    }
}
