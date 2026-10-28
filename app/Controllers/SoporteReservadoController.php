<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Permission;
use App\Models\SoporteReservado;
use App\Services\EvidenceStorage;
use App\Support\Auth;
use DomainException;
use RuntimeException;

final class SoporteReservadoController extends BaseController
{
    public function store():void
    {
        $area=(string)$this->input('area');$this->authorizeArea($area);$return=$this->returnPath($area);
        try{$id=SoporteReservado::upload($this->tenantId(),$area,(string)$this->input('entidad_tipo'),(int)$this->input('entidad_id'),$_POST,$_FILES['archivo']??[],Auth::id());flash('success','Soporte guardado en el expediente reservado, fuera de la biblioteca general.');}catch(DomainException|RuntimeException $e){flash('error',$e->getMessage());}redirect($return);
    }
    public function download(int $id):void
    {
        $support=SoporteReservado::findForTenant($id,$this->tenantId())?:$this->notFound();$this->authorizeArea($support['area']);
        try{$path=EvidenceStorage::reservedPath($this->tenantId(),$support['nombre_almacenado']);if(!hash_equals($support['hash_sha256'],hash_file('sha256',$path)))throw new RuntimeException('La integridad del archivo no coincide; no se entregará una copia alterada.');}catch(RuntimeException|DomainException $e){$this->abort(409,$e->getMessage());}
        while(ob_get_level()>0)ob_end_clean();header('Content-Type: '.$support['mime']);header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',$support['nombre_original']).'"');header('Cache-Control: private, no-store');readfile($path);exit;
    }
    public function review(int $id):void
    {
        $support=SoporteReservado::findForTenant($id,$this->tenantId())?:$this->notFound();$this->authorizeArea($support['area']);
        SoporteReservado::review($id,$this->tenantId(),$support['area'],Auth::id());flash('success','Revisión humana registrada.');redirect($this->returnPath($support['area']));
    }
    private function authorizeArea(string $area):void
    {
        if($area==='salud'){$this->requirePermission(Permission::HEALTH,'Este soporte pertenece al expediente reservado de salud.');return;}
        if($area==='convivencia'){$this->requirePermission(Permission::COEXISTENCE,'Este soporte pertenece al expediente reservado de convivencia.');return;}
        $this->abort(403,'Área reservada no válida.');
    }
    private function returnPath(string $area):string
    {
        $path=(string)$this->input('return_url',$area==='salud'?'/salud':'/convivencia');$base=$area==='salud'?'/salud':'/convivencia';
        if(!str_starts_with($path,$base)||str_contains($path,'..')||str_contains($path,"\r")||str_contains($path,"\n"))return$base;return$path;
    }
}
