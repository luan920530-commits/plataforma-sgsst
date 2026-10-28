<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\OperationCatalog;
use App\Support\Audit;
use PDO;
use DomainException;

final class Operation
{
    public static function statuses(): array { return ['Pendiente','En curso','Completado','Verificado','Cancelado']; }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $stmt = Database::connection()->prepare(self::selectSql() . ' WHERE o.id = ? AND o.tenant_id = ? LIMIT 1');
        $stmt->execute([$id,$tenantId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrate($row) : null;
    }

    public static function forTenant(int $tenantId, string $module, array $filters = []): array
    {
        $where = ['o.tenant_id = ?','o.modulo = ?']; $args = [$tenantId,$module];
        if (!empty($filters['estado']) && in_array($filters['estado'], self::statuses(), true)) { $where[]='o.estado = ?'; $args[]=$filters['estado']; }
        if (!empty($filters['tipo'])) { $where[]='o.tipo = ?'; $args[]=text_substr((string)$filters['tipo'],0,100); }
        if (!empty($filters['q'])) { $where[]='(o.titulo LIKE ? OR o.descripcion LIKE ? OR o.responsable LIKE ?)'; $q='%'.text_substr((string)$filters['q'],0,100).'%'; array_push($args,$q,$q,$q); }
        $stmt=Database::connection()->prepare(self::selectSql().' WHERE '.implode(' AND ',$where).' ORDER BY CASE WHEN o.fecha_vencimiento IS NULL THEN 1 ELSE 0 END, o.fecha_vencimiento, o.id DESC');
        $stmt->execute($args);
        return array_map(self::hydrate(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create(int $tenantId, array $input, int $userId): int
    {
        $data=self::validate($tenantId,$input);
        $stmt=Database::connection()->prepare('INSERT INTO operacion_registros (tenant_id,modulo,tipo,titulo,descripcion,responsable,recursos,fecha_programada,fecha_ejecucion,fecha_vencimiento,estado,prioridad,resultado,datos_json,requisito_clave,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$tenantId,$data['modulo'],$data['tipo'],$data['titulo'],$data['descripcion'],$data['responsable'],$data['recursos'],$data['fecha_programada'],$data['fecha_ejecucion'],$data['fecha_vencimiento'],'Pendiente',$data['prioridad'],$data['resultado'],json_encode($data['datos'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$data['requisito_clave'],$userId]);
        $id=Database::lastId(); Audit::record('Registro operativo creado','Operacion',$id,['modulo'=>$data['modulo']],$tenantId,$userId); return $id;
    }

    public static function update(int $id, int $tenantId, array $input, int $userId): void
    {
        $current=self::findForTenant($id,$tenantId) ?: throw new DomainException('Registro no encontrado.');
        if ($current['estado']==='Verificado') throw new DomainException('Un registro verificado no puede editarse; cree un seguimiento nuevo.');
        $input['modulo']=$current['modulo']; $data=self::validate($tenantId,$input);
        Database::connection()->prepare('UPDATE operacion_registros SET tipo=?,titulo=?,descripcion=?,responsable=?,recursos=?,fecha_programada=?,fecha_ejecucion=?,fecha_vencimiento=?,prioridad=?,resultado=?,datos_json=?,requisito_clave=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?')
            ->execute([$data['tipo'],$data['titulo'],$data['descripcion'],$data['responsable'],$data['recursos'],$data['fecha_programada'],$data['fecha_ejecucion'],$data['fecha_vencimiento'],$data['prioridad'],$data['resultado'],json_encode($data['datos'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$data['requisito_clave'],$id,$tenantId]);
        Audit::record('Registro operativo actualizado','Operacion',$id,['modulo'=>$current['modulo']],$tenantId,$userId);
    }

    public static function setStatus(int $id, int $tenantId, string $status, int $userId, string $note = ''): void
    {
        $record=self::findForTenant($id,$tenantId) ?: throw new DomainException('Registro no encontrado.');
        if (!in_array($status,self::statuses(),true)) throw new DomainException('Estado no válido.');
        if ($record['estado']==='Verificado' && $status!=='Verificado') throw new DomainException('Un registro verificado no puede retroceder ni cancelarse.');
        if (in_array($status,['Completado','Verificado'],true) && (empty($record['fecha_ejecucion']) || trim((string)$record['resultado'])==='')) {
            throw new DomainException('Para completar debe registrar fecha de ejecución y resultado real en la edición.');
        }
        if ($status==='Verificado') {
            if (!in_array($record['estado'],['Completado','Verificado'],true)) throw new DomainException('Primero complete el registro y después solicite su verificación.');
            if (!Permission::has($userId,$tenantId,Permission::MATRIX_VERIFY)) throw new DomainException('Su usuario no tiene permiso de verificación humana.');
            if ((int)$record['evidencias_revisadas']<1) throw new DomainException('La verificación exige al menos una evidencia revisada por una persona.');
            if (trim($note)==='') throw new DomainException('La verificación requiere una nota de comprobación.');
            if ($record['estado']!=='Verificado') Database::connection()->prepare('UPDATE operacion_registros SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP,nota_verificacion=? WHERE id=? AND tenant_id=?')->execute([$userId,text_substr(trim($note),0,4000),$id,$tenantId]);
        }
        Database::connection()->prepare('UPDATE operacion_registros SET estado=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?')->execute([$status,$id,$tenantId]);
        Audit::record('Estado operativo actualizado','Operacion',$id,['modulo'=>$record['modulo'],'antes'=>$record['estado'],'despues'=>$status],$tenantId,$userId);
    }

    public static function delete(int $id, int $tenantId, int $userId): void
    {
        $record=self::findForTenant($id,$tenantId) ?: throw new DomainException('Registro no encontrado.');
        if ($record['estado']!=='Pendiente') throw new DomainException('Solo puede eliminarse un borrador pendiente sin ejecución.');
        if ((int)$record['evidencias_total']>0) throw new DomainException('Conserve el registro porque ya tiene evidencias vinculadas.');
        Database::connection()->prepare('DELETE FROM operacion_registros WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
        Audit::record('Borrador operativo eliminado','Operacion',$id,['modulo'=>$record['modulo']],$tenantId,$userId);
    }

    public static function stats(int $tenantId, string $module): array
    {
        $stmt=Database::connection()->prepare("SELECT COUNT(*) total, SUM(CASE WHEN estado IN ('Pendiente','En curso') THEN 1 ELSE 0 END) pendientes, SUM(CASE WHEN estado='Verificado' THEN 1 ELSE 0 END) verificados, SUM(CASE WHEN estado NOT IN ('Verificado','Cancelado') AND COALESCE(fecha_vencimiento,fecha_programada) < date('now') THEN 1 ELSE 0 END) vencidos FROM operacion_registros WHERE tenant_id=? AND modulo=?");
        $stmt->execute([$tenantId,$module]); $row=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['total'=>(int)($row['total']??0),'pendientes'=>(int)($row['pendientes']??0),'verificados'=>(int)($row['verificados']??0),'vencidos'=>(int)($row['vencidos']??0)];
    }

    private static function validate(int $tenantId, array $input): array
    {
        $module=(string)($input['modulo']??''); $definition=OperationCatalog::get($module) ?: throw new DomainException('Módulo operativo no válido.');
        $data=[
            'modulo'=>$module,'tipo'=>trim((string)($input['tipo']??'')),'titulo'=>trim((string)($input['titulo']??'')),
            'descripcion'=>trim((string)($input['descripcion']??'')),'responsable'=>trim((string)($input['responsable']??'')),
            'recursos'=>trim((string)($input['recursos']??'')),'fecha_programada'=>self::date($input['fecha_programada']??null,'fecha programada',true),
            'fecha_ejecucion'=>self::date($input['fecha_ejecucion']??null,'fecha de ejecución'), 'fecha_vencimiento'=>self::date($input['fecha_vencimiento']??null,'fecha de vencimiento'),
            'prioridad'=>(string)($input['prioridad']??'Media'),'resultado'=>trim((string)($input['resultado']??'')),
            'requisito_clave'=>text_substr(trim((string)($input['requisito_clave']??'')),0,40),
        ];
        if (!in_array($data['tipo'],$definition['types'],true)) throw new DomainException('Seleccione un tipo válido.');
        if ($data['titulo']===''||$data['descripcion']===''||$data['responsable']==='') throw new DomainException('Título, descripción y responsable son obligatorios.');
        if (!in_array($data['prioridad'],['Baja','Media','Alta','Critica'],true)) throw new DomainException('Prioridad no válida.');
        $data['datos']=[];
        foreach ($definition['fields'] as $field) {
            $name=$field['name']; $value=$input[$name]??'';
            if ($field['type']==='employee') {
                if ($value===''||$value===null) { if ($field['required']) throw new DomainException('Seleccione: '.$field['label']); $data['datos'][$name]=null; continue; }
                $employee=Empleado::findForTenant((int)$value,$tenantId); if (!$employee) throw new DomainException('La persona seleccionada no pertenece a la empresa.');
                $data['datos'][$name]=(int)$value; continue;
            }
            $value=trim((string)$value);
            if ($field['required'] && $value==='') throw new DomainException('Complete: '.$field['label']);
            if ($value!=='' && $field['type']==='date') self::date($value,$field['label']);
            if ($value!=='' && $field['type']==='number' && (!is_numeric($value)||!is_finite((float)$value))) throw new DomainException('Número no válido en '.$field['label']);
            if ($value!=='' && $field['type']==='select' && !in_array($value,$field['options'],true)) throw new DomainException('Opción no válida en '.$field['label']);
            $data['datos'][$name]=$value;
        }
        foreach (['titulo','responsable'] as $key) if (text_length($data[$key])>240) throw new DomainException('El campo '.$key.' supera 240 caracteres.');
        foreach (['descripcion','recursos','resultado'] as $key) if (text_length($data[$key])>12000) throw new DomainException('El campo '.$key.' supera 12.000 caracteres.');
        return $data;
    }

    private static function date(mixed $value, string $label, bool $required=false): ?string
    {
        $value=trim((string)($value??'')); if ($value==='') { if ($required) throw new DomainException('La '.$label.' es obligatoria.'); return null; }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if (!$date||$date->format('Y-m-d')!==$value) throw new DomainException('La '.$label.' no tiene formato AAAA-MM-DD.'); return $value;
    }

    private static function selectSql(): string
    {
        return "SELECT o.*, (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=o.tenant_id AND s.entidad_tipo='operacion' AND s.entidad_id=CAST(o.id AS TEXT)) evidencias_total, (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=o.tenant_id AND s.entidad_tipo='operacion' AND s.entidad_id=CAST(o.id AS TEXT) AND s.estado_revision='Revisado') evidencias_revisadas FROM operacion_registros o";
    }

    private static function hydrate(array $row): array
    {
        $decoded=json_decode((string)($row['datos_json']??'{}'),true); $row['datos']=is_array($decoded)?$decoded:[]; unset($row['datos_json']); return $row;
    }


}
