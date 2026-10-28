<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Audit;
use DomainException;

final class Convivencia
{
    public static function cases(int$tenantId,array$filters=[]):array
    {
        $where=['c.tenant_id=?'];$args=[$tenantId];if(in_array($filters['estado']??'',['Recibido','En analisis','Medidas activas','Encaminado','Cerrado'],true)){$where[]='c.estado=?';$args[]=$filters['estado'];}if(!empty($filters['q'])){$where[]='(c.codigo LIKE ? OR c.canal LIKE ?)';$q='%'.text_substr((string)$filters['q'],0,80).'%';array_push($args,$q,$q);}
        return Database::query('SELECT c.*,u.nombre responsable_nombre FROM convivencia_casos c LEFT JOIN users u ON u.id=c.responsable_id WHERE '.implode(' AND ',$where).' ORDER BY c.fecha_recepcion DESC,c.id DESC',$args);
    }
    public static function case(int$id,int$tenantId):?array{return Database::first('SELECT c.*,u.nombre responsable_nombre FROM convivencia_casos c LEFT JOIN users u ON u.id=c.responsable_id WHERE c.id=? AND c.tenant_id=?',[$id,$tenantId]);}
    public static function createCase(int$tenantId,array$input,int$userId):int
    {
        $type=(string)($input['tipo']??'');if(!in_array($type,['Acoso laboral','Acoso sexual','Violencia','Discriminacion','Otra'],true))throw new DomainException('Tipo de caso no válido.');$channel=trim((string)($input['canal']??''));$date=self::date($input['fecha_recepcion']??'','recepción',true);$description=trim((string)($input['descripcion']??''));$people=trim((string)($input['personas_involucradas']??''));if($channel===''||$description==='')throw new DomainException('Canal, fecha y descripción son obligatorios.');$responsible=self::responsible($tenantId,(int)($input['responsable_id']??0));
        $code='CCL-'.date('Y',strtotime($date)).'-'.strtoupper(bin2hex(random_bytes(4)));Database::execute('INSERT INTO convivencia_casos (tenant_id,codigo,reportante_user_id,canal,tipo,fecha_recepcion,descripcion,personas_involucradas,medidas_proteccion,encaminamiento,responsable_id,fecha_compromiso,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenantId,$code,null,$channel,$type,$date,$description,$people,trim((string)($input['medidas_proteccion']??'')),trim((string)($input['encaminamiento']??'')),$responsible,self::date($input['fecha_compromiso']??null,'compromiso'),$userId]);$id=Database::lastId();Audit::record('Caso reservado de convivencia recibido','Convivencia',$id,['tipo'=>$type],$tenantId,$userId);return$id;
    }
    public static function updateCase(int$id,int$tenantId,array$input,int$userId):void
    {
        $current=self::case($id,$tenantId)?:throw new DomainException('Caso no encontrado.');if($current['estado']==='Cerrado')throw new DomainException('El caso cerrado es inmutable.');$status=(string)($input['estado']??$current['estado']);if(!in_array($status,['Recibido','En analisis','Medidas activas','Encaminado','Cerrado'],true))throw new DomainException('Estado no válido.');$measures=trim((string)($input['medidas_proteccion']??''));$referral=trim((string)($input['encaminamiento']??''));$reason=trim((string)($input['cierre_motivo']??''));$responsible=self::responsible($tenantId,(int)($input['responsable_id']??0));
        if($status==='Medidas activas'&&$measures==='')throw new DomainException('Documente las medidas de protección.');if($status==='Encaminado'&&$referral==='')throw new DomainException('Documente el encaminamiento real.');if($status==='Cerrado'&&($reason===''||($measures===''&&$referral==='')))throw new DomainException('El cierre requiere motivo y medidas o encaminamiento documentados.');
        Database::execute('UPDATE convivencia_casos SET medidas_proteccion=?,encaminamiento=?,responsable_id=?,fecha_compromiso=?,estado=?,cierre_motivo=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$measures,$referral,$responsible,self::date($input['fecha_compromiso']??null,'compromiso'),$status,$reason,$id,$tenantId]);Audit::record('Caso reservado de convivencia actualizado','Convivencia',$id,['estado'=>$status],$tenantId,$userId);
    }
    public static function meetings(int$tenantId):array{return Database::query('SELECT * FROM convivencia_reuniones WHERE tenant_id=? ORDER BY fecha DESC,id DESC',[$tenantId]);}
    public static function meeting(int$id,int$tenantId):?array{return Database::first('SELECT * FROM convivencia_reuniones WHERE id=? AND tenant_id=?',[$id,$tenantId]);}
    public static function scheduleMeeting(int$tenantId,array$input,int$userId):int
    {
        $type=(string)($input['tipo']??'');if(!in_array($type,['Ordinaria mensual','Extraordinaria'],true))throw new DomainException('Tipo de reunión no válido.');$date=self::date($input['fecha']??'','reunión',true);$agenda=trim((string)($input['agenda']??''));if($agenda==='')throw new DomainException('La agenda es obligatoria.');Database::execute('INSERT INTO convivencia_reuniones (tenant_id,tipo,fecha,participantes,agenda,proxima_fecha,created_by) VALUES (?,?,?,?,?,?,?)',[$tenantId,$type,$date,trim((string)($input['participantes']??'')),$agenda,self::date($input['proxima_fecha']??null,'próxima fecha'),$userId]);$id=Database::lastId();Audit::record('Reunión CCL programada','ConvivenciaReunion',$id,['fecha'=>$date],$tenantId,$userId);return$id;
    }
    public static function recordMeeting(int$id,int$tenantId,array$input,int$userId):void
    {
        $current=self::meeting($id,$tenantId)?:throw new DomainException('Reunión no encontrada.');if($current['estado']==='Realizada')throw new DomainException('La reunión realizada es inmutable.');$status=(string)($input['estado']??'Realizada');$commitments=trim((string)($input['compromisos']??''));if($status==='Cancelada'){if($commitments==='')throw new DomainException('Justifique la cancelación en compromisos/observaciones.');Database::execute("UPDATE convivencia_reuniones SET estado='Cancelada',compromisos=? WHERE id=? AND tenant_id=?",[$commitments,$id,$tenantId]);return;}
        $participants=trim((string)($input['participantes']??''));$minutes=trim((string)($input['acta_referencia']??''));if($participants===''||$commitments===''||$minutes==='')throw new DomainException('Participantes, compromisos/observaciones y referencia del acta son obligatorios.');if(($input['quorum_verificado']??'')!=='1')throw new DomainException('Confirme manualmente la verificación de asistencia y quórum.');
        Database::execute("UPDATE convivencia_reuniones SET participantes=?,quorum_verificado=1,compromisos=?,acta_referencia=?,proxima_fecha=?,estado='Realizada' WHERE id=? AND tenant_id=?",[$participants,$commitments,$minutes,self::date($input['proxima_fecha']??null,'próxima fecha'),$id,$tenantId]);Audit::record('Reunión CCL realizada','ConvivenciaReunion',$id,[],$tenantId,$userId);
    }
    public static function aggregate(int$tenantId):array{return Database::query('SELECT tipo,estado,COUNT(*) cantidad FROM convivencia_casos WHERE tenant_id=? GROUP BY tipo,estado ORDER BY tipo,estado',[$tenantId]);}
    private static function responsible(int$tenantId,int$userId):?int{if($userId===0)return null;$user=User::findForTenant($userId,$tenantId);if(!$user||!$user['activo']||!in_array($user['rol'],['AdminEmpresa','ResponsableSST'],true)||!Permission::has($userId,$tenantId,Permission::COEXISTENCE))throw new DomainException('El responsable debe tener acceso reservado de convivencia vigente.');return$userId;}
    private static function date(mixed$value,string$label,bool$required=false):?string{$value=trim((string)($value??''));if($value===''){if($required)throw new DomainException('Fecha de '.$label.' obligatoria.');return null;}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$d||$d->format('Y-m-d')!==$value)throw new DomainException('Fecha no válida: '.$label);return$value;}
}
