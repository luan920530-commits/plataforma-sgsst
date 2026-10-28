<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\EvidenceStorage;
use App\Support\Audit;
use DomainException;
use Throwable;

final class SoporteReservado
{
    private const ENTITIES=['salud_registro'=>['salud','salud_registros'],'psicosocial'=>['salud','psicosocial_evaluaciones'],'convivencia_caso'=>['convivencia','convivencia_casos'],'convivencia_reunion'=>['convivencia','convivencia_reuniones']];

    public static function forEntity(int $tenantId,string $area,string $entityType,int $entityId):array
    {
        self::assertDefinition($area,$entityType);
        return Database::query('SELECT s.*,u.nombre subido_por_nombre,r.nombre revisado_por_nombre FROM soportes_reservados s LEFT JOIN users u ON u.id=s.subido_por LEFT JOIN users r ON r.id=s.revisado_por WHERE s.tenant_id=? AND s.area=? AND s.entidad_tipo=? AND s.entidad_id=? ORDER BY s.id DESC',[$tenantId,$area,$entityType,$entityId]);
    }

    public static function findForTenant(int $id,int $tenantId):?array{return Database::first('SELECT * FROM soportes_reservados WHERE id=? AND tenant_id=?',[$id,$tenantId]);}

    public static function upload(int $tenantId,string $area,string $entityType,int $entityId,array $input,array $file,int $userId):int
    {
        $definition=self::assertDefinition($area,$entityType);
        if(!Database::first('SELECT id FROM '.$definition[1].' WHERE id=? AND tenant_id=?',[$entityId,$tenantId]))throw new DomainException('El registro reservado no existe o no pertenece a la empresa.');
        $title=trim((string)($input['titulo']??''));$category=trim((string)($input['categoria']??''));if($title===''||$category==='')throw new DomainException('Título y categoría del soporte son obligatorios.');
        $stored=EvidenceStorage::storeReserved($file,$tenantId);
        try{Database::execute('INSERT INTO soportes_reservados (tenant_id,area,entidad_tipo,entidad_id,categoria,titulo,nombre_original,nombre_almacenado,ruta_relativa,mime,extension,tamano,hash_sha256,subido_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenantId,$area,$entityType,$entityId,text_substr($category,0,100),text_substr($title,0,240),$stored['nombre_original'],$stored['nombre_almacenado'],$stored['ruta_relativa'],$stored['mime'],$stored['extension'],$stored['tamano'],$stored['hash_sha256'],$userId]);$id=Database::lastId();}catch(Throwable $e){if(is_file($stored['absolute_path']))@unlink($stored['absolute_path']);throw$e;}
        Audit::record('Soporte reservado cargado','SoporteReservado',$id,['area'=>$area,'entidad_tipo'=>$entityType,'entidad_id'=>$entityId],$tenantId,$userId);return$id;
    }

    public static function review(int $id,int $tenantId,string $area,int $userId):void
    {
        $support=self::findForTenant($id,$tenantId)?:throw new DomainException('Soporte no encontrado.');if($support['area']!==$area)throw new DomainException('El soporte pertenece a otra área reservada.');
        Database::execute("UPDATE soportes_reservados SET estado_revision='Revisado',revisado_por=?,revisado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=? AND area=?",[$userId,$id,$tenantId,$area]);Audit::record('Soporte reservado revisado','SoporteReservado',$id,['area'=>$area],$tenantId,$userId);
    }

    public static function reviewedCount(int $tenantId,string $area,string $entityType,int $entityId):int
    {
        self::assertDefinition($area,$entityType);return(int)(Database::first("SELECT COUNT(*) n FROM soportes_reservados WHERE tenant_id=? AND area=? AND entidad_tipo=? AND entidad_id=? AND estado_revision='Revisado'",[$tenantId,$area,$entityType,$entityId])['n']??0);
    }

    private static function assertDefinition(string $area,string $entityType):array
    {
        $definition=self::ENTITIES[$entityType]??null;if(!$definition||$definition[0]!==$area)throw new DomainException('Tipo de expediente reservado no válido.');return$definition;
    }
}
