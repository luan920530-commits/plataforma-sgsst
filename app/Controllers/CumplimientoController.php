<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\RequisitoAccion;
use App\Models\RequisitoSeguimiento;
use App\Models\SoporteCumplimiento;
use App\Models\User;
use App\Services\ComplianceService;
use App\Services\EvidenceStorage;
use App\Services\ModuleAccess;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class CumplimientoController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $board = ComplianceService::board($tenantId);
        $filters = ['fase' => (string) $this->query('fase'), 'estado' => (string) $this->query('estado')];
        if (!in_array($filters['fase'], array_merge([''], ComplianceService::categories()), true)) {
            $filters['fase'] = '';
        }
        if (!in_array($filters['estado'], ['', 'Pendiente', 'En gestion', 'Verificado', 'No aplica'], true)) {
            $filters['estado'] = '';
        }
        $items = array_filter($board['items'], static function (array $item) use ($filters): bool {
            return ($filters['fase'] === '' || $item['fase'] === $filters['fase'])
                && ($filters['estado'] === '' || $item['estado'] === $filters['estado']);
        });
        $selectedKey = (string) $this->query('requisito');
        $selected = $selectedKey !== '' && isset($board['items'][$selectedKey]) ? $board['items'][$selectedKey] : null;
        $this->view('tenant/cumplimiento/index', [
            'title' => 'Documentación y evidencias',
            'items' => $items,
            'allItems' => $board['items'],
            'stats' => $board['stats'],
            'filters' => $filters,
            'categories' => ComplianceService::categories(),
            'selected' => $selected,
            'supports' => $selected === null ? [] : SoporteCumplimiento::forRequirement($tenantId, $selected['key']),
            'tenant' => $board['tenant'],
        ]);
    }

    public function manage(): void
    {
        $key = (string) $this->query('requisito');
        try {
            ComplianceService::definition($key, ModuleAccess::current());
        } catch (InvalidArgumentException) {
            $this->notFound('El módulo de gestión solicitado no existe.');
        }
        $tenantId = $this->tenantId();
        $board = ComplianceService::board($tenantId);
        $this->view('tenant/cumplimiento/gestion', [
            'title' => $board['items'][$key]['nombre'],
            'selected' => $board['items'][$key],
            'actions' => RequisitoAccion::forRequirement($tenantId, $key),
            'users' => User::allByTenant($tenantId),
            'supports' => SoporteCumplimiento::forRequirement($tenantId, $key),
            'tenant' => $board['tenant'],
        ]);
    }

    public function storeAction(): void
    {
        $key = $this->requirementKey();
        $data = [
            'titulo' => (string) $this->input('titulo'), 'descripcion' => (string) $this->input('descripcion'),
            'responsable_id' => $this->input('responsable_id'), 'recursos' => (string) $this->input('recursos'),
            'fecha_compromiso' => (string) $this->input('fecha_compromiso'),
        ];
        $validator = new Validator($data);
        $validator->required('titulo', 'acción o compromiso')->max('titulo', 'acción o compromiso', 220)
            ->max('descripcion', 'descripción', 3000)->required('responsable_id', 'responsable')
            ->integer('responsable_id', 'responsable', 1)->max('recursos', 'recursos', 2000)
            ->date('fecha_compromiso', 'fecha de compromiso');
        $errors = $validator->errors();
        $responsible = !empty($data['responsable_id']) ? User::findForTenant((int) $data['responsable_id'], $this->tenantId()) : null;
        if ($responsible === null || !(bool) $responsible['activo']) {
            $errors['responsable_id'] = 'Selecciona un usuario activo de esta empresa.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/cumplimiento-documental/gestion?requisito=' . rawurlencode($key) . '#acciones');
        }
        $id = RequisitoAccion::create($data + ['tenant_id' => $this->tenantId(), 'requisito_clave' => $key, 'created_by' => Auth::id()]);
        Audit::record('Acción de gestión creada', 'RequisitoAccion', $id, ['requisito' => $key, 'responsable_id' => (int) $data['responsable_id']]);
        flash('success', 'Acción creada con responsable y recursos. El requisito queda en gestión; su verificación sigue requiriendo revisión humana.');
        redirect('/cumplimiento-documental/gestion?requisito=' . rawurlencode($key) . '#acciones');
    }

    public function updateAction(int $id): void
    {
        $action = RequisitoAccion::findForTenant($id, $this->tenantId());
        if ($action === null) {
            $this->notFound();
        }
        $this->authorizeRequirement($action['requisito_clave']);
        $data = ['estado' => (string) $this->input('estado'), 'evidencia' => (string) $this->input('evidencia')];
        $validator = new Validator($data);
        $validator->required('estado', 'estado')->in('estado', 'estado', ['Pendiente', 'En Proceso', 'Completado'])
            ->max('evidencia', 'evidencia de cierre', 3000);
        if ($data['estado'] === 'Completado') {
            $validator->required('evidencia', 'evidencia de cierre');
        }
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/cumplimiento-documental/gestion?requisito=' . rawurlencode($action['requisito_clave']) . '#acciones');
        }
        RequisitoAccion::updateManagement($id, $this->tenantId(), $data['estado'], $data['evidencia']);
        Audit::record('Acción de gestión actualizada', 'RequisitoAccion', $id, ['requisito' => $action['requisito_clave'], 'estado' => $data['estado']]);
        flash('success', 'Estado y evidencia de la acción actualizados.');
        redirect('/cumplimiento-documental/gestion?requisito=' . rawurlencode($action['requisito_clave']) . '#acciones');
    }

    public function deleteAction(int $id): void
    {
        $action = RequisitoAccion::findForTenant($id, $this->tenantId());
        if ($action === null) {
            $this->notFound();
        }
        $this->authorizeRequirement($action['requisito_clave']);
        RequisitoAccion::delete($id, $this->tenantId());
        Audit::record('Acción de gestión eliminada', 'RequisitoAccion', $id, ['requisito' => $action['requisito_clave']]);
        flash('success', 'La acción fue eliminada. Los soportes y el seguimiento documental se conservaron.');
        redirect('/cumplimiento-documental/gestion?requisito=' . rawurlencode($action['requisito_clave']) . '#acciones');
    }

    public function updateTracking(): void
    {
        $key = $this->requirementKey();
        $redirectTo = $this->requirementPath($key, 'gestion');
        $data = [
            'estado' => (string) $this->input('estado'),
            'responsable' => (string) $this->input('responsable'),
            'fecha_limite' => (string) $this->input('fecha_limite'),
            'observaciones' => (string) $this->input('observaciones'),
        ];
        $validator = new Validator($data);
        $validator->required('estado', 'estado')->in('estado', 'estado', ['Pendiente', 'En gestion', 'Verificado', 'No aplica'])
            ->date('fecha_limite', 'fecha límite')->max('responsable', 'responsable', 250)
            ->max('observaciones', 'observaciones', 4000);
        if (in_array($data['estado'], ['Verificado', 'No aplica'], true)) {
            $validator->required('responsable', 'responsable de la revisión')
                ->required('observaciones', $data['estado'] === 'Verificado' ? 'observaciones de la verificación' : 'justificación de no aplicabilidad');
        }
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, $redirectTo);
        }
        RequisitoSeguimiento::save($this->tenantId(), $key, $data, Auth::id());
        Audit::record('Seguimiento documental actualizado', 'RequisitoSeguimiento', null, ['requisito' => $key, 'estado' => $data['estado']]);
        flash('success', 'Seguimiento guardado. El estado refleja la revisión registrada por la empresa, no una certificación automática.');
        redirect($redirectTo);
    }

    public function upload(): void
    {
        $key = $this->requirementKey();
        $redirectTo = $this->requirementPath($key, 'soportes');
        $description = (string) $this->input('descripcion');
        $validator = new Validator(['descripcion' => $description]);
        $validator->max('descripcion', 'descripción', 500);
        if ($validator->fails()) {
            fail_validation($validator->errors(), ['descripcion' => $description], $redirectTo);
        }
        $stored = null;
        try {
            $stored = EvidenceStorage::store($_FILES['soporte'] ?? [], $this->tenantId());
            $data = $stored;
            unset($data['absolute_path']);
            $data['tenant_id'] = $this->tenantId();
            $data['requisito_clave'] = $key;
            $data['descripcion'] = $description;
            $data['subido_por'] = Auth::id();
            $id = SoporteCumplimiento::create($data);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            redirect($redirectTo);
        } catch (Throwable $exception) {
            if (is_array($stored) && isset($stored['absolute_path']) && is_file($stored['absolute_path'])) {
                @unlink($stored['absolute_path']);
            }
            error_log((string) $exception);
            flash('error', 'No fue posible guardar el soporte. Revisa los permisos de storage y el registro de errores.');
            redirect($redirectTo);
        }
        Audit::record('Soporte documental cargado', 'SoporteCumplimiento', $id, ['requisito' => $key, 'sha256' => $stored['hash_sha256']]);
        flash('success', 'Soporte cargado con validación de formato y hash de integridad. Todavía debes revisar y verificar el requisito.');
        redirect($redirectTo);
    }

    public function download(int $id): void
    {
        $support = SoporteCumplimiento::findForTenant($id, $this->tenantId());
        if ($support === null) {
            $this->notFound();
        }
        $this->authorizeRequirement($support['requisito_clave']);
        try {
            $path = EvidenceStorage::path($this->tenantId(), $support['nombre_almacenado']);
            if (!hash_equals($support['hash_sha256'], (string) hash_file('sha256', $path))) {
                throw new RuntimeException('La integridad del soporte no coincide con el hash registrado.');
            }
        } catch (Throwable $exception) {
            error_log((string) $exception);
            $this->abort(404, 'El soporte no está disponible o no superó la verificación de integridad.');
        }
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $support['nombre_original']) ?: 'soporte.' . $support['extension'];
        header('Content-Type: ' . EvidenceStorage::mime($support['extension']));
        header('Content-Length: ' . (string) filesize($path));
        header("Content-Disposition: attachment; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($support['nombre_original']));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function deleteSupport(int $id): void
    {
        $support = SoporteCumplimiento::findForTenant($id, $this->tenantId());
        if ($support === null) {
            $this->notFound();
        }
        $this->authorizeRequirement($support['requisito_clave']);
        $redirectTo = $this->requirementPath($support['requisito_clave'], 'soportes');
        try {
            EvidenceStorage::delete($this->tenantId(), $support['nombre_almacenado']);
            SoporteCumplimiento::delete($id, $this->tenantId());
        } catch (Throwable $exception) {
            error_log((string) $exception);
            flash('error', 'No fue posible eliminar el archivo. Revisa los permisos de storage.');
            redirect($redirectTo);
        }
        Audit::record('Soporte documental eliminado', 'SoporteCumplimiento', $id, ['requisito' => $support['requisito_clave'], 'sha256' => $support['hash_sha256']]);
        flash('success', 'Soporte eliminado. El seguimiento y las observaciones se conservan.');
        redirect($redirectTo);
    }

    private function authorizeRequirement(string $key): void
    {
        try {
            ComplianceService::definition($key, ModuleAccess::current());
        } catch (InvalidArgumentException) {
            $this->abort(403, 'Este requisito no pertenece al módulo asignado a la empresa.');
        }
    }

    private function requirementPath(string $key, string $anchor): string
    {
        $base = $this->input('origen') === 'centro' ? '/cumplimiento-documental/gestion?requisito=' : '/cumplimiento-documental?requisito=';
        return $base . rawurlencode($key) . '#' . $anchor;
    }

    private function requirementKey(): string
    {
        $key = (string) $this->input('requisito_clave');
        try {
            ComplianceService::definition($key, ModuleAccess::current());
        } catch (InvalidArgumentException) {
            $this->abort(403, 'El requisito documental indicado no es válido.');
        }
        return $key;
    }
}
