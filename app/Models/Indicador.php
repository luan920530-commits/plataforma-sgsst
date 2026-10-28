<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Indicador
{
    public static function forTenant(int $tenantId): array{return Database::query('SELECT i.*,u.nombre verificado_por_nombre FROM indicadores i LEFT JOIN users u ON u.id=i.verificado_por WHERE i.tenant_id = ? ORDER BY i.fecha_corte DESC, i.id DESC', [$tenantId]);}
    public static function findForTenant(int $id, int $tenantId): ?array{return Database::first('SELECT i.*,u.nombre verificado_por_nombre FROM indicadores i LEFT JOIN users u ON u.id=i.verificado_por WHERE i.id = ? AND i.tenant_id = ?', [$id, $tenantId]);}
    public static function latest(int $tenantId, int $limit = 6): array{return Database::query('SELECT * FROM indicadores WHERE tenant_id = ? ORDER BY fecha_corte DESC, id DESC LIMIT '.max(1,$limit),[$tenantId]);}
    public static function create(array $data): int
    {
        Database::execute('INSERT INTO indicadores (tenant_id,tipo,nombre,periodo,numerador,denominador,factor,resultado,unidad,meta,fecha_corte,fuente_numerador,fuente_denominador,responsable,observaciones) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$data['tenant_id'],$data['tipo'],trim($data['nombre']),trim($data['periodo']),(float)$data['numerador'],(float)$data['denominador'],(float)$data['factor'],(float)$data['resultado'],$data['unidad'],$data['meta']===null||$data['meta']===''?null:(float)$data['meta'],$data['fecha_corte'],trim($data['fuente_numerador']??''),trim($data['fuente_denominador']??''),trim($data['responsable']??''),trim($data['observaciones']??'')]);return Database::lastId();
    }
    public static function update(int $id, int $tenantId, array $data): bool
    {
        return Database::execute('UPDATE indicadores SET tipo=?,nombre=?,periodo=?,numerador=?,denominador=?,factor=?,resultado=?,unidad=?,meta=?,fecha_corte=?,fuente_numerador=?,fuente_denominador=?,responsable=?,observaciones=?,verificado_por=NULL,verificado_at=NULL WHERE id=? AND tenant_id=?',[$data['tipo'],trim($data['nombre']),trim($data['periodo']),(float)$data['numerador'],(float)$data['denominador'],(float)$data['factor'],(float)$data['resultado'],$data['unidad'],$data['meta']===null||$data['meta']===''?null:(float)$data['meta'],$data['fecha_corte'],trim($data['fuente_numerador']??''),trim($data['fuente_denominador']??''),trim($data['responsable']??''),trim($data['observaciones']??''),$id,$tenantId])===1;
    }
    public static function verify(int$id,int$tenantId,int$userId):void
    {
        $indicator=self::findForTenant($id,$tenantId)?:throw new DomainException('Indicador no encontrado.');if(trim((string)$indicator['fuente_numerador'])===''||trim((string)$indicator['fuente_denominador'])===''||trim((string)$indicator['responsable'])==='')throw new DomainException('Complete las dos fuentes y el responsable antes de verificar.');if(Evidencia::reviewedCountForEntity($tenantId,'indicador',(string)$id)===0)throw new DomainException('La verificación requiere un soporte revisado de los datos fuente.');Database::execute('UPDATE indicadores SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?',[$userId,$id,$tenantId]);
    }
    public static function delete(int $id, int $tenantId): bool{return Database::execute('DELETE FROM indicadores WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;}
}
