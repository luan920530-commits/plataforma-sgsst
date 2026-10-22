<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PermisoTrabajo
{
    private const FIELDS = 'tipo, tarea, ubicacion, descripcion, fecha_inicio, fecha_fin, solicitante_id, solicitante_nombre, solicitante_documento, ejecutantes, responsable_trabajo, peligros, controles, epp, equipos_herramientas, autorreporte_condiciones, altura_trabajo, coordinador_alturas, responsable_emergencias, procedimiento_rescate, vigia_espacio, mediciones_atmosfericas, formacion_personal, observaciones';

    public static function forTenant(int $tenantId, array $filters = []): array
    {
        $sql = 'SELECT p.*, u.nombre AS aprobado_por_usuario FROM permisos_trabajo p
                LEFT JOIN users u ON u.id = p.aprobado_por WHERE p.tenant_id = ?';
        $params = [$tenantId];
        if (!empty($filters['estado'])) {
            $sql .= ' AND p.estado = ?';
            $params[] = $filters['estado'];
        }
        if (!empty($filters['tipo'])) {
            $sql .= ' AND p.tipo = ?';
            $params[] = $filters['tipo'];
        }
        return Database::query($sql . ' ORDER BY p.fecha_inicio DESC, p.id DESC', $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first(
            'SELECT p.*, u.nombre AS aprobado_por_usuario, c.nombre AS cerrado_por_nombre
             FROM permisos_trabajo p LEFT JOIN users u ON u.id = p.aprobado_por
             LEFT JOIN users c ON c.id = p.cerrado_por WHERE p.id = ? AND p.tenant_id = ?',
            [$id, $tenantId]
        );
    }

    public static function create(array $data): int
    {
        $temporaryCode = 'TMP-' . bin2hex(random_bytes(12));
        $sql = 'INSERT INTO permisos_trabajo (tenant_id, codigo, version, revisado_de, ' . self::FIELDS . ')
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        Database::execute($sql, self::params($data, $temporaryCode));
        $id = Database::lastId();
        $code = 'PT-' . date('Y') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
        Database::execute('UPDATE permisos_trabajo SET codigo = ? WHERE id = ? AND tenant_id = ?', [$code, $id, (int) $data['tenant_id']]);
        return $id;
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        $assignments = implode(', ', array_map(static fn(string $field): string => $field . ' = ?', explode(', ', self::FIELDS)));
        $params = self::values($data);
        $params[] = $id;
        $params[] = $tenantId;
        Database::execute('UPDATE permisos_trabajo SET ' . $assignments . ", estado = 'Borrador', aprobado_por = NULL,
            aprobador_nombre = '', aprobador_documento = '', aprobado_at = NULL, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND tenant_id = ?", $params);
    }

    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        Database::execute('UPDATE permisos_trabajo SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }

    public static function approve(int $id, int $tenantId, int $userId, string $name, string $document): void
    {
        Database::execute(
            "UPDATE permisos_trabajo SET estado = 'Aprobado', aprobado_por = ?, aprobador_nombre = ?,
                    aprobador_documento = ?, aprobado_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?",
            [$userId, trim($name), trim($document), $id, $tenantId]
        );
    }

    public static function close(int $id, int $tenantId, int $userId, string $observations): void
    {
        Database::execute(
            "UPDATE permisos_trabajo SET estado = 'Cerrado', cerrado_por = ?, cerrado_at = CURRENT_TIMESTAMP,
                    observaciones_cierre = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?",
            [$userId, trim($observations), $id, $tenantId]
        );
    }

    public static function cancel(int $id, int $tenantId, int $userId, string $reason): void
    {
        Database::execute(
            "UPDATE permisos_trabajo SET estado = 'Cancelado', cerrado_por = ?, cerrado_at = CURRENT_TIMESTAMP,
                    observaciones_cierre = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?",
            [$userId, trim($reason), $id, $tenantId]
        );
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM permisos_trabajo WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function revisions(int $id, int $tenantId): array
    {
        $rows = Database::query(
            'SELECT id, codigo, version, estado, revisado_de, created_at FROM permisos_trabajo WHERE tenant_id = ? ORDER BY id',
            [$tenantId]
        );
        $byId = [];
        $children = [];
        foreach ($rows as $row) {
            $rowId = (int) $row['id'];
            $byId[$rowId] = $row;
            if (!empty($row['revisado_de'])) {
                $children[(int) $row['revisado_de']][] = $rowId;
            }
        }
        if (!isset($byId[$id])) {
            return [];
        }
        $root = $id;
        $visited = [$id => true];
        while (!empty($byId[$root]['revisado_de'])) {
            $parent = (int) $byId[$root]['revisado_de'];
            if (!isset($byId[$parent]) || isset($visited[$parent])) {
                break;
            }
            $root = $parent;
            $visited[$parent] = true;
        }
        $history = [];
        $walk = static function (int $current) use (&$walk, &$history, $byId, $children): void {
            if (isset($history[$current])) {
                return;
            }
            $history[$current] = $byId[$current];
            foreach ($children[$current] ?? [] as $child) {
                $walk($child);
            }
        };
        $walk($root);
        $history = array_values($history);
        usort($history, static fn(array $left, array $right): int => ((int) $right['version'] <=> (int) $left['version']) ?: ((int) $right['id'] <=> (int) $left['id']));
        return $history;
    }

    public static function stats(int $tenantId): array
    {
        return Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado IN ('Borrador','Pendiente') THEN 1 ELSE 0 END) AS por_gestionar,
                    SUM(CASE WHEN estado IN ('Aprobado','Activo') AND datetime(replace(fecha_fin, 'T', ' ')) >= CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS vigentes,
                    SUM(CASE WHEN estado IN ('Aprobado','Activo') AND datetime(replace(fecha_fin, 'T', ' ')) < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) AS vencidos,
                    SUM(CASE WHEN estado = 'Cerrado' THEN 1 ELSE 0 END) AS cerrados
             FROM permisos_trabajo WHERE tenant_id = ?",
            [$tenantId]
        ) ?? [];
    }

    private static function params(array $data, string $code): array
    {
        return array_merge([
            (int) $data['tenant_id'], $code, (int) ($data['version'] ?? 1),
            !empty($data['revisado_de']) ? (int) $data['revisado_de'] : null,
        ], self::values($data));
    }

    private static function values(array $data): array
    {
        return [
            $data['tipo'], trim($data['tarea']), trim($data['ubicacion']), trim($data['descripcion'] ?? ''),
            $data['fecha_inicio'], $data['fecha_fin'],
            !empty($data['solicitante_id']) ? (int) $data['solicitante_id'] : null,
            trim($data['solicitante_nombre']), trim($data['solicitante_documento'] ?? ''), trim($data['ejecutantes']),
            trim($data['responsable_trabajo']), trim($data['peligros']), trim($data['controles']), trim($data['epp']),
            trim($data['equipos_herramientas'] ?? ''), trim($data['autorreporte_condiciones'] ?? ''),
            $data['altura_trabajo'] !== '' && $data['altura_trabajo'] !== null ? (float) $data['altura_trabajo'] : null,
            trim($data['coordinador_alturas'] ?? ''), trim($data['responsable_emergencias'] ?? ''),
            trim($data['procedimiento_rescate'] ?? ''), trim($data['vigia_espacio'] ?? ''),
            trim($data['mediciones_atmosfericas'] ?? ''), trim($data['formacion_personal'] ?? ''),
            trim($data['observaciones'] ?? ''),
        ];
    }
}
