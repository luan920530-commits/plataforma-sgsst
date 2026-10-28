<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class ComiteCompromiso
{
    public static function forCommittee(int $committeeId, int $tenantId): array
    {
        return Database::query("SELECT c.*,r.fecha reunion_fecha,u.nombre verificado_por_nombre,
            (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=c.tenant_id AND s.entidad_tipo='comite_compromiso' AND s.entidad_id=CAST(c.id AS TEXT) AND s.estado_revision='Revisado') evidencias_revisadas
            FROM comite_compromisos c LEFT JOIN comite_reuniones r ON r.id=c.reunion_id LEFT JOIN users u ON u.id=c.verificado_por
            WHERE c.comite_id=? AND c.tenant_id=? ORDER BY c.fecha_compromiso,c.id", [$committeeId, $tenantId]);
    }

    public static function create(int $committeeId, int $tenantId, array $input, int $userId): int
    {
        $committee = Comite::findForTenant($committeeId, $tenantId) ?: throw new DomainException('Instancia no encontrada.');
        $meetingId = (int) ($input['reunion_id'] ?? 0) ?: null;
        if ($meetingId && !Comite::meeting($meetingId, $committeeId, $tenantId)) throw new DomainException('La reunión no pertenece a esta instancia.');
        $category = (string) ($input['categoria'] ?? 'Compromiso');
        if (!in_array($category, ['Compromiso', 'Recomendacion Vigia', 'Reporte condicion insegura', 'Seguimiento COPASST'], true)) throw new DomainException('Categoría no válida.');
        if ($committee['tipo'] === 'Vigia SST' && $category === 'Seguimiento COPASST') throw new DomainException('La categoría seleccionada corresponde a COPASST.');
        $description = trim((string) ($input['descripcion'] ?? ''));$responsible = trim((string) ($input['responsable'] ?? ''));
        if ($description === '' || $responsible === '') throw new DomainException('Descripción y responsable son obligatorios.');
        Database::execute('INSERT INTO comite_compromisos (tenant_id,comite_id,reunion_id,categoria,descripcion,responsable,fecha_compromiso,created_by) VALUES (?,?,?,?,?,?,?,?)', [$tenantId, $committeeId, $meetingId, $category, $description, $responsible, self::date($input['fecha_compromiso'] ?? ''), $userId]);
        $id = Database::lastId();Audit::record('Compromiso de instancia registrado', 'ComiteCompromiso', $id, ['comite_id' => $committeeId, 'categoria' => $category], $tenantId, $userId);return $id;
    }

    public static function setStatus(int $id, int $committeeId, int $tenantId, array $input, int $userId): void
    {
        $commitment = Database::first('SELECT * FROM comite_compromisos WHERE id=? AND comite_id=? AND tenant_id=?', [$id, $committeeId, $tenantId]) ?: throw new DomainException('Compromiso no encontrado.');
        $status = (string) ($input['estado'] ?? '');
        if (!in_array($status, ['Pendiente', 'En curso', 'Completado', 'Verificado', 'Cancelado'], true)) throw new DomainException('Estado no válido.');
        if ($commitment['estado'] === 'Verificado' && $status !== 'Verificado') throw new DomainException('Un compromiso verificado se conserva como histórico.');
        $result = trim((string) ($input['resultado'] ?? $commitment['resultado']));
        if (in_array($status, ['Completado', 'Cancelado'], true) && $result === '') throw new DomainException('Documente el resultado o la justificación.');
        if ($status === 'Verificado') {
            if ($commitment['estado'] !== 'Completado') throw new DomainException('Primero registre el compromiso como completado.');
            if ($result === '') throw new DomainException('Documente el resultado antes de verificar.');
            if (!Permission::has($userId, $tenantId, Permission::MATRIX_VERIFY)) throw new DomainException('La verificación requiere permiso humano de verificación.');
            if (Evidencia::reviewedCountForEntity($tenantId, 'comite_compromiso', (string) $id) < 1) throw new DomainException('La verificación exige una evidencia revisada.');
        }
        Database::execute('UPDATE comite_compromisos SET estado=?,resultado=?,verificado_por=?,verificado_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND comite_id=? AND tenant_id=?', [$status, $result, $status === 'Verificado' ? $userId : null, $status === 'Verificado' ? date('Y-m-d H:i:s') : null, $id, $committeeId, $tenantId]);
        Audit::record('Estado de compromiso de instancia actualizado', 'ComiteCompromiso', $id, ['estado' => $status], $tenantId, $userId);
    }

    private static function date(mixed $value): string
    {
        $value = trim((string) $value);$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new DomainException('Fecha compromiso no válida.');return $value;
    }
}
