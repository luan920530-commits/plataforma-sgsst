<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\MaturityPolicy;
use App\Services\RequirementCatalog;
use RuntimeException;

final class Autoevaluacion
{
    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT * FROM autoevaluaciones WHERE tenant_id = ? ORDER BY vigencia DESC', [$tenantId]);
    }

    public static function findForTenant(int $tenantId, int $year): ?array
    {
        return Database::first('SELECT * FROM autoevaluaciones WHERE tenant_id = ? AND vigencia = ?', [$tenantId, $year]);
    }

    public static function initialize(int $tenantId, int $year, int $userId): array
    {
        if ($year < 2019 || $year > 2200) throw new \InvalidArgumentException('Vigencia fuera de rango.');
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) throw new RuntimeException('La empresa no existe.');
        Database::transaction(static function () use ($tenantId, $year, $userId, $tenant): void {
            Database::execute(
                'INSERT OR IGNORE INTO autoevaluaciones (tenant_id, vigencia, grupo, numero_trabajadores, nivel_riesgo, created_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$tenantId, $year, (int) $tenant['grupo_estandares'], (int) $tenant['numero_trabajadores'], $tenant['nivel_riesgo'], $userId]
            );
            $evaluation = self::findForTenant($tenantId, $year);
            $evaluationId = (int) $evaluation['id'];
            $existing = (int) (Database::first('SELECT COUNT(*) AS total FROM requisitos_matriz WHERE autoevaluacion_id = ?', [$evaluationId])['total'] ?? 0);
            if ($existing > 0) return;
            $applicable = RequirementCatalog::applicableCodes((int) $tenant['grupo_estandares']);
            $definitions = array_merge(RequirementCatalog::autoevaluation(), RequirementCatalog::legal(), RequirementCatalog::iso());
            $statement = Database::connection()->prepare(
                'INSERT INTO requisitos_matriz (
                    tenant_id, autoevaluacion_id, vigencia, tipo, item_codigo, ciclo, estandar, norma_referencia,
                    articulo, iso_clausula, criterio, evidencia_requerida, periodicidad, fecha_vencimiento,
                    puntaje_maximo, aplica_sugerida, justificacion_na, modulo_url, fuente_oficial
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($definitions as $definition) {
                $suggested = array_key_exists('aplica_sugerida', $definition)
                    ? (int) $definition['aplica_sugerida']
                    : (in_array($definition['item_codigo'], $applicable, true) ? 1 : 0);
                $justification = '';
                if (!$suggested && $definition['tipo'] === 'Autoevaluacion') {
                    $justification = 'Ítem no incluido en el grupo de ' . (int) $tenant['grupo_estandares'] . ' estándares de la Resolución 0312; requiere confirmación humana de aplicabilidad.';
                } elseif (!$suggested && $definition['tipo'] === 'ISO 45001') {
                    $justification = 'Referencia voluntaria ISO 45001; activar únicamente si la organización adopta este criterio.';
                }
                $statement->execute([
                    $tenantId, $evaluationId, $year, $definition['tipo'], $definition['item_codigo'], $definition['ciclo'], $definition['estandar'],
                    $definition['norma_referencia'], $definition['articulo'], $definition['iso_clausula'], $definition['criterio'],
                    $definition['evidencia_requerida'], $definition['periodicidad'], $year . '-12-31',
                    (float) $definition['puntaje_maximo'], $suggested, $justification, $definition['modulo_url'], $definition['fuente_oficial'],
                ]);
            }
        });
        return self::findForTenant($tenantId, $year);
    }

    public static function requirements(int $tenantId, int $evaluationId, string $type = 'Autoevaluacion'): array
    {
        return Database::query(
            'SELECT r.*, v.nombre AS verificado_por_nombre, n.nombre AS na_aprobado_por_nombre, e.nombre AS eficacia_por_nombre
             FROM requisitos_matriz r
             LEFT JOIN users v ON v.id = r.verificado_por
             LEFT JOIN users n ON n.id = r.na_aprobado_por
             LEFT JOIN users e ON e.id = r.eficacia_por
             WHERE r.tenant_id = ? AND r.autoevaluacion_id = ? AND r.tipo = ?
             ORDER BY r.id', [$tenantId, $evaluationId, $type]
        );
    }

    public static function requirement(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM requisitos_matriz WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function confirmApplicability(int $evaluationId, int $tenantId, int $userId): void
    {
        Database::transaction(static function () use ($evaluationId, $tenantId, $userId): void {
            $evaluation = Database::first('SELECT * FROM autoevaluaciones WHERE id = ? AND tenant_id = ?', [$evaluationId, $tenantId]);
            if ($evaluation === null || $evaluation['estado'] === 'Cerrada') throw new RuntimeException('La autoevaluación no está disponible para edición.');
            Database::execute(
                "UPDATE requisitos_matriz SET nivel_madurez = 'No aplica', evaluacion = 'No aplica',
                    puntaje_obtenido = puntaje_maximo, na_aprobado_por = ?, na_aprobado_at = CURRENT_TIMESTAMP,
                    justificacion_na = CASE WHEN justificacion_na = '' THEN 'No aplica según el grupo de estándares confirmado por la empresa.' ELSE justificacion_na END,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE tenant_id = ? AND autoevaluacion_id = ? AND tipo = 'Autoevaluacion' AND aplica_sugerida = 0
                   AND nivel_madurez = 'Sin iniciar' AND evaluacion = 'Sin evaluar'",
                [$userId, $tenantId, $evaluationId]
            );
            Database::execute(
                'UPDATE autoevaluaciones SET aplicabilidad_confirmada = 1, aplicabilidad_por = ?, aplicabilidad_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
                [$userId, $evaluationId, $tenantId]
            );
            self::recalculate($evaluationId, $tenantId);
        });
    }

    public static function updateRequirement(int $id, int $tenantId, array $input, int $userId, bool $canVerify): array
    {
        $current = self::requirement($id, $tenantId);
        if ($current === null) throw new RuntimeException('El requisito no existe o no pertenece a la empresa.');
        $evaluation = Database::first('SELECT * FROM autoevaluaciones WHERE id = ? AND tenant_id = ?', [(int) $current['autoevaluacion_id'], $tenantId]);
        if ($evaluation === null || $evaluation['estado'] === 'Cerrada') throw new RuntimeException('La vigencia está cerrada; conserva su trazabilidad y crea la siguiente evaluación.');
        $errors = MaturityPolicy::validate($input, $current, $tenantId, $id, $canVerify);
        if ($errors !== []) return $errors;

        $level = $input['nivel_madurez'];
        $evaluationValue = $input['evaluacion'];
        $verifiedBy = $current['verificado_por'];
        $verifiedAt = $current['verificado_at'];
        if (in_array($level, ['Verificado','Eficaz'], true)) {
            if (!in_array($current['nivel_madurez'], ['Verificado','Eficaz'], true) || $input['nota_verificacion'] !== $current['nota_verificacion']) {
                $verifiedBy = $userId;
                $verifiedAt = date('Y-m-d H:i:s');
            }
        } else {
            $verifiedBy = null; $verifiedAt = null;
        }
        $efficacyBy = $current['eficacia_por'];
        if ($level === 'Eficaz' && ($current['nivel_madurez'] !== 'Eficaz' || $input['eficacia_resultado'] !== $current['eficacia_resultado'])) $efficacyBy = $userId;
        if ($level !== 'Eficaz') $efficacyBy = null;
        $naBy = $level === 'No aplica' ? $userId : null;
        $naAt = $level === 'No aplica' ? date('Y-m-d H:i:s') : null;
        $score = in_array($evaluationValue, ['Cumple','No aplica'], true) ? (float) $current['puntaje_maximo'] : 0;

        Database::execute(
            'UPDATE requisitos_matriz SET responsable = ?, periodicidad = ?, fecha_vencimiento = ?, nivel_madurez = ?, evaluacion = ?,
                puntaje_obtenido = ?, justificacion_na = ?, na_aprobado_por = ?, na_aprobado_at = ?, nota_implementacion = ?,
                fecha_implementacion = ?, verificado_por = ?, verificado_at = ?, nota_verificacion = ?, eficacia_resultado = ?,
                eficacia_fecha = ?, eficacia_por = ?, accion_mejora = ?, recursos = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            [
                trim($input['responsable']), trim($input['periodicidad']), $input['fecha_vencimiento'] ?: null, $level, $evaluationValue, $score,
                trim($input['justificacion_na']), $naBy, $naAt, trim($input['nota_implementacion']), $input['fecha_implementacion'] ?: null,
                $verifiedBy, $verifiedAt, trim($input['nota_verificacion']), trim($input['eficacia_resultado']), $input['eficacia_fecha'] ?: null,
                $efficacyBy, trim($input['accion_mejora']), trim($input['recursos']), $id, $tenantId,
            ]
        );
        self::recalculate((int) $current['autoevaluacion_id'], $tenantId);
        return [];
    }

    public static function updateHeader(int $evaluationId, int $tenantId, array $data): void
    {
        Database::execute(
            'UPDATE autoevaluaciones SET fecha_evaluacion = ?, responsable = ?, remitida_arl_at = ?, registrada_sgrl_at = ?, radicado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            [$data['fecha_evaluacion'] ?: null, trim($data['responsable']), $data['remitida_arl_at'] ?: null, $data['registrada_sgrl_at'] ?: null, trim($data['radicado']), $evaluationId, $tenantId]
        );
    }

    public static function close(int $evaluationId, int $tenantId, int $userId): array
    {
        $evaluation = Database::first('SELECT * FROM autoevaluaciones WHERE id = ? AND tenant_id = ?', [$evaluationId, $tenantId]);
        if ($evaluation === null || $evaluation['estado'] === 'Cerrada') return ['general' => 'La autoevaluación no existe o ya está cerrada.'];
        if (trim((string) $evaluation['responsable']) === '' || empty($evaluation['fecha_evaluacion'])) return ['general' => 'Registra primero la fecha y la persona responsable de la autoevaluación.'];
        if (!(int) $evaluation['aplicabilidad_confirmada']) return ['general' => 'Confirma la aplicabilidad del grupo antes de cerrar.'];
        $rows = self::requirements($tenantId, $evaluationId);
        $errors = [];
        foreach ($rows as $row) {
            if ($row['evaluacion'] === 'Sin evaluar') $errors['general'] = 'Todos los ítems deben tener Cumple, No cumple o No aplica antes del cierre.';
            if ($row['evaluacion'] === 'Cumple' && !in_array($row['nivel_madurez'], ['Verificado','Eficaz'], true)) $errors['general'] = 'Hay ítems marcados Cumple sin verificación humana.';
            if ($row['evaluacion'] === 'No aplica' && empty($row['na_aprobado_por'])) $errors['general'] = 'Hay ítems No aplica sin aprobación.';
            if ($row['evaluacion'] === 'No cumple' && (trim($row['accion_mejora']) === '' || trim($row['responsable']) === '' || trim($row['recursos']) === '' || empty($row['fecha_vencimiento']))) {
                $errors['general'] = 'Cada No cumple necesita acción, responsable, recursos y fecha límite para el plan de mejora. Revisa ' . $row['item_codigo'] . '.';
            }
            if ($errors !== []) break;
        }
        if ($errors !== []) return $errors;
        self::recalculate($evaluationId, $tenantId);
        $score = (float) (Database::first('SELECT puntaje FROM autoevaluaciones WHERE id = ?', [$evaluationId])['puntaje'] ?? 0);
        $rating = self::rating($score);
        Database::execute(
            'UPDATE autoevaluaciones SET estado = ?, valoracion = ?, cerrada_por = ?, cerrada_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?',
            ['Cerrada', $rating, $userId, $evaluationId, $tenantId]
        );
        return [];
    }

    public static function stats(int $tenantId, int $evaluationId): array
    {
        $row = Database::first(
            "SELECT COUNT(*) AS total,
                SUM(CASE WHEN nivel_madurez = 'Sin iniciar' THEN 1 ELSE 0 END) AS sin_iniciar,
                SUM(CASE WHEN nivel_madurez = 'Documentado' THEN 1 ELSE 0 END) AS documentado,
                SUM(CASE WHEN nivel_madurez = 'Implementado' THEN 1 ELSE 0 END) AS implementado,
                SUM(CASE WHEN nivel_madurez IN ('Verificado','Eficaz') THEN 1 ELSE 0 END) AS verificado,
                SUM(CASE WHEN nivel_madurez = 'Eficaz' THEN 1 ELSE 0 END) AS eficaz,
                SUM(CASE WHEN evaluacion = 'Cumple' THEN 1 ELSE 0 END) AS cumple,
                SUM(CASE WHEN evaluacion = 'No cumple' THEN 1 ELSE 0 END) AS no_cumple,
                SUM(CASE WHEN evaluacion = 'No aplica' THEN 1 ELSE 0 END) AS no_aplica,
                SUM(CASE WHEN evaluacion = 'Sin evaluar' THEN 1 ELSE 0 END) AS sin_evaluar,
                COALESCE(SUM(puntaje_obtenido),0) AS puntaje
             FROM requisitos_matriz WHERE tenant_id = ? AND autoevaluacion_id = ? AND tipo = 'Autoevaluacion'",
            [$tenantId, $evaluationId]
        );
        return $row ?: [];
    }

    public static function rating(float $score): string
    {
        if ($score < 60) return 'Critico';
        if ($score <= 85) return 'Moderadamente aceptable';
        return 'Aceptable';
    }

    private static function recalculate(int $evaluationId, int $tenantId): void
    {
        $score = (float) (Database::first(
            "SELECT COALESCE(SUM(puntaje_obtenido),0) AS total FROM requisitos_matriz WHERE tenant_id = ? AND autoevaluacion_id = ? AND tipo = 'Autoevaluacion'",
            [$tenantId, $evaluationId]
        )['total'] ?? 0);
        Database::execute('UPDATE autoevaluaciones SET puntaje = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND tenant_id = ?', [round($score, 2), $evaluationId, $tenantId]);
    }
}
