<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\EventStatistics;
use App\Support\Audit;
use DomainException;

final class PeriodoEstadistico
{
    public static function forTenant(int $tenantId): array
    {
        $rows = Database::query('SELECT p.*,u.nombre verificado_por_nombre FROM periodos_estadisticos p LEFT JOIN users u ON u.id=p.verificado_por WHERE p.tenant_id=? ORDER BY p.fecha_inicio DESC,p.id DESC', [$tenantId]);
        return array_map(static function (array $row): array { $row['indicadores'] = EventStatistics::calculate($row); return $row; }, $rows);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM periodos_estadisticos WHERE id=? AND tenant_id=?', [$id, $tenantId]);
    }

    public static function create(int $tenantId, array $input, int $userId): int
    {
        $period = trim((string) ($input['periodo'] ?? ''));
        $start = self::date($input['fecha_inicio'] ?? '', 'inicio');
        $end = self::date($input['fecha_fin'] ?? '', 'fin');
        if ($period === '' || text_length($period) > 40) throw new DomainException('Registre un nombre de periodo de máximo 40 caracteres.');
        if ($end < $start) throw new DomainException('La fecha final no puede ser anterior a la inicial.');
        $hht = self::number($input['horas_hombre_trabajadas'] ?? '', 'horas-hombre trabajadas', true);
        $workers = self::number($input['trabajadores_promedio'] ?? '', 'promedio de trabajadores', true);
        $programmed = self::number($input['dias_programados'] ?? 0, 'días programados');
        $absence = self::number($input['dias_ausencia'] ?? 0, 'días de ausencia');
        if ($programmed > 0 && $absence > $programmed) throw new DomainException('Los días de ausencia no pueden superar los días programados.');
        $source = trim((string) ($input['fuente'] ?? ''));
        if ($source === '') throw new DomainException('Registre la fuente de HHT, población y días; no se calculan denominadores por defecto.');
        try {
            Database::execute('INSERT INTO periodos_estadisticos (tenant_id,periodo,fecha_inicio,fecha_fin,horas_hombre_trabajadas,trabajadores_promedio,dias_programados,dias_ausencia,fuente,observaciones,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)', [$tenantId, $period, $start, $end, $hht, $workers, $programmed, $absence, $source, trim((string) ($input['observaciones'] ?? '')), $userId]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) throw new DomainException('Ya existe un periodo con ese nombre.');
            throw $e;
        }
        $id = Database::lastId();
        Audit::record('Periodo estadístico registrado', 'PeriodoEstadistico', $id, ['periodo' => $period], $tenantId, $userId);
        return $id;
    }

    public static function verify(int $id, int $tenantId, int $userId): void
    {
        self::findForTenant($id, $tenantId) ?: throw new DomainException('Periodo no encontrado.');
        if (!Permission::has($userId, $tenantId, Permission::MATRIX_VERIFY)) throw new DomainException('La verificación requiere permiso humano de verificación.');
        if (Evidencia::reviewedCountForEntity($tenantId, 'periodo_estadistico', (string) $id) < 1) throw new DomainException('Adjunte y revise el soporte de HHT, trabajadores y días antes de verificar.');
        Database::execute('UPDATE periodos_estadisticos SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [$userId, $id, $tenantId]);
        Audit::record('Denominadores estadísticos verificados', 'PeriodoEstadistico', $id, [], $tenantId, $userId);
    }

    public static function delete(int $id, int $tenantId, int $userId): void
    {
        $period = self::findForTenant($id, $tenantId) ?: throw new DomainException('Periodo no encontrado.');
        if ($period['verificado_at']) throw new DomainException('Un periodo verificado se conserva como evidencia histórica.');
        if (Evidencia::countForEntity($tenantId, 'periodo_estadistico', (string) $id) > 0) throw new DomainException('El periodo tiene soportes vinculados y no puede eliminarse.');
        Database::execute('DELETE FROM periodos_estadisticos WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        Audit::record('Periodo estadístico borrador eliminado', 'PeriodoEstadistico', $id, ['periodo' => $period['periodo']], $tenantId, $userId);
    }

    private static function number(mixed $value, string $label, bool $positive = false): float
    {
        if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0 || ($positive && (float) $value <= 0)) throw new DomainException('Número no válido en ' . $label . '.');
        return (float) $value;
    }

    private static function date(mixed $value, string $label): string
    {
        $value = trim((string) $value);$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new DomainException('Fecha no válida: ' . $label);
        return $value;
    }
}
