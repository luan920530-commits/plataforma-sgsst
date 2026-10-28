<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Empleado;
use App\Models\Operation;
use App\Services\CsvExport;
use App\Services\OperationCatalog;
use App\Support\Auth;
use DomainException;

final class OperationController extends BaseController
{
    public function home(): void { redirect('/operacion/responsables'); }

    public function index(): void
    {
        $module=$this->module(); $definition=OperationCatalog::get($module);
        $filters=['estado'=>(string)$this->query('estado'),'tipo'=>(string)$this->query('tipo'),'q'=>(string)$this->query('q')];
        $this->view('tenant/operaciones/index',[
            'title'=>$definition['title'],'module'=>$module,'definition'=>$definition,'modules'=>OperationCatalog::modules(),
            'records'=>Operation::forTenant($this->tenantId(),$module,$filters),'stats'=>Operation::stats($this->tenantId(),$module),
            'filters'=>$filters,'employees'=>Empleado::forTenant($this->tenantId()),'statuses'=>Operation::statuses(),
            'requisitoClave'=>text_substr((string)$this->query('requisito'),0,40),
        ]);
    }

    public function store(): void
    {
        $module=$this->module(); $_POST['modulo']=$module;
        try { $id=Operation::create($this->tenantId(),$_POST,Auth::id()); }
        catch (DomainException $e) { fail_validation(['general'=>$e->getMessage()],$_POST,'/operacion/'.$module); }
        flash('success','Registro operativo creado en estado Pendiente. No se ha marcado como ejecutado.');
        redirect('/operacion-registros/'.$id.'/editar');
    }

    public function edit(int $id): void
    {
        $record=Operation::findForTenant($id,$this->tenantId()) ?: $this->notFound();
        $module=$record['modulo']; $definition=OperationCatalog::get($module);
        $this->view('tenant/operaciones/edit',[
            'title'=>'Editar · '.$record['titulo'],'record'=>$record,'module'=>$module,'definition'=>$definition,
            'modules'=>OperationCatalog::modules(),'employees'=>Empleado::forTenant($this->tenantId()),'statuses'=>Operation::statuses(),
        ]);
    }

    public function update(int $id): void
    {
        try { Operation::update($id,$this->tenantId(),$_POST,Auth::id()); }
        catch (DomainException $e) { fail_validation(['general'=>$e->getMessage()],$_POST,'/operacion-registros/'.$id.'/editar'); }
        flash('success','Registro actualizado. Su estado de ejecución no cambió automáticamente.');
        $record=Operation::findForTenant($id,$this->tenantId()); redirect('/operacion-registros/'.$id.'/editar');
    }

    public function status(int $id): void
    {
        $status=(string)$this->input('estado'); $record=Operation::findForTenant($id,$this->tenantId()) ?: $this->notFound();
        if ($status==='Verificado') $this->requirePermission('matriz.verificar','La verificación operativa requiere autorización específica.');
        try { Operation::setStatus($id,$this->tenantId(),$status,Auth::id(),(string)$this->input('nota_verificacion')); }
        catch (DomainException $e) { flash('error',$e->getMessage()); redirect('/operacion/'.$record['modulo']); }
        flash('success','Estado actualizado con trazabilidad de auditoría.'); redirect('/operacion/'.$record['modulo']);
    }

    public function delete(int $id): void
    {
        $record=Operation::findForTenant($id,$this->tenantId()) ?: $this->notFound();
        try { Operation::delete($id,$this->tenantId(),Auth::id()); }
        catch (DomainException $e) { flash('error',$e->getMessage()); redirect('/operacion/'.$record['modulo']); }
        flash('success','Borrador pendiente eliminado.'); redirect('/operacion/'.$record['modulo']);
    }

    public function export(): void
    {
        $module=$this->module(); $definition=OperationCatalog::get($module); $rows=Operation::forTenant($this->tenantId(),$module);
        $header=['ID','Tipo','Título','Descripción','Responsable','Recursos','Programada','Ejecutada','Vencimiento','Estado','Resultado','Datos específicos (JSON)','Requisito','Evidencias','Evidencias revisadas'];
        $data=array_map(fn(array $r)=>[$r['id'],$r['tipo'],$r['titulo'],$r['descripcion'],$r['responsable'],$r['recursos'],$r['fecha_programada'],$r['fecha_ejecucion'],$r['fecha_vencimiento'],$r['estado'],$r['resultado'],json_encode($r['datos'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$r['requisito_clave'],$r['evidencias_total'],$r['evidencias_revisadas']],$rows);
        CsvExport::download('operacion-'.$module.'-'.date('Y-m-d').'.csv',$header,$data);
    }

    private function module(): string
    {
        if (!preg_match('#/operacion/([a-z][a-z0-9-]*)#',current_path(),$match)) $this->notFound();
        $module=$match[1]; if (!OperationCatalog::get($module)) $this->notFound(); return $module;
    }
}
