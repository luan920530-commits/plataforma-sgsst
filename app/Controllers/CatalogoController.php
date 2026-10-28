<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ActividadEconomica;
use App\Models\EstandarMinimo;
use App\Services\ActivityCatalog;
use App\Support\Audit;
use App\Support\Validator;
use PDOException;

final class CatalogoController extends BaseController
{
    public function activities(): void
    {
        $search = (string) $this->query('q');
        $this->view('admin/catalogo/actividades', [
            'title' => 'Actividades económicas y riesgos',
            'activities' => ActividadEconomica::all(false, $search),
            'search' => $search,
            'sectors' => ['Agropecuario', 'Industrial', 'Construcción', 'Comercio', 'Transporte', 'Salud', 'Servicios', 'Educación', 'Otro'],
        ]);
    }

    public function storeActivity(): void
    {
        $data = [
            'codigo_ciuu' => (string) $this->input('codigo_ciuu'), 'descripcion' => (string) $this->input('descripcion'),
            'sector' => (string) $this->input('sector'), 'nivel_riesgo_predeterminado' => (string) $this->input('nivel_riesgo_predeterminado'),
            'perfil_sociodemografico' => (string) $this->input('perfil_sociodemografico'),
        ];
        $validator = new Validator($data);
        $validator->required('codigo_ciuu', 'código CIIU')->max('codigo_ciuu', 'código CIIU', 12)
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 250)
            ->required('sector', 'sector')->max('sector', 'sector', 100)
            ->required('nivel_riesgo_predeterminado', 'riesgo predeterminado')->in('nivel_riesgo_predeterminado', 'riesgo', ['I', 'II', 'III', 'IV', 'V'])
            ->required('perfil_sociodemografico', 'perfil sociodemográfico')->max('perfil_sociodemografico', 'perfil', 3000);
        if ($validator->fails()) {
            fail_validation($validator->errors(), $data, '/admin/actividades');
        }
        $exams = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('examenes_medicos')) ?: [])));
        if ($exams === []) {
            $exams = ['Evaluación médica ocupacional de ingreso; definir otras según exposición'];
        }
        try {
            $id = ActividadEconomica::create($data + [
                'examenes_medicos' => $exams,
                'matriz_ipevr_base' => ActivityCatalog::commonHazards(),
            ]);
        } catch (PDOException) {
            fail_validation(['codigo_ciuu' => 'Este código CIIU ya existe en el catálogo.'], $data, '/admin/actividades');
        }
        Audit::record('Actividad económica creada', 'ActividadEconomica', $id, ['codigo_ciuu' => $data['codigo_ciuu']]);
        flash('success', 'Actividad agregada con matriz transversal base. Completa y valida sus peligros específicos antes de usarla productivamente.');
        redirect('/admin/actividades');
    }

    public function editActivity(int $id): void
    {
        $activity = ActividadEconomica::find($id);
        if ($activity === null) $this->notFound('La actividad no existe.');
        $activity['examenes'] = json_decode($activity['examenes_medicos'], true) ?: [];
        $this->view('admin/catalogo/actividad-edit', [
            'title' => 'Editar actividad económica', 'activity' => $activity,
            'sectors' => ['Agropecuario', 'Industrial', 'Construcción', 'Comercio', 'Transporte', 'Salud', 'Servicios', 'Educación', 'Otro'],
        ]);
    }

    public function updateActivity(int $id): void
    {
        $before = ActividadEconomica::find($id);
        if ($before === null) $this->notFound('La actividad no existe.');
        $data = [
            'codigo_ciuu' => (string) $this->input('codigo_ciuu'), 'descripcion' => (string) $this->input('descripcion'),
            'sector' => (string) $this->input('sector'), 'nivel_riesgo_predeterminado' => (string) $this->input('nivel_riesgo_predeterminado'),
            'perfil_sociodemografico' => (string) $this->input('perfil_sociodemografico'),
        ];
        $validator = new Validator($data);
        $validator->required('codigo_ciuu', 'código CIIU')->max('codigo_ciuu', 'código CIIU', 12)
            ->required('descripcion', 'descripción')->max('descripcion', 'descripción', 250)
            ->required('sector', 'sector')->max('sector', 'sector', 100)
            ->required('nivel_riesgo_predeterminado', 'riesgo predeterminado')->in('nivel_riesgo_predeterminado', 'riesgo', ['I', 'II', 'III', 'IV', 'V'])
            ->required('perfil_sociodemografico', 'perfil sociodemográfico')->max('perfil_sociodemografico', 'perfil', 3000);
        if ($validator->fails()) fail_validation($validator->errors(), $data, '/admin/actividades/' . $id . '/editar');
        $data['examenes_medicos'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('examenes_medicos')) ?: [])));
        if ($data['examenes_medicos'] === []) $data['examenes_medicos'] = ['Evaluación médica ocupacional de ingreso; definir otras según exposición'];
        try {
            ActividadEconomica::update($id, $data);
        } catch (PDOException) {
            fail_validation(['codigo_ciuu' => 'Este código CIIU ya existe en el catálogo.'], $data, '/admin/actividades/' . $id . '/editar');
        }
        Audit::record('Actividad económica actualizada', 'ActividadEconomica', $id, ['antes' => $before, 'despues' => $data]);
        flash('success', 'Actividad actualizada. La matriz IPEVR base y las empresas existentes se conservaron; valida los cambios antes de nuevas generaciones.');
        redirect('/admin/actividades');
    }

    public function activityStatus(int $id): void
    {
        $activity = ActividadEconomica::find($id);
        if ($activity === null) {
            $this->notFound('La actividad no existe.');
        }
        $active = (string) $this->input('activo') === '1';
        ActividadEconomica::setActive($id, $active);
        Audit::record('Estado de actividad económica actualizado', 'ActividadEconomica', $id, ['activo' => $active]);
        flash('success', 'La disponibilidad de la actividad fue actualizada.');
        redirect('/admin/actividades');
    }

    public function standards(): void
    {
        $group = (int) $this->query('grupo', 60);
        if (!in_array($group, [7, 21, 60], true)) {
            $group = 60;
        }
        $standards = EstandarMinimo::byGroup($group);
        $phases = ['Planear' => [], 'Hacer' => [], 'Verificar' => [], 'Actuar' => []];
        foreach ($standards as $standard) {
            $phase = (string) ($standard['fase_phva'] ?? '');
            if (isset($phases[$phase])) {
                $phases[$phase][] = $standard;
            }
        }
        $counts = EstandarMinimo::counts();
        $this->view('admin/catalogo/estandares', [
            'title' => 'Catálogo de estándares mínimos',
            'phases' => $phases,
            'standardCount' => count($standards),
            'counts' => $counts,
            'group' => $group,
        ]);
    }
}
