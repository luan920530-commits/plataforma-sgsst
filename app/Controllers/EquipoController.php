<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Empleado;
use App\Models\PlanSaaS;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ModuleAccess;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use PDOException;

final class EquipoController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $search = (string) $this->query('q');
        $this->view('tenant/equipo/index', [
            'title' => 'Equipo y accesos',
            'employees' => Empleado::forTenant($tenantId, $search),
            'users' => User::allByTenant($tenantId),
            'tenant' => Tenant::find($tenantId),
            'planModule' => ModuleAccess::forTenant($tenantId),
            'search' => $search,
        ]);
    }

    public function storeEmployee(): void
    {
        $tenantId = $this->tenantId();
        $data = [
            'tenant_id' => $tenantId, 'nombre' => (string) $this->input('nombre'), 'documento' => (string) $this->input('documento'),
            'email' => text_lower((string) $this->input('email')), 'cargo' => (string) $this->input('cargo'),
            'area' => (string) $this->input('area'), 'tipo_vinculacion' => (string) $this->input('tipo_vinculacion'),
            'fecha_ingreso' => (string) $this->input('fecha_ingreso'), 'telefono' => (string) $this->input('telefono'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre completo')->max('nombre', 'nombre', 180)->required('documento', 'documento')
            ->max('documento', 'documento', 40)->email('email')->required('cargo', 'cargo')->required('area', 'área')
            ->required('tipo_vinculacion', 'tipo de vinculación')->in('tipo_vinculacion', 'vinculación', ['Contrato laboral', 'Contratista', 'Aprendiz', 'Temporal'])
            ->date('fecha_ingreso', 'fecha de ingreso')->required('fecha_ingreso', 'fecha de ingreso');
        $errors = $validator->errors();
        $tenant = Tenant::find($tenantId);
        $plan = !empty($tenant['plan_id']) ? PlanSaaS::find((int) $tenant['plan_id']) : null;
        if ($plan !== null && Empleado::countActive($tenantId) >= (int) $plan['max_empleados']) {
            $errors['general'] = 'El plan actual alcanzó su límite de empleados registrados. El SuperAdmin puede ampliar el plan.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/equipo');
        }
        try {
            $id = Empleado::create($data);
        } catch (PDOException) {
            fail_validation(['documento' => 'Ya existe una persona con este documento en la empresa.'], $data, '/equipo');
        }
        Audit::record('Empleado registrado', 'Empleado', $id, ['tipo_vinculacion' => $data['tipo_vinculacion']]);
        flash('success', 'La persona fue agregada al equipo. Ya puedes crearle un acceso al portal si lo necesita.');
        redirect('/equipo');
    }

    public function storeUser(): void
    {
        $tenantId = $this->tenantId();
        $data = [
            'tenant_id' => $tenantId, 'nombre' => (string) $this->input('nombre'), 'email' => text_lower((string) $this->input('email')),
            'rol' => (string) $this->input('rol'), 'licencia_sst' => (string) $this->input('licencia_sst'),
            'password' => (string) $this->input('password'), 'password_confirmation' => (string) $this->input('password_confirmation'),
            'empleado_id' => (int) $this->input('empleado_id', 0),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre')->required('email', 'correo')->email('email')
            ->required('rol', 'rol')->in('rol', 'rol', ['ResponsableSST', 'Trabajador'])
            ->required('password', 'contraseña')->password('password')->confirmed('password', 'password_confirmation');
        $errors = $validator->errors();
        if ($data['rol'] === 'Trabajador' && ModuleAccess::forTenant($tenantId) !== ModuleAccess::SGST) {
            $errors['rol'] = 'Este plan especializado no incluye el portal SG-SST de trabajadores. Crea una cuenta de Responsable SST o cambia el plan.';
        }
        if (User::emailExists($data['email'])) {
            $errors['email'] = 'Este correo ya está registrado en la plataforma.';
        }
        $tenant = Tenant::find($tenantId);
        $plan = !empty($tenant['plan_id']) ? PlanSaaS::find((int) $tenant['plan_id']) : null;
        if ($plan !== null && User::countByTenant($tenantId) >= (int) $plan['max_usuarios']) {
            $errors['general'] = 'El plan actual alcanzó su límite de usuarios activos.';
        }
        $employee = null;
        if ($data['empleado_id'] > 0) {
            $employee = Empleado::findForTenant($data['empleado_id'], $tenantId);
            if ($employee === null || !empty($employee['user_id'])) {
                $errors['empleado_id'] = 'La persona seleccionada no está disponible para vincular.';
            }
        }
        if ($errors !== []) {
            fail_validation($errors, $data, '/equipo');
        }
        try {
            $userId = Database::transaction(static function () use ($data, $tenantId, $employee): int {
                $userId = User::create([
                    'tenant_id' => $tenantId, 'nombre' => $data['nombre'], 'email' => $data['email'], 'rol' => $data['rol'],
                    'licencia_sst' => $data['licencia_sst'], 'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                ]);
                if ($employee !== null) {
                    Empleado::linkUser((int) $employee['id'], $tenantId, $userId);
                }
                return $userId;
            });
        } catch (PDOException) {
            fail_validation(['email' => 'No fue posible crear el acceso; verifica el correo y la vinculación.'], $data, '/equipo');
        }
        Audit::record('Acceso de empresa creado', 'User', $userId, ['rol' => $data['rol']]);
        flash('success', 'El acceso fue creado de forma segura. Comparte la contraseña inicial por un canal privado y solicita su cambio.');
        redirect('/equipo');
    }

    public function employeeStatus(int $id): void
    {
        $tenantId = $this->tenantId();
        $employee = Empleado::findForTenant($id, $tenantId);
        if ($employee === null) {
            $this->notFound();
        }
        $active = (string) $this->input('activo') === '1';
        Empleado::setActive($id, $tenantId, $active);
        if (!$active && !empty($employee['user_id'])) {
            User::setActiveForTenant((int) $employee['user_id'], $tenantId, false);
        }
        Audit::record('Estado de empleado actualizado', 'Empleado', $id, ['activo' => $active]);
        flash('success', $active ? 'Persona reactivada.' : 'Persona desactivada; su acceso asociado también quedó inactivo.');
        redirect('/equipo');
    }

    public function userStatus(int $id): void
    {
        $tenantId = $this->tenantId();
        $user = User::findForTenant($id, $tenantId);
        if ($user === null) {
            $this->notFound();
        }
        if ($id === Auth::id()) {
            flash('error', 'No puedes desactivar tu propia cuenta mientras tienes una sesión activa.');
            redirect('/equipo');
        }
        $active = (string) $this->input('activo') === '1';
        User::setActiveForTenant($id, $tenantId, $active);
        Audit::record('Estado de acceso actualizado', 'User', $id, ['activo' => $active]);
        flash('success', 'El estado del acceso fue actualizado.');
        redirect('/equipo');
    }
}
