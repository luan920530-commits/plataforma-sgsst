<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Convivencia;
use App\Models\Permission;
use App\Models\SoporteReservado;
use App\Models\User;
use App\Services\CsvExport;
use App\Support\Auth;
use DomainException;

final class ConvivenciaController extends BaseController
{
    public function index():void
    {
        $this->guard();$tenantId=$this->tenantId();$filters=['estado'=>(string)$this->query('estado'),'q'=>(string)$this->query('q')];$meetings=Convivencia::meetings($tenantId);$supports=[];foreach($meetings as$meeting)$supports[$meeting['id']]=SoporteReservado::forEntity($tenantId,'convivencia','convivencia_reunion',(int)$meeting['id']);
        $this->view('tenant/convivencia/index',['title'=>'Convivencia y CCL reservado','cases'=>Convivencia::cases($tenantId,$filters),'meetings'=>$meetings,'meetingSupports'=>$supports,'filters'=>$filters,'responsibles'=>$this->responsibles($tenantId)]);
    }
    public function storeCase():void{$this->guard();try{$id=Convivencia::createCase($this->tenantId(),$_POST,Auth::id());}catch(DomainException $e){fail_validation(['general'=>$e->getMessage()],$_POST,'/convivencia');}flash('success','Caso recibido en expediente reservado.');redirect('/convivencia/casos/'.$id);}
    public function showCase(int$id):void{$this->guard();$case=$this->case($id);$this->view('tenant/convivencia/show',['title'=>$case['codigo'].' · Expediente reservado','case'=>$case,'responsibles'=>$this->responsibles($this->tenantId()),'supports'=>SoporteReservado::forEntity($this->tenantId(),'convivencia','convivencia_caso',$id)]);}
    public function updateCase(int$id):void{$this->guard();$this->case($id);try{Convivencia::updateCase($id,$this->tenantId(),$_POST,Auth::id());flash('success','Medidas, encaminamiento y estado actualizados.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/convivencia/casos/'.$id);}
    public function scheduleMeeting():void{$this->guard();try{Convivencia::scheduleMeeting($this->tenantId(),$_POST,Auth::id());flash('success','Reunión CCL programada; aún no consta como realizada.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/convivencia#reuniones');}
    public function recordMeeting(int$id):void{$this->guard();try{Convivencia::recordMeeting($id,$this->tenantId(),$_POST,Auth::id());flash('success','Resultado de reunión CCL registrado.');}catch(DomainException $e){flash('error',$e->getMessage());}redirect('/convivencia#reunion-'.$id);}
    public function export():void{$this->guard();$rows=Convivencia::aggregate($this->tenantId());CsvExport::download('convivencia-estadistica-anonima-'.date('Y-m-d').'.csv',['Tipo agregado','Estado','Cantidad'],array_map(static fn($r)=>[$r['tipo'],$r['estado'],$r['cantidad']],$rows));}
    private function guard():void{$this->requirePermission(Permission::COEXISTENCE,'Las quejas, denuncias y reuniones CCL requieren acceso reservado.');}
    private function case(int$id):array{return Convivencia::case($id,$this->tenantId())?:$this->notFound();}
    private function responsibles(int$tenantId):array{return array_values(array_filter(User::allByTenant($tenantId),static fn($u)=>$u['activo']&&in_array($u['rol'],['AdminEmpresa','ResponsableSST'],true)&&Permission::has((int)$u['id'],$tenantId,Permission::COEXISTENCE)));}
}
