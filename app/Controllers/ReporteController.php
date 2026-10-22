<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Autoreporte;
use App\Support\Audit;
use App\Support\Validator;

final class ReporteController extends BaseController
{
    public function index(): void
    {
        $this->view('tenant/reportes', [
            'title' => 'Autoreportes de trabajadores',
            'reports' => Autoreporte::forTenant($this->tenantId()),
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        if (Autoreporte::findForTenant($id, $tenantId) === null) {
            $this->notFound();
        }
        $data = ['estado' => (string) $this->input('estado'), 'respuesta' => (string) $this->input('respuesta')];
        $validator = new Validator($data);
        $validator->required('estado', 'estado')->in('estado', 'estado', ['Recibido', 'En revision', 'Gestionado'])
            ->required('respuesta', 'respuesta de seguimiento')->max('respuesta', 'respuesta', 3000);
        if ($validator->fails()) {
            fail_validation($validator->errors(), [], '/autoreportes');
        }
        Autoreporte::updateManagement($id, $tenantId, $data['estado'], $data['respuesta']);
        Audit::record('Autoreporte gestionado', 'Autoreporte', $id, ['estado' => $data['estado']]);
        flash('success', 'El seguimiento quedó disponible para la persona que realizó el reporte.');
        redirect('/autoreportes');
    }
}
