<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Documento;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\Peligro;
use App\Models\PlanTrabajo;
use App\Models\Tenant;
use App\Support\Audit;
use App\Support\Risk;
use App\Support\Validator;

final class PHVAController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $documents = Documento::forTenant($tenantId);
        $phases = [];
        foreach (['Planear', 'Hacer', 'Verificar', 'Actuar'] as $phase) {
            $phases[$phase] = array_values(array_filter($documents, static fn(array $document): bool => $document['fase_phva'] === $phase));
        }
        $this->view('tenant/phva/index', [
            'title' => 'Ciclo PHVA',
            'phases' => $phases,
            'documentStats' => Documento::stats($tenantId),
            'planStats' => PlanTrabajo::stats($tenantId),
            'hazardStats' => Peligro::stats($tenantId),
            'findings' => array_slice(Hallazgo::forTenant($tenantId, 'Abierto'), 0, 6),
            'indicators' => Indicador::latest($tenantId, 4),
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function plan(): void
    {
        $tenantId = $this->tenantId();
        $status = (string) $this->query('estado');
        if (!in_array($status, ['', 'Pendiente', 'En Proceso', 'Completado'], true)) $status = '';
        $this->view('tenant/phva/plan', [
            'title' => 'Plan Anual de Trabajo',
            'activities' => PlanTrabajo::forTenant($tenantId, $status),
            'stats' => PlanTrabajo::stats($tenantId),
            'status' => $status,
            'user' => \App\Support\Auth::user(),
        ]);
    }

    public function showPlan(int $id): void
    {
        $activity = PlanTrabajo::findForTenant($id, $this->tenantId());
        if ($activity === null) $this->notFound();
        $this->view('tenant/phva/plan-show', [
            'title' => 'Detalle de actividad del PAT',
            'activity' => $activity,
        ]);
    }

    public function storePlan(): void
    {
        $data = $this->planInput();
        $data['tenant_id'] = $this->tenantId();
        $errors = $this->planErrors($data);
        if ($errors !== []) fail_validation($errors, $data, '/plan-anual');
        $id = PlanTrabajo::create($data);
        Audit::record('Actividad del PAT creada', 'PlanTrabajo', $id);
        flash('success', 'La actividad fue agregada al Plan Anual de Trabajo. Ya puedes consultarla, editarla o actualizar su avance.');
        redirect('/plan-anual/' . $id);
    }

    public function editPlan(int $id): void
    {
        $activity = PlanTrabajo::findForTenant($id, $this->tenantId());
        if ($activity === null) $this->notFound();
        $this->view('tenant/phva/plan-form', [
            'title' => 'Editar actividad del PAT',
            'activity' => $activity,
        ]);
    }

    public function updatePlan(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = PlanTrabajo::findForTenant($id, $tenantId);
        if ($before === null) $this->notFound();
        $data = $this->planInput(true);
        $errors = $this->planErrors($data, true);
        if ($errors !== []) fail_validation($errors, $data, '/plan-anual/' . $id . '/editar');
        PlanTrabajo::update($id, $tenantId, $data);
        Audit::record('Actividad del PAT actualizada', 'PlanTrabajo', $id, ['antes' => $before, 'despues' => $data]);
        flash('success', 'Los datos de la actividad fueron actualizados correctamente.');
        redirect('/plan-anual/' . $id);
    }

    public function deletePlan(int $id): void
    {
        $tenantId = $this->tenantId();
        $activity = PlanTrabajo::findForTenant($id, $tenantId);
        if ($activity === null) $this->notFound();
        PlanTrabajo::delete($id, $tenantId);
        Audit::record('Actividad del PAT eliminada', 'PlanTrabajo', $id, ['registro' => $activity]);
        flash('success', 'Actividad eliminada del Plan Anual de Trabajo. La acción quedó registrada en auditoría; conserva por separado los soportes que deban mantenerse.');
        redirect('/plan-anual');
    }

    public function planStatus(int $id): void
    {
        $tenantId = $this->tenantId();
        if (PlanTrabajo::findForTenant($id, $tenantId) === null) $this->notFound();
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Pendiente', 'En Proceso', 'Completado'], true)) {
            $this->abort(403, 'Estado no válido.');
        }
        $return = $this->input('origen') === 'detalle' ? '/plan-anual/' . $id : '/plan-anual';
        $executionDate = (string) $this->input('fecha_ejecucion');
        if ($status === 'Completado' && $executionDate !== '') {
            $validator = new Validator(['fecha_ejecucion' => $executionDate]);
            $validator->date('fecha_ejecucion', 'fecha de ejecución');
            if ($validator->fails()) fail_validation($validator->errors(), ['estado' => $status, 'fecha_ejecucion' => $executionDate], $return);
        }
        PlanTrabajo::updateStatus($id, $tenantId, $status, $executionDate !== '' ? $executionDate : null);
        $updated = PlanTrabajo::findForTenant($id, $tenantId);
        Audit::record('Estado de actividad PAT actualizado', 'PlanTrabajo', $id, ['estado' => $status, 'fecha_ejecucion' => $updated['fecha_ejecucion']]);
        flash('success', 'El avance de la actividad fue actualizado.');
        redirect($return);
    }

    public function findings(): void
    {
        $tenantId = $this->tenantId();
        $status = (string) $this->query('estado');
        $this->view('tenant/phva/hallazgos', [
            'title' => 'Hallazgos y acciones ACPA',
            'findings' => Hallazgo::forTenant($tenantId, $status),
            'openCount' => Hallazgo::openCount($tenantId),
            'status' => $status,
        ]);
    }

    public function storeFinding(): void
    {
        $data = [
            'tenant_id' => $this->tenantId(), 'origen' => (string) $this->input('origen'),
            'descripcion' => (string) $this->input('descripcion'), 'accion_correctiva' => (string) $this->input('accion_correctiva'),
            'responsable' => (string) $this->input('responsable'), 'fecha_limite' => (string) $this->input('fecha_limite'),
        ];
        $validator = new Validator($data);
        $validator->required('origen', 'origen')->in('origen', 'origen', ['Auditoria', 'Inspeccion', 'Accidente', 'Reporte'])
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 3000)
            ->required('accion_correctiva', 'acción correctiva')->max('accion_correctiva', 'acción correctiva', 3000)
            ->required('responsable', 'responsable')->required('fecha_limite', 'fecha límite')->date('fecha_limite', 'fecha límite');
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/hallazgos');
        }
        $id = Hallazgo::create($data);
        Audit::record('Hallazgo ACPA creado', 'Hallazgo', $id, ['origen' => $data['origen']]);
        flash('success', 'Hallazgo registrado con su acción correctiva y responsable.');
        redirect('/hallazgos');
    }

    public function findingStatus(int $id): void
    {
        $tenantId = $this->tenantId();
        if (Hallazgo::findForTenant($id, $tenantId) === null) {
            $this->notFound();
        }
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Abierto', 'Cerrado'], true)) {
            $this->abort(403, 'Estado no válido.');
        }
        Hallazgo::setStatus($id, $tenantId, $status);
        Audit::record('Estado de hallazgo actualizado', 'Hallazgo', $id, ['estado' => $status]);
        flash('success', $status === 'Cerrado' ? 'Hallazgo cerrado. Conserva las evidencias de eficacia de la acción.' : 'Hallazgo reabierto para seguimiento.');
        redirect('/hallazgos');
    }

    public function matrix(): void
    {
        $tenantId = $this->tenantId();
        $classification = (string) $this->query('clasificacion');
        $this->view('tenant/phva/matriz', [
            'title' => 'Matriz IPEVR',
            'hazards' => Peligro::forTenant($tenantId, $classification),
            'stats' => Peligro::stats($tenantId),
            'classification' => $classification,
            'classifications' => $this->classifications(),
            'tenant' => Tenant::find($tenantId),
        ]);
    }

    public function storeHazard(): void
    {
        $data = $this->hazardInput();
        $data['tenant_id'] = $this->tenantId();
        $errors = $this->hazardErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/matriz-ipevr#nuevo-peligro');
        }
        $this->applyRiskCalculation($data);
        $id = Peligro::create($data);
        Audit::record('Peligro IPEVR registrado', 'Peligro', $id, ['clasificacion' => $data['clasificacion'], 'nivel_riesgo' => $data['nivel_riesgo']]);
        flash('success', 'Peligro agregado a la matriz IPEVR. Verifica la valoración con la metodología adoptada por la empresa.');
        redirect('/matriz-ipevr');
    }

    public function editHazard(int $id): void
    {
        $hazard = Peligro::findForTenant($id, $this->tenantId());
        if ($hazard === null) {
            $this->notFound();
        }
        $this->view('tenant/phva/peligro-form', [
            'title' => 'Editar peligro IPEVR',
            'hazard' => $hazard,
            'classifications' => $this->classifications(),
        ]);
    }

    public function updateHazard(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = Peligro::findForTenant($id, $tenantId);
        if ($before === null) {
            $this->notFound();
        }
        $data = $this->hazardInput();
        $errors = $this->hazardErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/matriz-ipevr/' . $id . '/editar');
        }
        $this->applyRiskCalculation($data);
        Peligro::update($id, $tenantId, $data);
        Audit::record('Peligro IPEVR actualizado', 'Peligro', $id, ['antes' => $before, 'despues' => $data]);
        flash('success', 'Peligro y valoración actualizados. Revisa si el cambio exige ajustar controles o el plan anual.');
        redirect('/matriz-ipevr');
    }

    public function deleteHazard(int $id): void
    {
        $tenantId = $this->tenantId();
        $hazard = Peligro::findForTenant($id, $tenantId);
        if ($hazard === null) {
            $this->notFound();
        }
        Peligro::delete($id, $tenantId);
        Audit::record('Peligro IPEVR eliminado', 'Peligro', $id, ['registro' => $hazard]);
        flash('success', 'Peligro eliminado de la matriz. La acción quedó registrada en auditoría.');
        redirect('/matriz-ipevr');
    }

    public function hazardStatus(int $id): void
    {
        $tenantId = $this->tenantId();
        $hazard = \App\Core\Database::first('SELECT id FROM peligros_ipevr WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($hazard === null) {
            $this->notFound();
        }
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Activo', 'Controlado'], true)) {
            $this->abort(403, 'Estado no válido.');
        }
        Peligro::setStatus($id, $tenantId, $status);
        Audit::record('Estado de peligro actualizado', 'Peligro', $id, ['estado' => $status]);
        flash('success', 'La matriz IPEVR fue actualizada.');
        redirect('/matriz-ipevr');
    }

    private function planInput(bool $withProgress = false): array
    {
        $data = [
            'actividad' => (string) $this->input('actividad'),
            'responsable' => (string) $this->input('responsable'),
            'fecha_programada' => (string) $this->input('fecha_programada'),
            'presupuesto' => $this->input('presupuesto', 0),
            'fase_phva' => (string) $this->input('fase_phva'),
        ];
        if ($withProgress) {
            $data['estado'] = (string) $this->input('estado');
            $data['fecha_ejecucion'] = (string) $this->input('fecha_ejecucion');
        }
        return $data;
    }

    private function planErrors(array $data, bool $withProgress = false): array
    {
        $validator = new Validator($data);
        $validator->required('actividad', 'actividad')->max('actividad', 'actividad', 300)
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)
            ->required('fecha_programada', 'fecha programada')->date('fecha_programada', 'fecha programada')
            ->numeric('presupuesto', 'presupuesto')
            ->required('fase_phva', 'fase PHVA')->in('fase_phva', 'fase PHVA', ['Planear', 'Hacer', 'Verificar', 'Actuar']);
        if ($withProgress) {
            $validator->required('estado', 'estado')->in('estado', 'estado', ['Pendiente', 'En Proceso', 'Completado']);
            if ($data['estado'] === 'Completado') {
                $validator->required('fecha_ejecucion', 'fecha de ejecución')->date('fecha_ejecucion', 'fecha de ejecución');
            }
        }
        return $validator->errors();
    }

    private function hazardInput(): array
    {
        return [
            'proceso' => (string) $this->input('proceso'),
            'cargo' => (string) $this->input('cargo'), 'peligro' => (string) $this->input('peligro'),
            'clasificacion' => (string) $this->input('clasificacion'), 'efectos' => (string) $this->input('efectos'),
            'controles_existentes' => (string) $this->input('controles_existentes'), 'medidas_control' => (string) $this->input('medidas_control'),
            'nivel_deficiencia' => $this->nullableInt('nivel_deficiencia'),
            'nivel_exposicion' => $this->nullableInt('nivel_exposicion'),
            'nivel_consecuencia' => $this->nullableInt('nivel_consecuencia'),
            'nivel_riesgo' => (string) $this->input('nivel_riesgo', 'Medio'),
        ];
    }

    private function hazardErrors(array $data): array
    {
        $validator = new Validator($data);
        $validator->required('proceso', 'proceso')->required('cargo', 'cargo')->required('peligro', 'peligro')
            ->max('peligro', 'peligro', 500)->required('clasificacion', 'clasificación')
            ->in('clasificacion', 'clasificación', $this->classifications())
            ->required('efectos', 'efectos posibles')->required('medidas_control', 'medidas de control')
            ->in('nivel_riesgo', 'nivel de riesgo', ['Bajo', 'Medio', 'Alto', 'Critico'])
            ->integer('nivel_deficiencia', 'nivel de deficiencia', 0, 10)
            ->integer('nivel_exposicion', 'nivel de exposición', 1, 4)
            ->integer('nivel_consecuencia', 'nivel de consecuencia', 1, 100);
        $errors = $validator->errors();
        $values = [$data['nivel_deficiencia'], $data['nivel_exposicion'], $data['nivel_consecuencia']];
        $provided = count(array_filter($values, static fn($value) => $value !== null));
        if ($provided > 0 && $provided < 3) {
            $errors['nivel_deficiencia'] = 'Diligencia deficiencia, exposición y consecuencia; o deja los tres campos vacíos.';
        }
        return $errors;
    }

    private function applyRiskCalculation(array &$data): void
    {
        $risk = Risk::calculate($data['nivel_deficiencia'], $data['nivel_exposicion'], $data['nivel_consecuencia'], $data['nivel_riesgo']);
        $data['nivel_probabilidad'] = $risk['probability'];
        $data['nivel_riesgo'] = $risk['level'];
    }

    private function classifications(): array
    {
        return ['Fisico', 'Quimico', 'Biologico', 'Biomecanico', 'Psicosocial', 'Seguridad', 'Publico', 'Natural', 'Tecnologico'];
    }

    private function nullableInt(string $key): ?int
    {
        $value = $this->input($key);
        return $value === '' || $value === null ? null : (int) $value;
    }
}
