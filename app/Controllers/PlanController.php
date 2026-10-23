<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PlanSaaS;
use App\Services\ModuleAccess;
use App\Support\Audit;
use App\Support\Validator;
use PDOException;

final class PlanController extends BaseController
{
    public function index(): void
    {
        $this->view('admin/planes/index', ['title' => 'Planes del SaaS', 'plans' => PlanSaaS::all(), 'moduleOptions' => ModuleAccess::options()]);
    }

    public function store(): void
    {
        $data = $this->data();
        $validator = $this->validate($data);
        $validator->required('nombre', 'nombre del plan')->max('nombre', 'nombre', 80);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/admin/planes');
        }
        try {
            $id = PlanSaaS::create($data);
        } catch (PDOException) {
            fail_validation(['nombre' => 'Ya existe un plan con este nombre.'], $data, '/admin/planes');
        }
        Audit::record('Plan SaaS creado', 'PlanSaaS', $id, ['nombre' => $data['nombre']]);
        flash('success', 'El nuevo plan está disponible para las empresas.');
        redirect('/admin/planes');
    }

    public function update(int $id): void
    {
        $plan = PlanSaaS::find($id);
        if ($plan === null) {
            $this->notFound('El plan no existe.');
        }
        $data = $this->data();
        $data['activo'] = (string) $this->input('activo') === '1';
        $validator = $this->validate($data);
        if ($validator->fails()) {
            fail_validation($validator->errors(), [], '/admin/planes');
        }
        PlanSaaS::update($id, $data);
        Audit::record('Plan SaaS actualizado', 'PlanSaaS', $id, ['activo' => $data['activo']]);
        flash('success', 'Plan actualizado. Los cambios no alteran los registros históricos de auditoría.');
        redirect('/admin/planes');
    }

    private function data(): array
    {
        return [
            'nombre' => (string) $this->input('nombre'), 'descripcion' => (string) $this->input('descripcion'),
            'precio_mensual' => $this->input('precio_mensual'), 'max_usuarios' => $this->input('max_usuarios'),
            'max_empleados' => $this->input('max_empleados'),
            'modulo' => (string) $this->input('modulo', 'sgsst'),
        ];
    }

    private function validate(array $data): Validator
    {
        $validator = new Validator($data);
        $validator->required('nombre', 'nombre del plan')->max('nombre', 'nombre', 80)
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 500)
            ->required('precio_mensual', 'precio')->numeric('precio_mensual', 'precio')
            ->required('max_usuarios', 'máximo de usuarios')->integer('max_usuarios', 'máximo de usuarios', 1, 100000)
            ->required('max_empleados', 'máximo de empleados')->integer('max_empleados', 'máximo de empleados', 1, 10000000)
            ->required('modulo', 'módulo del plan')->in('modulo', 'módulo del plan', array_keys(ModuleAccess::options()));
        return $validator;
    }
}
