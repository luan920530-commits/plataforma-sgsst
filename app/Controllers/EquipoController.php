<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\Empleado;
use App\Models\PlanSaaS;
use App\Models\Permission;
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
            'permissionDefinitions' => Permission::definitions(),
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
        $data['permisos'] = $this->requestedPermissions($data['rol']);
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
        $permissions = $data['permisos'];
        try {
            $userId = Database::transaction(static function () use ($data, $tenantId, $employee, $permissions): int {
                $userId = User::create([
                    'tenant_id' => $tenantId, 'nombre' => $data['nombre'], 'email' => $data['email'], 'rol' => $data['rol'],
                    'licencia_sst' => $data['licencia_sst'], 'password' => password_hash($data['password'], PASSWORD_DEFAULT), 'must_change_password' => true,
                ]);
                if ($employee !== null) {
                    Empleado::linkUser((int) $employee['id'], $tenantId, $userId);
                }
                Permission::sync($userId, $tenantId, $permissions, Auth::id());
                return $userId;
            });
        } catch (PDOException) {
            fail_validation(['email' => 'No fue posible crear el acceso; verifica el correo y la vinculación.'], $data, '/equipo');
        }
        Audit::record('Acceso de empresa creado', 'User', $userId, ['rol' => $data['rol']]);
        flash('success', 'El acceso fue creado de forma segura. Comparte la contraseña inicial por un canal privado y solicita su cambio.');
        redirect('/equipo');
    }

    public function editEmployee(int $id): void
    {
        $employee = Empleado::findForTenant($id, $this->tenantId());
        if ($employee === null) $this->notFound();
        $this->view('tenant/equipo/edit', ['title' => 'Editar persona', 'employee' => $employee]);
    }

    public function updateEmployee(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = Empleado::findForTenant($id, $tenantId);
        if ($before === null) $this->notFound();
        $data = [
            'nombre' => (string) $this->input('nombre'), 'documento' => (string) $this->input('documento'),
            'email' => text_lower((string) $this->input('email')), 'cargo' => (string) $this->input('cargo'),
            'area' => (string) $this->input('area'), 'tipo_vinculacion' => (string) $this->input('tipo_vinculacion'),
            'fecha_ingreso' => (string) $this->input('fecha_ingreso'), 'telefono' => (string) $this->input('telefono'),
        ];
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre completo')->max('nombre', 'nombre', 180)->required('documento', 'documento')
            ->max('documento', 'documento', 40)->email('email')->required('cargo', 'cargo')->required('area', 'área')
            ->required('tipo_vinculacion', 'tipo de vinculación')->in('tipo_vinculacion', 'vinculación', ['Contrato laboral', 'Contratista', 'Aprendiz', 'Temporal'])
            ->date('fecha_ingreso', 'fecha de ingreso')->required('fecha_ingreso', 'fecha de ingreso')->max('telefono', 'teléfono', 60);
        if ($validator->fails()) fail_validation($validator->errors(), $data, '/equipo/empleados/' . $id . '/editar');
        try {
            Empleado::update($id, $tenantId, $data);
        } catch (PDOException) {
            fail_validation(['documento' => 'Ya existe otra persona con este documento en la empresa.'], $data, '/equipo/empleados/' . $id . '/editar');
        }
        Audit::record('Empleado actualizado', 'Empleado', $id, ['antes' => $before, 'despues' => $data]);
        flash('success', 'Datos de la persona actualizados. El acceso y el estado de vinculación se conservaron.');
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

    public function editUser(int $id): void
    {
        $account = User::findForTenant($id, $this->tenantId());
        if ($account === null) {
            $this->notFound('La cuenta no existe o no pertenece a tu empresa.');
        }
        $this->view('tenant/equipo/user-edit', [
            'title' => 'Editar acceso', 'account' => $account,
            'permissionDefinitions' => Permission::definitions(), 'accountPermissions' => Permission::forUser($id, $this->tenantId()),
        ]);
    }

    public function updateUser(int $id): void
    {
        $tenantId = $this->tenantId();
        $before = User::findForTenant($id, $tenantId);
        if ($before === null) {
            $this->notFound('La cuenta no existe o no pertenece a tu empresa.');
        }
        $target = '/equipo/usuarios/' . $id . '/editar';
        $data = [
            'nombre' => (string) $this->input('nombre'), 'email' => text_lower((string) $this->input('email')),
            'licencia_sst' => (string) $this->input('licencia_sst'),
            'password' => (string) $this->input('password'), 'password_confirmation' => (string) $this->input('password_confirmation'),
        ];
        $data['permisos'] = $before['rol'] === 'Trabajador' ? [] : Permission::forUser($id, $tenantId);
        if (array_key_exists('permisos', $_POST) || $before['rol'] === 'Trabajador') $data['permisos'] = $this->requestedPermissions($before['rol']);
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre')->max('nombre', 'nombre', 180)
            ->required('email', 'correo')->email('email')->max('email', 'correo', 190)
            ->max('licencia_sst', 'licencia SST', 120)->password('password')->confirmed('password', 'password_confirmation');
        $errors = $validator->errors();
        if (User::emailExists($data['email'], $id)) {
            $errors['email'] = 'Este correo ya está registrado en otra cuenta.';
        }
        if ($data['password'] !== '' && $id === Auth::id()) {
            $errors['password'] = 'Para cambiar tu propia contraseña utiliza Seguridad de la cuenta; aquí sólo puedes restablecer la de otras cuentas.';
        }
        if ($errors !== []) {
            fail_validation($errors, $data, $target);
        }
        $permissions = $data['permisos'];
        try {
            Database::transaction(static function () use ($id, $tenantId, $data, $permissions): void {
                User::updateForTenant($id, $tenantId, $data);
                if ($data['password'] !== '') {
                    User::resetPasswordForTenant($id, $tenantId, password_hash($data['password'], PASSWORD_DEFAULT));
                }
                Permission::sync($id, $tenantId, $permissions, Auth::id());
            });
        } catch (PDOException) {
            fail_validation(['email' => 'No fue posible actualizar la cuenta; verifica que el correo no esté duplicado.'], $data, $target);
        }
        Audit::record('Acceso de empresa actualizado', 'User', $id, [
            'antes' => ['nombre' => $before['nombre'], 'email' => $before['email'], 'licencia_sst' => $before['licencia_sst']],
            'despues' => ['nombre' => $data['nombre'], 'email' => $data['email'], 'licencia_sst' => $data['licencia_sst']],
            'clave_temporal_restablecida' => $data['password'] !== '',
        ]);
        flash('success', $data['password'] !== ''
            ? 'Cuenta actualizada. La nueva contraseña es temporal y deberá cambiarse en el próximo ingreso.'
            : 'Datos de la cuenta actualizados; su rol y estado se conservaron.');
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

    private function requestedPermissions(string $role): array
    {
        if ($role !== 'ResponsableSST' && $role !== 'AdminEmpresa') return [];
        $requested = $_POST['permisos'] ?? [];
        if (!is_array($requested)) return [];
        return array_values(array_intersect(array_keys(Permission::definitions()), array_filter($requested, 'is_string')));
    }
}
