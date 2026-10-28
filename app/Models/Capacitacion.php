<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Capacitacion
{
    public static function trainingTypes(): array
    {
        return ['Induccion general', 'Induccion especifica', 'Capacitacion de seguridad', 'Reentrenamiento', 'Formacion COPASST', 'Formacion Vigia', 'Entrenamiento brigada'];
    }

    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT c.*,(SELECT COUNT(*) FROM capacitacion_asistentes a WHERE a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id) inscritos,(SELECT COUNT(*) FROM capacitacion_asistentes a WHERE a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id AND a.asistio=1) asistentes FROM capacitaciones c WHERE c.tenant_id = ? ORDER BY c.fecha_programada, c.id', [$tenantId]);
    }

    public static function upcoming(int $tenantId, int $limit = 4): array
    {
        return Database::query("SELECT * FROM capacitaciones WHERE tenant_id = ? AND estado = 'Programada' ORDER BY fecha_programada LIMIT " . max(1, $limit), [$tenantId]);
    }

    public static function forPortal(int $tenantId, int $employeeId): array
    {
        return Database::query("SELECT c.*,a.id AS asistencia_id,a.asistio,a.evaluacion,a.evaluacion_escala,a.eficacia AS eficacia_individual,a.certificado_codigo,a.certificado_emitido_at FROM capacitaciones c LEFT JOIN capacitacion_asistentes a ON a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id AND a.empleado_id=? WHERE c.tenant_id=? AND c.estado IN ('Programada','Realizada') ORDER BY CASE WHEN c.estado='Programada' THEN 0 ELSE 1 END,CASE WHEN c.estado='Programada' THEN c.fecha_programada END ASC,CASE WHEN c.estado='Realizada' THEN c.fecha_programada END DESC,c.id DESC", [$employeeId, $tenantId]);
    }

    public static function findForPortal(int $id, int $tenantId, int $employeeId): ?array
    {
        return Database::first("SELECT c.*,a.id AS asistencia_id,a.asistio,a.evaluacion,a.evaluacion_escala,a.eficacia AS eficacia_individual,a.certificado_codigo,a.certificado_emitido_at FROM capacitaciones c LEFT JOIN capacitacion_asistentes a ON a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id AND a.empleado_id=? WHERE c.id=? AND c.tenant_id=? AND c.estado IN ('Programada','Realizada')", [$employeeId, $id, $tenantId]);
    }

    public static function attendeeForEmployee(int $trainingId, int $employeeId, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM capacitacion_asistentes WHERE capacitacion_id=? AND empleado_id=? AND tenant_id=?', [$trainingId, $employeeId, $tenantId]);
    }

    public static function matrixByPosition(int $tenantId): array
    {
        $positions = [];
        $directory = Database::query('SELECT cargo,area,COUNT(*) AS trabajadores FROM empleados WHERE tenant_id=? AND activo=1 GROUP BY cargo,area ORDER BY cargo,area', [$tenantId]);
        foreach ($directory as $row) {
            $cargo = trim((string) $row['cargo']) ?: 'Sin puesto especificado';
            $area = trim((string) $row['area']);
            $key = json_encode([$cargo, $area], JSON_UNESCAPED_UNICODE);
            $positions[$key] = ['cargo' => $cargo, 'area' => $area, 'trabajadores' => (int) $row['trabajadores'], 'formaciones' => []];
        }
        $coverage = Database::query("SELECT c.*,CASE WHEN e.id IS NOT NULL THEN e.cargo WHEN NULLIF(a.vinculacion,'') IS NOT NULL THEN a.vinculacion ELSE c.area_puesto END AS cargo_matriz,CASE WHEN e.id IS NOT NULL THEN e.area ELSE c.area_puesto END AS area_matriz,COUNT(a.id) AS inscritos,COALESCE(SUM(a.asistio),0) AS asistencias,COALESCE(SUM(CASE WHEN a.evaluacion IS NOT NULL AND a.evaluacion<>'' THEN 1 ELSE 0 END),0) AS evaluaciones,COALESCE(SUM(CASE WHEN a.certificado_emitido_at IS NOT NULL AND a.certificado_emitido_at<>'' THEN 1 ELSE 0 END),0) AS constancias FROM capacitaciones c LEFT JOIN capacitacion_asistentes a ON a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id LEFT JOIN empleados e ON e.id=a.empleado_id AND e.tenant_id=a.tenant_id WHERE c.tenant_id=? GROUP BY c.id,cargo_matriz,area_matriz ORDER BY c.fecha_programada,c.id", [$tenantId]);
        foreach ($coverage as $row) {
            $cargo = trim((string) $row['cargo_matriz']) ?: 'Sin puesto especificado';
            $area = trim((string) $row['area_matriz']);
            $key = json_encode([$cargo, $area], JSON_UNESCAPED_UNICODE);
            if (!isset($positions[$key])) $positions[$key] = ['cargo' => $cargo, 'area' => $area, 'trabajadores' => 0, 'formaciones' => []];
            foreach (['inscritos', 'asistencias', 'evaluaciones', 'constancias'] as $field) $row[$field] = (int) $row[$field];
            $positions[$key]['formaciones'][] = $row;
        }
        $positions = array_values($positions);
        usort($positions, static fn(array $a, array $b): int => [$a['cargo'], $a['area']] <=> [$b['cargo'], $b['area']]);
        return $positions;
    }

    public static function create(array $data): int
    {
        Database::execute('INSERT INTO capacitaciones (tenant_id,tema,descripcion,responsable,fecha_programada,fecha_realizacion,duracion_horas,modalidad,publico_objetivo,competencia,contenido,idoneidad_instructor,tipo_formacion,area_puesto,necesidad_detectada,temas_normativos) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', self::trainingValues($data, true));
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        self::editableTraining($id, $tenantId);
        $values = self::trainingValues($data, false);
        $values[] = $id; $values[] = $tenantId;
        Database::execute('UPDATE capacitaciones SET tema=?,descripcion=?,responsable=?,fecha_programada=?,fecha_realizacion=?,duracion_horas=?,modalidad=?,publico_objetivo=?,competencia=?,contenido=?,idoneidad_instructor=?,tipo_formacion=?,area_puesto=?,necesidad_detectada=?,temas_normativos=? WHERE id=? AND tenant_id=?', $values);
    }

    private static function trainingValues(array $data, bool $creating): array
    {
        $values = [
            trim($data['tema']), trim($data['descripcion'] ?? ''), trim($data['responsable']), $data['fecha_programada'],
            !empty($data['fecha_realizacion']) ? $data['fecha_realizacion'] : null, (float) $data['duracion_horas'], $data['modalidad'],
            trim($data['publico_objetivo'] ?? ''), trim($data['competencia'] ?? ''), trim($data['contenido'] ?? ''),
            trim($data['idoneidad_instructor'] ?? ''), $data['tipo_formacion'] ?? 'Capacitacion de seguridad',
            trim($data['area_puesto'] ?? ''), trim($data['necesidad_detectada'] ?? ''), trim($data['temas_normativos'] ?? ''),
        ];
        if ($creating) array_unshift($values, (int) $data['tenant_id']);
        return $values;
    }

    public static function delete(int $id, int $tenantId): void
    {
        self::findForTenant($id, $tenantId) ?: throw new DomainException('Capacitación no encontrada.');
        $issued = (int) (Database::first('SELECT COUNT(*) n FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? AND certificado_emitido_at IS NOT NULL', [$id, $tenantId])['n'] ?? 0);
        if ($issued > 0) throw new DomainException('La capacitación tiene constancias emitidas y debe conservarse como histórico.');
        Database::execute('DELETE FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function attendees(int $id, int $tenantId): array
    {
        return Database::query('SELECT * FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? ORDER BY nombre,id', [$id, $tenantId]);
    }

    public static function attendee(int $id, int $trainingId, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM capacitacion_asistentes WHERE id=? AND capacitacion_id=? AND tenant_id=?', [$id, $trainingId, $tenantId]);
    }

    public static function addAttendee(int $trainingId, int $tenantId, array $input): int
    {
        self::editableTraining($trainingId, $tenantId);
        $employeeId = (int) ($input['empleado_id'] ?? 0) ?: null;
        $employee = $employeeId ? Empleado::findForTenant($employeeId, $tenantId) : null;
        if ($employeeId && !$employee) throw new DomainException('La persona seleccionada no pertenece a la empresa.');
        $name = trim((string) ($employee['nombre'] ?? $input['nombre'] ?? ''));
        $document = trim((string) ($employee['documento'] ?? $input['documento'] ?? ''));
        $link = trim((string) ($input['vinculacion'] ?? ''));
        if ($link === '' && $employee) $link = trim((string) ($employee['cargo'] ?? ''));
        if ($name === '' || $document === '') throw new DomainException('Nombre y documento son obligatorios para la asistencia.');
        if (Database::first('SELECT id FROM capacitacion_asistentes WHERE capacitacion_id=? AND documento=?', [$trainingId, $document])) throw new DomainException('La persona ya está registrada en esta capacitación.');
        $code = 'CER-' . strtoupper(bin2hex(random_bytes(10)));
        Database::execute('INSERT INTO capacitacion_asistentes (tenant_id,capacitacion_id,empleado_id,nombre,documento,vinculacion,certificado_codigo) VALUES (?,?,?,?,?,?,?)', [$tenantId, $trainingId, $employeeId, $name, $document, $link, $code]);
        return Database::lastId();
    }

    public static function updateAttendee(int $id, int $trainingId, int $tenantId, array $input): void
    {
        self::editableTraining($trainingId, $tenantId);
        self::attendee($id, $trainingId, $tenantId) ?: throw new DomainException('Asistente no encontrado.');
        $evaluation = trim((string) ($input['evaluacion'] ?? ''));
        $scale = trim((string) ($input['evaluacion_escala'] ?? ''));
        if ($evaluation !== '' && (!is_numeric($evaluation) || (float) $evaluation < 0)) throw new DomainException('La evaluación debe ser numérica y conservar la escala aplicada.');
        if ($evaluation !== '' && $scale === '') throw new DomainException('Registre la escala de evaluación, por ejemplo 0 a 100.');
        Database::execute('UPDATE capacitacion_asistentes SET asistio=?,evaluacion=?,evaluacion_escala=?,eficacia=? WHERE id=? AND capacitacion_id=? AND tenant_id=?', [(int) (($input['asistio'] ?? '') === '1'), $evaluation === '' ? null : (float) $evaluation, $evaluation === '' ? '' : $scale, trim((string) ($input['eficacia'] ?? '')), $id, $trainingId, $tenantId]);
    }

    public static function deleteAttendee(int $id, int $trainingId, int $tenantId): void
    {
        self::editableTraining($trainingId, $tenantId);
        Database::execute('DELETE FROM capacitacion_asistentes WHERE id=? AND capacitacion_id=? AND tenant_id=?', [$id, $trainingId, $tenantId]);
    }

    public static function setStatus(int $id, int $tenantId, string $status, ?string $executionDate = null): void
    {
        $training = self::findForTenant($id, $tenantId) ?: throw new DomainException('Capacitación no encontrada.');
        if (!in_array($status, ['Programada', 'Realizada', 'Cancelada'], true)) throw new DomainException('Estado no válido.');
        if ($training['estado'] === 'Realizada' && $status !== 'Realizada') {
            $issued = (int) (Database::first('SELECT COUNT(*) n FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? AND certificado_emitido_at IS NOT NULL', [$id, $tenantId])['n'] ?? 0);
            if ($issued > 0) throw new DomainException('No puede reabrirse una capacitación con constancias emitidas; conserva el histórico y registra una nueva acción formativa si corresponde.');
        }
        $actualDate = $training['fecha_realizacion'];
        if ($status === 'Realizada') {
            $actualDate = self::date($executionDate ?: $training['fecha_realizacion'], 'fecha real de realización');
            $attended = (int) (Database::first('SELECT COUNT(*) n FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? AND asistio=1', [$id, $tenantId])['n'] ?? 0);
            if ($attended === 0) throw new DomainException('Registre al menos una asistencia real antes de marcar la capacitación como realizada.');
            if (Evidencia::reviewedCountForEntity($tenantId, 'capacitacion', (string) $id) === 0) throw new DomainException('Se requiere una evidencia de capacitación revisada antes de marcarla como realizada.');
        }
        if ($status === 'Programada' && $training['estado'] === 'Realizada') $actualDate = null;
        Database::execute('UPDATE capacitaciones SET estado=?,fecha_realizacion=? WHERE id=? AND tenant_id=?', [$status, $actualDate, $id, $tenantId]);
    }

    public static function recordEfficacy(int $id, int $tenantId, string $efficacy, string $date, int $userId): void
    {
        $training = self::findForTenant($id, $tenantId) ?: throw new DomainException('Capacitación no encontrada.');
        if ($training['estado'] !== 'Realizada') throw new DomainException('La eficacia se evalúa después de realizar la capacitación.');
        if (trim($efficacy) === '') throw new DomainException('Documente el resultado de eficacia.');
        self::date($date, 'fecha de eficacia');
        Database::execute('UPDATE capacitaciones SET eficacia=?,fecha_eficacia=?,verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [trim($efficacy), $date, $userId, $id, $tenantId]);
    }

    public static function certificate(int $trainingId, int $attendeeId, int $tenantId): array
    {
        $training = self::findForTenant($trainingId, $tenantId) ?: throw new DomainException('Capacitación no encontrada.');
        $attendee = self::attendee($attendeeId, $trainingId, $tenantId) ?: throw new DomainException('Participante no encontrado.');
        if ($training['estado'] !== 'Realizada' || empty($training['fecha_realizacion'])) throw new DomainException('El certificado requiere capacitación realizada y fecha real registrada.');
        if (!(int) $attendee['asistio']) throw new DomainException('No puede emitirse un certificado sin asistencia confirmada.');
        if ($attendee['evaluacion'] === null || trim((string) $attendee['evaluacion_escala']) === '') throw new DomainException('Registre la evaluación y su escala antes de emitir la constancia.');
        if (!$attendee['certificado_emitido_at']) {
            $code = $attendee['certificado_codigo'] ?: 'CER-' . strtoupper(bin2hex(random_bytes(10)));
            $snapshot = [
                'tenant_id' => $tenantId, 'capacitacion_id' => $trainingId, 'asistente_id' => $attendeeId,
                'codigo' => $code, 'tema' => $training['tema'], 'tipo_formacion' => $training['tipo_formacion'],
                'fecha_realizacion' => $training['fecha_realizacion'], 'duracion_horas' => (float) $training['duracion_horas'],
                'responsable' => $training['responsable'], 'nombre' => $attendee['nombre'], 'documento' => $attendee['documento'],
                'vinculacion' => $attendee['vinculacion'], 'evaluacion' => (float) $attendee['evaluacion'],
                'evaluacion_escala' => $attendee['evaluacion_escala'],
            ];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hash = hash('sha256', $json);
            Database::execute('UPDATE capacitacion_asistentes SET certificado_codigo=?,certificado_emitido_at=CURRENT_TIMESTAMP,certificado_hash=?,certificado_snapshot_json=? WHERE id=? AND capacitacion_id=? AND tenant_id=?', [$code, $hash, $json, $attendeeId, $trainingId, $tenantId]);
            $attendee['certificado_codigo'] = $code;
            $attendee['certificado_emitido_at'] = date('Y-m-d H:i:s');
            $attendee['certificado_hash'] = $hash;
            $attendee['certificado_snapshot_json'] = $json;
        }
        if (!preg_match('/^[0-9a-f]{64}$/', (string) $attendee['certificado_hash']) || $attendee['certificado_snapshot_json'] === '' || !hash_equals((string) $attendee['certificado_hash'], hash('sha256', (string) $attendee['certificado_snapshot_json']))) {
            throw new DomainException('La constancia histórica no tiene una instantánea de integridad completa; no se reemitirá con datos modificados.');
        }
        return ['training' => $training, 'attendee' => $attendee, 'integrityHash' => $attendee['certificado_hash']];
    }

    private static function editableTraining(int $id, int $tenantId): array
    {
        $training = self::findForTenant($id, $tenantId) ?: throw new DomainException('Capacitación no encontrada.');
        if ($training['estado'] === 'Realizada') throw new DomainException('Reabra la capacitación antes de modificar la asistencia.');
        return $training;
    }

    private static function date(mixed $value, string $label): string
    {
        $value = trim((string) $value);$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) throw new DomainException('Fecha no válida: ' . $label . '.');
        return $value;
    }
}
