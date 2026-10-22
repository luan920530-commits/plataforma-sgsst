<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\ActividadEconomica;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Hallazgo;
use App\Models\Peligro;
use App\Models\PlanSaaS;
use App\Models\PlanTrabajo;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\DocumentGenerator;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use PDOException;
use Throwable;

final class TenantController extends BaseController
{
    public function index(): void
    {
        $search = (string) $this->query('q');
        $status = (string) $this->query('estado');
        $this->view('admin/empresas/index', [
            'title' => 'Empresas',
            'tenants' => Tenant::all($search, $status),
            'stats' => Tenant::globalStats(),
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(): void
    {
        $this->view('admin/empresas/onboarding', [
            'title' => 'Configurar una nueva empresa',
            'activities' => ActividadEconomica::all(true),
            'plans' => PlanSaaS::all(true),
            'sectors' => $this->sectors(),
        ]);
    }

    public function store(): void
    {
        $data = [
            'razonsocial' => (string) $this->input('razonsocial'),
            'nit' => (string) $this->input('nit'),
            'metodo_configuracion' => (string) $this->input('metodo_configuracion', 'Combinado'),
            'actividad_economica_id' => (int) $this->input('actividad_economica_id', 0),
            'sector_economico' => (string) $this->input('sector_economico'),
            'numero_trabajadores' => $this->input('numero_trabajadores'),
            'nivel_riesgo' => (string) $this->input('nivel_riesgo'),
            'plan_id' => (int) $this->input('plan_id', 0),
            'representante_legal' => (string) $this->input('representante_legal'),
            'correo_contacto' => (string) $this->input('correo_contacto'),
            'telefono' => (string) $this->input('telefono'),
            'ciudad' => (string) $this->input('ciudad'),
            'direccion' => (string) $this->input('direccion'),
            'admin_nombre' => (string) $this->input('admin_nombre'),
            'admin_email' => text_lower((string) $this->input('admin_email')),
            'password' => (string) $this->input('password'),
            'password_confirmation' => (string) $this->input('password_confirmation'),
        ];

        $validator = new Validator($data);
        $validator->required('razonsocial', 'razón social')->max('razonsocial', 'razón social', 180)
            ->required('nit', 'NIT')->max('nit', 'NIT', 30)
            ->required('metodo_configuracion', 'método de configuración')->in('metodo_configuracion', 'método', ['Riesgo', 'Actividad', 'Combinado'])
            ->required('numero_trabajadores', 'número de trabajadores')->integer('numero_trabajadores', 'número de trabajadores', 1, 1000000)
            ->required('nivel_riesgo', 'clase de riesgo')->in('nivel_riesgo', 'clase de riesgo', ['I', 'II', 'III', 'IV', 'V'])
            ->required('sector_economico', 'sector económico')->max('sector_economico', 'sector', 100)
            ->required('representante_legal', 'representante legal')
            ->required('correo_contacto', 'correo de contacto')->email('correo_contacto')
            ->required('ciudad', 'ciudad')->max('direccion', 'dirección', 250)
            ->required('admin_nombre', 'nombre del administrador')
            ->required('admin_email', 'correo del administrador')->email('admin_email')
            ->required('password', 'contraseña')->password('password')->confirmed('password', 'password_confirmation');
        if (in_array($data['metodo_configuracion'], ['Actividad', 'Combinado'], true)) {
            $validator->required('actividad_economica_id', 'actividad económica')->integer('actividad_economica_id', 'actividad económica', 1);
        }
        $errors = $validator->errors();
        $activity = null;
        if ($data['actividad_economica_id'] > 0) {
            $activity = ActividadEconomica::find($data['actividad_economica_id']);
            if ($activity === null || !(bool) $activity['activo']) {
                $errors['actividad_economica_id'] = 'La actividad económica seleccionada no está disponible.';
            }
        }
        if (Database::first('SELECT id FROM tenants WHERE nit = ?', [$data['nit']]) !== null) {
            $errors['nit'] = 'Ya existe una empresa registrada con este NIT.';
        }
        if (User::emailExists($data['admin_email'])) {
            $errors['admin_email'] = 'Este correo ya está asociado a una cuenta.';
        }
        $plan = $data['plan_id'] > 0 ? PlanSaaS::find($data['plan_id']) : null;
        if ($plan === null || !(bool) $plan['activo']) {
            $errors['plan_id'] = 'Selecciona un plan activo para la empresa.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/admin/empresas/nueva');
        }

        $data['grupo_estandares'] = ClassificationService::groupFor((int) $data['numero_trabajadores'], $data['nivel_riesgo']);
        if ($activity !== null) {
            $data['sector_economico'] = $activity['sector'];
        }

        try {
            [$tenantId, $summary] = Database::transaction(static function () use ($data): array {
                $tenantId = Tenant::create($data);
                $adminId = User::create([
                    'tenant_id' => $tenantId,
                    'nombre' => $data['admin_nombre'],
                    'email' => $data['admin_email'],
                    'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'rol' => 'AdminEmpresa',
                ]);
                $summary = DocumentGenerator::generate($tenantId);
                Audit::record('Empresa creada y configurada', 'Tenant', $tenantId, [
                    'grupo_estandares' => $data['grupo_estandares'], 'admin_user_id' => $adminId, 'generacion' => $summary,
                ], $tenantId);
                return [$tenantId, $summary];
            });
        } catch (PDOException $exception) {
            error_log($exception->getMessage());
            fail_validation(['general' => 'No pudimos guardar la empresa. Verifica que el NIT y los correos no estén duplicados.'], $data, '/admin/empresas/nueva');
        } catch (Throwable $exception) {
            error_log((string) $exception);
            fail_validation(['general' => 'La configuración no se completó y se revirtieron los cambios. ' . ($GLOBALS['config']['debug'] ? $exception->getMessage() : 'Inténtalo nuevamente.')], $data, '/admin/empresas/nueva');
        }

        flash('success', "Empresa configurada: {$summary['estandares']} estándares, {$summary['plan_trabajo']} actividades del PAT y {$summary['peligros']} peligros base.");
        redirect('/admin/empresas/' . $tenantId);
    }

    public function show(int $id): void
    {
        $tenant = Tenant::find($id);
        if ($tenant === null) {
            $this->notFound('La empresa solicitada no existe.');
        }
        $this->view('admin/empresas/show', [
            'title' => $tenant['razonsocial'],
            'tenant' => $tenant,
            'users' => User::allByTenant($id),
            'documentStats' => Documento::stats($id),
            'planStats' => PlanTrabajo::stats($id),
            'hazardStats' => Peligro::stats($id),
            'openFindings' => Hallazgo::openCount($id),
            'audit' => Auditoria::all($id, '', 12),
            'plans' => PlanSaaS::all(true),
        ]);
    }

    public function setStatus(int $id): void
    {
        $tenant = Tenant::find($id);
        if ($tenant === null) {
            $this->notFound('La empresa solicitada no existe.');
        }
        $status = (string) $this->input('estado');
        if (!in_array($status, ['Activa', 'Suspendida'], true)) {
            $this->abort(403, 'El estado de empresa no es válido.');
        }
        Tenant::setStatus($id, $status);
        Audit::record('Estado de empresa actualizado', 'Tenant', $id, ['estado' => $status], $id);
        flash('success', "La empresa quedó en estado {$status}.");
        redirect('/admin/empresas/' . $id);
    }

    public function setPlan(int $id): void
    {
        if (Tenant::find($id) === null) {
            $this->notFound('La empresa solicitada no existe.');
        }
        $planId = (int) $this->input('plan_id');
        $plan = PlanSaaS::find($planId);
        if ($plan === null || !(bool) $plan['activo']) {
            flash('error', 'Selecciona un plan activo.');
            redirect('/admin/empresas/' . $id);
        }
        Tenant::setPlan($id, $planId);
        Audit::record('Plan de empresa actualizado', 'Tenant', $id, ['plan_id' => $planId], $id);
        flash('success', 'El plan de la empresa fue actualizado.');
        redirect('/admin/empresas/' . $id);
    }

    public function settings(): void
    {
        $this->view('tenant/settings', [
            'title' => 'Configuración de la empresa',
            'tenant' => Tenant::find($this->tenantId()),
        ]);
    }

    public function updateSettings(): void
    {
        $tenantId = $this->tenantId();
        $tenant = Tenant::find($tenantId);
        $data = [
            'representante_legal' => (string) $this->input('representante_legal'),
            'correo_contacto' => text_lower((string) $this->input('correo_contacto')),
            'telefono' => (string) $this->input('telefono'),
            'ciudad' => (string) $this->input('ciudad'),
            'direccion' => (string) $this->input('direccion'),
            'numero_trabajadores' => $tenant['numero_trabajadores'],
            'nivel_riesgo' => $tenant['nivel_riesgo'],
        ];
        $validator = new Validator($data);
        $validator->required('representante_legal', 'representante legal')->required('correo_contacto', 'correo de contacto')
            ->email('correo_contacto')->required('ciudad', 'ciudad')->max('direccion', 'dirección', 250);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/empresa/configuracion');
        }
        Tenant::updateConfiguration($tenantId, $data);
        Audit::record('Datos de contacto actualizados', 'Tenant', $tenantId);
        flash('success', 'Los datos de contacto fueron actualizados.');
        redirect('/empresa/configuracion');
    }

    private function sectors(): array
    {
        return ['Agropecuario', 'Industrial', 'Construcción', 'Comercio', 'Transporte', 'Salud', 'Servicios', 'Educación', 'Otro'];
    }
}
