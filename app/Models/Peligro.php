<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Risk;
use DomainException;

final class Peligro
{
    public static function forTenant(int $tenantId, string $classification = ''): array
    {
        $sql = "SELECT p.*, u.nombre verificado_por_nombre,
            (SELECT COUNT(*) FROM peligro_acciones a WHERE a.peligro_id=p.id AND a.tenant_id=p.tenant_id) acciones,
            (SELECT COUNT(*) FROM peligro_acciones a WHERE a.peligro_id=p.id AND a.tenant_id=p.tenant_id AND a.estado IN ('Pendiente','En ejecucion','Implementado','No eficaz')) acciones_abiertas
            FROM peligros_ipevr p LEFT JOIN users u ON u.id=p.verificado_por WHERE p.tenant_id = ?";
        $params = [$tenantId];
        if ($classification !== '') { $sql .= ' AND p.clasificacion = ?'; $params[] = $classification; }
        $rows = Database::query($sql . " ORDER BY CASE p.nivel_riesgo WHEN 'Critico' THEN 1 WHEN 'Alto' THEN 2 WHEN 'Medio' THEN 3 ELSE 4 END, p.clasificacion, p.peligro", $params);
        return array_map(static function (array $row): array {
            return $row + Risk::interpretation((string) $row['nivel_riesgo']);
        }, $rows);
    }

    public static function create(array $data): int
    {
        Database::execute('INSERT INTO peligros_ipevr (tenant_id,actividad_economica_id,proceso,cargo,ubicacion,trabajadores_expuestos,peligro,clasificacion,efectos,controles_existentes,nivel_deficiencia,nivel_exposicion,nivel_probabilidad,nivel_consecuencia,nivel_riesgo,medidas_control,metodologia,participantes,fecha_evaluacion,proxima_revision,jerarquia_controles) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
            (int) $data['tenant_id'], !empty($data['actividad_economica_id']) ? (int) $data['actividad_economica_id'] : null,
            trim($data['proceso']), trim($data['cargo']), trim((string) ($data['ubicacion'] ?? '')),
            max(0, (int) ($data['trabajadores_expuestos'] ?? 0)), trim($data['peligro']), $data['clasificacion'],
            trim($data['efectos'] ?? ''), trim($data['controles_existentes'] ?? ''), $data['nivel_deficiencia'] ?? null,
            $data['nivel_exposicion'] ?? null, $data['nivel_probabilidad'] ?? null, $data['nivel_consecuencia'] ?? null,
            $data['nivel_riesgo'], trim($data['medidas_control'] ?? ''), trim($data['metodologia'] ?? 'GTC 45'),
            trim($data['participantes'] ?? ''), $data['fecha_evaluacion'] ?? null, $data['proxima_revision'] ?? null,
            trim($data['jerarquia_controles'] ?? ''),
        ]);
        return Database::lastId();
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $row = Database::first('SELECT p.*,u.nombre verificado_por_nombre FROM peligros_ipevr p LEFT JOIN users u ON u.id=p.verificado_por WHERE p.id = ? AND p.tenant_id = ?', [$id, $tenantId]);
        return $row ? $row + Risk::interpretation((string) $row['nivel_riesgo']) : null;
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute('UPDATE peligros_ipevr SET proceso=?,cargo=?,ubicacion=?,trabajadores_expuestos=?,peligro=?,clasificacion=?,efectos=?,controles_existentes=?,nivel_deficiencia=?,nivel_exposicion=?,nivel_probabilidad=?,nivel_consecuencia=?,nivel_riesgo=?,medidas_control=?,metodologia=?,participantes=?,fecha_evaluacion=?,proxima_revision=?,jerarquia_controles=?,verificado_por=NULL,verificado_at=NULL,estado=CASE WHEN estado=\'Controlado\' THEN \'Activo\' ELSE estado END,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [
            trim($data['proceso']), trim($data['cargo']), trim((string) ($data['ubicacion'] ?? '')),
            max(0, (int) ($data['trabajadores_expuestos'] ?? 0)), trim($data['peligro']), $data['clasificacion'],
            trim($data['efectos'] ?? ''), trim($data['controles_existentes'] ?? ''), $data['nivel_deficiencia'] ?? null,
            $data['nivel_exposicion'] ?? null, $data['nivel_probabilidad'] ?? null, $data['nivel_consecuencia'] ?? null,
            $data['nivel_riesgo'], trim($data['medidas_control'] ?? ''), trim($data['metodologia'] ?? 'GTC 45'),
            trim($data['participantes'] ?? ''), $data['fecha_evaluacion'] ?? null, $data['proxima_revision'] ?? null,
            trim($data['jerarquia_controles'] ?? ''), $id, $tenantId,
        ]);
    }

    public static function verify(int $id, int $tenantId, int $userId): void
    {
        $hazard = self::findForTenant($id, $tenantId) ?: throw new DomainException('Peligro no encontrado.');
        if (!Permission::has($userId, $tenantId, Permission::MATRIX_VERIFY)) throw new DomainException('La verificación IPEVR requiere el permiso humano de verificación.');
        foreach (['participantes' => 'participantes', 'fecha_evaluacion' => 'fecha de evaluación', 'jerarquia_controles' => 'jerarquía de controles', 'ubicacion' => 'ubicación'] as $field => $label) {
            if (trim((string) $hazard[$field]) === '') throw new DomainException('Complete ' . $label . ' antes de verificar.');
        }
        if (Evidencia::reviewedCountForEntity($tenantId, 'peligro', (string) $id) === 0) throw new DomainException('La verificación requiere un soporte revisado de la evaluación.');
        Database::execute('UPDATE peligros_ipevr SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [$userId, $id, $tenantId]);
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM peligros_ipevr WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        $hazard = self::findForTenant($id, $tenantId) ?: throw new DomainException('Peligro no encontrado.');
        if (!in_array($status, ['Activo', 'Controlado'], true)) throw new DomainException('Estado no válido.');
        if ($status === 'Controlado') {
            if (!$hazard['verificado_por']) throw new DomainException('Antes de marcar Controlado, verifique la evaluación y sus soportes.');
            $open = (int) (Database::first("SELECT COUNT(*) n FROM peligro_acciones WHERE peligro_id=? AND tenant_id=? AND estado IN ('Pendiente','En ejecucion','Implementado','No eficaz')", [$id, $tenantId])['n'] ?? 0);
            if ($open > 0) throw new DomainException('Aún hay controles abiertos o no eficaces asociados a este peligro.');
        }
        Database::execute('UPDATE peligros_ipevr SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }

    public static function stats(int $tenantId): array
    {
        $row = Database::first("SELECT COUNT(*) AS total,
            SUM(CASE WHEN nivel_riesgo IN ('Alto','Critico') AND estado = 'Activo' THEN 1 ELSE 0 END) AS prioritarios,
            SUM(CASE WHEN estado = 'Controlado' THEN 1 ELSE 0 END) AS controlados,
            COALESCE(SUM(trabajadores_expuestos),0) expuestos,
            SUM(CASE WHEN proxima_revision IS NOT NULL AND proxima_revision < date('now') THEN 1 ELSE 0 END) revisiones_vencidas
            FROM peligros_ipevr WHERE tenant_id = ?", [$tenantId]) ?? [];
        $row['acciones_vencidas'] = (int) (Database::first("SELECT COUNT(*) n FROM peligro_acciones WHERE tenant_id=? AND estado IN ('Pendiente','En ejecucion','Implementado','No eficaz') AND fecha_compromiso < date('now')", [$tenantId])['n'] ?? 0);
        return $row;
    }

    public static function dashboard(int $tenantId): array
    {
        $byRisk = array_fill_keys(['Critico', 'Alto', 'Medio', 'Bajo'], 0);
        foreach (Database::query('SELECT nivel_riesgo,COUNT(*) cantidad FROM peligros_ipevr WHERE tenant_id=? GROUP BY nivel_riesgo', [$tenantId]) as $row) $byRisk[$row['nivel_riesgo']] = (int) $row['cantidad'];
        return [
            'byRisk' => $byRisk,
            'byClass' => Database::query('SELECT clasificacion,COUNT(*) cantidad FROM peligros_ipevr WHERE tenant_id=? GROUP BY clasificacion ORDER BY cantidad DESC,clasificacion', [$tenantId]),
            'byProcess' => Database::query("SELECT proceso, SUM(CASE WHEN nivel_riesgo IN ('Alto','Critico') THEN 1 ELSE 0 END) prioritarios, COUNT(*) total, SUM(trabajadores_expuestos) expuestos FROM peligros_ipevr WHERE tenant_id=? GROUP BY proceso ORDER BY prioritarios DESC,total DESC,proceso", [$tenantId]),
        ];
    }
}
