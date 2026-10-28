<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class Comite
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query("SELECT c.*,(SELECT COUNT(*) FROM comite_miembros m WHERE m.comite_id=c.id AND m.activo=1) miembros,(SELECT COUNT(*) FROM comite_reuniones r WHERE r.comite_id=c.id AND r.estado='Realizada') reuniones FROM comites c WHERE c.tenant_id=? ORDER BY c.estado,c.tipo,c.centro_trabajo",[$tenantId]);
    }
    public static function findForTenant(int $id,int $tenantId):?array{return Database::first('SELECT * FROM comites WHERE id=? AND tenant_id=?',[$id,$tenantId]);}

    public static function create(int $tenantId,array $input,int $userId):int
    {
        $type=(string)($input['tipo']??'');if(!in_array($type,['COPASST','Vigia SST'],true))throw new DomainException('Tipo de instancia no válido.');
        $center=trim((string)($input['centro_trabajo']??''));$date=self::date($input['fecha_constitucion']??'','constitución',true);$period=trim((string)($input['periodo']??''));$quorum=self::percentage($input['quorum_minimo']??51,'quórum mínimo',false);
        if($center===''||$period==='')throw new DomainException('Centro de trabajo y periodo son obligatorios.');
        if(Database::first("SELECT id FROM comites WHERE tenant_id=? AND tipo=? AND centro_trabajo=? AND estado='Activo'",[$tenantId,$type,$center]))throw new DomainException('Ya existe una instancia activa de este tipo en el centro.');
        Database::execute('INSERT INTO comites (tenant_id,tipo,centro_trabajo,fecha_constitucion,periodo,quorum_minimo) VALUES (?,?,?,?,?,?)',[$tenantId,$type,$center,$date,$period,$quorum]);$id=Database::lastId();Audit::record('Instancia de participación constituida','Comite',$id,['tipo'=>$type],$tenantId,$userId);return$id;
    }

    public static function setActive(int $id,int $tenantId,bool $active,int $userId):void
    {
        $committee=self::findForTenant($id,$tenantId)?:throw new DomainException('Instancia no encontrada.');
        Database::execute('UPDATE comites SET estado=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$active?'Activo':'Inactivo',$id,$tenantId]);Audit::record('Estado de instancia actualizado','Comite',$id,['activo'=>$active],$tenantId,$userId);
    }

    public static function members(int $committeeId,int $tenantId):array{return Database::query('SELECT * FROM comite_miembros WHERE comite_id=? AND tenant_id=? ORDER BY activo DESC,representacion,condicion,nombre',[$committeeId,$tenantId]);}

    public static function addMember(int $committeeId,int $tenantId,array $input,int $userId):int
    {
        $committee=self::findForTenant($committeeId,$tenantId)?:throw new DomainException('Instancia no encontrada.');if($committee['estado']!=='Activo')throw new DomainException('Active la instancia antes de agregar miembros.');
        $employeeId=(int)($input['empleado_id']??0);$employee=null;if($employeeId>0)$employee=Empleado::findForTenant($employeeId,$tenantId)?:throw new DomainException('La persona no pertenece a la empresa.');
        $name=trim((string)($employee['nombre']??$input['nombre']??''));if($name==='')throw new DomainException('Registre el nombre del miembro.');
        $representation=(string)($input['representacion']??'');if(!in_array($representation,['Empleador','Trabajadores'],true))throw new DomainException('Representación no válida.');
        $condition=(string)($input['condicion']??'Principal');if(!in_array($condition,['Principal','Suplente'],true))throw new DomainException('Condición no válida.');
        $start=self::date($input['fecha_inicio']??'','inicio',true);$end=self::date($input['fecha_fin']??null,'fin');$hours=self::number($input['capacitacion_horas']??0,'horas de capacitación');
        if(Database::first('SELECT id FROM comite_miembros WHERE comite_id=? AND empleado_id IS ? AND nombre=? AND activo=1',[$committeeId,$employeeId?:null,$name]))throw new DomainException('La persona ya figura como miembro activo.');
        Database::execute('INSERT INTO comite_miembros (tenant_id,comite_id,empleado_id,nombre,documento,representacion,condicion,fecha_inicio,fecha_fin,capacitacion_horas) VALUES (?,?,?,?,?,?,?,?,?,?)',[$tenantId,$committeeId,$employeeId?:null,$name,trim((string)($employee['documento']??$input['documento']??'')),$representation,$condition,$start,$end,$hours]);$id=Database::lastId();Audit::record('Miembro agregado a instancia','Comite',$committeeId,['miembro_id'=>$id,'representacion'=>$representation],$tenantId,$userId);return$id;
    }

    public static function setMemberActive(int $memberId,int $committeeId,int $tenantId,bool $active,int $userId):void
    {
        $member=Database::first('SELECT * FROM comite_miembros WHERE id=? AND comite_id=? AND tenant_id=?',[$memberId,$committeeId,$tenantId])?:throw new DomainException('Miembro no encontrado.');
        Database::execute('UPDATE comite_miembros SET activo=?,fecha_fin=? WHERE id=? AND comite_id=? AND tenant_id=?',[$active?1:0,$active?null:date('Y-m-d'),$memberId,$committeeId,$tenantId]);Audit::record('Estado de miembro actualizado','Comite',$committeeId,['miembro_id'=>$memberId,'activo'=>$active],$tenantId,$userId);
    }

    public static function meetings(int $committeeId,int $tenantId):array{return Database::query('SELECT * FROM comite_reuniones WHERE comite_id=? AND tenant_id=? ORDER BY fecha DESC,id DESC',[$committeeId,$tenantId]);}
    public static function meeting(int $meetingId,int $committeeId,int $tenantId):?array{return Database::first('SELECT * FROM comite_reuniones WHERE id=? AND comite_id=? AND tenant_id=?',[$meetingId,$committeeId,$tenantId]);}

    public static function scheduleMeeting(int $committeeId,int $tenantId,array $input,int $userId):int
    {
        $committee=self::findForTenant($committeeId,$tenantId)?:throw new DomainException('Instancia no encontrada.');if($committee['estado']!=='Activo')throw new DomainException('La instancia está inactiva.');
        $type=(string)($input['tipo']??'Ordinaria');if(!in_array($type,['Ordinaria','Extraordinaria'],true))throw new DomainException('Tipo de reunión no válido.');
        $date=self::date($input['fecha']??'','reunión',true);$next=self::date($input['proxima_reunion']??null,'próxima reunión');$place=trim((string)($input['lugar']??''));$agenda=trim((string)($input['orden_dia']??''));if($place===''||$agenda==='')throw new DomainException('Lugar y orden del día son obligatorios.');
        Database::execute('INSERT INTO comite_reuniones (tenant_id,comite_id,tipo,fecha,lugar,orden_dia,proxima_reunion,created_by) VALUES (?,?,?,?,?,?,?,?)',[$tenantId,$committeeId,$type,$date,$place,$agenda,$next,$userId]);$id=Database::lastId();Audit::record('Reunión de instancia programada','Comite',$committeeId,['reunion_id'=>$id,'fecha'=>$date],$tenantId,$userId);return$id;
    }

    public static function recordMeeting(int $meetingId,int $committeeId,int $tenantId,array $input,int $userId):void
    {
        $meeting=self::meeting($meetingId,$committeeId,$tenantId)?:throw new DomainException('Reunión no encontrada.');if($meeting['estado']==='Realizada')throw new DomainException('El acta realizada es inmutable; registre una nueva reunión o un soporte complementario.');
        $status=(string)($input['estado']??'Realizada');if(!in_array($status,['Realizada','Cancelada'],true))throw new DomainException('Estado no válido.');$development=trim((string)($input['desarrollo']??''));
        if($status==='Cancelada'){if($development==='')throw new DomainException('Justifique la cancelación en desarrollo.');Database::execute("UPDATE comite_reuniones SET estado='Cancelada',desarrollo=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND comite_id=? AND tenant_id=?",[$development,$meetingId,$committeeId,$tenantId]);return;}
        if(($input['confirmar_asistencia']??'')!=='1')throw new DomainException('Confirme manualmente que la asistencia registrada corresponde al acta.');
        $members=self::members($committeeId,$tenantId);$active=array_values(array_filter($members,static fn($m)=>(int)$m['activo']===1));if($active===[])throw new DomainException('Registre miembros activos antes de calcular asistencia.');
        $ids=$input['asistentes']??[];if(!is_array($ids))throw new DomainException('Asistencia no válida.');$ids=array_unique(array_map('intval',$ids));$valid=array_column($active,null,'id');$attendees=[];
        foreach($ids as $id){if(!isset($valid[$id]))throw new DomainException('Un asistente no pertenece a los miembros activos.');$attendees[]=['id'=>$id,'nombre'=>$valid[$id]['nombre'],'representacion'=>$valid[$id]['representacion'],'condicion'=>$valid[$id]['condicion']];}
        $committee=self::findForTenant($committeeId,$tenantId);$percentage=round(count($attendees)*100/count($active),2);$quorum=$percentage>=(float)$committee['quorum_minimo']?1:0;
        $commitments=trim((string)($input['compromisos']??''));$minutes=trim((string)($input['acta_referencia']??''));$next=self::date($input['proxima_reunion']??null,'próxima reunión');
        if($development===''||$minutes==='')throw new DomainException('Desarrollo y referencia real del acta son obligatorios.');
        Database::execute("UPDATE comite_reuniones SET asistentes_json=?,quorum_porcentaje=?,quorum_cumple=?,desarrollo=?,compromisos=?,acta_referencia=?,proxima_reunion=?,estado='Realizada',updated_at=CURRENT_TIMESTAMP WHERE id=? AND comite_id=? AND tenant_id=?",[json_encode($attendees,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$percentage,$quorum,$development,$commitments,$minutes,$next,$meetingId,$committeeId,$tenantId]);
        Audit::record('Reunión y asistencia registradas','Comite',$committeeId,['reunion_id'=>$meetingId,'asistentes'=>count($attendees),'quorum_porcentaje'=>$percentage],$tenantId,$userId);
    }

    public static function composition(int $committeeId,int $tenantId):array
    {
        $row=Database::first("SELECT SUM(CASE WHEN activo=1 AND condicion='Principal' AND representacion='Empleador' THEN 1 ELSE 0 END) empleador,SUM(CASE WHEN activo=1 AND condicion='Principal' AND representacion='Trabajadores' THEN 1 ELSE 0 END) trabajadores,SUM(CASE WHEN activo=1 THEN 1 ELSE 0 END) activos FROM comite_miembros WHERE comite_id=? AND tenant_id=?",[$committeeId,$tenantId])?:[];return['empleador'=>(int)($row['empleador']??0),'trabajadores'=>(int)($row['trabajadores']??0),'activos'=>(int)($row['activos']??0)];
    }

    private static function date(mixed$value,string$label,bool$required=false):?string{$value=trim((string)($value??''));if($value===''){if($required)throw new DomainException('Fecha de '.$label.' obligatoria.');return null;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('Fecha no válida: '.$label);return$value;}
    private static function number(mixed$value,string$label):float{if(!is_numeric($value)||(float)$value<0||!is_finite((float)$value))throw new DomainException('Número no válido: '.$label);return(float)$value;}
    private static function percentage(mixed$value,string$label,bool$zero=true):float{$number=self::number($value,$label);if($number>100||(!$zero&&$number<=0))throw new DomainException('Porcentaje no válido: '.$label);return$number;}
}
