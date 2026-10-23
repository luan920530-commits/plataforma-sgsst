<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Tenant
{
    public static function all(string $search = '', string $status = ''): array
    {
        $sql = 'SELECT t.*, a.descripcion AS actividad, a.codigo_ciuu, p.nombre AS plan_nombre, p.modulo AS plan_modulo,
                       (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) AS total_usuarios
                FROM tenants t
                LEFT JOIN actividades_economicas a ON a.id = t.actividad_economica_id
                LEFT JOIN planes_saas p ON p.id = t.plan_id WHERE 1 = 1';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (t.razonsocial LIKE ? OR t.nit LIKE ? OR a.descripcion LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
        }
        if ($status !== '') {
            $sql .= ' AND t.estado = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY t.created_at DESC, t.id DESC';
        return Database::query($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT t.*, a.descripcion AS actividad, a.codigo_ciuu, a.perfil_sociodemografico,
                    a.examenes_medicos, p.nombre AS plan_nombre, p.modulo AS plan_modulo
             FROM tenants t
             LEFT JOIN actividades_economicas a ON a.id = t.actividad_economica_id
             LEFT JOIN planes_saas p ON p.id = t.plan_id WHERE t.id = ?',
            [$id]
        );
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO tenants (razonsocial, nit, sector_economico, actividad_economica_id, numero_trabajadores,
                                  nivel_riesgo, grupo_estandares, metodo_configuracion, plan_id, representante_legal,
                                  correo_contacto, telefono, ciudad, direccion, onboarding_completado, plan_condicion)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
            [
                trim($data['razonsocial']), trim($data['nit']), $data['sector_economico'],
                $data['actividad_economica_id'] ?: null, (int) $data['numero_trabajadores'],
                $data['nivel_riesgo'], (int) $data['grupo_estandares'], $data['metodo_configuracion'],
                !empty($data['plan_id']) ? (int) $data['plan_id'] : null,
                trim($data['representante_legal'] ?? ''), text_lower(trim($data['correo_contacto'] ?? '')),
                trim($data['telefono'] ?? ''), trim($data['ciudad'] ?? ''), trim($data['direccion'] ?? ''),
                $data['plan_condicion'] ?? 'Pagado',
            ]
        );
        return Database::lastId();
    }

    public static function updateConfiguration(int $id, array $data): void
    {
        Database::execute(
            'UPDATE tenants SET representante_legal = ?, correo_contacto = ?, telefono = ?, ciudad = ?,
                                direccion = ?, numero_trabajadores = ?, nivel_riesgo = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ?',
            [
                trim($data['representante_legal']), text_lower(trim($data['correo_contacto'])), trim($data['telefono']),
                trim($data['ciudad']), trim($data['direccion']), (int) $data['numero_trabajadores'],
                $data['nivel_riesgo'], $id,
            ]
        );
    }

    public static function recalculateGroup(int $id, int $group): void
    {
        Database::execute('UPDATE tenants SET grupo_estandares = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$group, $id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::execute('UPDATE tenants SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$status, $id]);
    }

    public static function setPlan(int $id, ?int $planId, string $condition = 'Pagado'): void
    {
        Database::execute(
            'UPDATE tenants SET plan_id = ?, plan_condicion = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$planId, $condition, $id]
        );
    }

    public static function markGenerated(int $id): void
    {
        Database::execute('UPDATE tenants SET configuracion_generada_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public static function globalStats(): array
    {
        return Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'Activa' THEN 1 ELSE 0 END) AS activas,
                    SUM(numero_trabajadores) AS trabajadores,
                    SUM(CASE WHEN grupo_estandares = 60 THEN 1 ELSE 0 END) AS grupo_60
             FROM tenants"
        ) ?? [];
    }
}
