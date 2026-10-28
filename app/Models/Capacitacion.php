<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Capacitacion
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT c.*,(SELECT COUNT(*) FROM capacitacion_asistentes a WHERE a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id) inscritos,(SELECT COUNT(*) FROM capacitacion_asistentes a WHERE a.capacitacion_id=c.id AND a.tenant_id=c.tenant_id AND a.asistio=1) asistentes FROM capacitaciones c WHERE c.tenant_id = ? ORDER BY c.fecha_programada, c.id', [$tenantId]);
    }
    public static function upcoming(int $tenantId, int $limit = 4): array{return Database::query("SELECT * FROM capacitaciones WHERE tenant_id = ? AND estado = 'Programada' ORDER BY fecha_programada LIMIT ".max(1,$limit),[$tenantId]);}
    public static function create(array $data): int{Database::execute('INSERT INTO capacitaciones (tenant_id,tema,descripcion,responsable,fecha_programada,duracion_horas,modalidad,publico_objetivo,competencia,contenido,idoneidad_instructor) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[(int)$data['tenant_id'],trim($data['tema']),trim($data['descripcion']??''),trim($data['responsable']),$data['fecha_programada'],(float)$data['duracion_horas'],$data['modalidad'],trim($data['publico_objetivo']??''),trim($data['competencia']??''),trim($data['contenido']??''),trim($data['idoneidad_instructor']??'')]);return Database::lastId();}
    public static function update(int $id, int $tenantId, array $data): void{Database::execute('UPDATE capacitaciones SET tema=?,descripcion=?,responsable=?,fecha_programada=?,duracion_horas=?,modalidad=?,publico_objetivo=?,competencia=?,contenido=?,idoneidad_instructor=? WHERE id=? AND tenant_id=?',[trim($data['tema']),trim($data['descripcion']??''),trim($data['responsable']),$data['fecha_programada'],(float)$data['duracion_horas'],$data['modalidad'],trim($data['publico_objetivo']??''),trim($data['competencia']??''),trim($data['contenido']??''),trim($data['idoneidad_instructor']??''),$id,$tenantId]);}
    public static function delete(int $id, int $tenantId): void{Database::execute('DELETE FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);}
    public static function findForTenant(int $id, int $tenantId): ?array{return Database::first('SELECT * FROM capacitaciones WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);}

    public static function attendees(int$id,int$tenantId):array{return Database::query('SELECT * FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? ORDER BY nombre,id',[$id,$tenantId]);}
    public static function attendee(int$id,int$trainingId,int$tenantId):?array{return Database::first('SELECT * FROM capacitacion_asistentes WHERE id=? AND capacitacion_id=? AND tenant_id=?',[$id,$trainingId,$tenantId]);}
    public static function addAttendee(int$trainingId,int$tenantId,array$input):int
    {
        $training=self::editableTraining($trainingId,$tenantId);$employeeId=(int)($input['empleado_id']??0)?:null;$employee=$employeeId?Empleado::findForTenant($employeeId,$tenantId):null;if($employeeId&&!$employee)throw new DomainException('La persona seleccionada no pertenece a la empresa.');$name=trim((string)($employee['nombre']??$input['nombre']??''));$document=trim((string)($employee['documento']??$input['documento']??''));$link=trim((string)($input['vinculacion']??$employee['cargo']??''));if($name===''||$document==='')throw new DomainException('Nombre y documento son obligatorios para la asistencia.');if(Database::first('SELECT id FROM capacitacion_asistentes WHERE capacitacion_id=? AND documento=?',[$trainingId,$document]))throw new DomainException('La persona ya está registrada en esta capacitación.');Database::execute('INSERT INTO capacitacion_asistentes (tenant_id,capacitacion_id,empleado_id,nombre,documento,vinculacion) VALUES (?,?,?,?,?,?)',[$tenantId,$trainingId,$employeeId,$name,$document,$link]);return Database::lastId();
    }
    public static function updateAttendee(int$id,int$trainingId,int$tenantId,array$input):void
    {
        self::editableTraining($trainingId,$tenantId);$attendee=self::attendee($id,$trainingId,$tenantId)?:throw new DomainException('Asistente no encontrado.');$evaluation=trim((string)($input['evaluacion']??''));if($evaluation!==''&&(!is_numeric($evaluation)||(float)$evaluation<0))throw new DomainException('La evaluación debe ser numérica y conservar la escala aplicada.');Database::execute('UPDATE capacitacion_asistentes SET asistio=?,evaluacion=?,eficacia=? WHERE id=? AND capacitacion_id=? AND tenant_id=?',[(int)(($input['asistio']??'')==='1'),$evaluation===''?null:(float)$evaluation,trim((string)($input['eficacia']??'')),$id,$trainingId,$tenantId]);
    }
    public static function deleteAttendee(int$id,int$trainingId,int$tenantId):void{self::editableTraining($trainingId,$tenantId);Database::execute('DELETE FROM capacitacion_asistentes WHERE id=? AND capacitacion_id=? AND tenant_id=?',[$id,$trainingId,$tenantId]);}
    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        $training=self::findForTenant($id,$tenantId)?:throw new DomainException('Capacitación no encontrada.');if(!in_array($status,['Programada','Realizada','Cancelada'],true))throw new DomainException('Estado no válido.');if($status==='Realizada'){$attended=(int)(Database::first('SELECT COUNT(*) n FROM capacitacion_asistentes WHERE capacitacion_id=? AND tenant_id=? AND asistio=1',[$id,$tenantId])['n']??0);if($attended===0)throw new DomainException('Registre al menos una asistencia real antes de marcar la capacitación como realizada.');if(Evidencia::reviewedCountForEntity($tenantId,'capacitacion',(string)$id)===0)throw new DomainException('Se requiere una evidencia de capacitación revisada antes de marcarla como realizada.');}Database::execute('UPDATE capacitaciones SET estado = ? WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }
    public static function recordEfficacy(int$id,int$tenantId,string$efficacy,string$date,int$userId):void
    {
        $training=self::findForTenant($id,$tenantId)?:throw new DomainException('Capacitación no encontrada.');if($training['estado']!=='Realizada')throw new DomainException('La eficacia se evalúa después de realizar la capacitación.');if(trim($efficacy)==='')throw new DomainException('Documente el resultado de eficacia.');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new DomainException('Fecha de eficacia no válida.');Database::execute('UPDATE capacitaciones SET eficacia=?,fecha_eficacia=?,verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[trim($efficacy),$date,$userId,$id,$tenantId]);
    }
    private static function editableTraining(int$id,int$tenantId):array{$training=self::findForTenant($id,$tenantId)?:throw new DomainException('Capacitación no encontrada.');if($training['estado']==='Realizada')throw new DomainException('Reabra la capacitación antes de modificar la asistencia.');return$training;}
}
