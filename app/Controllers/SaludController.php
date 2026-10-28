<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Empleado;
use App\Models\Permission;
use App\Models\Salud;
use App\Models\SoporteReservado;
use App\Services\CsvExport;
use App\Support\Auth;
use DomainException;

final class SaludController extends BaseController
{
    public function index():void{$this->guard();$filters=['tipo'=>(string)$this->query('tipo'),'q'=>(string)$this->query('q')];$this->view('tenant/salud/index',['title'=>'Salud ocupacional reservada','records'=>Salud::forTenant($this->tenantId(),$filters),'stats'=>Salud::stats($this->tenantId()),'filters'=>$filters,'types'=>Salud::types(),'employees'=>Empleado::forTenant($this->tenantId())]);}
    public function store():void{$this->guard();try{$id=Salud::create($this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/salud');}flash('success','Concepto reservado registrado; no se añadió a la biblioteca general.');redirect('/salud/'.$id);}
    public function show(int$id):void{$this->guard();$record=$this->record($id);$this->view('tenant/salud/show',['title'=>'Seguimiento reservado · '.$record['empleado_nombre'],'record'=>$record,'types'=>Salud::types(),'employees'=>Empleado::forTenant($this->tenantId()),'supports'=>SoporteReservado::forEntity($this->tenantId(),'salud','salud_registro',$id)]);}
    public function update(int$id):void{$this->guard();$this->record($id);try{Salud::update($id,$this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/salud/'.$id);}flash('success','Concepto y seguimiento actualizados.');redirect('/salud/'.$id);}
    public function status(int$id):void{$this->guard();$this->record($id);try{Salud::setStatus($id,$this->tenantId(),(string)$this->input('estado'),(string)$this->input('seguimiento'),Auth::id());flash('success','Estado de seguimiento actualizado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/salud/'.$id);}
    public function export():void{$this->guard();$rows=Salud::aggregate($this->tenantId());CsvExport::download('salud-estadistica-anonima-'.date('Y-m-d').'.csv',['Tipo','Estado','Cantidad agregada'],array_map(static fn($r)=>[$r['tipo'],$r['estado'],$r['cantidad']],$rows));}
    private function guard():void{$this->requirePermission(Permission::HEALTH,'El expediente de salud requiere acceso reservado y necesario.');}
    private function record(int$id):array{return Salud::findForTenant($id,$this->tenantId())?:$this->notFound();}
}
