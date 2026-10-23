<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Evidencia;
use App\Models\PlanEmergencia;
use App\Models\PlanoEmergencia;
use App\Services\EvidenceStorage;
use App\Services\FloorPlan;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use DomainException;
use Throwable;

final class PlanoEmergenciaController extends BaseController
{
    public function create(int $planId): void
    {
        $plan = $this->plan($planId);
        $this->ensureEditable($plan);
        $this->editor($plan, null, FloorPlan::defaults(), []);
    }

    public function store(int $planId): void
    {
        $plan = $this->plan($planId);
        $this->ensureEditable($plan);
        $data = $this->metadata();
        try {
            $design = $this->design((string) $this->input('datos_json'));
        } catch (DomainException $exception) {
            fail_validation(['datos_json' => $exception->getMessage()], $data + ['datos_json' => (string) $this->input('datos_json')], '/emergencias/' . $planId . '/planos/nuevo');
        }
        $data += [
            'tenant_id' => $this->tenantId(), 'plan_id' => $planId,
            'datos_json' => FloorPlan::encode($design),
            'created_by' => Auth::id(),
        ];
        $id = PlanoEmergencia::create($data);
        Audit::record('Plano de emergencia creado', 'PlanoEmergencia', $id, ['plan_id' => $planId, 'version' => 1]);
        flash('success', 'Plano guardado como borrador, versión 1. Debe contrastarse con las condiciones reales del centro de trabajo.');
        redirect('/planos-emergencia/' . $id . '/editar');
    }

    public function edit(int $id): void
    {
        $drawing = $this->drawing($id);
        $plan = $this->plan((int) $drawing['plan_id']);
        $design = $this->storedDesign($drawing['datos_json']);
        $this->editor($plan, $drawing, $design, PlanoEmergencia::versions($id, $this->tenantId()));
    }

    public function version(int $id, int $versionId): void
    {
        $drawing = $this->drawing($id);
        $version = PlanoEmergencia::findVersion($versionId, $id, $this->tenantId());
        if ($version === null) {
            $this->notFound('La versión solicitada no existe o no pertenece a este plano.');
        }
        $plan = $this->plan((int) $drawing['plan_id']);
        $historical = $drawing;
        foreach (['nombre', 'escala', 'descripcion', 'datos_json'] as $key) {
            $historical[$key] = $version[$key];
        }
        $this->editor($plan, $historical, $this->storedDesign($version['datos_json']), PlanoEmergencia::versions($id, $this->tenantId()), $version);
    }

    public function update(int $id): void
    {
        $drawing = $this->drawing($id);
        $this->ensureEditable($this->plan((int) $drawing['plan_id']));
        $data = $this->metadata();
        try {
            $design = $this->design((string) $this->input('datos_json'));
        } catch (DomainException $exception) {
            fail_validation(['datos_json' => $exception->getMessage()], $data + ['datos_json' => (string) $this->input('datos_json')], '/planos-emergencia/' . $id . '/editar');
        }
        try {
            $version = PlanoEmergencia::update($id, $this->tenantId(), $data + [
                'datos_json' => FloorPlan::encode($design),
                'created_by' => Auth::id(),
                'expected_version' => (int) $this->input('version_actual'),
            ]);
        } catch (DomainException $exception) {
            fail_validation(
                ['datos_json' => $exception->getMessage()],
                $data + ['datos_json' => (string) $this->input('datos_json')],
                '/planos-emergencia/' . $id . '/editar'
            );
        }
        Audit::record('Nueva versión de plano de emergencia', 'PlanoEmergencia', $id, [
            'plan_id' => $drawing['plan_id'], 'version' => $version,
        ]);
        flash('success', 'Diseño guardado como versión ' . $version . '. Las versiones anteriores se conservaron sin modificaciones.');
        redirect('/planos-emergencia/' . $id . '/editar');
    }

    /** Recibe el Blob PNG del navegador sin debilitar la validación de archivos subidos. */
    public function exportPng(int $id): void
    {
        $drawing = $this->drawing($id);
        $design = $this->storedDesign($drawing['datos_json']);
        $stored = null;
        try {
            $stored = EvidenceStorage::store($_FILES['png'] ?? [], $this->tenantId());
            if ($stored['extension'] !== 'png') {
                throw new DomainException('La exportación debe enviarse como un archivo PNG.');
            }
            $image = @getimagesize($stored['absolute_path']);
            if (!is_array($image) || ($image['mime'] ?? '') !== 'image/png'
                || (int) $image[0] !== (int) $design['width'] || (int) $image[1] !== (int) $design['height']) {
                throw new DomainException('El PNG no tiene las dimensiones del plano guardado. Guarda la última versión y vuelve a exportarlo.');
            }
            $payload = $stored;
            unset($payload['absolute_path']);
            $evidenceId = Evidencia::create($payload + [
                'tenant_id' => $this->tenantId(), 'requisito_clave' => '', 'modulo' => 'emergencias',
                'categoria' => 'Plano de emergencia',
                'titulo' => text_substr($drawing['nombre'] . ' · v' . $drawing['version'], 0, 180),
                'fecha_evidencia' => date('Y-m-d'), 'entidad_tipo' => 'plano', 'entidad_id' => (string) $id,
                'entidad_referencia' => 'Plano · ' . $drawing['nombre'] . ' · v' . $drawing['version'],
                'descripcion' => 'Exportación PNG del diseño. Pendiente de contraste y aprobación por una persona responsable.',
                'estado_revision' => 'Pendiente', 'subido_por' => Auth::id(),
            ]);
        } catch (DomainException $exception) {
            $this->discardStoredFile($stored);
            $this->exportResponse(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->discardStoredFile($stored);
            error_log((string) $exception);
            $this->exportResponse(['ok' => false, 'message' => 'No fue posible archivar el PNG. Revisa los permisos de almacenamiento e inténtalo nuevamente.'], 500);
        }
        Audit::record('Plano de emergencia exportado a evidencia PNG', 'Evidencia', $evidenceId, [
            'plano_id' => $id, 'version' => $drawing['version'], 'sha256' => $stored['hash_sha256'],
        ]);
        $this->exportResponse([
            'ok' => true,
            'message' => 'PNG archivado como evidencia pendiente de revisión humana.',
            'url' => url('/evidencias#evidencia-' . $evidenceId),
        ]);
    }

    private function editor(array $plan, ?array $drawing, array $design, array $versions, ?array $sourceVersion = null): void
    {
        $this->view('tenant/emergencias/plano-editor', [
            'title' => $drawing === null ? 'Nuevo plano de emergencia' : $drawing['nombre'],
            'plan' => $plan, 'drawing' => $drawing, 'design' => $design, 'versions' => $versions,
            'sourceVersion' => $sourceVersion, 'extraScripts' => ['js/floor-plan.js'],
            'tools' => FloorPlan::tools(),
        ]);
    }

    private function metadata(): array
    {
        $data = [
            'nombre' => (string) $this->input('nombre'),
            'escala' => (string) $this->input('escala', 'Referencial · verificar en sitio'),
            'descripcion' => (string) $this->input('descripcion'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre del plano')->max('nombre', 'nombre del plano', 180)
            ->required('escala', 'escala o referencia dimensional')->max('escala', 'escala o referencia', 120)
            ->max('descripcion', 'datos y observaciones', 2000);
        if ($validator->fails()) {
            $id = (string) $this->input('id');
            $planId = (string) $this->input('plan_id');
            $target = ctype_digit($id) && (int) $id > 0 ? '/planos-emergencia/' . $id . '/editar'
                : (ctype_digit($planId) && (int) $planId > 0 ? '/emergencias/' . $planId . '/planos/nuevo' : '/emergencias');
            fail_validation($validator->errors(), $data + ['datos_json' => (string) $this->input('datos_json')], $target);
        }
        return $data;
    }

    private function design(string $json): array
    {
        return FloorPlan::normalize($json);
    }

    private function storedDesign(string $json): array
    {
        try {
            return $this->design($json);
        } catch (DomainException $exception) {
            error_log((string) $exception);
            $this->abort(404, 'El diseño almacenado no se puede abrir porque su estructura no es válida.');
        }
    }

    private function ensureEditable(array $plan): void
    {
        if ($plan['estado'] === 'Obsoleto') {
            $this->abort(403, 'Un plan obsoleto se conserva para consulta; no admite nuevas versiones de planos.');
        }
    }

    private function plan(int $id): array
    {
        $plan = PlanEmergencia::findForTenant($id, $this->tenantId());
        if ($plan === null) {
            $this->notFound('El plan de emergencia no existe o no pertenece a tu empresa.');
        }
        return $plan;
    }

    private function drawing(int $id): array
    {
        $drawing = PlanoEmergencia::findForTenant($id, $this->tenantId());
        if ($drawing === null) {
            $this->notFound('El plano no existe o no pertenece a tu empresa.');
        }
        return $drawing;
    }

    private function discardStoredFile(?array $stored): void
    {
        if (is_array($stored) && isset($stored['absolute_path']) && is_file($stored['absolute_path'])) {
            @unlink($stored['absolute_path']);
        }
    }

    private function exportResponse(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
