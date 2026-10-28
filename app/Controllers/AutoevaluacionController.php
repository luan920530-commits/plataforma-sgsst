<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Autoevaluacion;
use App\Models\Permission;
use App\Models\Tenant;
use App\Support\AccessControl;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Validator;
use RuntimeException;

final class AutoevaluacionController extends BaseController
{
    public function index(): void
    {
        $tenantId = $this->tenantId();
        $evaluations = Autoevaluacion::forTenant($tenantId);
        $year = (int) $this->query('vigencia', $evaluations[0]['vigencia'] ?? 0);
        $evaluation = $year > 0 ? Autoevaluacion::findForTenant($tenantId, $year) : null;
        $type = (string) $this->query('tipo', 'Autoevaluacion');
        if (!in_array($type, ['Autoevaluacion','Legal','ISO 45001'], true)) $type = 'Autoevaluacion';
        $this->view('tenant/autoevaluacion/index', [
            'title' => 'Matriz única y autoevaluación SG-SST', 'evaluations' => $evaluations, 'evaluation' => $evaluation,
            'requirements' => $evaluation ? Autoevaluacion::requirements($tenantId, (int) $evaluation['id'], $type) : [],
            'stats' => $evaluation ? Autoevaluacion::stats($tenantId, (int) $evaluation['id']) : [],
            'type' => $type, 'tenant' => Tenant::find($tenantId), 'canVerify' => AccessControl::can(Permission::MATRIX_VERIFY),
        ]);
    }

    public function initialize(): void
    {
        $year = (int) $this->input('vigencia', date('Y'));
        if ($year < 2019 || $year > (int) date('Y') + 1) {
            fail_validation(['vigencia' => 'Selecciona una vigencia entre 2019 y el próximo año.'], [], '/autoevaluacion');
        }
        $evaluation = Autoevaluacion::initialize($this->tenantId(), $year, Auth::id());
        Audit::record('Matriz de autoevaluación inicializada', 'Autoevaluacion', (int) $evaluation['id'], ['vigencia' => $year]);
        flash('success', 'Matriz creada en borrador: 60 ítems del artículo 27, referencias legales e ISO 45001. Ningún ítem fue marcado como cumplido.');
        redirect('/autoevaluacion?vigencia=' . $year);
    }

    public function editRequirement(int $id): void
    {
        $requirement = Autoevaluacion::requirement($id, $this->tenantId());
        if ($requirement === null) $this->notFound();
        $evaluation = Autoevaluacion::findForTenant($this->tenantId(), (int) $requirement['vigencia']);
        $this->view('tenant/autoevaluacion/edit', [
            'title' => $requirement['item_codigo'] . ' · ' . $requirement['estandar'], 'requirement' => $requirement,
            'evaluation' => $evaluation, 'canVerify' => AccessControl::can(Permission::MATRIX_VERIFY),
            'evidenceTotal' => \App\Models\Evidencia::countForEntity($this->tenantId(), 'matriz_requisito', (string) $id),
            'evidenceReviewed' => \App\Models\Evidencia::reviewedCountForEntity($this->tenantId(), 'matriz_requisito', (string) $id),
        ]);
    }

    public function updateRequirement(int $id): void
    {
        $tenantId = $this->tenantId();
        $current = Autoevaluacion::requirement($id, $tenantId);
        if ($current === null) $this->notFound();
        $input = $this->requirementInput($current);
        $validator = new Validator($input);
        $validator->max('responsable', 'responsable', 250)->max('periodicidad', 'periodicidad', 120)
            ->date('fecha_vencimiento', 'fecha de vencimiento')->date('fecha_implementacion', 'fecha de implementación')
            ->date('eficacia_fecha', 'fecha de eficacia')->max('accion_mejora', 'acción de mejora', 4000)->max('recursos', 'recursos', 2000);
        if ($validator->fails()) fail_validation($validator->errors(), $input, '/autoevaluacion/requisitos/' . $id . '/editar');
        try {
            $errors = Autoevaluacion::updateRequirement($id, $tenantId, $input, Auth::id(), AccessControl::can(Permission::MATRIX_VERIFY));
        } catch (RuntimeException $exception) {
            $errors = ['general' => $exception->getMessage()];
        }
        if ($errors !== []) fail_validation($errors, $input, '/autoevaluacion/requisitos/' . $id . '/editar');
        Audit::record('Requisito de matriz actualizado', 'RequisitoMatriz', $id, [
            'vigencia' => $current['vigencia'], 'item' => $current['item_codigo'], 'madurez' => $input['nivel_madurez'], 'evaluacion' => $input['evaluacion'],
        ]);
        flash('success', 'Requisito actualizado. El puntaje se recalculó únicamente con la calificación validada.');
        redirect('/autoevaluacion/requisitos/' . $id . '/editar');
    }

    public function confirmApplicability(int $id): void
    {
        $this->requirePermission(Permission::MATRIX_VERIFY, 'La confirmación de aplicabilidad requiere un verificador autorizado.');
        $evaluation = $this->evaluation($id);
        Autoevaluacion::confirmApplicability($id, $this->tenantId(), Auth::id());
        Audit::record('Aplicabilidad de autoevaluación confirmada', 'Autoevaluacion', $id, ['grupo' => $evaluation['grupo']]);
        flash('success', 'Aplicabilidad confirmada. Los ítems fuera del grupo quedaron No aplica con justificación y aprobación; los aplicables siguen pendientes de evaluación.');
        redirect('/autoevaluacion?vigencia=' . $evaluation['vigencia']);
    }

    public function updateHeader(int $id): void
    {
        $evaluation = $this->evaluation($id);
        $data = [
            'fecha_evaluacion' => (string) $this->input('fecha_evaluacion'), 'responsable' => (string) $this->input('responsable'),
            'remitida_arl_at' => (string) $this->input('remitida_arl_at'), 'registrada_sgrl_at' => (string) $this->input('registrada_sgrl_at'),
            'radicado' => (string) $this->input('radicado'),
        ];
        $validator = new Validator($data);
        $validator->required('fecha_evaluacion', 'fecha de evaluación')->date('fecha_evaluacion', 'fecha de evaluación')
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)
            ->date('remitida_arl_at', 'fecha de remisión a ARL')->date('registrada_sgrl_at', 'fecha de registro SGRL')->max('radicado', 'radicado', 180);
        if ($validator->fails()) fail_validation($validator->errors(), $data, '/autoevaluacion?vigencia=' . $evaluation['vigencia']);
        Autoevaluacion::updateHeader($id, $this->tenantId(), $data);
        Audit::record('Encabezado y remisión de autoevaluación actualizados', 'Autoevaluacion', $id, ['vigencia' => $evaluation['vigencia']]);
        flash('success', 'Datos guardados. Las fechas de ARL/SGRL se muestran solo como lo registrado por el usuario; no son acuses automáticos.');
        redirect('/autoevaluacion?vigencia=' . $evaluation['vigencia']);
    }

    public function close(int $id): void
    {
        $this->requirePermission(Permission::MATRIX_VERIFY, 'El cierre de la autoevaluación requiere un verificador autorizado.');
        $evaluation = $this->evaluation($id);
        $errors = Autoevaluacion::close($id, $this->tenantId(), Auth::id());
        if ($errors !== []) fail_validation($errors, [], '/autoevaluacion?vigencia=' . $evaluation['vigencia']);
        Audit::record('Autoevaluación cerrada', 'Autoevaluacion', $id, ['vigencia' => $evaluation['vigencia']]);
        flash('success', 'Autoevaluación cerrada con puntaje y plan de mejora trazables. Verifica su registro en SGRL y remisión a la ARL.');
        redirect('/autoevaluacion?vigencia=' . $evaluation['vigencia']);
    }

    public function export(int $id): void
    {
        $evaluation = $this->evaluation($id);
        $rows = Autoevaluacion::requirements($this->tenantId(), $id);
        $format = (string) $this->query('formato', 'csv');
        $scope = (string) $this->query('alcance', 'autoevaluacion');
        if ($scope === 'plan') $rows = array_values(array_filter($rows, static fn(array $row): bool => $row['evaluacion'] === 'No cumple'));
        $tenant = Tenant::find($this->tenantId());
        $payload = [
            'formato' => 'apoyo_diligenciamiento_no_radicacion', 'norma' => 'Resolución 0312 de 2019 · artículo 27',
            'empresa' => ['razon_social' => $tenant['razonsocial'], 'nit' => $tenant['nit']],
            'vigencia' => (int) $evaluation['vigencia'], 'grupo_estandares' => (int) $evaluation['grupo'],
            'trabajadores' => (int) $evaluation['numero_trabajadores'], 'riesgo' => $evaluation['nivel_riesgo'],
            'estado' => $evaluation['estado'], 'puntaje' => (float) $evaluation['puntaje'], 'valoracion' => $evaluation['estado'] === 'Cerrada' ? $evaluation['valoracion'] : 'Parcial · sin cerrar',
            'items' => array_map(static fn(array $row): array => [
                'item' => $row['item_codigo'], 'ciclo' => $row['ciclo'], 'estandar' => $row['estandar'], 'peso' => (float) $row['puntaje_maximo'],
                'calificacion' => $row['evaluacion'], 'puntaje' => (float) $row['puntaje_obtenido'], 'madurez' => $row['nivel_madurez'],
                'justificacion_no_aplica' => $row['justificacion_na'], 'accion_mejora' => $row['accion_mejora'], 'responsable' => $row['responsable'],
                'recursos' => $row['recursos'], 'fecha_limite' => $row['fecha_vencimiento'], 'eficacia' => $row['eficacia_resultado'],
            ], $rows),
        ];
        if ($format === 'json') {
            while (ob_get_level() > 0) ob_end_clean();
            header('Content-Type: application/json; charset=UTF-8');
            header('Content-Disposition: attachment; filename="autoevaluacion-' . $evaluation['vigencia'] . ($scope === 'plan' ? '-plan' : '') . '.json"');
            header('Cache-Control: private, no-store');
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            exit;
        }
        $headers = ['Empresa','NIT','Vigencia','Grupo','Item','Ciclo','Estandar','Peso','Calificacion','Puntaje','Madurez','Justificacion No aplica','Accion de mejora','Responsable','Recursos','Fecha limite','Eficacia'];
        $lines = [];
        foreach ($payload['items'] as $item) {
            $lines[] = [$tenant['razonsocial'],$tenant['nit'],$evaluation['vigencia'],$evaluation['grupo'],$item['item'],$item['ciclo'],$item['estandar'],$item['peso'],$item['calificacion'],$item['puntaje'],$item['madurez'],$item['justificacion_no_aplica'],$item['accion_mejora'],$item['responsable'],$item['recursos'],$item['fecha_limite'],$item['eficacia']];
        }
        \App\Services\CsvExport::download('autoevaluacion-' . $evaluation['vigencia'] . ($scope === 'plan' ? '-plan' : '') . '.csv', $headers, $lines);
    }

    private function evaluation(int $id): array
    {
        $evaluation = \App\Core\Database::first('SELECT * FROM autoevaluaciones WHERE id = ? AND tenant_id = ?', [$id, $this->tenantId()]);
        if ($evaluation === null) $this->notFound('La autoevaluación no existe o no pertenece a tu empresa.');
        return $evaluation;
    }

    private function requirementInput(array $current): array
    {
        $data = [];
        foreach (['responsable','periodicidad','fecha_vencimiento','nivel_madurez','evaluacion','justificacion_na','nota_implementacion','fecha_implementacion','nota_verificacion','eficacia_resultado','eficacia_fecha','accion_mejora','recursos'] as $field) {
            $data[$field] = (string) $this->input($field, $current[$field] ?? '');
        }
        return $data;
    }
}
