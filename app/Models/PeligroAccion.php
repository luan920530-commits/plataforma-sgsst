<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class PeligroAccion
{
    public static function forHazard(int $hazardId, int $tenantId): array
    {
        return Database::query("SELECT a.*,u.nombre verificado_por_nombre,
            (SELECT COUNT(*) FROM peligro_accion_hitos h WHERE h.accion_id=a.id AND h.tenant_id=a.tenant_id) hitos,
            (SELECT COUNT(*) FROM peligro_accion_hitos h WHERE h.accion_id=a.id AND h.tenant_id=a.tenant_id AND h.estado='Completado') hitos_completados,
            (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=a.tenant_id AND s.entidad_tipo='peligro_accion' AND s.entidad_id=CAST(a.id AS TEXT) AND s.estado_revision='Revisado') evidencias_revisadas
            FROM peligro_acciones a LEFT JOIN users u ON u.id=a.verificado_por
            WHERE a.peligro_id=? AND a.tenant_id=? ORDER BY a.fecha_compromiso,a.id", [$hazardId, $tenantId]);
    }

    public static function findForTenant(int $id, int $hazardId, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM peligro_acciones WHERE id=? AND peligro_id=? AND tenant_id=?', [$id, $hazardId, $tenantId]);
    }

    public static function create(int $hazardId, int $tenantId, array $input, int $userId): int
    {
        $hazard = Peligro::findForTenant($hazardId, $tenantId) ?: throw new DomainException('Peligro no encontrado.');
        $description = trim((string) ($input['descripcion'] ?? ''));
        $hierarchy = (string) ($input['jerarquia'] ?? '');
        $responsible = trim((string) ($input['responsable'] ?? ''));
        $due = self::date($input['fecha_compromiso'] ?? '', 'fecha compromiso', true);
        if ($description === '' || $responsible === '') throw new DomainException('Descripción y responsable son obligatorios.');
        if (!in_array($hierarchy, ['Eliminacion', 'Sustitucion', 'Ingenieria', 'Administrativo', 'EPP'], true)) throw new DomainException('Seleccione un nivel de la jerarquía de controles.');
        Database::execute('INSERT INTO peligro_acciones (tenant_id,peligro_id,descripcion,jerarquia,responsable,recursos,fecha_compromiso,indicador_eficacia,created_by) VALUES (?,?,?,?,?,?,?,?,?)', [
            $tenantId, $hazardId, $description, $hierarchy, $responsible, trim((string) ($input['recursos'] ?? '')),
            $due, trim((string) ($input['indicador_eficacia'] ?? '')), $userId,
        ]);
        $id = Database::lastId();
        if ($hazard['estado'] === 'Controlado') Database::execute("UPDATE peligros_ipevr SET estado='Activo',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?", [$hazardId, $tenantId]);
        Audit::record('Control IPEVR programado', 'PeligroAccion', $id, ['peligro_id' => $hazardId, 'jerarquia' => $hierarchy], $tenantId, $userId);
        return $id;
    }

    public static function setStatus(int $id, int $hazardId, int $tenantId, array $input, int $userId): void
    {
        $action = self::findForTenant($id, $hazardId, $tenantId) ?: throw new DomainException('Control no encontrado.');
        $status = (string) ($input['estado'] ?? '');
        if (!in_array($status, ['Pendiente', 'En ejecucion', 'Implementado', 'Eficaz', 'No eficaz', 'Cancelado'], true)) throw new DomainException('Estado de control no válido.');
        if (in_array($action['estado'], ['Eficaz', 'Cancelado'], true) && $status !== $action['estado']) throw new DomainException('Un control eficaz o cancelado no puede retroceder; registre otro control si es necesario.');
        $percentage = $input['porcentaje'] ?? $action['porcentaje'];
        if (!is_numeric($percentage) || (int) $percentage != (float) $percentage || (int) $percentage < 0 || (int) $percentage > 100) throw new DomainException('El avance debe ser un entero entre 0 y 100.');
        $percentage = (int) $percentage;
        $execution = $action['fecha_ejecucion'];
        $result = trim((string) ($input['resultado'] ?? $action['resultado']));
        $efficacyDate = $action['eficacia_fecha'];
        if ($status === 'Implementado') {
            if ($percentage !== 100) throw new DomainException('Para marcar Implementado, el avance debe ser 100 %.');
            $execution = self::date($input['fecha_ejecucion'] ?? '', 'fecha real de ejecución', true);
            if ($result === '') throw new DomainException('Documente el resultado real de la implementación.');
            $pending = (int) (Database::first("SELECT COUNT(*) n FROM peligro_accion_hitos WHERE accion_id=? AND tenant_id=? AND estado='Pendiente'", [$id, $tenantId])['n'] ?? 0);
            if ($pending > 0) throw new DomainException('Complete o ajuste los hitos pendientes antes de cerrar la implementación.');
        }
        if (in_array($status, ['Eficaz', 'No eficaz'], true)) {
            if (!in_array($action['estado'], ['Implementado', 'No eficaz'], true)) throw new DomainException('Primero registre la implementación del control.');
            if (!Permission::has($userId, $tenantId, Permission::MATRIX_VERIFY)) throw new DomainException('La eficacia requiere permiso de verificación humana.');
            if (trim((string) $action['indicador_eficacia']) === '') throw new DomainException('El control necesita un indicador de eficacia definido.');
            $efficacyDate = self::date($input['eficacia_fecha'] ?? '', 'fecha de eficacia', true);
            if ($result === '') throw new DomainException('Registre el resultado medido de eficacia.');
            if (Evidencia::reviewedCountForEntity($tenantId, 'peligro_accion', (string) $id) < 1) throw new DomainException('La eficacia exige una evidencia revisada vinculada a este control.');
        }
        if ($status === 'Cancelado' && (!in_array($action['estado'], ['Pendiente', 'En ejecucion'], true) || $result === '')) throw new DomainException('Solo puede cancelarse un control no implementado y con justificación en el resultado.');
        Database::execute('UPDATE peligro_acciones SET estado=?,porcentaje=?,fecha_ejecucion=?,resultado=?,eficacia_fecha=?,verificado_por=?,verificado_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND peligro_id=? AND tenant_id=?', [
            $status, $percentage, $execution, $result, $efficacyDate,
            in_array($status, ['Eficaz', 'No eficaz'], true) ? $userId : null,
            in_array($status, ['Eficaz', 'No eficaz'], true) ? date('Y-m-d H:i:s') : null,
            $id, $hazardId, $tenantId,
        ]);
        Audit::record('Estado de control IPEVR actualizado', 'PeligroAccion', $id, ['estado' => $status, 'porcentaje' => $percentage], $tenantId, $userId);
    }

    public static function milestones(int $actionId, int $tenantId): array
    {
        return Database::query('SELECT * FROM peligro_accion_hitos WHERE accion_id=? AND tenant_id=? ORDER BY fecha_programada,id', [$actionId, $tenantId]);
    }

    public static function addMilestone(int $actionId, int $hazardId, int $tenantId, array $input, int $userId): int
    {
        $action = self::findForTenant($actionId, $hazardId, $tenantId) ?: throw new DomainException('Control no encontrado.');
        if (in_array($action['estado'], ['Implementado', 'Eficaz', 'Cancelado'], true)) throw new DomainException('No pueden añadirse hitos después de cerrar la implementación.');
        $description = trim((string) ($input['descripcion'] ?? ''));
        if ($description === '') throw new DomainException('Describa el hito verificable.');
        Database::execute('INSERT INTO peligro_accion_hitos (tenant_id,accion_id,descripcion,fecha_programada) VALUES (?,?,?,?)', [$tenantId, $actionId, $description, self::date($input['fecha_programada'] ?? '', 'fecha del hito', true)]);
        $id = Database::lastId();
        Audit::record('Hito de control IPEVR programado', 'PeligroAccion', $actionId, ['hito_id' => $id], $tenantId, $userId);
        return $id;
    }

    public static function completeMilestone(int $milestoneId, int $actionId, int $hazardId, int $tenantId, array $input, int $userId): void
    {
        self::findForTenant($actionId, $hazardId, $tenantId) ?: throw new DomainException('Control no encontrado.');
        $milestone = Database::first('SELECT * FROM peligro_accion_hitos WHERE id=? AND accion_id=? AND tenant_id=?', [$milestoneId, $actionId, $tenantId]) ?: throw new DomainException('Hito no encontrado.');
        if ($milestone['estado'] === 'Completado') return;
        $note = trim((string) ($input['nota'] ?? ''));
        if ($note === '') throw new DomainException('Documente la evidencia o resultado observado del hito.');
        Database::execute("UPDATE peligro_accion_hitos SET estado='Completado',fecha_cumplimiento=?,nota=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND accion_id=? AND tenant_id=?", [self::date($input['fecha_cumplimiento'] ?? '', 'fecha de cumplimiento', true), $note, $milestoneId, $actionId, $tenantId]);
        Audit::record('Hito de control IPEVR completado', 'PeligroAccion', $actionId, ['hito_id' => $milestoneId], $tenantId, $userId);
    }

    private static function date(mixed $value, string $label, bool $required = false): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') { if ($required) throw new DomainException('La ' . $label . ' es obligatoria.'); return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new DomainException('Fecha no válida: ' . $label);
        return $value;
    }
}
