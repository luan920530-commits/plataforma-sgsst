<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class Salud
{
    public static function types():array{return['Evaluacion ocupacional','Concepto medico-laboral','Restriccion','Recomendacion','Vigilancia','Remision','Salud mental y SPA','Seguimiento'];}
    public static function forTenant(int $tenantId,array$filters=[]):array
    {
        $where=['s.tenant_id=?'];$args=[$tenantId];if(in_array($filters['tipo']??'',self::types(),true)){$where[]='s.tipo=?';$args[]=$filters['tipo'];}
        if(!empty($filters['q'])){$where[]='(e.nombre LIKE ? OR e.documento LIKE ? OR s.concepto LIKE ?)';$q='%'.text_substr((string)$filters['q'],0,100).'%';array_push($args,$q,$q,$q);}
        $rows=Database::query('SELECT s.*,e.nombre empleado_nombre,e.documento empleado_documento,e.cargo empleado_cargo FROM salud_registros s LEFT JOIN empleados e ON e.id=s.empleado_id WHERE '.implode(' AND ',$where).' ORDER BY COALESCE(s.proxima_fecha,s.fecha),s.id DESC',$args);
        return array_map(static function(array$row):array{$row['estado_calculado']=$row['estado']!=='Cerrado'&&$row['proxima_fecha']&&$row['proxima_fecha']<date('Y-m-d')?'Vencido':$row['estado'];return$row;},$rows);
    }
    public static function findForTenant(int$id,int$tenantId):?array{return Database::first('SELECT s.*,e.nombre empleado_nombre,e.documento empleado_documento,e.cargo empleado_cargo,e.area empleado_area FROM salud_registros s LEFT JOIN empleados e ON e.id=s.empleado_id WHERE s.id=? AND s.tenant_id=?',[$id,$tenantId]);}
    public static function create(int$tenantId,array$input,int$userId):int
    {
        $data=self::validate($tenantId,$input);$state=$data['proxima_fecha']?'En seguimiento':'Vigente';
        Database::execute('INSERT INTO salud_registros (tenant_id,empleado_id,tipo,fecha,proxima_fecha,entidad,profesional,concepto,restricciones,recomendaciones,seguimiento,soporte_referencia,estado,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenantId,$data['empleado_id'],$data['tipo'],$data['fecha'],$data['proxima_fecha'],$data['entidad'],$data['profesional'],$data['concepto'],$data['restricciones'],$data['recomendaciones'],$data['seguimiento'],$data['soporte_referencia'],$state,$userId]);$id=Database::lastId();Audit::record('Concepto reservado de salud registrado','Salud',$id,['tipo'=>$data['tipo']],$tenantId,$userId);return$id;
    }
    public static function update(int$id,int$tenantId,array$input,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Registro no encontrado.');if($current['estado']==='Cerrado')throw new DomainException('El seguimiento cerrado es inmutable; cree un nuevo registro de seguimiento.');$input['tipo']=$current['tipo'];$data=self::validate($tenantId,$input);
        Database::execute('UPDATE salud_registros SET empleado_id=?,fecha=?,proxima_fecha=?,entidad=?,profesional=?,concepto=?,restricciones=?,recomendaciones=?,seguimiento=?,soporte_referencia=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$data['empleado_id'],$data['fecha'],$data['proxima_fecha'],$data['entidad'],$data['profesional'],$data['concepto'],$data['restricciones'],$data['recomendaciones'],$data['seguimiento'],$data['soporte_referencia'],$id,$tenantId]);Audit::record('Concepto reservado actualizado','Salud',$id,['tipo'=>$current['tipo']],$tenantId,$userId);
    }
    public static function setStatus(int$id,int$tenantId,string$status,string$followup,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Registro no encontrado.');if($current['estado']==='Cerrado')return;if(!in_array($status,['Vigente','En seguimiento','Cerrado'],true))throw new DomainException('Estado no válido.');$followup=trim($followup);if($status==='Cerrado'&&$followup===''&&trim($current['seguimiento'])==='')throw new DomainException('Documente el seguimiento antes de cerrar.');
        Database::execute('UPDATE salud_registros SET estado=?,seguimiento=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$status,$followup?:$current['seguimiento'],$id,$tenantId]);Audit::record('Estado de seguimiento de salud actualizado','Salud',$id,['estado'=>$status],$tenantId,$userId);
    }
    public static function stats(int$tenantId):array
    {
        $row=Database::first("SELECT COUNT(*) total,SUM(CASE WHEN estado='En seguimiento' THEN 1 ELSE 0 END) seguimiento,SUM(CASE WHEN estado='Cerrado' THEN 1 ELSE 0 END) cerrados,SUM(CASE WHEN estado<>'Cerrado' AND proxima_fecha IS NOT NULL AND proxima_fecha<date('now') THEN 1 ELSE 0 END) vencidos FROM salud_registros WHERE tenant_id=?",[$tenantId])?:[];return['total'=>(int)($row['total']??0),'seguimiento'=>(int)($row['seguimiento']??0),'cerrados'=>(int)($row['cerrados']??0),'vencidos'=>(int)($row['vencidos']??0)];
    }
    public static function aggregate(int$tenantId):array{return Database::query('SELECT tipo,estado,COUNT(*) cantidad FROM salud_registros WHERE tenant_id=? GROUP BY tipo,estado ORDER BY tipo,estado',[$tenantId]);}
    private static function validate(int$tenantId,array$input):array
    {
        $type=(string)($input['tipo']??'');if(!in_array($type,self::types(),true))throw new DomainException('Tipo de registro de salud no válido.');$employeeId=(int)($input['empleado_id']??0);if(!Empleado::findForTenant($employeeId,$tenantId))throw new DomainException('Seleccione una persona del directorio de esta empresa.');
        $data=['empleado_id'=>$employeeId,'tipo'=>$type,'fecha'=>self::date($input['fecha']??'','fecha',true),'proxima_fecha'=>self::date($input['proxima_fecha']??null,'próxima fecha'),'entidad'=>trim((string)($input['entidad']??'')),'profesional'=>trim((string)($input['profesional']??'')),'concepto'=>trim((string)($input['concepto']??'')),'restricciones'=>trim((string)($input['restricciones']??'')),'recomendaciones'=>trim((string)($input['recomendaciones']??'')),'seguimiento'=>trim((string)($input['seguimiento']??'')),'soporte_referencia'=>trim((string)($input['soporte_referencia']??''))];
        if(in_array($type,['Evaluacion ocupacional','Concepto medico-laboral','Remision'],true)&&($data['entidad']===''||$data['profesional']===''))throw new DomainException('Entidad y profesional son obligatorios para este concepto.');
        if($data['concepto']===''&&$data['restricciones']===''&&$data['recomendaciones']===''&&$data['seguimiento']==='')throw new DomainException('Registre un concepto, restricción, recomendación o seguimiento.');
        foreach(['concepto','restricciones','recomendaciones']as$field)if(text_length($data[$field])>4000)throw new DomainException('El campo '.$field.' supera el espacio permitido; no pegue historias clínicas completas.');if(text_length($data['seguimiento'])>6000)throw new DomainException('El seguimiento supera 6.000 caracteres.');return$data;
    }
    private static function date(mixed$value,string$label,bool$required=false):?string{$value=trim((string)($value??''));if($value===''){if($required)throw new DomainException('La '.$label.' es obligatoria.');return null;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('Fecha no válida: '.$label);return$value;}
}
