<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Documento;
use App\Models\PlanEmergencia;
use App\Models\SimulacroEmergencia;
use App\Models\Tenant;
use App\Services\OperationalDocument;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class EmergenciaController extends BaseController
{
    public function index(): void
    {
        $this->view('tenant/emergencias/index', [
            'title' => 'Planes de emergencia',
            'plans' => PlanEmergencia::forTenant($this->tenantId()),
        ]);
    }

    public function create(): void
    {
        $tenant = Tenant::find($this->tenantId());
        $this->view('tenant/emergencias/form', [
            'title' => 'Nuevo plan de emergencias',
            'plan' => $this->defaults($tenant ?? []),
            'action' => '/emergencias',
        ]);
    }

    public function store(): void
    {
        $tenantId = $this->tenantId();
        $data = $this->planInput();
        $data['tenant_id'] = $tenantId;
        $errors = $this->planErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $_POST, '/emergencias/nuevo');
        }
        $id = Database::transaction(static function () use ($data, $tenantId): int {
            $id = PlanEmergencia::create($data);
            $documentId = OperationalDocument::createEmergency($id, $tenantId);
            PlanEmergencia::linkDocument($id, $tenantId, $documentId);
            return $id;
        });
        Audit::record('Plan de emergencias generado en borrador', 'PlanEmergencia', $id);
        flash('success', 'Plan generado con los datos registrados. Revisa el documento, carga el plano y valida los recursos antes de aprobar.');
        redirect('/emergencias/' . $id);
    }

    public function show(int $id): void
    {
        $plan = $this->plan($id);
        $this->view('tenant/emergencias/show', [
            'title' => $plan['titulo'],
            'plan' => $plan,
            'tenant' => Tenant::find($this->tenantId()),
            'drills' => SimulacroEmergencia::forPlan($id, $this->tenantId()),
            'amenazas' => $this->decode($plan['amenazas_json']),
            'contactos' => $this->decode($plan['contactos_json']),
            'brigada' => $this->decode($plan['brigada_json']),
            'recursos' => $this->decode($plan['recursos_json']),
        ]);
    }

    public function edit(int $id): void
    {
        $plan = $this->plan($id);
        if ($plan['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un plan obsoleto se conserva solo para consulta.');
        }
        $this->view('tenant/emergencias/form', [
            'title' => 'Editar ' . $plan['codigo'],
            'plan' => $plan,
            'action' => '/emergencias/' . $id,
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = $this->plan($id);
        if ($before['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un plan obsoleto no puede editarse.');
        }
        $data = $this->planInput();
        $errors = $this->planErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $_POST, '/emergencias/' . $id . '/editar');
        }
        Database::transaction(static function () use ($id, $tenantId, $data): void {
            PlanEmergencia::update($id, $tenantId, $data);
            OperationalDocument::refreshEmergency($id, $tenantId);
        });
        Audit::record('Plan de emergencias actualizado; nueva versión en borrador', 'PlanEmergencia', $id, ['version_anterior' => (int) $before['version']]);
        flash('success', 'Plan actualizado y nueva versión documental en borrador. Requiere revisión y aprobación nuevamente.');
        redirect('/emergencias/' . $id);
    }

    public function approve(int $id): void
    {
        $plan = $this->plan($id);
        if ($plan['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo una versión en borrador puede aprobarse.');
        }
        if ((string) $this->input('revision_confirmada') !== '1') {
            flash('error', 'Confirma que revisaste los datos, los soportes y los recursos antes de aprobar.');
            redirect('/emergencias/' . $id . '#aprobar');
        }
        Documento::approve((int) $plan['documento_id'], $this->tenantId(), Auth::id());
        Audit::record('Plan de emergencias aprobado', 'PlanEmergencia', $id, ['version' => (int) $plan['version']]);
        flash('success', 'Versión aprobada. Continúa con divulgación, entrenamiento, simulacros, inspecciones y carga de evidencias.');
        redirect('/emergencias/' . $id);
    }

    public function obsolete(int $id): void
    {
        $plan = $this->plan($id);
        if ($plan['estado'] === 'Obsoleto') {
            $this->abort(403, 'El plan ya está marcado como obsoleto.');
        }
        Documento::setObsolete((int) $plan['documento_id'], $this->tenantId());
        Audit::record('Plan de emergencias marcado como obsoleto', 'PlanEmergencia', $id);
        flash('success', 'El plan quedó obsoleto y se conserva para trazabilidad.');
        redirect('/emergencias/' . $id);
    }

    public function delete(int $id): void
    {
        $tenantId = $this->tenantId();
        $plan = $this->plan($id);
        if ($plan['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo los borradores pueden eliminarse; los demás deben conservarse o marcarse obsoletos.');
        }
        if (SimulacroEmergencia::forPlan($id, $tenantId) !== []) {
            $this->abort(403, 'El plan tiene simulacros asociados y no puede eliminarse.');
        }
        Database::transaction(static function () use ($id, $tenantId, $plan): void {
            PlanEmergencia::delete($id, $tenantId);
            Documento::deleteForTenant((int) $plan['documento_id'], $tenantId);
        });
        Audit::record('Borrador de plan de emergencias eliminado', 'PlanEmergencia', $id);
        flash('success', 'Borrador y documento asociado eliminados.');
        redirect('/emergencias');
    }

    public function storeDrill(int $id): void
    {
        $tenantId = $this->tenantId();
        $this->plan($id);
        $data = $this->drillInput();
        $data['tenant_id'] = $tenantId;
        $data['plan_id'] = $id;
        $validator = $this->drillValidator($data);
        $errors = $validator->errors();
        if ($data['estado'] === 'Realizado' && (int) $data['participantes'] < 1) {
            $errors['participantes'] = 'Un simulacro realizado debe registrar al menos un participante.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/emergencias/' . $id . '#simulacros');
        }
        $drillId = SimulacroEmergencia::create($data);
        Audit::record('Simulacro de emergencia registrado', 'SimulacroEmergencia', $drillId, ['plan_id' => $id, 'estado' => $data['estado']]);
        flash('success', 'Simulacro registrado. Adjunta las evidencias en el panel documental.');
        redirect('/emergencias/' . $id . '#simulacros');
    }

    public function drillStatus(int $id, int $drillId): void
    {
        $this->plan($id);
        $drill = SimulacroEmergencia::findForTenant($drillId, $this->tenantId());
        if ($drill === null || (int) $drill['plan_id'] !== $id) {
            $this->notFound();
        }
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Programado', 'Realizado', 'Cancelado'], true)) {
            $this->abort(403, 'Estado de simulacro no válido.');
        }
        $result = (string) $this->input('resultado');
        $improvements = (string) $this->input('acciones_mejora');
        if ($status === 'Realizado' && trim($result) === '') {
            flash('error', 'Registra el resultado del simulacro antes de marcarlo como realizado.');
            redirect('/emergencias/' . $id . '#simulacros');
        }
        SimulacroEmergencia::setStatus($drillId, $this->tenantId(), $status, $result, $improvements);
        Audit::record('Estado de simulacro actualizado', 'SimulacroEmergencia', $drillId, ['estado' => $status]);
        flash('success', 'Seguimiento del simulacro actualizado.');
        redirect('/emergencias/' . $id . '#simulacros');
    }

    public function deleteDrill(int $id, int $drillId): void
    {
        $this->plan($id);
        $drill = SimulacroEmergencia::findForTenant($drillId, $this->tenantId());
        if ($drill === null || (int) $drill['plan_id'] !== $id) {
            $this->notFound();
        }
        SimulacroEmergencia::delete($drillId, $this->tenantId());
        Audit::record('Registro de simulacro eliminado', 'SimulacroEmergencia', $drillId, ['registro' => $drill]);
        flash('success', 'Registro de simulacro eliminado.');
        redirect('/emergencias/' . $id . '#simulacros');
    }

    private function plan(int $id): array
    {
        $plan = PlanEmergencia::findForTenant($id, $this->tenantId());
        if ($plan === null) {
            $this->notFound();
        }
        return $plan;
    }

    private function planInput(): array
    {
        $data = [];
        foreach (['titulo', 'centro_trabajo', 'direccion', 'alcance', 'rutas_evacuacion', 'puntos_encuentro',
            'procedimientos', 'plan_rescate', 'ayuda_mutua', 'responsable_plan', 'telefono_alarma',
            'proximo_simulacro', 'fecha_revision'] as $field) {
            $data[$field] = (string) $this->input($field);
        }
        $data['amenazas_json'] = json_encode($this->collectRows('amenaza', ['nombre', 'probabilidad', 'impacto', 'controles']), JSON_UNESCAPED_UNICODE);
        $data['contactos_json'] = json_encode($this->collectRows('contacto', ['nombre', 'tipo', 'telefono']), JSON_UNESCAPED_UNICODE);
        $data['brigada_json'] = json_encode($this->collectRows('brigada', ['nombre', 'rol', 'contacto']), JSON_UNESCAPED_UNICODE);
        $data['recursos_json'] = json_encode($this->collectRows('recurso', ['nombre', 'cantidad', 'ubicacion', 'estado', 'inspeccion']), JSON_UNESCAPED_UNICODE);
        return $data;
    }

    private function planErrors(array $data): array
    {
        $validator = new Validator($data);
        $validator->required('titulo', 'título')->max('titulo', 'título', 250)
            ->required('centro_trabajo', 'centro de trabajo')->required('alcance', 'alcance')
            ->required('rutas_evacuacion', 'descripción de rutas de evacuación')
            ->required('puntos_encuentro', 'puntos de encuentro')
            ->required('procedimientos', 'procedimientos de respuesta')
            ->required('responsable_plan', 'responsable del plan')
            ->date('proximo_simulacro', 'próximo simulacro')->date('fecha_revision', 'fecha de revisión');
        foreach (['alcance', 'rutas_evacuacion', 'puntos_encuentro', 'procedimientos', 'plan_rescate', 'ayuda_mutua'] as $field) {
            $validator->max($field, $field, 12000);
        }
        $errors = $validator->errors();
        if ($this->decode($data['amenazas_json']) === []) {
            $errors['amenazas'] = 'Registra al menos una amenaza con su análisis.';
        }
        if ($this->decode($data['contactos_json']) === []) {
            $errors['contactos'] = 'Registra al menos un contacto de emergencia.';
        }
        return $errors;
    }

    private function collectRows(string $prefix, array $fields): array
    {
        $rows = [];
        $names = $this->arrayInput($prefix . '_nombre');
        foreach (array_slice($names, 0, 50, true) as $index => $name) {
            $row = [];
            $hasContent = false;
            foreach ($fields as $field) {
                $values = $this->arrayInput($prefix . '_' . $field);
                $value = isset($values[$index]) && is_scalar($values[$index]) ? trim((string) $values[$index]) : '';
                $row[$field] = text_substr($value, 0, 2000);
                $hasContent = $hasContent || $value !== '';
            }
            if ($hasContent && trim((string) $row['nombre']) !== '') {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private function arrayInput(string $key): array
    {
        return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
    }

    private function drillInput(): array
    {
        return [
            'fecha' => (string) $this->input('fecha'), 'tipo' => (string) $this->input('tipo'),
            'participantes' => $this->input('participantes'), 'responsable' => (string) $this->input('responsable'),
            'resultado' => (string) $this->input('resultado'), 'acciones_mejora' => (string) $this->input('acciones_mejora'),
            'estado' => (string) $this->input('estado', 'Programado'),
        ];
    }

    private function drillValidator(array $data): Validator
    {
        $validator = new Validator($data);
        $validator->required('fecha', 'fecha')->date('fecha', 'fecha')->required('tipo', 'tipo')
            ->required('participantes', 'participantes')->integer('participantes', 'participantes', 0)
            ->required('responsable', 'responsable')->in('estado', 'estado', ['Programado', 'Realizado', 'Cancelado'])
            ->max('resultado', 'resultado', 5000)->max('acciones_mejora', 'acciones de mejora', 5000);
        if ($data['estado'] === 'Realizado') {
            $validator->required('resultado', 'resultado del simulacro');
        }
        return $validator;
    }

    private function decode(?string $json): array
    {
        $data = json_decode($json ?: '[]', true);
        return is_array($data) ? $data : [];
    }

    private function defaults(array $tenant): array
    {
        $user = Auth::user();
        return [
            'titulo' => 'Plan de prevención, preparación y respuesta ante emergencias',
            'centro_trabajo' => $tenant['razonsocial'] ?? '', 'direccion' => trim(($tenant['direccion'] ?? '') . ', ' . ($tenant['ciudad'] ?? ''), ', '),
            'alcance' => 'Todos los centros y turnos de trabajo, trabajadores, contratistas, proveedores y visitantes, según la organización real de la empresa.',
            'amenazas_json' => '[]', 'contactos_json' => json_encode([['nombre' => 'Bomberos', 'tipo' => 'Externo', 'telefono' => '119'], ['nombre' => 'Ambulancia', 'tipo' => 'Externo', 'telefono' => '123']], JSON_UNESCAPED_UNICODE),
            'brigada_json' => '[]', 'recursos_json' => '[]', 'rutas_evacuacion' => '', 'puntos_encuentro' => '',
            'procedimientos' => "Ante una emergencia:\n1. Active la alarma y comunique la ubicación y el tipo de evento.\n2. Suspenda las actividades si las condiciones lo exigen.\n3. Evacúe por las rutas señalizadas sin exponerse a mayor riesgo.\n4. Reporte asistencia en el punto de encuentro y no reingrese sin autorización.",
            'plan_rescate' => '', 'ayuda_mutua' => '', 'responsable_plan' => $user['nombre'] ?? '', 'telefono_alarma' => '',
            'proximo_simulacro' => date('Y-m-d', strtotime('+6 months')), 'fecha_revision' => date('Y-m-d', strtotime('+1 year')),
        ];
    }
}
