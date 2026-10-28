<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Evidencia;
use App\Models\IvcRequerimiento;
use App\Models\SanitarioRegistro;
use App\Services\SanitaryCatalog;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class SanitarioController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $this->view('tenant/sanitario/index', [
            'title' => 'Gestión sanitaria operativa',
            'types' => SanitaryCatalog::types(),
            'counts' => SanitarioRegistro::countsByType($tenantId),
            'recent' => SanitarioRegistro::forTenant($tenantId),
        ]);
    }

    public function records(): void
    {
        $tenantId = $this->tenantId();
        $type = (string) $this->query('tipo');
        $search = text_substr((string) $this->query('q'), 0, 180);
        if ($type !== '') {
            $this->definition($type);
        }
        $this->view('tenant/sanitario/records', [
            'title' => $type === '' ? 'Registros sanitarios' : SanitaryCatalog::definition($type)['label'],
            'types' => SanitaryCatalog::types(),
            'type' => $type,
            'definition' => $type === '' ? null : SanitaryCatalog::definition($type),
            'records' => SanitarioRegistro::forTenant($tenantId, $type, $search),
            'search' => $search,
        ]);
    }

    public function create(): void
    {
        $type = (string) $this->query('tipo');
        $definition = $this->definition($type);
        $tenantId = $this->tenantId();
        $selectedRequirement = (int) $this->query('requerimiento', 0);
        if ($selectedRequirement > 0 && IvcRequerimiento::findForTenant($selectedRequirement, $tenantId) === null) {
            $this->notFound('El requerimiento seleccionado no existe o no pertenece a tu empresa.');
        }
        $this->view('tenant/sanitario/create', [
            'title' => 'Nuevo registro · ' . $definition['label'],
            'type' => $type,
            'definition' => $definition,
            'requirements' => IvcRequerimiento::forTenant($tenantId),
            'selectedRequirement' => $selectedRequirement,
        ]);
    }

    public function store(): void
    {
        $tenantId = $this->tenantId();
        [$data, $errors] = $this->validateRecord($tenantId);
        $type = (string) $data['tipo'];
        if ($errors !== []) {
            fail_validation($errors, $data, '/sanitario/registros/nuevo?tipo=' . rawurlencode($type));
        }
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = Auth::id();
        $id = SanitarioRegistro::create($data);
        Audit::record('Registro sanitario creado', 'SanitarioRegistro', $id, ['tipo' => $type, 'estado' => $data['estado']]);
        flash('success', 'Registro guardado. Su estado no cierra automáticamente ningún requerimiento IVC.');
        redirect('/sanitario/registros/' . $id);
    }

    public function show(int $id): void
    {
        $tenantId = $this->tenantId();
        $record = $this->record($id, $tenantId);
        $definition = $this->definition($record['tipo']);
        $this->view('tenant/sanitario/show', [
            'title' => $record['titulo'],
            'record' => $record,
            'definition' => $definition,
            'evidenceCount' => Evidencia::countForEntity($tenantId, 'sanitario', (string) $id),
        ]);
    }

    public function edit(int $id): void
    {
        $tenantId = $this->tenantId();
        $record = $this->record($id, $tenantId);
        $definition = $this->definition($record['tipo']);
        $this->view('tenant/sanitario/edit', [
            'title' => 'Editar · ' . $record['titulo'],
            'record' => $record,
            'type' => $record['tipo'],
            'definition' => $definition,
            'requirements' => IvcRequerimiento::forTenant($tenantId),
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $current = $this->record($id, $tenantId);
        [$data, $errors] = $this->validateRecord($tenantId, $current['tipo']);
        if ($errors !== []) {
            fail_validation($errors, $data, '/sanitario/registros/' . $id . '/editar');
        }
        SanitarioRegistro::update($id, $tenantId, $data);
        Audit::record('Registro sanitario actualizado', 'SanitarioRegistro', $id, ['tipo' => $current['tipo'], 'estado' => $data['estado']]);
        flash('success', 'Registro sanitario actualizado.');
        redirect('/sanitario/registros/' . $id);
    }

    public function delete(int $id): void
    {
        $tenantId = $this->tenantId();
        $record = $this->record($id, $tenantId);
        if (Evidencia::countForEntity($tenantId, 'sanitario', (string) $id) > 0) {
            flash('error', 'No se puede eliminar el registro porque tiene evidencias vinculadas. Conserva la trazabilidad del soporte.');
            redirect('/sanitario/registros/' . $id);
        }
        SanitarioRegistro::delete($id, $tenantId);
        Audit::record('Registro sanitario eliminado', 'SanitarioRegistro', $id, ['tipo' => $record['tipo']]);
        flash('success', 'Registro eliminado. La acción quedó auditada.');
        redirect('/sanitario/registros?tipo=' . rawurlencode($record['tipo']));
    }

    private function validateRecord(int $tenantId, ?string $fixedType = null): array
    {
        $type = $fixedType ?? (string) $this->input('tipo');
        $definition = $this->definition($type);
        $data = [
            'tipo' => $type,
            'titulo' => (string) $this->input('titulo'),
            'fecha_registro' => (string) $this->input('fecha_registro'),
            'responsable' => (string) $this->input('responsable'),
            'estado' => (string) $this->input('estado', 'Pendiente'),
            'requerimiento_id' => $this->input('requerimiento_id', 0),
            'observaciones' => (string) $this->input('observaciones'),
            'datos' => [],
        ];
        $validator = new Validator($data);
        $validator->required('titulo', 'título')->max('titulo', 'título', 220)
            ->required('fecha_registro', 'fecha del registro')->date('fecha_registro', 'fecha del registro')
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)
            ->required('estado', 'estado')->in('estado', 'estado', SanitarioRegistro::ESTADOS)
            ->integer('requerimiento_id', 'requerimiento', 0)->max('observaciones', 'observaciones', 5000);
        $errors = $validator->errors();
        if (in_array($data['estado'], ['Verificado', 'No aplica'], true) && trim($data['observaciones']) === '') {
            $errors['observaciones'] = $data['estado'] === 'Verificado'
                ? 'Documenta el alcance y resultado de la verificación.'
                : 'Justifica por qué el registro no aplica.';
        }
        $requirementId = (int) $data['requerimiento_id'];
        if ($requirementId > 0 && IvcRequerimiento::findForTenant($requirementId, $tenantId) === null) {
            $errors['requerimiento_id'] = 'El requerimiento seleccionado no pertenece a tu empresa.';
        }

        $raw = $_POST['datos'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
            $errors['datos'] = 'Los campos específicos no tienen un formato válido.';
        }
        foreach ($definition['fields'] as $field) {
            $key = $field['key'];
            $rawValue = $raw[$key] ?? '';
            $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
            $data['datos'][$key] = $value;
            $label = $field['label'];
            if ($field['required'] && $value === '') {
                $errors['datos_' . $key] = "El campo {$label} es obligatorio.";
                continue;
            }
            if ($field['type'] === 'date' && $value !== '') {
                $date = \DateTime::createFromFormat('Y-m-d', $value);
                if ($date === false || $date->format('Y-m-d') !== $value) {
                    $errors['datos_' . $key] = "El campo {$label} debe tener una fecha válida.";
                }
            } elseif ($field['type'] === 'number' && $value !== '' && (!is_numeric($value) || (float) $value < 0)) {
                $errors['datos_' . $key] = "El campo {$label} debe ser un número mayor o igual a cero.";
            } elseif ($field['type'] === 'select' && $value !== '' && !in_array($value, $field['options'], true)) {
                $errors['datos_' . $key] = "El valor seleccionado para {$label} no es válido.";
            }
            $maximum = $field['type'] === 'textarea' ? 5000 : 500;
            if (text_length($value) > $maximum) {
                $errors['datos_' . $key] = "El campo {$label} no puede superar {$maximum} caracteres.";
            }
        }
        return [$data, $errors];
    }

    private function definition(string $type): array
    {
        $types = SanitaryCatalog::types();
        if (!isset($types[$type])) {
            $this->notFound('El tipo de registro sanitario solicitado no existe.');
        }
        return $types[$type];
    }

    private function record(int $id, int $tenantId): array
    {
        $record = SanitarioRegistro::findForTenant($id, $tenantId);
        if ($record === null) {
            $this->notFound('El registro sanitario no existe o no pertenece a tu empresa.');
        }
        return $record;
    }
}
