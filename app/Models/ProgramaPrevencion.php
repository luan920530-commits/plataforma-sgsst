<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ProgramaPrevencion
{
    private const SELECT = 'SELECT p.*, d.nombre_documento, d.contenido_html, d.estado, d.version,
        d.aprobado_por, d.aprobado_at, u.nombre AS aprobado_por_nombre,
        (SELECT COUNT(*) FROM programa_actividades a WHERE a.programa_id = p.id) AS total_actividades,
        (SELECT COUNT(*) FROM programa_actividades a WHERE a.programa_id = p.id AND a.estado = \'Completado\') AS actividades_completadas
        FROM programas_prevencion p LEFT JOIN documentos_sgsst d ON d.id = p.documento_id
        LEFT JOIN users u ON u.id = d.aprobado_por';

    public static function forTenant(int $tenantId, array $filters = []): array
    {
        $sql = self::SELECT . ' WHERE p.tenant_id = ?';
        $params = [$tenantId];
        if (!empty($filters['anio'])) {
            $sql .= ' AND p.anio = ?';
            $params[] = (int) $filters['anio'];
        }
        if (!empty($filters['categoria'])) {
            $sql .= ' AND p.categoria = ?';
            $params[] = $filters['categoria'];
        }
        return Database::query($sql . ' ORDER BY p.anio DESC, p.nombre, p.id', $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first(self::SELECT . ' WHERE p.id = ? AND p.tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        $temporaryCode = 'TMP-' . bin2hex(random_bytes(12));
        Database::execute(
            'INSERT INTO programas_prevencion (tenant_id, codigo, categoria, nombre, anio, diagnostico, objetivo_general,
                objetivos_especificos, poblacion, peligros_relacionados, metas_indicadores, responsable, recursos,
                presupuesto, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::values($data, $temporaryCode)
        );
        $id = Database::lastId();
        $code = 'PYP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        Database::execute('UPDATE programas_prevencion SET codigo = ? WHERE id = ? AND tenant_id = ?', [$code, $id, (int) $data['tenant_id']]);
        return $id;
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE programas_prevencion SET categoria = ?, nombre = ?, anio = ?, diagnostico = ?, objetivo_general = ?,
                objetivos_especificos = ?, poblacion = ?, peligros_relacionados = ?, metas_indicadores = ?, responsable = ?,
                recursos = ?, presupuesto = ?, fecha_inicio = ?, fecha_fin = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            [
                $data['categoria'], trim($data['nombre']), (int) $data['anio'], trim($data['diagnostico']),
                trim($data['objetivo_general']), trim($data['objetivos_especificos'] ?? ''), trim($data['poblacion']),
                trim($data['peligros_relacionados'] ?? ''), trim($data['metas_indicadores']), trim($data['responsable']),
                trim($data['recursos'] ?? ''), (float) $data['presupuesto'], $data['fecha_inicio'], $data['fecha_fin'],
                $id, $tenantId,
            ]
        );
    }

    public static function linkDocument(int $id, int $tenantId, int $documentId): void
    {
        Database::execute('UPDATE programas_prevencion SET documento_id = ? WHERE id = ? AND tenant_id = ?', [$documentId, $id, $tenantId]);
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM programas_prevencion WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    private static function values(array $data, string $code): array
    {
        return [
            (int) $data['tenant_id'], $code, $data['categoria'], trim($data['nombre']), (int) $data['anio'],
            trim($data['diagnostico']), trim($data['objetivo_general']), trim($data['objetivos_especificos'] ?? ''),
            trim($data['poblacion']), trim($data['peligros_relacionados'] ?? ''), trim($data['metas_indicadores']),
            trim($data['responsable']), trim($data['recursos'] ?? ''), (float) $data['presupuesto'],
            $data['fecha_inicio'], $data['fecha_fin'],
        ];
    }
}
