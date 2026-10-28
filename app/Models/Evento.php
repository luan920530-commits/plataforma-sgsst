<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;
use PDO;

final class Evento
{
    public static function types(): array { return ['Incidente','Accidente de trabajo','Enfermedad laboral']; }
    public static function statuses(): array { return ['Reportado','En investigacion','Investigado','Cerrado']; }

    public static function forTenant(int $tenantId, array $filters = [], bool $includeHealth = false): array
    {
        $where=['e.tenant_id = ?']; $args=[$tenantId];
        if (!$includeHealth) $where[]="e.tipo <> 'Enfermedad laboral'";
        if (in_array($filters['tipo']??'',self::types(),true)) { $where[]='e.tipo = ?'; $args[]=$filters['tipo']; }
        if (in_array($filters['estado']??'',self::statuses(),true)) { $where[]='e.estado = ?'; $args[]=$filters['estado']; }
        if (!empty($filters['anio']) && preg_match('/^\d{4}$/',(string)$filters['anio'])) { $where[]="strftime('%Y',e.fecha_evento) = ?"; $args[]=(string)$filters['anio']; }
        if (!empty($filters['q'])) { $where[]='(e.codigo LIKE ? OR e.lugar LIKE ? OR e.proceso LIKE ?)'; $q='%'.text_substr((string)$filters['q'],0,100).'%'; array_push($args,$q,$q,$q); }
        return Database::query('SELECT e.*, (SELECT COUNT(*) FROM evento_personas p WHERE p.evento_id=e.id) personas, (SELECT COUNT(*) FROM evento_acciones a WHERE a.evento_id=e.id) acciones, (SELECT COUNT(*) FROM evento_acciones a WHERE a.evento_id=e.id AND a.estado IN (\'Pendiente\',\'En curso\',\'No eficaz\')) acciones_abiertas FROM eventos_sst e WHERE '.implode(' AND ',$where).' ORDER BY e.fecha_evento DESC,e.id DESC',$args);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM eventos_sst WHERE id = ? AND tenant_id = ?',[$id,$tenantId]);
    }

    public static function create(int $tenantId, array $input, int $userId): int
    {
        $data=self::validateEvent($input,true);
        return Database::transaction(static function()use($tenantId,$data,$input,$userId):int{
            $prefix=['Incidente'=>'INC','Accidente de trabajo'=>'AT','Enfermedad laboral'=>'EL'][$data['tipo']];
            $code=$prefix.'-'.date('Y',strtotime($data['fecha_evento'])).'-'.strtoupper(bin2hex(random_bytes(4)));
            Database::execute('INSERT INTO eventos_sst (tenant_id,codigo,tipo,fecha_evento,fecha_reporte,lugar,proceso,descripcion,lesion,gravedad,dias_perdidos,dias_cargados,reportado_arl_at,reportado_eps_at,reportado_mintrabajo_at,radicados,fecha_limite_investigacion,responsable,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenantId,$code,$data['tipo'],$data['fecha_evento'],$data['fecha_reporte'],$data['lugar'],$data['proceso'],$data['descripcion'],$data['lesion'],$data['gravedad'],$data['dias_perdidos'],$data['dias_cargados'],$data['reportado_arl_at'],$data['reportado_eps_at'],$data['reportado_mintrabajo_at'],$data['radicados'],$data['fecha_limite_investigacion'],$data['responsable'],$userId]);
            $id=Database::lastId();
            if ($data['tipo']!=='Incidente') self::addPerson($id,$tenantId,['empleado_id'=>$input['afectado_empleado_id']??0,'nombre'=>$input['afectado_nombre']??'','documento'=>$input['afectado_documento']??'','vinculacion'=>$input['afectado_vinculacion']??'','rol'=>'Persona afectada','declaracion'=>''],$userId,false);
            Audit::record('Evento SST reportado','Evento',$id,['tipo'=>$data['tipo'],'codigo'=>$code],$tenantId,$userId); return $id;
        });
    }

    public static function update(int $id,int $tenantId,array $input,int $userId): void
    {
        $current=self::findForTenant($id,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if ($current['estado']==='Cerrado') throw new DomainException('El evento cerrado es inmutable; registre un seguimiento independiente.');
        $input['tipo']=$current['tipo']; $data=self::validateEvent($input,false);
        Database::execute('UPDATE eventos_sst SET fecha_evento=?,fecha_reporte=?,lugar=?,proceso=?,descripcion=?,lesion=?,gravedad=?,dias_perdidos=?,dias_cargados=?,reportado_arl_at=?,reportado_eps_at=?,reportado_mintrabajo_at=?,radicados=?,fecha_limite_investigacion=?,responsable=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$data['fecha_evento'],$data['fecha_reporte'],$data['lugar'],$data['proceso'],$data['descripcion'],$data['lesion'],$data['gravedad'],$data['dias_perdidos'],$data['dias_cargados'],$data['reportado_arl_at'],$data['reportado_eps_at'],$data['reportado_mintrabajo_at'],$data['radicados'],$data['fecha_limite_investigacion'],$data['responsable'],$id,$tenantId]);
        Audit::record('Evento SST actualizado','Evento',$id,['tipo'=>$current['tipo']],$tenantId,$userId);
    }

    public static function people(int $eventId,int $tenantId): array
    {
        return Database::query('SELECT * FROM evento_personas WHERE evento_id = ? AND tenant_id = ? ORDER BY CASE rol WHEN \'Persona afectada\' THEN 1 WHEN \'Involucrado\' THEN 2 ELSE 3 END,id',[$eventId,$tenantId]);
    }

    public static function addPerson(int $eventId,int $tenantId,array $input,int $userId,bool $audit=true): int
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if (in_array($event['estado'],['Investigado','Cerrado'],true)) throw new DomainException('No pueden añadirse declaraciones después de cerrar la investigación.');
        $role=(string)($input['rol']??''); if (!in_array($role,['Persona afectada','Involucrado','Testigo'],true)) throw new DomainException('Rol de persona no válido.');
        $employeeId=(int)($input['empleado_id']??0); $employee=null;
        if ($employeeId>0) { $employee=Empleado::findForTenant($employeeId,$tenantId) ?: throw new DomainException('La persona no pertenece a esta empresa.'); }
        $name=trim((string)($employee['nombre']??$input['nombre']??'')); if ($name==='') throw new DomainException('Registre el nombre de la persona.');
        $document=trim((string)($employee['documento']??$input['documento']??'')); $link=trim((string)($employee['tipo_vinculacion']??$input['vinculacion']??''));
        Database::execute('INSERT INTO evento_personas (tenant_id,evento_id,empleado_id,nombre,documento,vinculacion,rol,declaracion) VALUES (?,?,?,?,?,?,?,?)',[$tenantId,$eventId,$employeeId?:null,text_substr($name,0,240),text_substr($document,0,80),text_substr($link,0,120),$role,trim((string)($input['declaracion']??''))]);
        $id=Database::lastId(); if($audit)Audit::record('Persona vinculada a evento','Evento',$eventId,['rol'=>$role,'persona_id'=>$id],$tenantId,$userId); return $id;
    }

    public static function deletePerson(int $personId,int $eventId,int $tenantId,int $userId): void
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if ($event['estado']!=='Reportado') throw new DomainException('Las declaraciones quedan congeladas al iniciar la investigación.');
        $person=Database::first('SELECT * FROM evento_personas WHERE id=? AND evento_id=? AND tenant_id=?',[$personId,$eventId,$tenantId]) ?: throw new DomainException('Persona no encontrada.');
        if ($person['rol']==='Persona afectada' && $event['tipo']!=='Incidente') throw new DomainException('Conserve a la persona afectada; corrija sus datos desde el directorio o contacte al administrador.');
        Database::execute('DELETE FROM evento_personas WHERE id=? AND evento_id=? AND tenant_id=?',[$personId,$eventId,$tenantId]);
        Audit::record('Vínculo de persona retirado antes de investigar','Evento',$eventId,['persona_id'=>$personId],$tenantId,$userId);
    }

    public static function investigation(int $eventId,int $tenantId): ?array
    {
        return Database::first('SELECT i.*,u.nombre elaborado_por_nombre FROM evento_investigaciones i LEFT JOIN users u ON u.id=i.elaborado_por WHERE i.evento_id=? AND i.tenant_id=?',[$eventId,$tenantId]);
    }

    public static function saveInvestigation(int $eventId,int $tenantId,array $input,int $userId): void
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if (in_array($event['estado'],['Investigado','Cerrado'],true)) throw new DomainException('La investigación cerrada no puede sobrescribirse.');
        $team=trim((string)($input['equipo']??'')); $start=self::date($input['fecha_inicio']??'','inicio de investigación',true);
        if ($team==='') throw new DomainException('Registre el equipo investigador.');
        $method=trim((string)($input['metodologia']??'Resolucion 1401 de 2007'));
        $fields=[]; foreach(['causas_inmediatas','causas_basicas','causas_administrativas','analisis','conclusiones'] as $key)$fields[$key]=trim((string)($input[$key]??''));
        $existing=self::investigation($eventId,$tenantId);
        if($existing){Database::execute('UPDATE evento_investigaciones SET metodologia=?,equipo=?,fecha_inicio=?,causas_inmediatas=?,causas_basicas=?,causas_administrativas=?,analisis=?,conclusiones=?,elaborado_por=?,updated_at=CURRENT_TIMESTAMP WHERE evento_id=? AND tenant_id=?',array_merge([$method,$team,$start],array_values($fields),[$userId,$eventId,$tenantId]));}
        else{Database::execute('INSERT INTO evento_investigaciones (tenant_id,evento_id,metodologia,equipo,fecha_inicio,causas_inmediatas,causas_basicas,causas_administrativas,analisis,conclusiones,elaborado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?)',array_merge([$tenantId,$eventId,$method,$team,$start],array_values($fields),[$userId]));}
        if($event['estado']==='Reportado')Database::execute("UPDATE eventos_sst SET estado='En investigacion',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$eventId,$tenantId]);
        Audit::record('Borrador de investigación guardado','Evento',$eventId,['metodologia'=>$method],$tenantId,$userId);
    }

    public static function closeInvestigation(int $eventId,int $tenantId,int $userId): void
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        $investigation=self::investigation($eventId,$tenantId) ?: throw new DomainException('Primero registre la investigación.');
        if($event['estado']==='Cerrado')throw new DomainException('El evento ya está cerrado.');
        if($event['estado']==='Investigado')return;
        foreach(['causas_inmediatas','causas_basicas','causas_administrativas','analisis','conclusiones'] as $field)if(trim((string)$investigation[$field])==='')throw new DomainException('Complete causas, análisis y conclusiones antes de cerrar la investigación.');
        Database::transaction(static function()use($eventId,$tenantId,$userId):void{Database::execute('UPDATE evento_investigaciones SET fecha_cierre=?,elaborado_por=?,updated_at=CURRENT_TIMESTAMP WHERE evento_id=? AND tenant_id=?',[date('Y-m-d'),$userId,$eventId,$tenantId]);Database::execute("UPDATE eventos_sst SET estado='Investigado',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$eventId,$tenantId]);});
        Audit::record('Investigación de evento cerrada','Evento',$eventId,[],$tenantId,$userId);
    }

    public static function actions(int $eventId,int $tenantId): array
    {
        return Database::query('SELECT a.*,u.nombre verificado_por_nombre,(SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=a.tenant_id AND s.entidad_tipo=\'evento_accion\' AND s.entidad_id=CAST(a.id AS TEXT) AND s.estado_revision=\'Revisado\') evidencias_revisadas FROM evento_acciones a LEFT JOIN users u ON u.id=a.verificado_por WHERE a.evento_id=? AND a.tenant_id=? ORDER BY a.fecha_compromiso,a.id',[$eventId,$tenantId]);
    }

    public static function addAction(int $eventId,int $tenantId,array $input,int $userId): int
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if(!in_array($event['estado'],['En investigacion','Investigado'],true))throw new DomainException('Inicie la investigación antes de derivar acciones.');
        $type=(string)($input['tipo']??''); if(!in_array($type,['Preventiva','Correctiva','Mejora'],true))throw new DomainException('Tipo de acción no válido.');
        $description=trim((string)($input['descripcion']??''));$responsible=trim((string)($input['responsable']??''));$resources=trim((string)($input['recursos']??''));$due=self::date($input['fecha_compromiso']??'','fecha compromiso',true);
        if($description===''||$responsible===''||$resources==='')throw new DomainException('Acción, responsable, recursos y fecha son obligatorios.');
        Database::execute('INSERT INTO evento_acciones (tenant_id,evento_id,tipo,descripcion,responsable,recursos,fecha_compromiso) VALUES (?,?,?,?,?,?,?)',[$tenantId,$eventId,$type,$description,$responsible,$resources,$due]);$id=Database::lastId();Audit::record('Acción de evento programada','Evento',$eventId,['accion_id'=>$id,'tipo'=>$type],$tenantId,$userId);return $id;
    }

    public static function setActionStatus(int $actionId,int $eventId,int $tenantId,string $status,array $input,int $userId): void
    {
        $action=Database::first('SELECT a.*,e.estado evento_estado,(SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=a.tenant_id AND s.entidad_tipo=\'evento_accion\' AND s.entidad_id=CAST(a.id AS TEXT) AND s.estado_revision=\'Revisado\') evidencias_revisadas FROM evento_acciones a JOIN eventos_sst e ON e.id=a.evento_id WHERE a.id=? AND a.evento_id=? AND a.tenant_id=?',[$actionId,$eventId,$tenantId]) ?: throw new DomainException('Acción no encontrada.');
        if($action['evento_estado']==='Cerrado')throw new DomainException('El evento cerrado es inmutable.');
        if(!in_array($status,['Pendiente','En curso','Implementada','Eficaz','No eficaz','Cancelada'],true))throw new DomainException('Estado de acción no válido.');
        if(in_array($action['estado'],['Eficaz'],true)&&$status!=='Eficaz')throw new DomainException('Una acción eficaz no puede retroceder.');
        $execution=$action['fecha_ejecucion'];$result=trim((string)($input['eficacia_resultado']??$action['eficacia_resultado']));$efficacyDate=$action['eficacia_fecha'];
        if($status==='Implementada'){$execution=self::date($input['fecha_ejecucion']??'','fecha real de ejecución',true);if(!in_array($action['estado'],['Pendiente','En curso','Implementada','No eficaz'],true))throw new DomainException('Transición de estado no permitida.');}
        if(in_array($status,['Eficaz','No eficaz'],true)){
            if(!in_array($action['estado'],['Implementada','Eficaz','No eficaz'],true))throw new DomainException('Primero registre la implementación de la acción.');
            if(!Permission::has($userId,$tenantId,Permission::MATRIX_VERIFY))throw new DomainException('La evaluación de eficacia requiere permiso de verificación.');
            $efficacyDate=self::date($input['eficacia_fecha']??'','fecha de eficacia',true);if($result==='')throw new DomainException('Registre el resultado medido de eficacia.');
            if((int)$action['evidencias_revisadas']<1)throw new DomainException('La eficacia exige una evidencia revisada vinculada a esta acción.');
        }
        if($status==='Cancelada'){if(!in_array($action['estado'],['Pendiente','En curso'],true)||$result==='')throw new DomainException('Solo puede cancelarse una acción no implementada y debe justificarse en el resultado.');}
        Database::execute('UPDATE evento_acciones SET estado=?,fecha_ejecucion=?,eficacia_resultado=?,eficacia_fecha=?,verificado_por=?,verificado_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND evento_id=? AND tenant_id=?',[$status,$execution,$result,$efficacyDate,in_array($status,['Eficaz','No eficaz'],true)?$userId:null,in_array($status,['Eficaz','No eficaz'],true)?date('Y-m-d H:i:s'):null,$actionId,$eventId,$tenantId]);
        Audit::record('Estado de acción de evento actualizado','Evento',$eventId,['accion_id'=>$actionId,'estado'=>$status],$tenantId,$userId);
    }

    public static function close(int $eventId,int $tenantId,int $userId): void
    {
        $event=self::findForTenant($eventId,$tenantId) ?: throw new DomainException('Evento no encontrado.');
        if($event['estado']==='Cerrado')return;if($event['estado']!=='Investigado')throw new DomainException('Primero cierre la investigación.');
        $actions=self::actions($eventId,$tenantId);if($actions===[])throw new DomainException('Registre al menos una acción derivada y evalúe su eficacia antes de cerrar.');
        $effective=0;foreach($actions as $action){if($action['estado']==='Eficaz'){$effective++;continue;}if($action['estado']!=='Cancelada')throw new DomainException('Todas las acciones deben tener eficacia verificada o una cancelación justificada; las no eficaces requieren una nueva acción.');}
        if($effective===0)throw new DomainException('Debe existir al menos una acción implementada con eficacia verificada.');
        Database::execute("UPDATE eventos_sst SET estado='Cerrado',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$eventId,$tenantId]);Audit::record('Evento SST cerrado','Evento',$eventId,[],$tenantId,$userId);
    }

    public static function stats(int $tenantId,?int $year=null): array
    {
        $where='tenant_id = ?';$args=[$tenantId];if($year){$where.=" AND strftime('%Y',fecha_evento)=?";$args[]=(string)$year;}
        $row=Database::first("SELECT COUNT(*) total,SUM(CASE WHEN tipo='Incidente' THEN 1 ELSE 0 END) incidentes,SUM(CASE WHEN tipo='Accidente de trabajo' THEN 1 ELSE 0 END) accidentes,SUM(CASE WHEN tipo='Enfermedad laboral' THEN 1 ELSE 0 END) enfermedades,COALESCE(SUM(CASE WHEN tipo='Accidente de trabajo' THEN dias_perdidos ELSE 0 END),0) dias_perdidos,COALESCE(SUM(CASE WHEN tipo='Accidente de trabajo' THEN dias_cargados ELSE 0 END),0) dias_cargados,SUM(CASE WHEN estado IN ('Reportado','En investigacion') THEN 1 ELSE 0 END) por_investigar FROM eventos_sst WHERE $where",$args)?:[];
        return array_map(static fn($v)=>(int)$v,$row);
    }

    private static function validateEvent(array $input,bool $creating): array
    {
        $type=(string)($input['tipo']??'');if(!in_array($type,self::types(),true))throw new DomainException('Tipo de evento no válido.');
        $data=['tipo'=>$type,'fecha_evento'=>self::date($input['fecha_evento']??'','fecha del evento',true),'fecha_reporte'=>self::date($input['fecha_reporte']??'','fecha del reporte',true),'lugar'=>trim((string)($input['lugar']??'')),'proceso'=>trim((string)($input['proceso']??'')),'descripcion'=>trim((string)($input['descripcion']??'')),'lesion'=>trim((string)($input['lesion']??'')),'gravedad'=>(string)($input['gravedad']??'Sin lesion'),'dias_perdidos'=>self::nonnegative($input['dias_perdidos']??0,'días perdidos'),'dias_cargados'=>self::nonnegative($input['dias_cargados']??0,'días cargados'),'reportado_arl_at'=>self::date($input['reportado_arl_at']??null,'reporte ARL'),'reportado_eps_at'=>self::date($input['reportado_eps_at']??null,'reporte EPS'),'reportado_mintrabajo_at'=>self::date($input['reportado_mintrabajo_at']??null,'reporte MinTrabajo'),'radicados'=>trim((string)($input['radicados']??'')),'fecha_limite_investigacion'=>self::date($input['fecha_limite_investigacion']??null,'límite de investigación'),'responsable'=>trim((string)($input['responsable']??''))];
        if($data['lugar']===''||$data['proceso']===''||$data['descripcion']===''||$data['responsable']==='')throw new DomainException('Lugar, proceso, descripción y responsable son obligatorios.');
        if(!in_array($data['gravedad'],['Sin lesion','Leve','Grave','Mortal'],true))throw new DomainException('Gravedad no válida.');
        if($type==='Incidente'&&($data['gravedad']!=='Sin lesion'||$data['dias_perdidos']>0))throw new DomainException('Un incidente sin lesión no puede registrar gravedad o días perdidos por lesión.');
        if($type!=='Incidente'&&$data['lesion']==='')throw new DomainException('Registre la lesión o condición diagnosticada; no incluya historias clínicas completas.');
        if($type!=='Incidente'&&empty($data['fecha_limite_investigacion']))throw new DomainException('Programe la fecha límite de investigación.');
        if($creating&&$type!=='Incidente'&&(int)($input['afectado_empleado_id']??0)===0&&trim((string)($input['afectado_nombre']??''))==='')throw new DomainException('Identifique a la persona afectada.');
        return $data;
    }

    private static function date(mixed $value,string $label,bool $required=false):?string{$value=trim((string)($value??''));if($value===''){if($required)throw new DomainException('La '.$label.' es obligatoria.');return null;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('Fecha no válida: '.$label);return $value;}
    private static function nonnegative(mixed $value,string $label):int{if(!is_numeric($value)||(int)$value<0||(float)$value!==(float)(int)$value)throw new DomainException('Valor no válido en '.$label);return(int)$value;}
}
