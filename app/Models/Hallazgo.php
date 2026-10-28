<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Hallazgo
{
    public static function forTenant(int $tenantId, ?string $status = null): array{$sql='SELECT h.*,u.nombre verificado_por_nombre FROM hallazgos_acpa h LEFT JOIN users u ON u.id=h.verificado_por WHERE h.tenant_id = ?';$params=[$tenantId];if($status!==null&&$status!==''){$sql.=' AND h.estado = ?';$params[]=$status;}return Database::query($sql." ORDER BY CASE WHEN h.estado = 'Abierto' THEN 0 ELSE 1 END, h.fecha_limite, h.id DESC",$params);}
    public static function findForTenant(int $id, int $tenantId): ?array{return Database::first('SELECT h.*,u.nombre verificado_por_nombre FROM hallazgos_acpa h LEFT JOIN users u ON u.id=h.verificado_por WHERE h.id = ? AND h.tenant_id = ?', [$id, $tenantId]);}
    public static function create(array $data): int{Database::execute('INSERT INTO hallazgos_acpa (tenant_id,origen,descripcion,accion_correctiva,responsable,fecha_limite,tipo_accion,causa_raiz,recursos) VALUES (?,?,?,?,?,?,?,?,?)',[(int)$data['tenant_id'],$data['origen'],trim($data['descripcion']),trim($data['accion_correctiva']),trim($data['responsable']),$data['fecha_limite'],$data['tipo_accion']??'Correctiva',trim($data['causa_raiz']??''),trim($data['recursos']??'')]);return Database::lastId();}
    public static function update(int $id, int $tenantId, array $data): bool{$current=self::findForTenant($id,$tenantId)?:throw new DomainException('Hallazgo no encontrado.');if($current['estado']==='Cerrado')throw new DomainException('Reabra el hallazgo antes de modificar la acción.');return Database::execute('UPDATE hallazgos_acpa SET origen=?,descripcion=?,accion_correctiva=?,responsable=?,fecha_limite=?,tipo_accion=?,causa_raiz=?,recursos=?,eficacia_resultado=\'\',eficacia_fecha=NULL,verificado_por=NULL,verificado_at=NULL WHERE id=? AND tenant_id=?',[$data['origen'],trim($data['descripcion']),trim($data['accion_correctiva']),trim($data['responsable']),$data['fecha_limite'],$data['tipo_accion']??'Correctiva',trim($data['causa_raiz']??''),trim($data['recursos']??''),$id,$tenantId])===1;}
    public static function delete(int $id, int $tenantId): bool{return Database::execute('DELETE FROM hallazgos_acpa WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;}
    public static function verifyEfficacy(int$id,int$tenantId,array$input,int$userId):void
    {
        $finding=self::findForTenant($id,$tenantId)?:throw new DomainException('Hallazgo no encontrado.');if($finding['estado']==='Cerrado')throw new DomainException('El hallazgo cerrado requiere reapertura para una nueva evaluación.');$result=(string)($input['eficacia_resultado']??'');if(!in_array($result,['Eficaz','No eficaz'],true))throw new DomainException('Concluya si la acción fue Eficaz o No eficaz.');$date=self::date((string)($input['eficacia_fecha']??''));if(trim((string)$finding['causa_raiz'])==='')throw new DomainException('Documente la causa raíz antes de evaluar eficacia.');if(Evidencia::reviewedCountForEntity($tenantId,'hallazgo',(string)$id)===0)throw new DomainException('La eficacia requiere una evidencia revisada vinculada al hallazgo.');Database::execute('UPDATE hallazgos_acpa SET eficacia_resultado=?,eficacia_fecha=?,verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$result,$date,$userId,$id,$tenantId]);
    }
    public static function setStatus(int $id, int $tenantId, string $status): void
    {
        $finding=self::findForTenant($id,$tenantId)?:throw new DomainException('Hallazgo no encontrado.');if(!in_array($status,['Abierto','Cerrado'],true))throw new DomainException('Estado no válido.');if($status==='Cerrado'){if(trim((string)$finding['causa_raiz'])==='')throw new DomainException('Documente la causa raíz antes del cierre.');if($finding['eficacia_resultado']!=='Eficaz'||!$finding['verificado_por']||!$finding['eficacia_fecha'])throw new DomainException('El cierre exige una evaluación de eficacia favorable y verificador humano.');if(Evidencia::reviewedCountForEntity($tenantId,'hallazgo',(string)$id)===0)throw new DomainException('El cierre requiere evidencia de eficacia revisada.');}Database::execute('UPDATE hallazgos_acpa SET estado=?,fecha_cierre=?,eficacia_resultado=?,eficacia_fecha=?,verificado_por=?,verificado_at=? WHERE id=? AND tenant_id=?',[$status,$status==='Cerrado'?date('Y-m-d'):null,$status==='Cerrado'?$finding['eficacia_resultado']:'',$status==='Cerrado'?$finding['eficacia_fecha']:null,$status==='Cerrado'?$finding['verificado_por']:null,$status==='Cerrado'?$finding['verificado_at']:null,$id,$tenantId]);
    }
    public static function openCount(int $tenantId): int{return (int)(Database::first("SELECT COUNT(*) AS total FROM hallazgos_acpa WHERE tenant_id = ? AND estado = 'Abierto'",[$tenantId])['total']??0);}
    private static function date(string$value):string{$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new DomainException('Fecha de eficacia no válida.');return$value;}
}
