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
        $data = $this->trainingInput();
        $data['tenant_id'] = $this->tenantId();
        $validator = $this->validateTraining($data);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/capacitaciones');
        }
        $id = Capacitacion::create($data);
        Audit::record('Capacitación programada', 'Capacitacion', $id);
        flash('success', 'La capacitación fue programada.');
        redirect('/capacitaciones');
    }

    public function edit(int $id): void
    {
        $training = Capacitacion::findForTenant($id, $this->tenantId());
        if ($training === null) {
            $this->notFound();
        }
        $this->view('tenant/phva/capacitacion-form', [
            'title' => 'Editar capacitación',
            'training' => $training,
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = Capacitacion::findForTenant($id, $tenantId);
        if ($before === null) {
            $this->notFound();
        }
        $data = $this->trainingInput();
        $validator = $this->validateTraining($data);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/capacitaciones/' . $id . '/editar');
        }
        Capacitacion::update($id, $tenantId, $data);
        Audit::record('Capacitación actualizada', 'Capacitacion', $id, ['antes' => $before, 'despues' => $data]);
        flash('success', 'La capacitación fue actualizada correctamente.');
        redirect('/capacitaciones');
    }

    public function delete(int $id): void
    {
        $tenantId = $this->tenantId();
        $training = Capacitacion::findForTenant($id, $tenantId);
        if ($training === null) {
            $this->notFound();
        }
        Capacitacion::delete($id, $tenantId);
        Audit::record('Capacitación eliminada', 'Capacitacion', $id, ['registro' => $training]);
        flash('success', 'Capacitación eliminada. Conserva por separado las evidencias de formación ya realizadas.');
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

    private function trainingInput(): array
    {
        return [
            'tema' => (string) $this->input('tema'),
            'descripcion' => (string) $this->input('descripcion'),
            'responsable' => (string) $this->input('responsable'),
            'fecha_programada' => (string) $this->input('fecha_programada'),
            'duracion_horas' => $this->input('duracion_horas'),
            'modalidad' => (string) $this->input('modalidad'),
        ];
    }

    private function validateTraining(array $data): Validator
    {
        $validator = new Validator($data);
        $validator->required('tema', 'tema')->max('tema', 'tema', 250)
            ->max('descripcion', 'descripción', 3000)
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)
            ->required('fecha_programada', 'fecha')->date('fecha_programada', 'fecha')
            ->required('duracion_horas', 'duración')->numeric('duracion_horas', 'duración', 0.1)
            ->required('modalidad', 'modalidad')->in('modalidad', 'modalidad', ['Presencial', 'Virtual', 'Mixta']);
        return $validator;
    }
}
