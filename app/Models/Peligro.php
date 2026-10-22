<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Peligro
{
    public static function forTenant(int $tenantId, string $classification = ''): array
    {
        $sql = 'SELECT * FROM peligros_ipevr WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($classification !== '') {
            $sql .= ' AND clasificacion = ?';
            $params[] = $classification;
        }
        return Database::query($sql . " ORDER BY CASE nivel_riesgo WHEN 'Critico' THEN 1 WHEN 'Alto' THEN 2 WHEN 'Medio' THEN 3 ELSE 4 END, clasificacion, peligro", $params);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO peligros_ipevr (tenant_id, actividad_economica_id, proceso, cargo, peligro, clasificacion,
                                         efectos, controles_existentes, nivel_deficiencia, nivel_exposicion,
                                         nivel_probabilidad, nivel_consecuencia, nivel_riesgo, medidas_control)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], !empty($data['actividad_economica_id']) ? (int) $data['actividad_economica_id'] : null,
                trim($data['proceso']), trim($data['cargo']), trim($data['peligro']), $data['clasificacion'],
                trim($data['efectos'] ?? ''), trim($data['controles_existentes'] ?? ''),
                $data['nivel_deficiencia'] ?? null, $data['nivel_exposicion'] ?? null,
                $data['nivel_probabilidad'] ?? null, $data['nivel_consecuencia'] ?? null,
                $data['nivel_riesgo'], trim($data['medidas_control'] ?? ''),
            ]
        );
        return Database::lastId();
    }

    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        Database::execute('UPDATE peligros_ipevr SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }

    public static function stats(int $tenantId): array
    {
        return Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN nivel_riesgo IN ('Alto','Critico') AND estado = 'Activo' THEN 1 ELSE 0 END) AS prioritarios,
                    SUM(CASE WHEN estado = 'Controlado' THEN 1 ELSE 0 END) AS controlados
             FROM peligros_ipevr WHERE tenant_id = ?",
            [$tenantId]
        ) ?? [];
    }
}
