<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\EstandarMinimo;
use App\Models\Induccion;
use App\Models\Peligro;
use App\Models\PlanTrabajo;
use App\Models\Tenant;
use DomainException;
use RuntimeException;

final class DocumentGenerator
{
    public static function generate(int $tenantId): array
    {
        return Database::transaction(static function () use ($tenantId): array {
            $tenant = Tenant::find($tenantId);
            if ($tenant === null) {
                throw new RuntimeException('La empresa no existe.');
            }
            if (!empty($tenant['configuracion_generada_at'])) {
                throw new DomainException('La configuración inicial ya fue generada para esta empresa.');
            }

            $standards = EstandarMinimo::byGroup((int) $tenant['grupo_estandares']);
            if (count($standards) !== (int) $tenant['grupo_estandares']) {
                throw new RuntimeException('El catálogo de estándares está incompleto. Ejecute nuevamente el instalador.');
            }

            $templateTenant = DocumentTemplate::prepareTenant($tenant);
            $documentStatement = Database::connection()->prepare(
                'INSERT INTO documentos_sgsst (tenant_id, codigo_estandar, nombre_documento, fase_phva, contenido_html, tipo)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($standards as $standard) {
                $lowerName = text_lower($standard['nombre']);
                $type = self::documentType($lowerName);
                $documentStatement->execute([
                    $tenantId, $standard['codigo'], $standard['nombre'], $standard['fase_phva'],
                    DocumentTemplate::standard($templateTenant, $standard), $type,
                ]);
            }

            $mandatoryPolicies = self::mandatoryPolicies($standards);
            foreach ($mandatoryPolicies as [$code, $name, $phase, $type]) {
                $documentStatement->execute([$tenantId, $code, $name, $phase, DocumentTemplate::policy($templateTenant, $type, $code), 'Politica']);
            }

            $planActivities = self::planActivities($tenant);
            $baseDate = new \DateTimeImmutable('first day of this month');
            foreach ($planActivities as $index => $activity) {
                PlanTrabajo::create([
                    'tenant_id' => $tenantId,
                    'actividad' => $activity[0],
                    'responsable' => $activity[1],
                    'fecha_programada' => $baseDate->modify('+' . $activity[2] . ' months')->format('Y-m-t'),
                    'presupuesto' => $activity[3],
                    'fase_phva' => $activity[4],
                ]);
            }

            foreach (self::trainingProgram($tenant) as $training) {
                Capacitacion::create([
                    'tenant_id' => $tenantId,
                    'tema' => $training[0],
                    'descripcion' => $training[1],
                    'responsable' => $training[2],
                    'fecha_programada' => $baseDate->modify('+' . $training[3] . ' months')->format('Y-m-t'),
                    'duracion_horas' => $training[4],
                    'modalidad' => $training[5],
                ]);
            }

            $hazardCount = self::generateHazards($tenant);
            self::generateProfiles($tenant);
            Induccion::create([
                'tenant_id' => $tenantId,
                'titulo' => 'Inducción general SG-SST · ' . $tenant['razonsocial'],
                'contenido_html' => DocumentTemplate::induction($templateTenant),
                'version' => 1,
            ]);
            $procedureKeys = ProcedureLibrary::automaticKeys($tenantId);
            $procedureDocuments = ProcedureLibrary::generate($tenantId, $procedureKeys);
            Tenant::markGenerated($tenantId);

            return [
                'documentos' => count($standards) + count($mandatoryPolicies) + $procedureDocuments,
                'estandares' => count($standards),
                'procedimientos_formatos' => $procedureDocuments,
                'pesv' => in_array('pesv', $procedureKeys, true),
                'plan_trabajo' => count($planActivities),
                'capacitaciones' => count(self::trainingProgram($tenant)),
                'peligros' => $hazardCount,
            ];
        });
    }

    /** Sincroniza sólo documentos de una empresa existente; no repite peligros, perfiles, planes ni capacitaciones. */
    public static function complement(int $tenantId): array
    {
        return Database::transaction(static function () use ($tenantId): array {
            $tenant = Tenant::find($tenantId);
            if ($tenant === null) {
                throw new RuntimeException('La empresa no existe.');
            }
            $standards = EstandarMinimo::byGroup((int) $tenant['grupo_estandares']);
            if (count($standards) !== (int) $tenant['grupo_estandares']) {
                throw new RuntimeException('El catálogo de estándares está incompleto. Ejecute nuevamente el instalador.');
            }
            $prepared = DocumentTemplate::prepareTenant($tenant);
            $created = 0;
            $updated = 0;
            foreach ($standards as $standard) {
                $result = self::upsertGeneratedDocument([
                    'tenant_id' => $tenantId, 'codigo' => $standard['codigo'], 'nombre' => $standard['nombre'],
                    'fase' => $standard['fase_phva'], 'contenido' => DocumentTemplate::standard($prepared, $standard),
                    'tipo' => self::documentType(text_lower($standard['nombre'])),
                ]);
                $created += $result[0];
                $updated += $result[1];
            }
            foreach (self::mandatoryPolicies($standards) as [$code, $name, $phase, $type]) {
                $result = self::upsertGeneratedDocument([
                    'tenant_id' => $tenantId, 'codigo' => $code, 'nombre' => $name, 'fase' => $phase,
                    'contenido' => DocumentTemplate::policy($prepared, $type, $code), 'tipo' => 'Politica',
                ]);
                $created += $result[0];
                $updated += $result[1];
            }
            $procedures = ProcedureLibrary::complement($tenantId);
            return [
                'created' => $created + $procedures['created'],
                'updated' => $updated + $procedures['updated'],
                'standards' => count($standards),
                'kits' => $procedures['kits'],
            ];
        });
    }

    /** @return array{0:int,1:int} creados y actualizados */
    private static function upsertGeneratedDocument(array $data): array
    {
        $id = Documento::createOperationalIfMissing($data);
        if ($id !== null) {
            return [1, 0];
        }
        $existing = Documento::findByCode($data['codigo'], (int) $data['tenant_id']);
        if ($existing === null || $existing['estado'] !== 'Borrador' || (int) $existing['version'] !== 1
            || !str_contains($existing['contenido_html'], 'documento base generado a partir de la clasificación registrada')) {
            return [0, 0];
        }
        if (self::normalizedContent($existing['contenido_html']) === self::normalizedContent($data['contenido'])) {
            return [0, 0];
        }
        Documento::saveDraft((int) $existing['id'], (int) $data['tenant_id'], $data['contenido']);
        return [0, 1];
    }

    private static function normalizedContent(string $content): string
    {
        $content = preg_replace('~<span><strong>Fecha:</strong>\s*\d{2}/\d{2}/\d{4}</span>~', '<span><strong>Fecha:</strong></span>', $content) ?? $content;
        return trim(preg_replace('/\s+/', ' ', $content) ?? $content);
    }

    private static function mandatoryPolicies(array $standards): array
    {
        $hasSstPolicy = false;
        foreach ($standards as $standard) {
            $hasSstPolicy = $hasSstPolicy || str_contains(text_lower($standard['nombre']), 'política de seguridad y salud');
        }
        $policies = [];
        if (!$hasSstPolicy) {
            $policies[] = ['POL-SST', 'Política de Seguridad y Salud en el Trabajo', 'Planear', 'sst'];
        }
        $policies[] = ['POL-AD', 'Política de prevención de consumo de alcohol y drogas', 'Hacer', 'alcohol'];
        $policies[] = ['POL-CCL', 'Política y ruta del Comité de Convivencia Laboral', 'Planear', 'convivencia'];
        return $policies;
    }

    private static function generateHazards(array $tenant): int
    {
        $activity = !empty($tenant['actividad_economica_id'])
            ? Database::first('SELECT * FROM actividades_economicas WHERE id = ?', [(int) $tenant['actividad_economica_id']])
            : null;
        $hazards = [];
        if ($activity !== null) {
            $decoded = json_decode($activity['matriz_ipevr_base'], true);
            if (is_array($decoded)) {
                $hazards = $decoded;
            }
        }
        $hazards = array_merge($hazards, ActivityCatalog::commonHazards());
        $unique = [];
        foreach ($hazards as $hazard) {
            $unique[text_lower($hazard['peligro'])] = $hazard;
        }
        foreach ($unique as $hazard) {
            Peligro::create([
                'tenant_id' => (int) $tenant['id'],
                'actividad_economica_id' => $activity['id'] ?? null,
                'proceso' => $hazard['proceso'],
                'cargo' => $hazard['cargo'],
                'peligro' => $hazard['peligro'],
                'clasificacion' => $hazard['clasificacion'],
                'efectos' => $hazard['efectos'],
                'controles_existentes' => $hazard['controles_existentes'],
                'nivel_riesgo' => $hazard['nivel_riesgo'],
                'medidas_control' => $hazard['medidas_control'],
            ]);
        }
        return count($unique);
    }

    private static function generateProfiles(array $tenant): void
    {
        if (empty($tenant['actividad_economica_id'])) {
            return;
        }
        $activity = Database::first('SELECT * FROM actividades_economicas WHERE id = ?', [(int) $tenant['actividad_economica_id']]);
        if ($activity === null) {
            return;
        }
        Database::execute(
            'INSERT INTO perfiles_riesgo (tenant_id, actividad_economica_id, tipo, nombre, contenido) VALUES (?, ?, ?, ?, ?)',
            [(int) $tenant['id'], (int) $activity['id'], 'Sociodemografico', 'Perfil base · ' . $activity['descripcion'], $activity['perfil_sociodemografico'] . ' Completar con datos agregados reales de edad, sexo, escolaridad, estado civil, cargos, antigüedad y diagnóstico de condiciones de salud; evitar exponer datos clínicos individuales.']
        );
        $exams = json_decode($activity['examenes_medicos'], true) ?: [];
        Database::execute(
            'INSERT INTO perfiles_riesgo (tenant_id, actividad_economica_id, tipo, nombre, contenido) VALUES (?, ?, ?, ?, ?)',
            [(int) $tenant['id'], (int) $activity['id'], 'Examenes medicos', 'Evaluaciones sugeridas · ' . $activity['descripcion'], "Orientación inicial para definir con el profesional de salud ocupacional:\n• " . implode("\n• ", $exams) . "\n\nLa pertinencia, los contenidos y la frecuencia deben definirse según los peligros, la exposición, el estado de salud y los sistemas de vigilancia. No constituye una orden médica."]
        );
    }

    private static function documentType(string $name): string
    {
        return match (true) {
            str_contains($name, 'política') => 'Politica',
            str_contains($name, 'plan anual') => 'Plan',
            str_contains($name, 'capacitación') || str_contains($name, 'inducción') => 'Programa',
            str_contains($name, 'identificación de peligros') || str_contains($name, 'metodología') => 'Matriz',
            str_contains($name, 'procedimiento') => 'Procedimiento',
            default => 'Estandar',
        };
    }

    private static function planActivities(array $tenant): array
    {
        $responsible = 'Responsable SG-SST';
        $management = $tenant['representante_legal'] ?: 'Alta dirección';
        return [
            ['Validar evaluación inicial, matriz legal y alcance del SG-SST', $responsible, 0, 350000, 'Planear'],
            ['Revisar, aprobar, firmar y divulgar las políticas de SST', $management, 1, 120000, 'Planear'],
            ['Validar la matriz IPEVR con trabajadores y priorizar controles', $responsible, 1, 450000, 'Hacer'],
            ['Ejecutar inducción y reinducción por cargo y centro de trabajo', $responsible, 2, 300000, 'Hacer'],
            ['Realizar inspección de condiciones de seguridad y gestionar hallazgos', 'COPASST / Responsable SG-SST', 3, 250000, 'Hacer'],
            ['Coordinar evaluaciones médicas ocupacionales según perfiles de exposición', 'Talento humano / Responsable SG-SST', 4, 800000, 'Hacer'],
            ['Implementar controles prioritarios de ingeniería y administrativos', $management, 5, 1500000, 'Hacer'],
            ['Realizar simulacro y evaluar el plan de respuesta ante emergencias', 'Brigada / Responsable SG-SST', 6, 600000, 'Hacer'],
            ['Consolidar indicadores de estructura, proceso y resultado', $responsible, 7, 150000, 'Verificar'],
            ['Verificar contratistas, mantenimiento y entrega de EPP', 'Compras / Responsable SG-SST', 8, 700000, 'Hacer'],
            ['Ejecutar auditoría anual y registrar acciones correctivas', 'Auditor competente / COPASST', 9, 900000, 'Verificar'],
            ['Realizar revisión por la alta dirección y aprobar plan de mejora', $management, 10, 200000, 'Actuar'],
        ];
    }

    private static function trainingProgram(array $tenant): array
    {
        return [
            ['Inducción y reinducción en SG-SST', 'Política, responsabilidades, peligros del cargo, controles y respuesta ante emergencias.', 'Responsable SG-SST', 0, 2, 'Mixta'],
            ['Peligros prioritarios de ' . $tenant['sector_economico'], 'Reconocimiento de peligros y aplicación práctica de controles según la IPEVR.', 'Responsable SG-SST / ARL', 1, 2, 'Presencial'],
            ['Uso, cuidado y limitaciones de los EPP', 'Selección, ajuste, mantenimiento, reposición y verificación del uso correcto.', 'Responsable SG-SST', 3, 1.5, 'Presencial'],
            ['Prevención y respuesta ante emergencias', 'Alarmas, evacuación, punto de encuentro, primeros auxilios y roles de brigada.', 'Brigada de emergencias', 5, 3, 'Presencial'],
            ['Convivencia laboral y prevención de riesgo psicosocial', 'Canales de comunicación, trato respetuoso, autocuidado y ruta del comité.', 'Comité de Convivencia', 7, 1.5, 'Virtual'],
            ['Estilos de vida y entornos saludables', 'Actividad física, pausas, higiene del sueño y prevención de consumo problemático.', 'Responsable SG-SST / ARL', 9, 1, 'Mixta'],
        ];
    }
}
