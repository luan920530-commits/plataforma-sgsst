<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Evidencia;

/** Reglas deliberadamente distintas para documentar, implementar, verificar y probar eficacia. */
final class MaturityPolicy
{
    public static function validate(array $input, ?array $current, int $tenantId, int $requirementId, bool $canVerify): array
    {
        $errors = [];
        $level = (string) ($input['nivel_madurez'] ?? 'Sin iniciar');
        $evaluation = (string) ($input['evaluacion'] ?? 'Sin evaluar');
        $rank = ['Sin iniciar'=>0,'Documentado'=>1,'Implementado'=>2,'Verificado'=>3,'Eficaz'=>4,'No aplica'=>0];
        if (!isset($rank[$level])) $errors['nivel_madurez'] = 'Nivel de madurez no válido.';
        if (!in_array($evaluation, ['Sin evaluar','Cumple','No cumple','No aplica'], true)) $errors['evaluacion'] = 'Calificación no válida.';

        $totalEvidence = Evidencia::countForEntity($tenantId, 'matriz_requisito', (string) $requirementId);
        $reviewedEvidence = Evidencia::reviewedCountForEntity($tenantId, 'matriz_requisito', (string) $requirementId);
        if (in_array($level, ['Documentado','Implementado','Verificado','Eficaz'], true) && $totalEvidence < 1) {
            $errors['nivel_madurez'] = 'Adjunta al menos una evidencia vinculada a este requisito antes de declararlo documentado.';
        }
        if (in_array($level, ['Implementado','Verificado','Eficaz'], true)) {
            if ($reviewedEvidence < 1) $errors['nivel_madurez'] = 'La implementación requiere al menos una evidencia revisada por una persona.';
            foreach (['responsable'=>'responsable','nota_implementacion'=>'nota de implementación','fecha_implementacion'=>'fecha de implementación'] as $field=>$label) {
                if (trim((string) ($input[$field] ?? '')) === '') $errors[$field] = 'La implementación exige ' . $label . '.';
            }
        }
        if (in_array($level, ['Verificado','Eficaz'], true)) {
            if (!$canVerify) $errors['nivel_madurez'] = 'Tu cuenta no tiene el permiso matriz.verificar.';
            if (trim((string) ($input['nota_verificacion'] ?? '')) === '') $errors['nota_verificacion'] = 'Registra qué se verificó, contra qué criterio y con qué resultado.';
        }
        if ($level === 'Eficaz') {
            if (trim((string) ($input['eficacia_resultado'] ?? '')) === '' || trim((string) ($input['eficacia_fecha'] ?? '')) === '') {
                $errors['eficacia_resultado'] = 'La eficacia exige resultado medido y fecha de evaluación posterior a la implementación.';
            }
            if (!$canVerify) $errors['eficacia_resultado'] = 'La evaluación de eficacia requiere el permiso matriz.verificar.';
            if (($current['nivel_madurez'] ?? '') !== 'Eficaz' && ($current['nivel_madurez'] ?? '') !== 'Verificado' && $level === 'Eficaz' && trim((string) ($input['nota_verificacion'] ?? '')) === '') {
                $errors['nota_verificacion'] = 'Antes de declarar eficacia debe existir una verificación documentada.';
            }
        }
        if ($level === 'No aplica' || $evaluation === 'No aplica') {
            if (!$canVerify) $errors['justificacion_na'] = 'La aprobación de No aplica requiere el permiso matriz.verificar.';
            if (text_length(trim((string) ($input['justificacion_na'] ?? ''))) < 15) $errors['justificacion_na'] = 'Justifica técnicamente por qué el requisito no aplica (mínimo 15 caracteres).';
            if ($level !== 'No aplica' || $evaluation !== 'No aplica') $errors['evaluacion'] = 'Para usar No aplica, la madurez y la calificación deben ser coherentes.';
        }
        if ($evaluation === 'Cumple') {
            if (!$canVerify) $errors['evaluacion'] = 'La calificación Cumple requiere validación de un usuario con permiso matriz.verificar.';
            if (!in_array($level, ['Verificado','Eficaz'], true)) $errors['evaluacion'] = 'Cumple solo puede declararse después de Verificado o Eficaz; un documento por sí solo no es suficiente.';
        }
        if ($level !== 'No aplica' && $evaluation === 'No aplica') $errors['evaluacion'] = 'La calificación No aplica exige madurez No aplica.';
        if ($level === 'No aplica' && $evaluation !== 'No aplica') $errors['nivel_madurez'] = 'La madurez No aplica exige calificación No aplica.';
        return $errors;
    }
}
