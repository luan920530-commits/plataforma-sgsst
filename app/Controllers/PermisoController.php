<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\PermisoTrabajo;
use App\Models\Tenant;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;

final class PermisoController extends BaseController
{
    public static function types(): array
    {
        return ['General', 'Alturas', 'Espacio confinado', 'Electrico', 'Trabajo en caliente', 'Excavacion', 'Otro'];
    }

    public function index(): void
    {
        $tenantId = $this->tenantId();
        $filters = ['estado' => (string) $this->query('estado'), 'tipo' => (string) $this->query('tipo')];
        if (!in_array($filters['estado'], ['', 'Borrador', 'Pendiente', 'Aprobado', 'Activo', 'Cerrado', 'Cancelado', 'Obsoleto'], true)) {
            $filters['estado'] = '';
        }
        if (!in_array($filters['tipo'], array_merge([''], self::types()), true)) {
            $filters['tipo'] = '';
        }
        $this->view('tenant/permisos/index', [
            'title' => 'Permisos de trabajo',
            'permits' => PermisoTrabajo::forTenant($tenantId, $filters),
            'stats' => PermisoTrabajo::stats($tenantId),
            'filters' => $filters,
            'types' => self::types(),
        ]);
    }

    public function create(): void
    {
        $this->view('tenant/permisos/form', [
            'title' => 'Nuevo permiso de trabajo',
            'permit' => $this->defaults(),
            'action' => '/permisos-trabajo',
            'types' => self::types(),
        ]);
    }

    public function store(): void
    {
        $tenantId = $this->tenantId();
        $data = $this->permitInput();
        $data['tenant_id'] = $tenantId;
        $data['solicitante_id'] = Auth::id();
        $errors = $this->permitErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/permisos-trabajo/nuevo');
        }
        $id = Database::transaction(static fn(): int => PermisoTrabajo::create($data));
        Audit::record('Permiso de trabajo creado en borrador', 'PermisoTrabajo', $id, ['tipo' => $data['tipo']]);
        flash('success', 'Permiso generado en borrador. Completa la verificación previa antes de solicitar aprobación.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function show(int $id): void
    {
        $permit = $this->permit($id);
        $this->view('tenant/permisos/show', [
            'title' => $permit['codigo'],
            'permit' => $permit,
            'revisions' => PermisoTrabajo::revisions($id, $this->tenantId()),
            'expired' => $this->expired($permit),
        ]);
    }

    public function edit(int $id): void
    {
        $permit = $this->permit($id);
        if (!in_array($permit['estado'], ['Borrador', 'Pendiente'], true)) {
            $this->abort(403, 'Un permiso aprobado o cerrado no se edita directamente. Crea una nueva revisión.');
        }
        $this->view('tenant/permisos/form', [
            'title' => 'Editar ' . $permit['codigo'],
            'permit' => $permit,
            'action' => '/permisos-trabajo/' . $id,
            'types' => self::types(),
        ]);
    }

    public function update(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = $this->permit($id);
        if (!in_array($before['estado'], ['Borrador', 'Pendiente'], true)) {
            $this->abort(403, 'Este permiso ya no admite edición directa.');
        }
        $data = $this->permitInput();
        $data['solicitante_id'] = $before['solicitante_id'];
        $errors = $this->permitErrors($data);
        if ($errors !== []) {
            fail_validation($errors, $data, '/permisos-trabajo/' . $id . '/editar');
        }
        PermisoTrabajo::update($id, $tenantId, $data);
        Audit::record('Permiso de trabajo actualizado', 'PermisoTrabajo', $id, ['version' => (int) $before['version']]);
        flash('success', 'Cambios guardados. El permiso quedó en borrador y requiere una nueva solicitud de aprobación.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function submit(int $id): void
    {
        $permit = $this->permit($id);
        if ($permit['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo un borrador puede enviarse a aprobación.');
        }
        if ($this->expired($permit)) {
            flash('error', 'La vigencia ya terminó. Edita las fechas antes de enviar el permiso.');
            redirect('/permisos-trabajo/' . $id . '/editar');
        }
        PermisoTrabajo::setStatus($id, $this->tenantId(), 'Pendiente');
        Audit::record('Permiso enviado a aprobación', 'PermisoTrabajo', $id);
        flash('success', 'El permiso quedó pendiente de verificación y aprobación.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function withdraw(int $id): void
    {
        $permit = $this->permit($id);
        if ($permit['estado'] !== 'Pendiente') {
            $this->abort(403, 'Solo puedes retirar una solicitud pendiente.');
        }
        PermisoTrabajo::setStatus($id, $this->tenantId(), 'Borrador');
        Audit::record('Solicitud de permiso retirada', 'PermisoTrabajo', $id);
        flash('success', 'La solicitud volvió a borrador para realizar ajustes.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function approve(int $id): void
    {
        $permit = $this->permit($id);
        if (!in_array($permit['estado'], ['Borrador', 'Pendiente'], true)) {
            $this->abort(403, 'Este permiso no está pendiente de aprobación.');
        }
        if ($this->expired($permit)) {
            flash('error', 'No puedes aprobar un permiso cuya vigencia ya terminó. Crea una revisión con fechas vigentes.');
            redirect('/permisos-trabajo/' . $id);
        }
        $data = [
            'aprobador_nombre' => (string) $this->input('aprobador_nombre'),
            'aprobador_documento' => (string) $this->input('aprobador_documento'),
            'verificacion_previa' => (string) $this->input('verificacion_previa'),
        ];
        $validator = new Validator($data);
        $validator->required('aprobador_nombre', 'nombre de quien autoriza')
            ->required('aprobador_documento', 'documento de quien autoriza')
            ->required('verificacion_previa', 'verificación previa');
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/permisos-trabajo/' . $id . '#aprobar');
        }
        PermisoTrabajo::approve($id, $this->tenantId(), Auth::id(), $data['aprobador_nombre'], $data['aprobador_documento']);
        Audit::record('Permiso de trabajo aprobado', 'PermisoTrabajo', $id, ['tipo' => $permit['tipo'], 'vigencia_fin' => $permit['fecha_fin']]);
        flash('success', 'Permiso aprobado. Debe permanecer disponible en el sitio y suspenderse si cambian las condiciones.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function activate(int $id): void
    {
        $permit = $this->permit($id);
        if ($permit['estado'] !== 'Aprobado') {
            $this->abort(403, 'Solo un permiso aprobado puede activarse.');
        }
        $now = time();
        if ($now < strtotime($permit['fecha_inicio']) || $now > strtotime($permit['fecha_fin'])) {
            flash('error', 'El permiso solo puede activarse dentro de su vigencia registrada.');
            redirect('/permisos-trabajo/' . $id);
        }
        PermisoTrabajo::setStatus($id, $this->tenantId(), 'Activo');
        Audit::record('Permiso de trabajo activado', 'PermisoTrabajo', $id);
        flash('success', 'Permiso activado. Verifica que los controles sigan vigentes durante toda la tarea.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function close(int $id): void
    {
        $permit = $this->permit($id);
        if (!in_array($permit['estado'], ['Aprobado', 'Activo'], true)) {
            $this->abort(403, 'Solo un permiso aprobado o activo puede cerrarse.');
        }
        $observations = (string) $this->input('observaciones_cierre');
        $validator = new Validator(['observaciones_cierre' => $observations]);
        $validator->required('observaciones_cierre', 'observaciones de cierre')->max('observaciones_cierre', 'observaciones', 3000);
        if ($validator->fails()) {
            fail_validation($validator->errors(), [], '/permisos-trabajo/' . $id . '#cerrar');
        }
        PermisoTrabajo::close($id, $this->tenantId(), Auth::id(), $observations);
        Audit::record('Permiso de trabajo cerrado', 'PermisoTrabajo', $id);
        flash('success', 'Permiso cerrado con observaciones y trazabilidad del responsable.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function cancel(int $id): void
    {
        $permit = $this->permit($id);
        if (in_array($permit['estado'], ['Cerrado', 'Cancelado', 'Obsoleto'], true)) {
            $this->abort(403, 'Este permiso ya finalizó su ciclo.');
        }
        $reason = (string) $this->input('observaciones_cierre');
        $validator = new Validator(['observaciones_cierre' => $reason]);
        $validator->required('observaciones_cierre', 'motivo de cancelación')->max('observaciones_cierre', 'motivo', 3000);
        if ($validator->fails()) {
            fail_validation($validator->errors(), [], '/permisos-trabajo/' . $id . '#cancelar');
        }
        PermisoTrabajo::cancel($id, $this->tenantId(), Auth::id(), $reason);
        Audit::record('Permiso de trabajo cancelado', 'PermisoTrabajo', $id, ['motivo' => $reason]);
        flash('success', 'Permiso cancelado. Conserva el motivo y evalúa los controles antes de reprogramar la tarea.');
        redirect('/permisos-trabajo/' . $id);
    }

    public function revise(int $id): void
    {
        $tenantId = $this->tenantId();
        $permit = $this->permit($id);
        if ($permit['estado'] === 'Activo') {
            $this->abort(403, 'Cierra o cancela el trabajo activo antes de crear una nueva revisión.');
        }
        if (in_array($permit['estado'], ['Borrador', 'Pendiente', 'Obsoleto'], true)) {
            $this->abort(403, 'Edita el borrador actual o utiliza una versión aprobada o finalizada para revisar.');
        }
        $data = $permit;
        $data['tenant_id'] = $tenantId;
        $data['version'] = (int) $permit['version'] + 1;
        $data['revisado_de'] = $id;
        $newId = Database::transaction(static function () use ($id, $tenantId, $permit, $data): int {
            if (!in_array($permit['estado'], ['Cerrado', 'Cancelado'], true)) {
                PermisoTrabajo::setStatus($id, $tenantId, 'Obsoleto');
            }
            return PermisoTrabajo::create($data);
        });
        Audit::record('Nueva revisión de permiso creada', 'PermisoTrabajo', $newId, ['revisado_de' => $id, 'version' => $data['version']]);
        flash('success', 'Se creó una nueva revisión en borrador. La versión anterior se conserva en el histórico.');
        redirect('/permisos-trabajo/' . $newId);
    }

    public function delete(int $id): void
    {
        $permit = $this->permit($id);
        if ($permit['estado'] !== 'Borrador') {
            $this->abort(403, 'Solo los borradores pueden eliminarse. Cancela o conserva los demás estados.');
        }
        PermisoTrabajo::delete($id, $this->tenantId());
        Audit::record('Borrador de permiso eliminado', 'PermisoTrabajo', $id, ['codigo' => $permit['codigo']]);
        flash('success', 'Borrador eliminado.');
        redirect('/permisos-trabajo');
    }

    public function print(int $id): void
    {
        $permit = $this->permit($id);
        $this->view('tenant/permisos/print', [
            'title' => 'Permiso ' . $permit['codigo'],
            'permit' => $permit,
            'tenant' => Tenant::find($this->tenantId()),
            'backUrl' => '/permisos-trabajo/' . $id,
            'expired' => $this->expired($permit),
        ], 'layouts/print');
    }

    private function permit(int $id): array
    {
        $permit = PermisoTrabajo::findForTenant($id, $this->tenantId());
        if ($permit === null) {
            $this->notFound();
        }
        return $permit;
    }

    private function expired(array $permit): bool
    {
        return in_array($permit['estado'], ['Aprobado', 'Activo'], true) && strtotime($permit['fecha_fin']) < time();
    }

    private function permitInput(): array
    {
        $data = [];
        foreach (['tipo', 'tarea', 'ubicacion', 'descripcion', 'fecha_inicio', 'fecha_fin', 'solicitante_nombre',
            'solicitante_documento', 'ejecutantes', 'responsable_trabajo', 'peligros', 'controles', 'epp',
            'equipos_herramientas', 'autorreporte_condiciones', 'altura_trabajo', 'coordinador_alturas',
            'responsable_emergencias', 'procedimiento_rescate', 'vigia_espacio', 'mediciones_atmosfericas',
            'formacion_personal', 'observaciones'] as $field) {
            $data[$field] = (string) $this->input($field);
        }
        return $data;
    }

    private function permitErrors(array $data): array
    {
        $validator = new Validator($data);
        foreach (['tipo' => 'tipo', 'tarea' => 'tarea', 'ubicacion' => 'ubicación', 'descripcion' => 'descripción',
            'fecha_inicio' => 'inicio de vigencia', 'fecha_fin' => 'fin de vigencia', 'solicitante_nombre' => 'solicitante',
            'solicitante_documento' => 'documento del solicitante', 'ejecutantes' => 'trabajadores autorizados',
            'responsable_trabajo' => 'responsable del trabajo', 'peligros' => 'peligros', 'controles' => 'controles',
            'epp' => 'EPP', 'autorreporte_condiciones' => 'autorreporte de condiciones', 'formacion_personal' => 'formación del personal'] as $field => $label) {
            $validator->required($field, $label);
        }
        $validator->in('tipo', 'tipo', self::types())->numeric('altura_trabajo', 'altura', 0);
        foreach (['tarea', 'ubicacion', 'solicitante_nombre', 'solicitante_documento', 'responsable_trabajo'] as $field) {
            $validator->max($field, $field, 250);
        }
        foreach (['descripcion', 'ejecutantes', 'peligros', 'controles', 'epp', 'equipos_herramientas', 'autorreporte_condiciones',
            'procedimiento_rescate', 'mediciones_atmosfericas', 'formacion_personal', 'observaciones'] as $field) {
            $validator->max($field, $field, 6000);
        }
        $errors = $validator->errors();
        $start = $this->dateTime($data['fecha_inicio']);
        $end = $this->dateTime($data['fecha_fin']);
        if ($start === null) {
            $errors['fecha_inicio'] = 'Ingresa una fecha y hora de inicio válidas.';
        }
        if ($end === null) {
            $errors['fecha_fin'] = 'Ingresa una fecha y hora de fin válidas.';
        }
        if ($start !== null && $end !== null && $end <= $start) {
            $errors['fecha_fin'] = 'El fin de la vigencia debe ser posterior al inicio.';
        }
        if ($data['tipo'] === 'Alturas') {
            if ($data['altura_trabajo'] === '' || (float) $data['altura_trabajo'] <= 0) {
                $errors['altura_trabajo'] = 'Registra la altura de trabajo.';
            }
            foreach (['coordinador_alturas' => 'coordinador de trabajo en alturas', 'responsable_emergencias' => 'responsable de activar emergencias', 'procedimiento_rescate' => 'plan de rescate'] as $field => $label) {
                if (trim($data[$field]) === '') {
                    $errors[$field] = "El campo {$label} es obligatorio para trabajo en alturas.";
                }
            }
        }
        if ($data['tipo'] === 'Espacio confinado') {
            foreach (['vigia_espacio' => 'vigía', 'mediciones_atmosfericas' => 'mediciones atmosféricas', 'responsable_emergencias' => 'responsable de emergencias', 'procedimiento_rescate' => 'procedimiento de rescate'] as $field => $label) {
                if (trim($data[$field]) === '') {
                    $errors[$field] = "El campo {$label} es obligatorio para espacio confinado.";
                }
            }
        }
        return $errors;
    }

    private function dateTime(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        return $date !== false && $date->format('Y-m-d\TH:i') === $value ? $date : null;
    }

    private function defaults(): array
    {
        $user = Auth::user();
        return [
            'tipo' => 'General', 'tarea' => '', 'ubicacion' => '', 'descripcion' => '',
            'fecha_inicio' => date('Y-m-d\TH:i', strtotime('+1 hour')),
            'fecha_fin' => date('Y-m-d\TH:i', strtotime('+9 hours')),
            'solicitante_nombre' => $user['nombre'] ?? '', 'solicitante_documento' => '',
            'ejecutantes' => '', 'responsable_trabajo' => '', 'peligros' => '', 'controles' => '', 'epp' => '',
            'equipos_herramientas' => '', 'autorreporte_condiciones' => '', 'altura_trabajo' => '',
            'coordinador_alturas' => '', 'responsable_emergencias' => '', 'procedimiento_rescate' => '',
            'vigia_espacio' => '', 'mediciones_atmosfericas' => '', 'formacion_personal' => '', 'observaciones' => '',
        ];
    }
}
