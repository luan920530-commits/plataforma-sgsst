<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PlanEmergencia
{
    private const SELECT = 'SELECT p.*, d.nombre_documento, d.contenido_html, d.estado, d.version,
        d.aprobado_por, d.aprobado_at, u.nombre AS aprobado_por_nombre
        FROM planes_emergencia p LEFT JOIN documentos_sgsst d ON d.id = p.documento_id
        LEFT JOIN users u ON u.id = d.aprobado_por';

    public static function forTenant(int $tenantId): array
    {
        return Database::query(self::SELECT . ' WHERE p.tenant_id = ? ORDER BY p.updated_at DESC, p.id DESC', [$tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first(self::SELECT . ' WHERE p.id = ? AND p.tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        $temporaryCode = 'TMP-' . bin2hex(random_bytes(12));
        Database::execute(
            'INSERT INTO planes_emergencia (tenant_id, codigo, titulo, centro_trabajo, direccion, alcance,
                amenazas_json, contactos_json, brigada_json, recursos_json, rutas_evacuacion, puntos_encuentro,
                procedimientos, plan_rescate, ayuda_mutua, responsable_plan, telefono_alarma, proximo_simulacro, fecha_revision)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            self::values($data, $temporaryCode)
        );
        $id = Database::lastId();
        $code = 'PE-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        Database::execute('UPDATE planes_emergencia SET codigo = ? WHERE id = ? AND tenant_id = ?', [$code, $id, (int) $data['tenant_id']]);
        return $id;
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE planes_emergencia SET titulo = ?, centro_trabajo = ?, direccion = ?, alcance = ?, amenazas_json = ?,
                contactos_json = ?, brigada_json = ?, recursos_json = ?, rutas_evacuacion = ?, puntos_encuentro = ?,
                procedimientos = ?, plan_rescate = ?, ayuda_mutua = ?, responsable_plan = ?, telefono_alarma = ?,
                proximo_simulacro = ?, fecha_revision = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [
                trim($data['titulo']), trim($data['centro_trabajo']), trim($data['direccion'] ?? ''), trim($data['alcance']),
                $data['amenazas_json'], $data['contactos_json'], $data['brigada_json'], $data['recursos_json'],
                trim($data['rutas_evacuacion'] ?? ''), trim($data['puntos_encuentro'] ?? ''), trim($data['procedimientos']),
                trim($data['plan_rescate'] ?? ''), trim($data['ayuda_mutua'] ?? ''), trim($data['responsable_plan']),
                trim($data['telefono_alarma'] ?? ''), $data['proximo_simulacro'] ?: null, $data['fecha_revision'] ?: null,
                $id, $tenantId,
            ]
        );
    }

    public static function linkDocument(int $id, int $tenantId, int $documentId): void
    {
        Database::execute('UPDATE planes_emergencia SET documento_id = ? WHERE id = ? AND tenant_id = ?', [$documentId, $id, $tenantId]);
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM planes_emergencia WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    private static function values(array $data, string $code): array
    {
        return [
            (int) $data['tenant_id'], $code, trim($data['titulo']), trim($data['centro_trabajo']),
            trim($data['direccion'] ?? ''), trim($data['alcance']), $data['amenazas_json'], $data['contactos_json'],
            $data['brigada_json'], $data['recursos_json'], trim($data['rutas_evacuacion'] ?? ''),
            trim($data['puntos_encuentro'] ?? ''), trim($data['procedimientos']), trim($data['plan_rescate'] ?? ''),
            trim($data['ayuda_mutua'] ?? ''), trim($data['responsable_plan']), trim($data['telefono_alarma'] ?? ''),
            $data['proximo_simulacro'] ?: null, $data['fecha_revision'] ?: null,
        ];
    }
}
