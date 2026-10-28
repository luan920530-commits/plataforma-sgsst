<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comite;
use App\Models\Empleado;
use App\Models\Permission;
use App\Support\AccessControl;
use App\Support\Auth;
use DomainException;

final class ComiteController extends BaseController
{
    public function index():void{$this->view('tenant/comites/index',['title'=>'COPASST y Vigía SST','committees'=>Comite::forTenant($this->tenantId()),'canCoexistence'=>AccessControl::can(Permission::COEXISTENCE)]);}
    public function store():void{try{$id=Comite::create($this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/comites');}flash('success','Instancia registrada. Agrega miembros y programa reuniones reales.');redirect('/comites/'.$id);}
    public function show(int $id):void{$committee=$this->committee($id);$this->view('tenant/comites/show',['title'=>$committee['tipo'].' · '.$committee['centro_trabajo'],'committee'=>$committee,'members'=>Comite::members($id,$this->tenantId()),'meetings'=>Comite::meetings($id,$this->tenantId()),'composition'=>Comite::composition($id,$this->tenantId()),'employees'=>Empleado::forTenant($this->tenantId())]);}
    public function setActive(int $id):void{$this->committee($id);Comite::setActive($id,$this->tenantId(),(string)$this->input('activo')==='1',Auth::id());flash('success','Estado de la instancia actualizado.');redirect('/comites/'.$id);}
    public function addMember(int $id):void{$this->committee($id);try{Comite::addMember($id,$this->tenantId(),$_POST,Auth::id());flash('success','Miembro agregado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/comites/'.$id.'#miembros');}
    public function memberStatus(int $id,int $memberId):void{$this->committee($id);try{Comite::setMemberActive($memberId,$id,$this->tenantId(),(string)$this->input('activo')==='1',Auth::id());flash('success','Estado del miembro actualizado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/comites/'.$id.'#miembros');}
    public function scheduleMeeting(int $id):void{$this->committee($id);try{Comite::scheduleMeeting($id,$this->tenantId(),$_POST,Auth::id());flash('success','Reunión programada; todavía no consta como realizada.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/comites/'.$id.'#reuniones');}
    public function recordMeeting(int $id,int $meetingId):void{$this->committee($id);try{Comite::recordMeeting($meetingId,$id,$this->tenantId(),$_POST,Auth::id());flash('success','Resultado de la reunión registrado con asistencia confirmada.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/comites/'.$id.'#reunion-'.$meetingId);}
    private function committee(int $id):array{return Comite::findForTenant($id,$this->tenantId())?:$this->notFound();}
}
