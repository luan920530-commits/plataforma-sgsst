<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Indicador;
use App\Support\Audit;
use App\Support\Indicator;
use App\Support\Validator;

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
        $data = [
            'tenant_id' => $this->tenantId(), 'tipo' => (string) $this->input('tipo'), 'nombre' => (string) $this->input('nombre'),
            'periodo' => (string) $this->input('periodo'), 'numerador' => $this->input('numerador'),
            'denominador' => $this->input('denominador'), 'meta' => $this->input('meta'), 'fecha_corte' => (string) $this->input('fecha_corte'),
        ];
        $types = array_keys($this->definitions());
        $validator = new Validator($data);
        $validator->required('tipo', 'tipo')->in('tipo', 'tipo', $types)->required('nombre', 'nombre')->max('nombre', 'nombre', 200)
            ->required('periodo', 'periodo')->max('periodo', 'periodo', 30)->required('numerador', 'numerador')->numeric('numerador', 'numerador')
            ->required('denominador', 'denominador')->numeric('denominador', 'denominador')
            ->numeric('meta', 'meta')->required('fecha_corte', 'fecha de corte')->date('fecha_corte', 'fecha de corte');
        $errors = $validator->errors();
        if (is_numeric($data['denominador']) && (float) $data['denominador'] <= 0) {
            $errors['denominador'] = 'El denominador debe ser mayor que cero para obtener un resultado interpretable.';
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
        if ($errors !== []) {
            fail_validation($errors, $data + ['factor' => $customFactor, 'unidad' => $customUnit], '/indicadores');
        }
        $definition = Indicator::definition($data['tipo']);
        $factor = $data['tipo'] === 'Personalizado' ? (float) $customFactor : $definition['factor'];
        $data['factor'] = $factor;
        $data['unidad'] = $data['tipo'] === 'Personalizado' ? $customUnit : $definition['unidad'];
        $data['resultado'] = Indicator::calculate((float) $data['numerador'], (float) $data['denominador'], $factor);
        $id = Indicador::create($data);
        Audit::record('Medición de indicador registrada', 'Indicador', $id, ['tipo' => $data['tipo'], 'resultado' => $data['resultado']]);
        flash('success', 'Medición guardada. Verifica la calidad y trazabilidad de los datos fuente.');
        redirect('/indicadores');
    }

    private function definitions(): array
    {
        $definitions = [];
        foreach (['Frecuencia', 'Severidad', 'Mortalidad', 'Prevalencia', 'Incidencia', 'Ausentismo', 'Personalizado'] as $type) {
            $definitions[$type] = Indicator::definition($type);
        }
        return $definitions;
    }
}
