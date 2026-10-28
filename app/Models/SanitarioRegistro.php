<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class SanitarioRegistro
{
    public const ESTADOS = ['Pendiente', 'Programado', 'Ejecutado', 'Verificado', 'No aplica'];

    public static function forTenant(int $tenantId, string $type = '', string $search = ''): array
    {
        $sql = 'SELECT sr.*, r.codigo AS requerimiento_codigo, a.numero_acta
                FROM sanitario_registros sr
                LEFT JOIN ivc_requerimientos r ON r.id = sr.requerimiento_id AND r.tenant_id = sr.tenant_id
                LEFT JOIN ivc_actas a ON a.id = r.acta_id AND a.tenant_id = sr.tenant_id
                WHERE sr.tenant_id = ?';
        $params = [$tenantId];
        if ($type !== '') {
            $sql .= ' AND sr.tipo = ?';
            $params[] = $type;
        }
        if ($search !== '') {
            $sql .= ' AND (sr.titulo LIKE ? OR sr.responsable LIKE ? OR sr.observaciones LIKE ? OR sr.datos_json LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term);
        }
        $sql .= ' ORDER BY sr.fecha_registro DESC, sr.id DESC';
        return array_map(self::decode(...), Database::query($sql, $params));
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $row = Database::first(
            'SELECT sr.*, r.codigo AS requerimiento_codigo, r.acta_id, a.numero_acta
             FROM sanitario_registros sr
             LEFT JOIN ivc_requerimientos r ON r.id = sr.requerimiento_id AND r.tenant_id = sr.tenant_id
             LEFT JOIN ivc_actas a ON a.id = r.acta_id AND a.tenant_id = sr.tenant_id
             WHERE sr.id = ? AND sr.tenant_id = ?',
            [$id, $tenantId]
        );
        return $row === null ? null : self::decode($row);
    }

    public static function countsByType(int $tenantId): array
    {
        $counts = [];
        foreach (Database::query('SELECT tipo, COUNT(*) AS total FROM sanitario_registros WHERE tenant_id = ? GROUP BY tipo', [$tenantId]) as $row) {
            $counts[$row['tipo']] = (int) $row['total'];
        }
        return $counts;
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO sanitario_registros (tenant_id, requerimiento_id, tipo, titulo, fecha_registro, responsable,
                                              estado, datos_json, observaciones, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
            'UPDATE sanitario_registros SET requerimiento_id = ?, tipo = ?, titulo = ?, fecha_registro = ?, responsable = ?,
                                              estado = ?, datos_json = ?, observaciones = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            $parameters
        ) === 1;
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute('DELETE FROM sanitario_registros WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;
    }

    private static function parameters(array $data, bool $withCreator = true): array
    {
        $parameters = [
            !empty($data['requerimiento_id']) ? (int) $data['requerimiento_id'] : null,
            $data['tipo'], trim($data['titulo']), $data['fecha_registro'], trim($data['responsable']),
            $data['estado'], json_encode($data['datos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            trim($data['observaciones']),
        ];
        if ($withCreator) {
            array_unshift($parameters, (int) $data['tenant_id']);
            $parameters[] = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        }
        return $parameters;
    }

    private static function decode(array $row): array
    {
        $decoded = json_decode((string) ($row['datos_json'] ?? '{}'), true);
        $row['datos'] = is_array($decoded) ? $decoded : [];
        return $row;
    }
}
