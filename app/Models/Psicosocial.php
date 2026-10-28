<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class Psicosocial
{
    public static function forTenant(int$tenantId):array{return Database::query('SELECT * FROM psicosocial_evaluaciones WHERE tenant_id=? ORDER BY vigencia DESC,id DESC',[$tenantId]);}
    public static function findForTenant(int$id,int$tenantId):?array{return Database::first('SELECT * FROM psicosocial_evaluaciones WHERE id=? AND tenant_id=?',[$id,$tenantId]);}
    public static function create(int$tenantId,array$input,int$userId):int
    {
        $year=(int)($input['vigencia']??0);if($year<2022||$year>2200)throw new DomainException('Vigencia no válida.');$population=trim((string)($input['poblacion']??''));$instrument=trim((string)($input['instrumento']??'Bateria Resolucion 2764 de 2022 · estructura sin alteraciones'));$date=self::date($input['fecha_aplicacion']??'','aplicación programada',true);$professional=trim((string)($input['profesional']??''));
        if($population===''||$instrument===''||$professional==='')throw new DomainException('Población, instrumento y profesional son obligatorios.');
        Database::execute('INSERT INTO psicosocial_evaluaciones (tenant_id,vigencia,poblacion,instrumento,fecha_aplicacion,participantes,profesional,plan_intervencion,proxima_fecha,created_by) VALUES (?,?,?,?,?,0,?,?,?,?)',[$tenantId,$year,$population,$instrument,$date,$professional,trim((string)($input['plan_intervencion']??'')),self::date($input['proxima_fecha']??null,'próxima fecha'),$userId]);$id=Database::lastId();Audit::record('Evaluación psicosocial planificada','Psicosocial',$id,['vigencia'=>$year],$tenantId,$userId);return$id;
    }
    public static function recordApplication(int$id,int$tenantId,array$input,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Evaluación no encontrada.');if(!in_array($current['estado'],['Planificada','Aplicada'],true))throw new DomainException('La aplicación ya avanzó a intervención; conserve sus resultados originales.');$participants=self::nonnegative($input['participantes']??-1);if($participants<1)throw new DomainException('Registre el número real de participantes; no se permiten resultados precargados.');
        $results=[];foreach(['intralaboral'=>'Intralaboral','extralaboral'=>'Extralaboral','estres'=>'Estrés']as$key=>$label){$value=trim((string)($input['resultado_'.$key]??''));if($value!=='')$results[]=['dimension'=>$label,'resultado_agregado'=>$value];}if($results===[])throw new DomainException('Registre al menos un resultado agregado; no ingrese respuestas individuales.');$interpretation=trim((string)($input['interpretacion_agregada']??''));if($interpretation==='')throw new DomainException('Registre la interpretación agregada del profesional.');
        Database::execute("UPDATE psicosocial_evaluaciones SET participantes=?,resultados_agregados_json=?,interpretacion_agregada=?,plan_intervencion=?,estado='Aplicada',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$participants,json_encode($results,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$interpretation,trim((string)($input['plan_intervencion']??$current['plan_intervencion'])),$id,$tenantId]);Audit::record('Resultados psicosociales agregados registrados','Psicosocial',$id,['participantes'=>$participants,'dimensiones'=>count($results)],$tenantId,$userId);
    }
    public static function saveIntervention(int$id,int$tenantId,string$plan,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Evaluación no encontrada.');if(!in_array($current['estado'],['Aplicada','Intervencion'],true))throw new DomainException('Primero registre la aplicación y sus resultados agregados.');$plan=trim($plan);if($plan==='')throw new DomainException('El plan de intervención es obligatorio.');Database::execute("UPDATE psicosocial_evaluaciones SET plan_intervencion=?,estado='Intervencion',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$plan,$id,$tenantId]);Audit::record('Intervención psicosocial actualizada','Psicosocial',$id,[],$tenantId,$userId);
    }
    public static function evaluate(int$id,int$tenantId,string$efficacy,string$next,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Evaluación no encontrada.');if(!in_array($current['estado'],['Intervencion','Evaluada'],true))throw new DomainException('La eficacia se evalúa después de la intervención.');if(!Permission::has($userId,$tenantId,Permission::MATRIX_VERIFY))throw new DomainException('La evaluación de eficacia requiere permiso de verificación.');$efficacy=trim($efficacy);$next=self::date($next,'próxima evaluación',true);if($efficacy==='')throw new DomainException('Registre el resultado agregado de eficacia.');if(SoporteReservado::reviewedCount($tenantId,'salud','psicosocial',$id)<1)throw new DomainException('Adjunte y revise un soporte reservado de la evaluación de eficacia.');
        Database::execute("UPDATE psicosocial_evaluaciones SET eficacia=?,proxima_fecha=?,estado='Evaluada',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$efficacy,$next,$id,$tenantId]);Audit::record('Eficacia psicosocial agregada evaluada','Psicosocial',$id,[],$tenantId,$userId);
    }
    public static function close(int$id,int$tenantId,int$userId):void
    {
        $current=self::findForTenant($id,$tenantId)?:throw new DomainException('Evaluación no encontrada.');if($current['estado']!=='Evaluada'&&$current['estado']!=='Cerrada')throw new DomainException('Evalúe la eficacia antes del cierre.');Database::execute("UPDATE psicosocial_evaluaciones SET estado='Cerrada',updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?",[$id,$tenantId]);Audit::record('Ciclo psicosocial cerrado','Psicosocial',$id,[],$tenantId,$userId);
    }
    public static function aggregate(int$tenantId):array{return Database::query('SELECT vigencia,estado,COUNT(*) cantidad,SUM(participantes) participantes FROM psicosocial_evaluaciones WHERE tenant_id=? GROUP BY vigencia,estado ORDER BY vigencia DESC,estado',[$tenantId]);}
    private static function nonnegative(mixed$value):int{if(!is_numeric($value)||(int)$value<0||(float)$value!==(float)(int)$value)throw new DomainException('Número de participantes no válido.');return(int)$value;}
    private static function date(mixed$value,string$label,bool$required=false):?string{$value=trim((string)($value??''));if($value===''){if($required)throw new DomainException('Fecha de '.$label.' obligatoria.');return null;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('Fecha no válida: '.$label);return$value;}
}
