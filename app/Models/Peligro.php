<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Peligro
{
    public static function forTenant(int $tenantId, string $classification = ''): array
    {
        $sql='SELECT p.*,u.nombre verificado_por_nombre FROM peligros_ipevr p LEFT JOIN users u ON u.id=p.verificado_por WHERE p.tenant_id = ?';$params=[$tenantId];if($classification!==''){$sql.=' AND p.clasificacion = ?';$params[]=$classification;}return Database::query($sql." ORDER BY CASE p.nivel_riesgo WHEN 'Critico' THEN 1 WHEN 'Alto' THEN 2 WHEN 'Medio' THEN 3 ELSE 4 END, p.clasificacion, p.peligro",$params);
    }
    public static function create(array $data): int
    {
        Database::execute('INSERT INTO peligros_ipevr (tenant_id,actividad_economica_id,proceso,cargo,peligro,clasificacion,efectos,controles_existentes,nivel_deficiencia,nivel_exposicion,nivel_probabilidad,nivel_consecuencia,nivel_riesgo,medidas_control,metodologia,participantes,fecha_evaluacion,proxima_revision,jerarquia_controles) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$data['tenant_id'],!empty($data['actividad_economica_id'])?(int)$data['actividad_economica_id']:null,trim($data['proceso']),trim($data['cargo']),trim($data['peligro']),$data['clasificacion'],trim($data['efectos']??''),trim($data['controles_existentes']??''),$data['nivel_deficiencia']??null,$data['nivel_exposicion']??null,$data['nivel_probabilidad']??null,$data['nivel_consecuencia']??null,$data['nivel_riesgo'],trim($data['medidas_control']??''),trim($data['metodologia']??'GTC 45'),trim($data['participantes']??''),$data['fecha_evaluacion']??null,$data['proxima_revision']??null,trim($data['jerarquia_controles']??'')]);return Database::lastId();
    }
    public static function findForTenant(int $id, int $tenantId): ?array{return Database::first('SELECT p.*,u.nombre verificado_por_nombre FROM peligros_ipevr p LEFT JOIN users u ON u.id=p.verificado_por WHERE p.id = ? AND p.tenant_id = ?', [$id, $tenantId]);}
    public static function update(int $id, int $tenantId, array $data): void
    {
        Database::execute('UPDATE peligros_ipevr SET proceso=?,cargo=?,peligro=?,clasificacion=?,efectos=?,controles_existentes=?,nivel_deficiencia=?,nivel_exposicion=?,nivel_probabilidad=?,nivel_consecuencia=?,nivel_riesgo=?,medidas_control=?,metodologia=?,participantes=?,fecha_evaluacion=?,proxima_revision=?,jerarquia_controles=?,verificado_por=NULL,verificado_at=NULL,estado=CASE WHEN estado=\'Controlado\' THEN \'Activo\' ELSE estado END,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[trim($data['proceso']),trim($data['cargo']),trim($data['peligro']),$data['clasificacion'],trim($data['efectos']??''),trim($data['controles_existentes']??''),$data['nivel_deficiencia']??null,$data['nivel_exposicion']??null,$data['nivel_probabilidad']??null,$data['nivel_consecuencia']??null,$data['nivel_riesgo'],trim($data['medidas_control']??''),trim($data['metodologia']??'GTC 45'),trim($data['participantes']??''),$data['fecha_evaluacion']??null,$data['proxima_revision']??null,trim($data['jerarquia_controles']??''),$id,$tenantId]);
    }
    public static function verify(int$id,int$tenantId,int$userId):void
    {
        $hazard=self::findForTenant($id,$tenantId)?:throw new DomainException('Peligro no encontrado.');foreach(['participantes'=>'participantes','fecha_evaluacion'=>'fecha de evaluación','jerarquia_controles'=>'jerarquía de controles']as$field=>$label)if(trim((string)$hazard[$field])==='')throw new DomainException('Complete '.$label.' antes de verificar.');if(Evidencia::reviewedCountForEntity($tenantId,'peligro',(string)$id)===0)throw new DomainException('La verificación requiere un soporte revisado de la evaluación.');Database::execute('UPDATE peligros_ipevr SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$userId,$id,$tenantId]);
    }
    public static function delete(int $id, int $tenantId): void{Database::execute('DELETE FROM peligros_ipevr WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);}
    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        $hazard=self::findForTenant($id,$tenantId)?:throw new DomainException('Peligro no encontrado.');if(!in_array($status,['Activo','Controlado'],true))throw new DomainException('Estado no válido.');if($status==='Controlado'&&!$hazard['verificado_por'])throw new DomainException('Antes de marcar Controlado, verifique la evaluación y sus soportes.');Database::execute('UPDATE peligros_ipevr SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [$status, $id, $tenantId]);
    }
    public static function stats(int $tenantId): array{return Database::first("SELECT COUNT(*) AS total,SUM(CASE WHEN nivel_riesgo IN ('Alto','Critico') AND estado = 'Activo' THEN 1 ELSE 0 END) AS prioritarios,SUM(CASE WHEN estado = 'Controlado' THEN 1 ELSE 0 END) AS controlados FROM peligros_ipevr WHERE tenant_id = ?",[$tenantId])??[];}
}
