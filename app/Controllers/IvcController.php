<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\IvcActa;
use App\Models\IvcRequerimiento;
use App\Services\SanitaryCatalog;
use App\Support\Audit;
use App\Support\Validator;
use PDOException;

final class IvcController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $this->view('tenant/ivc/index', [
            'title' => 'Cumplimiento sanitario IVC',
            'actas' => IvcActa::forTenant($tenantId),
            'stats' => IvcActa::stats($tenantId),
        ]);
    }

    public function create(): void
    {
        $this->view('tenant/ivc/create', ['title' => 'Registrar acta IVC']);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validateActa();
        if ($errors !== []) {
            fail_validation($errors, $data, '/ivc/nueva');
        }
        $data['tenant_id'] = $this->tenantId();
        try {
            $id = IvcActa::create($data);
        } catch (PDOException) {
            fail_validation(['numero_acta' => 'Ya existe un acta con este número en la empresa.'], $data, '/ivc/nueva');
        }
        Audit::record('Acta IVC creada', 'IvcActa', $id, ['numero_acta' => $data['numero_acta']]);
        flash('success', 'Acta registrada. Agrega sus requerimientos y conserva la fuente en el centro de evidencias.');
        redirect('/ivc/' . $id);
    }

    public function show(int $id): void
    {
        $tenantId = $this->tenantId();
        $acta = $this->acta($id, $tenantId);
        $requirements = IvcRequerimiento::forActa($id, $tenantId);
        $filters = ['estado' => (string) $this->query('estado'), 'origen' => (string) $this->query('origen')];
        if (!in_array($filters['estado'], array_merge([''], IvcRequerimiento::ESTADOS), true)) {
            $filters['estado'] = '';
        }
        if (!in_array($filters['origen'], array_merge([''], IvcRequerimiento::ORIGENES), true)) {
            $filters['origen'] = '';
        }
        $filtered = array_values(array_filter($requirements, static fn(array $row): bool =>
            ($filters['estado'] === '' || $row['estado'] === $filters['estado'])
            && ($filters['origen'] === '' || $row['origen'] === $filters['origen'])
        ));
        $this->view('tenant/ivc/show', [
            'title' => 'Acta ' . $acta['numero_acta'],
            'acta' => $acta,
            'requirements' => $filtered,
            'totalRequirements' => count($requirements),
            'filters' => $filters,
            'types' => SanitaryCatalog::types(),
            'states' => IvcRequerimiento::ESTADOS,
            'origins' => IvcRequerimiento::ORIGENES,
        ]);
    }

    public function edit(int $id): void
    {
        $acta = $this->acta($id, $this->tenantId());
        $this->view('tenant/ivc/edit', ['title' => 'Editar acta ' . $acta['numero_acta'], 'acta' => $acta]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $this->acta($id, $tenantId);
        [$data, $errors] = $this->validateActa();
        if ($errors !== []) {
            fail_validation($errors, $data, '/ivc/' . $id . '/editar');
        }
        try {
            IvcActa::update($id, $tenantId, $data);
        } catch (PDOException) {
            fail_validation(['numero_acta' => 'Ya existe un acta con este número en la empresa.'], $data, '/ivc/' . $id . '/editar');
        }
        Audit::record('Acta IVC actualizada', 'IvcActa', $id, ['numero_acta' => $data['numero_acta']]);
        flash('success', 'Información del acta actualizada.');
        redirect('/ivc/' . $id);
    }

    public function delete(int $id): void
    {
        $tenantId = $this->tenantId();
        $acta = $this->acta($id, $tenantId);
        $dependencies = IvcActa::dependencies($id, $tenantId);
        if ((int) $dependencies['evidencias'] > 0 || (int) $dependencies['registros'] > 0) {
            flash('error', 'No se puede eliminar el acta: tiene evidencias o registros sanitarios vinculados. Conserva la trazabilidad o desvincula primero los registros sin borrar sus soportes.');
            redirect('/ivc/' . $id);
        }
        IvcActa::delete($id, $tenantId);
        Audit::record('Acta IVC eliminada', 'IvcActa', $id, ['numero_acta' => $acta['numero_acta']]);
        flash('success', 'Acta y requerimientos sin dependencias eliminados. La acción quedó auditada.');
        redirect('/ivc');
    }

    public function storeRequirement(int $actaId): void
    {
        $tenantId = $this->tenantId();
        $this->acta($actaId, $tenantId);
        [$data, $errors] = $this->validateRequirement(null, $tenantId);
        $data['acta_id'] = $actaId;
        if ($errors !== []) {
            fail_validation($errors, $data, '/ivc/' . $actaId . '#nuevo-requerimiento');
        }
        $data['tenant_id'] = $tenantId;
        try {
            $id = IvcRequerimiento::create($data);
        } catch (PDOException) {
            fail_validation(['codigo' => 'El código ya existe dentro de esta acta.'], $data, '/ivc/' . $actaId . '#nuevo-requerimiento');
        }
        Audit::record('Requerimiento IVC creado', 'IvcRequerimiento', $id, ['acta_id' => $actaId, 'codigo' => $data['codigo']]);
        flash('success', 'Requerimiento agregado con estado y trazabilidad independiente.');
        redirect('/ivc/' . $actaId . '#requerimiento-' . $id);
    }

    public function editRequirement(int $id): void
    {
        $tenantId = $this->tenantId();
        $requirement = $this->requirement($id, $tenantId);
        $acta = $this->acta((int) $requirement['acta_id'], $tenantId);
        $this->view('tenant/ivc/edit-requirement', [
            'title' => 'Editar requerimiento ' . $requirement['codigo'],
            'acta' => $acta,
            'requirement' => $requirement,
            'types' => SanitaryCatalog::types(),
            'states' => IvcRequerimiento::ESTADOS,
            'origins' => IvcRequerimiento::ORIGENES,
        ]);
    }

    public function updateRequirement(int $id): void
    {
        $tenantId = $this->tenantId();
        $current = $this->requirement($id, $tenantId);
        [$data, $errors] = $this->validateRequirement($id, $tenantId);
        if ($errors !== []) {
            fail_validation($errors, $data, '/ivc/requerimientos/' . $id . '/editar');
        }
        try {
            IvcRequerimiento::update($id, $tenantId, $data);
        } catch (PDOException) {
            fail_validation(['codigo' => 'El código ya existe dentro de esta acta.'], $data, '/ivc/requerimientos/' . $id . '/editar');
        }
        Audit::record('Requerimiento IVC actualizado', 'IvcRequerimiento', $id, ['estado' => $data['estado'], 'codigo' => $data['codigo']]);
        flash('success', 'Requerimiento actualizado. El estado representa seguimiento interno, no aceptación de la autoridad.');
        redirect('/ivc/' . $current['acta_id'] . '#requerimiento-' . $id);
    }

    public function deleteRequirement(int $id): void
    {
        $tenantId = $this->tenantId();
        $requirement = $this->requirement($id, $tenantId);
        $dependencies = IvcRequerimiento::dependencies($id, $tenantId);
        if ((int) $dependencies['evidencias'] > 0 || (int) $dependencies['registros'] > 0) {
            flash('error', 'No se puede eliminar el requerimiento porque tiene evidencias o registros sanitarios vinculados.');
            redirect('/ivc/' . $requirement['acta_id'] . '#requerimiento-' . $id);
        }
        IvcRequerimiento::delete($id, $tenantId);
        Audit::record('Requerimiento IVC eliminado', 'IvcRequerimiento', $id, ['codigo' => $requirement['codigo']]);
        flash('success', 'Requerimiento eliminado. La acción quedó auditada.');
        redirect('/ivc/' . $requirement['acta_id']);
    }

    private function validateActa(): array
    {
        $data = [
            'numero_acta' => (string) $this->input('numero_acta'),
            'autoridad' => (string) $this->input('autoridad'),
            'fecha_visita' => (string) $this->input('fecha_visita'),
            'fecha_notificacion' => (string) $this->input('fecha_notificacion'),
            'fecha_limite' => (string) $this->input('fecha_limite'),
            'concepto' => (string) $this->input('concepto'),
            'porcentaje' => $this->input('porcentaje'),
            'direccion_visita' => (string) $this->input('direccion_visita'),
            'fuente_nombre' => (string) $this->input('fuente_nombre'),
            'fuente_sha256' => text_lower((string) $this->input('fuente_sha256')),
            'observaciones' => (string) $this->input('observaciones'),
        ];
        $validator = new Validator($data);
        $validator->required('numero_acta', 'número de acta')->max('numero_acta', 'número de acta', 100)
            ->required('autoridad', 'autoridad')->max('autoridad', 'autoridad', 220)
            ->required('fecha_visita', 'fecha de visita')->date('fecha_visita', 'fecha de visita')
            ->date('fecha_notificacion', 'fecha de notificación')->date('fecha_limite', 'fecha límite')
            ->required('concepto', 'concepto')->max('concepto', 'concepto', 250)
            ->numeric('porcentaje', 'porcentaje')->max('direccion_visita', 'dirección', 300)
            ->max('fuente_nombre', 'nombre de la fuente', 255)->max('observaciones', 'observaciones', 6000);
        $errors = $validator->errors();
        if ($data['porcentaje'] !== '' && is_numeric($data['porcentaje']) && (float) $data['porcentaje'] > 100) {
            $errors['porcentaje'] = 'El porcentaje no puede superar 100.';
        }
        if ($data['fuente_sha256'] !== '' && !preg_match('/^[a-f0-9]{64}$/D', $data['fuente_sha256'])) {
            $errors['fuente_sha256'] = 'La huella SHA-256 debe contener exactamente 64 caracteres hexadecimales.';
        }
        if ($data['fecha_visita'] !== '' && $data['fecha_limite'] !== '' && strtotime($data['fecha_limite']) < strtotime($data['fecha_visita'])) {
            $errors['fecha_limite'] = 'La fecha límite no puede ser anterior a la visita.';
        }
        return [$data, $errors];
    }

    private function validateRequirement(?int $id, int $tenantId): array
    {
        $data = [
            'codigo' => (string) $this->input('codigo'), 'origen' => (string) $this->input('origen'),
            'tema' => (string) $this->input('tema', 'general'), 'hallazgo' => (string) $this->input('hallazgo'),
            'accion' => (string) $this->input('accion'), 'responsable' => (string) $this->input('responsable'),
            'fecha_compromiso' => (string) $this->input('fecha_compromiso'), 'estado' => (string) $this->input('estado', 'Pendiente'),
            'evidencia_requerida' => (string) $this->input('evidencia_requerida'), 'verificacion' => (string) $this->input('verificacion'),
        ];
        $validator = new Validator($data);
        $validator->required('codigo', 'código')->max('codigo', 'código', 30)
            ->required('origen', 'origen')->in('origen', 'origen', IvcRequerimiento::ORIGENES)
            ->required('tema', 'tema')->in('tema', 'tema', array_merge(['general', 'emergencias', 'sgsst', 'capacitaciones'], array_keys(SanitaryCatalog::types())))
            ->required('hallazgo', 'hallazgo')->max('hallazgo', 'hallazgo', 5000)
            ->required('accion', 'acción propuesta')->max('accion', 'acción propuesta', 5000)
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)
            ->date('fecha_compromiso', 'fecha de compromiso')->required('estado', 'estado')
            ->in('estado', 'estado', IvcRequerimiento::ESTADOS)->max('evidencia_requerida', 'evidencia requerida', 3000)
            ->max('verificacion', 'verificación', 5000);
        $errors = $validator->errors();
        if (in_array($data['estado'], ['Cerrado', 'No aplica'], true)) {
            $validator->required('verificacion', $data['estado'] === 'Cerrado' ? 'verificación de cierre' : 'justificación de no aplicabilidad');
            $errors = $validator->errors();
        }
        if ($data['estado'] === 'Cerrado' && ($id === null || IvcRequerimiento::reviewedEvidenceCount($id, $tenantId) < 1)) {
            $errors['estado'] = 'Para cerrar se requiere al menos una evidencia vinculada y revisada por una persona. Guarda primero el requerimiento, adjunta la evidencia y registra su revisión.';
        }
        return [$data, $errors];
    }

    private function acta(int $id, int $tenantId): array
    {
        $acta = IvcActa::findForTenant($id, $tenantId);
        if ($acta === null) {
            $this->notFound('El acta no existe o no pertenece a tu empresa.');
        }
        return $acta;
    }

    private function requirement(int $id, int $tenantId): array
    {
        $requirement = IvcRequerimiento::findForTenant($id, $tenantId);
        if ($requirement === null) {
            $this->notFound('El requerimiento no existe o no pertenece a tu empresa.');
        }
        return $requirement;
    }
}
