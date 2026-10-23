<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Evidencia;
use App\Services\EvidenceLinker;
use App\Services\EvidenceStorage;
use App\Services\ModuleAccess;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use DomainException;
use RuntimeException;
use Throwable;

final class EvidenciaController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $module = ModuleAccess::current();
        $filters = [
            'q' => text_substr((string) $this->query('q'), 0, 180),
            'categoria' => (string) $this->query('categoria'),
            'revision' => (string) $this->query('revision'),
            'desde' => $this->dateFilter('desde'),
            'hasta' => $this->dateFilter('hasta'),
        ];
        if (!in_array($filters['categoria'], array_merge([''], Evidencia::categories()), true)) {
            $filters['categoria'] = '';
        }
        if (!in_array($filters['revision'], ['', 'Pendiente', 'Revisado'], true)) {
            $filters['revision'] = '';
        }
        $this->view('tenant/evidencias/index', [
            'title' => 'Centro de evidencias',
            'evidences' => Evidencia::forTenant($tenantId, $module, $filters),
            'stats' => Evidencia::stats($tenantId, $module),
            'filters' => $filters,
            'categories' => Evidencia::categories(),
            'linkGroups' => EvidenceLinker::options($tenantId, $module),
            'moduleOptions' => EvidenceLinker::moduleOptions($module),
            'planModule' => $module,
            'selectedLink' => text_substr((string) $this->query('vinculo'), 0, 80),
        ]);
    }

    public function store(): void
    {
        $tenantId = $this->tenantId();
        $module = ModuleAccess::current();
        $data = [
            'titulo' => (string) $this->input('titulo'),
            'categoria' => (string) $this->input('categoria'),
            'fecha_evidencia' => (string) $this->input('fecha_evidencia'),
            'descripcion' => (string) $this->input('descripcion'),
            'vinculo' => (string) $this->input('vinculo', 'general:'),
            'modulo_general' => (string) $this->input('modulo_general', $module),
        ];
        $validator = new Validator($data);
        $validator->required('titulo', 'título de la evidencia')->max('titulo', 'título', 180)
            ->required('categoria', 'categoría')->in('categoria', 'categoría', Evidencia::categories())
            ->required('fecha_evidencia', 'fecha de la evidencia')->date('fecha_evidencia', 'fecha de la evidencia')
            ->max('descripcion', 'descripción', 2000)
            ->in('modulo_general', 'módulo', array_keys(EvidenceLinker::moduleOptions($module)));
        $errors = $validator->errors();
        try {
            $link = EvidenceLinker::resolve($data['vinculo'], $tenantId, $module, $data['modulo_general']);
        } catch (DomainException $exception) {
            $errors['vinculo'] = $exception->getMessage();
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/evidencias#cargar');
        }

        $stored = null;
        try {
            $stored = EvidenceStorage::store($_FILES['soporte'] ?? [], $tenantId);
            $payload = $stored;
            unset($payload['absolute_path']);
            $id = Evidencia::create($payload + $link + [
                'tenant_id' => $tenantId, 'requisito_clave' => '', 'titulo' => $data['titulo'],
                'categoria' => $data['categoria'], 'fecha_evidencia' => $data['fecha_evidencia'],
                'descripcion' => $data['descripcion'], 'estado_revision' => 'Pendiente', 'subido_por' => Auth::id(),
            ]);
        } catch (DomainException $exception) {
            flash('error', $exception->getMessage());
            redirect('/evidencias#cargar');
        } catch (Throwable $exception) {
            if (is_array($stored) && isset($stored['absolute_path']) && is_file($stored['absolute_path'])) {
                @unlink($stored['absolute_path']);
            }
            error_log((string) $exception);
            flash('error', 'No fue posible guardar la evidencia. Revisa los permisos de almacenamiento e inténtalo nuevamente.');
            redirect('/evidencias#cargar');
        }
        Audit::record('Evidencia documental cargada', 'Evidencia', $id, [
            'categoria' => $data['categoria'], 'modulo' => $link['modulo'], 'entidad' => $link['entidad_tipo'],
            'entidad_id' => $link['entidad_id'], 'sha256' => $stored['hash_sha256'],
        ]);
        flash('success', 'Evidencia guardada en el archivo privado y vinculada correctamente. Queda pendiente de revisión humana.');
        redirect('/evidencias');
    }

    public function review(int $id): void
    {
        $evidence = $this->evidence($id);
        $status = (string) $this->input('estado_revision');
        $notes = (string) $this->input('observaciones_revision');
        if (!in_array($status, ['Pendiente', 'Revisado'], true) || ($status === 'Revisado' && trim($notes) === '')) {
            flash('error', 'Para marcar la evidencia como revisada, registra el alcance de la revisión.');
            redirect('/evidencias#evidencia-' . $id);
        }
        if (text_length($notes) > 2000) {
            flash('error', 'Las observaciones de revisión no pueden superar 2.000 caracteres.');
            redirect('/evidencias#evidencia-' . $id);
        }
        Evidencia::review($id, $this->tenantId(), $status, $notes, Auth::id());
        Audit::record('Revisión humana de evidencia actualizada', 'Evidencia', $id, ['estado' => $status]);
        flash('success', $status === 'Revisado'
            ? 'Revisión registrada. Este estado representa la revisión de tu empresa, no una certificación automática.'
            : 'La evidencia volvió a quedar pendiente de revisión.');
        redirect('/evidencias#evidencia-' . $id);
    }

    public function download(int $id): void
    {
        $evidence = $this->evidence($id);
        try {
            $path = EvidenceStorage::path($this->tenantId(), $evidence['nombre_almacenado']);
            if (!hash_equals($evidence['hash_sha256'], (string) hash_file('sha256', $path))) {
                throw new RuntimeException('El hash de la evidencia no coincide.');
            }
        } catch (Throwable $exception) {
            error_log((string) $exception);
            $this->abort(404, 'El archivo no está disponible o no superó la verificación de integridad.');
        }
        $inline = (string) $this->query('inline') === '1' && in_array($evidence['extension'], ['jpg', 'png'], true);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $evidence['nombre_original']) ?: 'evidencia.' . $evidence['extension'];
        header('Content-Type: ' . EvidenceStorage::mime($evidence['extension']));
        header('Content-Length: ' . (string) filesize($path));
        $disposition = $inline ? 'inline' : 'attachment';
        header("Content-Disposition: {$disposition}; filename=\"{$fallback}\"; filename*=UTF-8''" . rawurlencode($evidence['nombre_original']));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function delete(int $id): void
    {
        $evidence = $this->evidence($id);
        if ($evidence['estado_revision'] === 'Revisado') {
            $this->abort(403, 'Una evidencia revisada se conserva para trazabilidad. Si contiene un error, carga una nueva versión y documenta la revisión.');
        }
        try {
            Database::transaction(function () use ($id, $evidence): void {
                if (!Evidencia::delete($id, $this->tenantId())) {
                    throw new RuntimeException('La evidencia cambió de estado antes de eliminarla.');
                }
                EvidenceStorage::delete($this->tenantId(), $evidence['nombre_almacenado']);
            });
        } catch (Throwable $exception) {
            error_log((string) $exception);
            flash('error', 'No fue posible eliminar el archivo. Revisa los permisos de almacenamiento.');
            redirect('/evidencias#evidencia-' . $id);
        }
        Audit::record('Evidencia pendiente eliminada', 'Evidencia', $id, ['sha256' => $evidence['hash_sha256']]);
        flash('success', 'Evidencia pendiente eliminada. La acción quedó registrada en auditoría.');
        redirect('/evidencias');
    }

    private function evidence(int $id): array
    {
        $evidence = Evidencia::findForTenant($id, $this->tenantId(), ModuleAccess::current());
        if ($evidence === null) {
            $this->notFound('La evidencia no existe, no pertenece a tu empresa o está fuera del módulo habilitado.');
        }
        return $evidence;
    }

    private function dateFilter(string $key): string
    {
        $value = (string) $this->query($key);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && strtotime($value) !== false ? $value : '';
    }
}
