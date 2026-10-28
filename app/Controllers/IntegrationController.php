<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Integration;
use App\Models\IntegrationExecution;
use App\Models\Permission;
use App\Support\Audit;
use App\Support\Auth;
use DomainException;
use JsonException;

final class IntegrationController extends BaseController
{
    public function __construct()
    {
        $this->requirePermission(Permission::INTEGRATIONS, 'La gestión de conectores y credenciales requiere el permiso específico de integraciones.');
    }

    public function index(): void
    {
        $tenantId = $this->tenantId();
        $this->view('tenant/integraciones/index', [
            'title' => 'Integraciones externas',
            'integrations' => Integration::forTenant($tenantId),
            'executions' => IntegrationExecution::forTenant($tenantId),
            'types' => Integration::TYPES,
        ]);
    }

    public function store(): void
    {
        try {
            $id = Integration::create($this->configurationInput() + ['tenant_id' => $this->tenantId(), 'created_by' => Auth::id()]);
            Audit::record('Conector HTTPS configurado', 'Integration', $id, ['tipo' => $this->input('tipo'), 'proveedor' => $this->input('nombre')]);
            flash('success', 'Conector guardado con credencial cifrada. No se ha realizado ninguna solicitud externa.');
        } catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/integraciones');
    }

    public function update(int $id): void
    {
        try {
            Integration::update($id, $this->tenantId(), $this->configurationInput());
            Audit::record('Conector HTTPS actualizado', 'Integration', $id, ['tipo' => $this->input('tipo'), 'credencial_reemplazada' => trim((string) $this->input('credencial')) !== '']);
            flash('success', 'Configuración actualizada. La credencial anterior se conservó si dejaste el campo vacío.');
        } catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/integraciones#integracion-' . $id);
    }

    public function toggle(int $id): void
    {
        try {
            $active = $this->input('activo') === '1';
            Integration::setActive($id, $this->tenantId(), $active);
            Audit::record($active ? 'Conector activado' : 'Conector desactivado', 'Integration', $id);
            flash('success', $active ? 'Conector activado; cada operación requerirá una acción explícita.' : 'Conector desactivado; no admite nuevas ejecuciones.');
        } catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/integraciones#integracion-' . $id);
    }

    public function execute(int $id): void
    {
        try {
            $raw = (string) $this->input('payload');
            if ($raw === '' || strlen($raw) > 200000) throw new DomainException('El JSON es obligatorio y no puede superar 200.000 bytes.');
            try { $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
            catch (JsonException) { throw new DomainException('El payload no es JSON válido. No se realizó ninguna solicitud.'); }
            if (!is_array($payload)) throw new DomainException('El payload debe ser un objeto o arreglo JSON; no se aceptan valores escalares.');
            $executionId = IntegrationExecution::execute($id, $this->tenantId(), (string) $this->input('operacion'), $payload, Auth::id());
            $execution = IntegrationExecution::findForTenant($executionId, $this->tenantId());
            Audit::record('Solicitud HTTPS externa ejecutada', 'IntegrationExecution', $executionId, ['integracion_id' => $id, 'estado_tecnico' => $execution['estado_tecnico'], 'http_status' => $execution['http_status']]);
            flash($execution['estado_tecnico'] === 'Respuesta HTTPS' ? 'success' : 'error', $execution['detalle']);
        } catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/integraciones#ejecuciones');
    }

    public function verify(int $id): void
    {
        try {
            IntegrationExecution::verify($id, $this->tenantId(), Auth::id());
            Audit::record('Soporte de integración verificado', 'IntegrationExecution', $id);
            flash('success', 'Soporte externo verificado por un responsable. La traza técnica permanece separada del estado del trámite.');
        } catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/integraciones#ejecucion-' . $id);
    }

    private function configurationInput(): array
    {
        return [
            'tipo' => (string) $this->input('tipo'), 'nombre' => (string) $this->input('nombre'),
            'endpoint' => (string) $this->input('endpoint'), 'metodo' => (string) $this->input('metodo', 'POST'),
            'autenticacion' => (string) $this->input('autenticacion', 'Bearer'),
            'credencial' => (string) $this->input('credencial'), 'ruta_referencia' => (string) $this->input('ruta_referencia'),
        ];
    }
}
