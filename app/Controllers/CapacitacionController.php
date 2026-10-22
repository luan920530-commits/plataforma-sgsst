<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Capacitacion;
use App\Support\Audit;
use App\Support\Validator;

final class CapacitacionController extends BaseController
{
    public function index(): void
    {
        $this->view('tenant/phva/capacitaciones', [
            'title' => 'Programa de capacitaciones',
            'trainings' => Capacitacion::forTenant($this->tenantId()),
        ]);
    }

    public function store(): void
    {
        $data = [
            'tenant_id' => $this->tenantId(), 'tema' => (string) $this->input('tema'),
            'descripcion' => (string) $this->input('descripcion'), 'responsable' => (string) $this->input('responsable'),
            'fecha_programada' => (string) $this->input('fecha_programada'), 'duracion_horas' => $this->input('duracion_horas'),
            'modalidad' => (string) $this->input('modalidad'),
        ];
        $validator = new Validator($data);
        $validator->required('tema', 'tema')->max('tema', 'tema', 250)->required('responsable', 'responsable')
            ->required('fecha_programada', 'fecha')->date('fecha_programada', 'fecha')
            ->required('duracion_horas', 'duración')->numeric('duracion_horas', 'duración', 0.1)
            ->required('modalidad', 'modalidad')->in('modalidad', 'modalidad', ['Presencial', 'Virtual', 'Mixta']);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/capacitaciones');
        }
        $id = Capacitacion::create($data);
        Audit::record('Capacitación programada', 'Capacitacion', $id);
        flash('success', 'La capacitación fue programada.');
        redirect('/capacitaciones');
    }

    public function status(int $id): void
    {
        $tenantId = $this->tenantId();
        if (Capacitacion::findForTenant($id, $tenantId) === null) {
            $this->notFound();
        }
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Programada', 'Realizada', 'Cancelada'], true)) {
            $this->abort(403, 'Estado no válido.');
        }
        Capacitacion::setStatus($id, $tenantId, $status);
        Audit::record('Estado de capacitación actualizado', 'Capacitacion', $id, ['estado' => $status]);
        flash('success', 'El estado de la capacitación fue actualizado. Adjunta las evidencias de asistencia en tu archivo documental.');
        redirect('/capacitaciones');
    }
}
