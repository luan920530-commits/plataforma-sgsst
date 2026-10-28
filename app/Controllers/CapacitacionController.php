<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Capacitacion;
use App\Models\Empleado;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use DomainException;

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

    public function show(int $id): void
    {
        $training = Capacitacion::findForTenant($id, $this->tenantId()) ?: $this->notFound();
        $this->view('tenant/phva/capacitacion-show', ['title' => $training['tema'], 'training' => $training, 'attendees' => Capacitacion::attendees($id, $this->tenantId()), 'employees' => Empleado::forTenant($this->tenantId()), 'reviewedEvidence' => \App\Models\Evidencia::reviewedCountForEntity($this->tenantId(), 'capacitacion', (string) $id)]);
    }

    public function addAttendee(int $id): void
    {
        try { Capacitacion::addAttendee($id, $this->tenantId(), $_POST); Audit::record('Asistente agregado a capacitación', 'Capacitacion', $id); flash('success', 'Persona agregada; la asistencia aún está pendiente de confirmar.'); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/capacitaciones/' . $id . '#asistentes');
    }

    public function updateAttendee(int $id, int $attendeeId): void
    {
        try { Capacitacion::updateAttendee($attendeeId, $id, $this->tenantId(), $_POST); flash('success', 'Asistencia y evaluación actualizadas.'); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/capacitaciones/' . $id . '#asistentes');
    }

    public function deleteAttendee(int $id, int $attendeeId): void
    {
        try { Capacitacion::deleteAttendee($attendeeId, $id, $this->tenantId()); flash('success', 'Asistente retirado de la programación.'); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/capacitaciones/' . $id . '#asistentes');
    }

    public function efficacy(int $id): void
    {
        try { Capacitacion::recordEfficacy($id, $this->tenantId(), (string) $this->input('eficacia'), (string) $this->input('fecha_eficacia'), Auth::id()); Audit::record('Eficacia de capacitación verificada', 'Capacitacion', $id); flash('success', 'Evaluación de eficacia registrada.'); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/capacitaciones/' . $id . '#eficacia');
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
        try { Capacitacion::setStatus($id, $tenantId, $status); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); redirect('/capacitaciones/' . $id); }
        Audit::record('Estado de capacitación actualizado', 'Capacitacion', $id, ['estado' => $status]);
        flash('success', $status === 'Realizada' ? 'Capacitación realizada con asistencia y evidencia revisada.' : 'El estado de la capacitación fue actualizado.');
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
            'publico_objetivo' => (string) $this->input('publico_objetivo'),
            'competencia' => (string) $this->input('competencia'),
            'contenido' => (string) $this->input('contenido'),
            'idoneidad_instructor' => (string) $this->input('idoneidad_instructor'),
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
            ->required('modalidad', 'modalidad')->in('modalidad', 'modalidad', ['Presencial', 'Virtual', 'Mixta'])
            ->max('publico_objetivo', 'público objetivo', 2000)->max('competencia', 'competencia', 2000)
            ->max('contenido', 'contenido', 6000)->max('idoneidad_instructor', 'idoneidad del instructor', 2000);
        return $validator;
    }
}
