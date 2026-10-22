<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Documento;
use App\Models\ProgramaActividad;
use App\Models\ProgramaPrevencion;
use App\Services\OperationalDocument;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class ProgramaController extends BaseController
{
    public static function categories(): array
    {
        return ['Promocion de la salud', 'Prevencion de accidentes', 'Prevencion de enfermedad laboral', 'Vigilancia epidemiologica'];
    }

    public function index(): void
    {
        $filters = ['anio' => (string) $this->query('anio'), 'categoria' => (string) $this->query('categoria')];
        if ($filters['anio'] !== '' && filter_var($filters['anio'], FILTER_VALIDATE_INT) === false) {
            $filters['anio'] = '';
        }
        if (!in_array($filters['categoria'], array_merge([''], self::categories()), true)) {
            $filters['categoria'] = '';
        }
        $programs = ProgramaPrevencion::forTenant($this->tenantId(), $filters);
        $years = [];
        foreach (ProgramaPrevencion::forTenant($this->tenantId()) as $program) {
            $years[(int) $program['anio']] = true;
        }
        $this->view('tenant/programas/index', [
            'title' => 'Promoción y prevención',
            'programs' => $programs,
            'filters' => $filters,
            'categories' => self::categories(),
            'years' => array_keys($years),
        ]);
    }

    public function create(): void
    {
        $this->view('tenant/programas/form', [
            'title' => 'Nuevo programa de promoción y prevención',
            'program' => $this->defaults(),
            'action' => '/programas-prevencion',
            'categories' => self::categories(),
        ]);
    }

    public function store(): void
    {
        $tenantId = $this->tenantId();
        $data = $this->programInput();
        $data['tenant_id'] = $tenantId;
        $errors = $this->programErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/programas-prevencion/nuevo');
        }
        $id = Database::transaction(static function () use ($data, $tenantId): int {
            $id = ProgramaPrevencion::create($data);
            $documentId = OperationalDocument::createProgram($id, $tenantId);
            ProgramaPrevencion::linkDocument($id, $tenantId, $documentId);
            return $id;
        });
        Audit::record('Programa de promoción y prevención creado', 'ProgramaPrevencion', $id, ['categoria' => $data['categoria']]);
        flash('success', 'Programa creado en borrador. Agrega las actividades, responsables, metas y recursos antes de aprobar.');
        redirect('/programas-prevencion/' . $id);
    }

    public function show(int $id): void
    {
        $program = $this->program($id);
        $this->view('tenant/programas/show', [
            'title' => $program['nombre'],
            'program' => $program,
            'activities' => ProgramaActividad::forProgram($id, $this->tenantId()),
        ]);
    }

    public function edit(int $id): void
    {
        $program = $this->program($id);
        if ($program['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un programa obsoleto se conserva solo para consulta.');
        }
        $this->view('tenant/programas/form', [
            'title' => 'Editar ' . $program['codigo'],
            'program' => $program,
            'action' => '/programas-prevencion/' . $id,
            'categories' => self::categories(),
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = $this->program($id);
        if ($before['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un programa obsoleto no puede editarse.');
        }
        $data = $this->programInput();
        $errors = $this->programErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/programas-prevencion/' . $id . '/editar');
        }
        Database::transaction(static function () use ($id, $tenantId, $data): void {
            ProgramaPrevencion::update($id, $tenantId, $data);
            OperationalDocument::refreshProgram($id, $tenantId);
        });
        Audit::record('Programa preventivo actualizado', 'ProgramaPrevencion', $id, ['version_anterior' => (int) $before['version']]);
        flash('success', 'Programa actualizado y nueva versión documental en borrador.');
        redirect('/programas-prevencion/' . $id);
    }

    public function approve(int $id): void
    {
        $program = $this->program($id);
        if ($program['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo una versión en borrador puede aprobarse.');
        }
        if (ProgramaActividad::forProgram($id, $this->tenantId()) === []) {
            flash('error', 'Agrega al menos una actividad con responsable, fecha y meta antes de aprobar.');
            redirect('/programas-prevencion/' . $id . '#actividades');
        }
        if ((string) $this->input('revision_confirmada') !== '1') {
            flash('error', 'Confirma la revisión del diagnóstico, los recursos y el cronograma.');
            redirect('/programas-prevencion/' . $id . '#aprobar');
        }
        Documento::approve((int) $program['documento_id'], $this->tenantId(), Auth::id());
        Audit::record('Programa preventivo aprobado', 'ProgramaPrevencion', $id, ['version' => (int) $program['version']]);
        flash('success', 'Versión aprobada. Ejecuta las actividades y conserva evidencias de resultados y eficacia.');
        redirect('/programas-prevencion/' . $id);
    }

    public function obsolete(int $id): void
    {
        $program = $this->program($id);
        if ($program['estado'] === 'Obsoleto') {
            $this->abort(403, 'El programa ya está obsoleto.');
        }
        Documento::setObsolete((int) $program['documento_id'], $this->tenantId());
        Audit::record('Programa preventivo marcado como obsoleto', 'ProgramaPrevencion', $id);
        flash('success', 'El programa quedó obsoleto y se conserva para trazabilidad.');
        redirect('/programas-prevencion/' . $id);
    }

    public function delete(int $id): void
    {
        $tenantId = $this->tenantId();
        $program = $this->program($id);
        if ($program['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo los borradores pueden eliminarse.');
        }
        Database::transaction(static function () use ($id, $tenantId, $program): void {
            ProgramaPrevencion::delete($id, $tenantId);
            Documento::deleteForTenant((int) $program['documento_id'], $tenantId);
        });
        Audit::record('Borrador de programa preventivo eliminado', 'ProgramaPrevencion', $id);
        flash('success', 'Borrador, actividades y documento asociado eliminados.');
        redirect('/programas-prevencion');
    }

    public function storeActivity(int $id): void
    {
        $this->program($id);
        $data = $this->activityInput();
        $data['tenant_id'] = $this->tenantId();
        $data['programa_id'] = $id;
        $validator = $this->activityValidator($data);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/programas-prevencion/' . $id . '#actividades');
        }
        $activityId = Database::transaction(function () use ($data, $id): int {
            $activityId = ProgramaActividad::create($data);
            OperationalDocument::refreshProgram($id, $this->tenantId());
            return $activityId;
        });
        Audit::record('Actividad de programa preventivo creada', 'ProgramaActividad', $activityId, ['programa_id' => $id]);
        flash('success', 'Actividad agregada. El documento quedó en una nueva versión en borrador.');
        redirect('/programas-prevencion/' . $id . '#actividades');
    }

    public function updateActivity(int $id, int $activityId): void
    {
        $this->program($id);
        $activity = ProgramaActividad::findForTenant($activityId, $this->tenantId());
        if ($activity === null || (int) $activity['programa_id'] !== $id) {
            $this->notFound();
        }
        $data = $this->activityInput();
        $validator = $this->activityValidator($data);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/programas-prevencion/' . $id . '#actividad-' . $activityId);
        }
        Database::transaction(function () use ($activityId, $data, $id): void {
            ProgramaActividad::update($activityId, $this->tenantId(), $data);
            OperationalDocument::refreshProgram($id, $this->tenantId());
        });
        Audit::record('Actividad de programa preventivo actualizada', 'ProgramaActividad', $activityId);
        flash('success', 'Actividad actualizada y documento sincronizado en borrador.');
        redirect('/programas-prevencion/' . $id . '#actividades');
    }

    public function activityStatus(int $id, int $activityId): void
    {
        $this->program($id);
        $activity = ProgramaActividad::findForTenant($activityId, $this->tenantId());
        if ($activity === null || (int) $activity['programa_id'] !== $id) {
            $this->notFound();
        }
        $status = (string) $this->input('estado');
        $evidence = (string) $this->input('evidencia');
        if (!in_array($status, ['Pendiente', 'En Proceso', 'Completado', 'Cancelado'], true)) {
            $this->abort(403, 'Estado de actividad no válido.');
        }
        if ($status === 'Completado' && trim($evidence) === '') {
            flash('error', 'Registra la evidencia o referencia del soporte antes de completar la actividad.');
            redirect('/programas-prevencion/' . $id . '#actividad-' . $activityId);
        }
        Database::transaction(function () use ($activityId, $status, $evidence, $id): void {
            ProgramaActividad::setStatus($activityId, $this->tenantId(), $status, $evidence);
            OperationalDocument::refreshProgram($id, $this->tenantId());
        });
        Audit::record('Estado de actividad preventiva actualizado', 'ProgramaActividad', $activityId, ['estado' => $status]);
        flash('success', 'Avance actualizado y nueva versión documental en borrador.');
        redirect('/programas-prevencion/' . $id . '#actividad-' . $activityId);
    }

    public function deleteActivity(int $id, int $activityId): void
    {
        $this->program($id);
        $activity = ProgramaActividad::findForTenant($activityId, $this->tenantId());
        if ($activity === null || (int) $activity['programa_id'] !== $id) {
            $this->notFound();
        }
        Database::transaction(function () use ($activityId, $id): void {
            ProgramaActividad::delete($activityId, $this->tenantId());
            OperationalDocument::refreshProgram($id, $this->tenantId());
        });
        Audit::record('Actividad de programa preventivo eliminada', 'ProgramaActividad', $activityId, ['registro' => $activity]);
        flash('success', 'Actividad eliminada y documento sincronizado en borrador.');
        redirect('/programas-prevencion/' . $id . '#actividades');
    }

    private function program(int $id): array
    {
        $program = ProgramaPrevencion::findForTenant($id, $this->tenantId());
        if ($program === null) {
            $this->notFound();
        }
        return $program;
    }

    private function programInput(): array
    {
        $data = [];
        foreach (['categoria', 'nombre', 'anio', 'diagnostico', 'objetivo_general', 'objetivos_especificos',
            'poblacion', 'peligros_relacionados', 'metas_indicadores', 'responsable', 'recursos', 'presupuesto',
            'fecha_inicio', 'fecha_fin'] as $field) {
            $data[$field] = (string) $this->input($field);
        }
        return $data;
    }

    private function programErrors(array $data): array
    {
        $validator = new Validator($data);
        $validator->required('categoria', 'categoría')->in('categoria', 'categoría', self::categories())
            ->required('nombre', 'nombre')->max('nombre', 'nombre', 250)
            ->required('anio', 'año')->integer('anio', 'año', 2000, 2200)
            ->required('diagnostico', 'diagnóstico')->required('objetivo_general', 'objetivo general')
            ->required('poblacion', 'población')->required('metas_indicadores', 'metas e indicadores')
            ->required('responsable', 'responsable')->numeric('presupuesto', 'presupuesto', 0)
            ->required('fecha_inicio', 'fecha de inicio')->date('fecha_inicio', 'fecha de inicio')
            ->required('fecha_fin', 'fecha de fin')->date('fecha_fin', 'fecha de fin');
        foreach (['diagnostico', 'objetivo_general', 'objetivos_especificos', 'poblacion', 'peligros_relacionados', 'metas_indicadores', 'recursos'] as $field) {
            $validator->max($field, $field, 10000);
        }
        $errors = $validator->errors();
        if ($data['fecha_inicio'] !== '' && $data['fecha_fin'] !== '' && strtotime($data['fecha_fin']) < strtotime($data['fecha_inicio'])) {
            $errors['fecha_fin'] = 'La fecha final debe ser igual o posterior a la fecha de inicio.';
        }
        return $errors;
    }

    private function activityInput(): array
    {
        $data = [];
        foreach (['actividad', 'responsable', 'fecha_programada', 'indicador', 'meta', 'recursos'] as $field) {
            $data[$field] = (string) $this->input($field);
        }
        return $data;
    }

    private function activityValidator(array $data): Validator
    {
        $validator = new Validator($data);
        $validator->required('actividad', 'actividad')->max('actividad', 'actividad', 1000)
            ->required('responsable', 'responsable')->required('fecha_programada', 'fecha')->date('fecha_programada', 'fecha')
            ->required('indicador', 'indicador')->required('meta', 'meta')
            ->max('indicador', 'indicador', 1000)->max('meta', 'meta', 1000)->max('recursos', 'recursos', 3000);
        return $validator;
    }

    private function defaults(): array
    {
        $user = Auth::user();
        return [
            'categoria' => 'Prevencion de accidentes', 'nombre' => '', 'anio' => date('Y'), 'diagnostico' => '',
            'objetivo_general' => '', 'objetivos_especificos' => '', 'poblacion' => 'Todos los trabajadores y contratistas expuestos, según el diagnóstico.',
            'peligros_relacionados' => '', 'metas_indicadores' => '', 'responsable' => $user['nombre'] ?? '',
            'recursos' => '', 'presupuesto' => '0', 'fecha_inicio' => date('Y-01-01'), 'fecha_fin' => date('Y-12-31'),
        ];
    }
}
