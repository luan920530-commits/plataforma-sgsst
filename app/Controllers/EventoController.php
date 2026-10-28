<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Empleado;
use App\Models\Evento;
use App\Models\Permission;
use App\Services\CsvExport;
use App\Support\AccessControl;
use App\Support\Auth;
use DomainException;

final class EventoController extends BaseController
{
    public function index(): void
    {
        $canHealth=AccessControl::can(Permission::HEALTH);$filters=['tipo'=>(string)$this->query('tipo'),'estado'=>(string)$this->query('estado'),'anio'=>(string)$this->query('anio',date('Y')),'q'=>(string)$this->query('q')];
        if($filters['tipo']==='Enfermedad laboral'&&!$canHealth)$this->requirePermission(Permission::HEALTH,'El detalle de enfermedad laboral está reservado.');
        $this->view('tenant/eventos/index',['title'=>'Incidentes, AT y EL','events'=>Evento::forTenant($this->tenantId(),$filters,$canHealth),'stats'=>Evento::stats($this->tenantId(),(int)$filters['anio']?:null),'filters'=>$filters,'types'=>Evento::types(),'statuses'=>Evento::statuses(),'employees'=>Empleado::forTenant($this->tenantId()),'canHealth'=>$canHealth]);
    }

    public function store(): void
    {
        if((string)$this->input('tipo')==='Enfermedad laboral')$this->requirePermission(Permission::HEALTH,'Registrar una enfermedad laboral requiere acceso reservado de salud.');
        try{$id=Evento::create($this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/eventos');}
        flash('success','Evento reportado. No se ha marcado como investigado ni cerrado.');redirect('/eventos/'.$id);
    }

    public function show(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        $this->view('tenant/eventos/show',['title'=>$event['codigo'].' · '.$event['tipo'],'event'=>$event,'people'=>Evento::people($id,$this->tenantId()),'investigation'=>Evento::investigation($id,$this->tenantId()),'actions'=>Evento::actions($id,$this->tenantId()),'employees'=>Empleado::forTenant($this->tenantId()),'canVerify'=>AccessControl::can(Permission::MATRIX_VERIFY)]);
    }

    public function update(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::update($id,$this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/eventos/'.$id);}
        flash('success','Datos del evento actualizados sin alterar su estado.');redirect('/eventos/'.$id);
    }

    public function addPerson(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::addPerson($id,$this->tenantId(),$_POST,Auth::id());flash('success','Persona y declaración vinculadas al evento.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#personas');
    }

    public function deletePerson(int $id,int $personId): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::deletePerson($personId,$id,$this->tenantId(),Auth::id());flash('success','Vínculo retirado antes de iniciar la investigación.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#personas');
    }

    public function saveInvestigation(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::saveInvestigation($id,$this->tenantId(),$_POST,Auth::id());flash('success','Borrador de investigación guardado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#investigacion');
    }

    public function closeInvestigation(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::closeInvestigation($id,$this->tenantId(),Auth::id());flash('success','Investigación cerrada. Las acciones y su eficacia permanecen abiertas hasta verificarlas.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#investigacion');
    }

    public function addAction(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);
        try{Evento::addAction($id,$this->tenantId(),$_POST,Auth::id());flash('success','Acción programada con responsable, recursos y plazo.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#acciones');
    }

    public function actionStatus(int $id,int $actionId): void
    {
        $event=$this->event($id);$this->healthGuard($event);$status=(string)$this->input('estado');
        if(in_array($status,['Eficaz','No eficaz'],true))$this->requirePermission(Permission::MATRIX_VERIFY,'Evaluar la eficacia requiere permiso de verificación.');
        try{Evento::setActionStatus($actionId,$id,$this->tenantId(),$status,$_POST,Auth::id());flash('success','Estado de la acción actualizado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id.'#acciones');
    }

    public function close(int $id): void
    {
        $event=$this->event($id);$this->healthGuard($event);$this->requirePermission(Permission::MATRIX_VERIFY,'El cierre del evento requiere verificación humana.');
        try{Evento::close($id,$this->tenantId(),Auth::id());flash('success','Evento cerrado con investigación y eficacia de acciones verificadas.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/eventos/'.$id);
    }

    public function export(): void
    {
        $this->requirePermission(Permission::HEALTH,'La exportación puede contener enfermedades laborales y requiere acceso reservado.');
        $rows=Evento::forTenant($this->tenantId(),['anio'=>(string)$this->query('anio')],true);
        CsvExport::download('eventos-sst-'.date('Y-m-d').'.csv',['Código','Tipo','Fecha evento','Fecha reporte','Lugar','Proceso','Descripción','Lesión/condición','Gravedad','Días perdidos','Días cargados','ARL','EPS','MinTrabajo','Radicados','Responsable','Estado'],array_map(static fn(array $e)=>[$e['codigo'],$e['tipo'],$e['fecha_evento'],$e['fecha_reporte'],$e['lugar'],$e['proceso'],$e['descripcion'],$e['lesion'],$e['gravedad'],$e['dias_perdidos'],$e['dias_cargados'],$e['reportado_arl_at'],$e['reportado_eps_at'],$e['reportado_mintrabajo_at'],$e['radicados'],$e['responsable'],$e['estado']],$rows));
    }

    private function event(int $id):array{return Evento::findForTenant($id,$this->tenantId())?:$this->notFound();}
    private function healthGuard(array $event):void{if($event['tipo']==='Enfermedad laboral')$this->requirePermission(Permission::HEALTH,'Este evento contiene datos reservados de salud.');}
}
