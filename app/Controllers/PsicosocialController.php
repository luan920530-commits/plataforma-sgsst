<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Permission;
use App\Models\Psicosocial;
use App\Models\SoporteReservado;
use App\Support\AccessControl;
use App\Support\Auth;
use DomainException;

final class PsicosocialController extends BaseController
{
    public function index():void{$this->guard();$this->view('tenant/salud/psicosocial-index',['title'=>'Evaluación psicosocial agregada','evaluations'=>Psicosocial::forTenant($this->tenantId())]);}
    public function store():void{$this->guard();try{$id=Psicosocial::create($this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/salud/psicosocial');}flash('success','Evaluación planificada sin precargar participantes ni resultados.');redirect('/salud/psicosocial/'.$id);}
    public function show(int$id):void{$this->guard();$evaluation=$this->evaluation($id);$this->view('tenant/salud/psicosocial-show',['title'=>'Psicosocial · Vigencia '.$evaluation['vigencia'],'evaluation'=>$evaluation,'results'=>json_decode($evaluation['resultados_agregados_json'],true)?:[],'supports'=>SoporteReservado::forEntity($this->tenantId(),'salud','psicosocial',$id),'canVerify'=>AccessControl::can(Permission::MATRIX_VERIFY)]);}
    public function application(int$id):void{$this->guard();$this->evaluation($id);try{Psicosocial::recordApplication($id,$this->tenantId(),$_POST,Auth::id());flash('success','Resultados agregados guardados sin respuestas individuales.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/salud/psicosocial/'.$id.'#aplicacion');}
    public function intervention(int$id):void{$this->guard();$this->evaluation($id);try{Psicosocial::saveIntervention($id,$this->tenantId(),(string)$this->input('plan_intervencion'),Auth::id());flash('success','Plan de intervención actualizado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/salud/psicosocial/'.$id.'#intervencion');}
    public function efficacy(int$id):void{$this->guard();$this->evaluation($id);$this->requirePermission(Permission::MATRIX_VERIFY,'Evaluar eficacia requiere permiso de verificación.');try{Psicosocial::evaluate($id,$this->tenantId(),(string)$this->input('eficacia'),(string)$this->input('proxima_fecha'),Auth::id());flash('success','Eficacia agregada registrada con soporte revisado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/salud/psicosocial/'.$id.'#eficacia');}
    public function close(int$id):void{$this->guard();$this->evaluation($id);try{Psicosocial::close($id,$this->tenantId(),Auth::id());flash('success','Ciclo psicosocial cerrado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/salud/psicosocial/'.$id);}
    private function guard():void{$this->requirePermission(Permission::HEALTH,'Los resultados psicosociales requieren acceso reservado de salud.');}
    private function evaluation(int$id):array{return Psicosocial::findForTenant($id,$this->tenantId())?:$this->notFound();}
}
