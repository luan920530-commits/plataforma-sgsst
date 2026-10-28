<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Indicador;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Indicator;
use App\Support\Validator;
use DomainException;

final class IndicadorController extends BaseController
{
    public function index(): void
    {
        $this->view('tenant/indicadores/index', [
            'title' => 'Indicadores del SG-SST',
            'indicators' => Indicador::forTenant($this->tenantId()),
            'definitions' => $this->definitions(),
        ]);
    }

    public function store(): void
    {
        [$data, $errors] = $this->validatedInput();
        if ($errors !== []) {
            fail_validation($errors, $data, '/indicadores');
        }
        $data['tenant_id'] = $this->tenantId();
        $id = Indicador::create($data);
        Audit::record('Medición de indicador registrada', 'Indicador', $id, ['tipo' => $data['tipo'], 'resultado' => $data['resultado']]);
        flash('success', 'Medición guardada. Verifica la calidad y trazabilidad de los datos fuente.');
        redirect('/indicadores');
    }

    public function edit(int $id): void
    {
        $indicator = $this->indicator($id);
        $this->view('tenant/indicadores/edit', [
            'title' => 'Editar medición',
            'indicator' => $indicator,
            'definitions' => $this->definitions(),
        ]);
    }

    public function update(int $id): void
    {
        $current = $this->indicator($id);
        [$data, $errors] = $this->validatedInput();
        if ($errors !== []) {
            fail_validation($errors, $data, '/indicadores/' . $id . '/editar');
        }
        Indicador::update($id, $this->tenantId(), $data);
        Audit::record('Medición de indicador actualizada', 'Indicador', $id, ['antes' => $current, 'tipo' => $data['tipo'], 'resultado' => $data['resultado']]);
        flash('success', 'Medición actualizada y resultado recalculado en el servidor.');
        redirect('/indicadores');
    }

    public function verify(int $id): void
    {
        try { Indicador::verify($id, $this->tenantId(), Auth::id()); Audit::record('Fuentes de indicador verificadas', 'Indicador', $id); flash('success', 'Fuentes y soporte verificados por un responsable.'); }
        catch (DomainException $exception) { flash('error', $exception->getMessage()); }
        redirect('/indicadores#indicador-' . $id);
    }

    public function delete(int $id): void
    {
        $indicator = $this->indicator($id);
        Indicador::delete($id, $this->tenantId());
        Audit::record('Medición de indicador eliminada', 'Indicador', $id, ['registro' => $indicator]);
        flash('success', 'Medición eliminada. La acción quedó registrada en auditoría.');
        redirect('/indicadores');
    }

    private function validatedInput(): array
    {
        $data = [
            'tipo' => (string) $this->input('tipo'), 'nombre' => (string) $this->input('nombre'),
            'periodo' => (string) $this->input('periodo'), 'numerador' => $this->input('numerador'),
            'denominador' => $this->input('denominador'), 'meta' => $this->input('meta'),
            'fecha_corte' => (string) $this->input('fecha_corte'),
            'fuente_numerador' => (string) $this->input('fuente_numerador'),
            'fuente_denominador' => (string) $this->input('fuente_denominador'),
            'responsable' => (string) $this->input('responsable'),
            'observaciones' => (string) $this->input('observaciones'),
            'referencia_valor' => $this->input('referencia_valor'),
            'referencia_fuente' => (string) $this->input('referencia_fuente'),
            'referencia_periodo' => (string) $this->input('referencia_periodo'),
        ];
        $validator = new Validator($data);
        $validator->required('tipo', 'tipo')->in('tipo', 'tipo', array_keys($this->definitions()))
            ->required('nombre', 'nombre')->max('nombre', 'nombre', 200)
            ->required('periodo', 'periodo')->max('periodo', 'periodo', 30)
            ->required('numerador', 'numerador')->numeric('numerador', 'numerador')
            ->required('denominador', 'denominador')->numeric('denominador', 'denominador')
            ->numeric('meta', 'meta')->required('fecha_corte', 'fecha de corte')->date('fecha_corte', 'fecha de corte')
            ->required('fuente_numerador', 'fuente del numerador')->max('fuente_numerador', 'fuente del numerador', 2000)
            ->required('fuente_denominador', 'fuente del denominador')->max('fuente_denominador', 'fuente del denominador', 2000)
            ->required('responsable', 'responsable')->max('responsable', 'responsable', 250)->max('observaciones', 'observaciones', 3000)
            ->numeric('referencia_valor', 'valor de referencia')->max('referencia_fuente', 'fuente de referencia', 2000)->max('referencia_periodo', 'periodo de referencia', 60);
        $errors = $validator->errors();
        if (is_numeric($data['denominador']) && (float) $data['denominador'] <= 0) {
            $errors['denominador'] = 'El denominador debe ser mayor que cero para obtener un resultado interpretable.';
        }
        if ($data['tipo'] === 'Presentismo' && is_numeric($data['numerador']) && is_numeric($data['denominador']) && ((float) $data['numerador'] < 0 || (float) $data['numerador'] > (float) $data['denominador'])) {
            $errors['numerador'] = 'En presentismo, los días de ausencia deben estar entre cero y los días programados.';
        }
        if (is_numeric($data['referencia_valor']) && (trim($data['referencia_fuente']) === '' || trim($data['referencia_periodo']) === '')) {
            $errors['referencia_fuente'] = 'Una referencia comparativa exige fuente y periodo; no uses promedios sin trazabilidad.';
        }
        $customFactor = $this->input('factor', 1);
        $customUnit = (string) $this->input('unidad');
        if ($data['tipo'] === 'Personalizado') {
            if (!is_numeric($customFactor) || (float) $customFactor <= 0) {
                $errors['factor'] = 'El factor personalizado debe ser un número mayor que cero.';
            }
            if (text_length($customUnit) > 80) {
                $errors['unidad'] = 'La unidad personalizada no puede superar 80 caracteres.';
            }
        }
        $data['factor'] = $customFactor;
        $data['unidad'] = $customUnit;
        if ($errors === []) {
            $definition = Indicator::definition($data['tipo']);
            $data['factor'] = $data['tipo'] === 'Personalizado' ? (float) $customFactor : $definition['factor'];
            $data['unidad'] = $data['tipo'] === 'Personalizado' ? $customUnit : $definition['unidad'];
            $data['resultado'] = $data['tipo'] === 'Presentismo'
                ? Indicator::calculatePresentism((float) $data['numerador'], (float) $data['denominador'])
                : Indicator::calculate((float) $data['numerador'], (float) $data['denominador'], (float) $data['factor']);
        }
        return [$data, $errors];
    }

    private function indicator(int $id): array
    {
        $indicator = Indicador::findForTenant($id, $this->tenantId());
        if ($indicator === null) {
            $this->notFound('La medición no existe o no pertenece a tu empresa.');
        }
        return $indicator;
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach (['Frecuencia', 'Severidad', 'Mortalidad', 'Prevalencia', 'Incidencia', 'Ausentismo', 'Presentismo', 'Personalizado'] as $type) {
            $definitions[$type] = Indicator::definition($type);
        }
        return $definitions;
    }
}
