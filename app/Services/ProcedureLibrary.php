<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Documento;
use App\Models\Tenant;
use InvalidArgumentException;
use RuntimeException;

final class ProcedureLibrary
{
    public static function catalog(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            throw new RuntimeException('La empresa no existe.');
        }
        $activity = self::activity($tenant);
        $prepared = DocumentTemplate::prepareTenant($tenant);
        $activityCode = $activity['codigo_ciuu'];
        $groups = [
            'actividad' => [
                'nombre' => 'Actividad económica · CIIU ' . $activityCode,
                'descripcion' => 'Procedimiento y formato de verificación parametrizados con los peligros base de ' . $activity['descripcion'] . '.',
                'aplicabilidad' => 'Base sectorial para adaptar a procesos, cargos y centros reales.',
                'recomendado' => true,
                'documentos' => [
                    ['codigo' => 'PR-ACT-' . $activityCode, 'nombre' => 'Procedimiento de trabajo seguro · ' . $activity['descripcion'], 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::activity($prepared, $activity, 'PR-ACT-' . $activityCode)],
                    ['codigo' => 'FT-ACT-' . $activityCode, 'nombre' => 'Formato de verificación de actividad · CIIU ' . $activityCode, 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::activityFormat($prepared, $activity, 'FT-ACT-' . $activityCode)],
                ],
            ],
            'ast' => [
                'nombre' => 'Análisis Seguro de Trabajo',
                'descripcion' => 'Formato participativo para descomponer la tarea, identificar peligros, acordar controles y cerrar la actividad.',
                'aplicabilidad' => 'Utilizable en actividades rutinarias y no rutinarias.',
                'recomendado' => true,
                'documentos' => [
                    ['codigo' => 'FT-AST-01', 'nombre' => 'Formato de Análisis Seguro de Trabajo (AST)', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::ast($prepared, 'FT-AST-01')],
                ],
            ],
            'limpieza' => [
                'nombre' => 'Programa de limpieza y desinfección',
                'descripcion' => 'Programa formal, programación y ejecución, y lista de verificación. Los registros operativos reales también se conservan en Gestión sanitaria.',
                'aplicabilidad' => 'Kit base para adaptar, aprobar e implementar. Crear los documentos no acredita ejecución ni cierra el requerimiento IVC 2.3.',
                'recomendado' => true,
                'related_route' => '/sanitario/registros/nuevo?tipo=limpieza',
                'documentos' => CleaningProgramTemplate::kit($prepared),
            ],
            'alturas' => [
                'nombre' => 'Trabajo en alturas',
                'descripcion' => 'Procedimiento y lista de verificación para planificación, competencias, protección contra caídas, permiso y rescate.',
                'aplicabilidad' => 'Generar cuando exista riesgo de caída de personas u objetos. No reemplaza el permiso de cada tarea.',
                'recomendado' => self::riskDetected($activity, ['altura', 'techo', 'andamio']),
                'documentos' => [
                    ['codigo' => 'PR-ALT-01', 'nombre' => 'Procedimiento para trabajo en alturas', 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::heights($prepared, 'PR-ALT-01')],
                    ['codigo' => 'FT-ALT-01', 'nombre' => 'Formato de verificación previa para trabajo en alturas', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::heightsFormat($prepared, 'FT-ALT-01')],
                ],
            ],
            'pesv' => [
                'nombre' => 'Plan Estratégico de Seguridad Vial (PESV)',
                'descripcion' => 'Índice editable del paquete de 24 pasos aportado, con acceso a sus formularios y controles de versión.',
                'aplicabilidad' => 'Sugerido para actividades de transporte. Revisa desplazamientos, flota, contratistas y evidencias antes de aprobar.',
                'recomendado' => PesvLibrary::isRecommended($tenant, $activity),
                'related_route' => '/pesv',
                'documentos' => [
                    ['codigo' => PesvLibrary::PLAN_CODE, 'nombre' => 'Plan Estratégico de Seguridad Vial (PESV)', 'fase' => 'Planear', 'tipo' => 'Plan', 'biblioteca_id' => PesvLibrary::PLAN_CODE, 'contenido' => PesvLibrary::planDraft($prepared)],
                ],
            ],
            'espacios_confinados' => [
                'nombre' => 'Espacios confinados',
                'descripcion' => 'Procedimiento y formato para aislamiento, medición atmosférica, vigilancia, permiso, rescate y cierre.',
                'aplicabilidad' => 'Generar cuando la identificación de espacios y tareas determine su aplicabilidad. No reemplaza el permiso de ingreso.',
                'recomendado' => self::riskDetected($activity, ['confinado', 'tanque', 'silo', 'alcantar']),
                'documentos' => [
                    ['codigo' => 'PR-EC-01', 'nombre' => 'Procedimiento para trabajo en espacios confinados', 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::confined($prepared, 'PR-EC-01')],
                    ['codigo' => 'FT-EC-01', 'nombre' => 'Formato de verificación y medición para espacios confinados', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::confinedFormat($prepared, 'FT-EC-01')],
                ],
            ],
        ];

        $existing = [];
        foreach (Documento::forTenant($tenantId) as $document) {
            $existing[$document['codigo_estandar']] = $document;
        }
        foreach ($groups as &$group) {
            foreach ($group['documentos'] as &$document) {
                $document['registro'] = $existing[$document['codigo']] ?? null;
                unset($document['contenido']);
            }
            unset($document);
        }
        unset($group);
        return ['grupos' => $groups, 'tenant' => $tenant, 'actividad' => $activity];
    }

    public static function automaticKeys(int $tenantId): array
    {
        $groups = self::catalogWithoutExisting($tenantId);
        $keys = [];
        foreach ($groups as $key => $group) {
            if ($group['recomendado']) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    public static function generate(int $tenantId, array $keys): int
    {
        $catalog = self::catalogWithoutExisting($tenantId);
        foreach ($keys as $key) {
            if (!isset($catalog[$key])) {
                throw new InvalidArgumentException('El kit documental solicitado no existe.');
            }
        }
        return Database::transaction(static function () use ($tenantId, $catalog, $keys): int {
            $created = 0;
            foreach (array_unique($keys) as $key) {
                foreach ($catalog[$key]['documentos'] as $document) {
                    $id = Documento::createOperationalIfMissing([
                        'tenant_id' => $tenantId, 'codigo' => $document['codigo'], 'nombre' => $document['nombre'],
                        'fase' => $document['fase'] ?? 'Hacer', 'contenido' => $document['contenido'], 'tipo' => $document['tipo'],
                        'biblioteca_id' => $document['biblioteca_id'] ?? null,
                    ]);
                    $created += $id !== null ? 1 : 0;
                }
            }
            return $created;
        });
    }

    private static function catalogWithoutExisting(int $tenantId): array
    {
        // catalog() construye primero las plantillas; se vuelven a obtener con contenido para persistir.
        $tenant = Tenant::find($tenantId);
        if ($tenant === null) {
            throw new RuntimeException('La empresa no existe.');
        }
        $activity = self::activity($tenant);
        $prepared = DocumentTemplate::prepareTenant($tenant);
        $code = $activity['codigo_ciuu'];
        return [
            'actividad' => ['recomendado' => true, 'documentos' => [
                ['codigo' => 'PR-ACT-' . $code, 'nombre' => 'Procedimiento de trabajo seguro · ' . $activity['descripcion'], 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::activity($prepared, $activity, 'PR-ACT-' . $code)],
                ['codigo' => 'FT-ACT-' . $code, 'nombre' => 'Formato de verificación de actividad · CIIU ' . $code, 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::activityFormat($prepared, $activity, 'FT-ACT-' . $code)],
            ]],
            'ast' => ['recomendado' => true, 'documentos' => [
                ['codigo' => 'FT-AST-01', 'nombre' => 'Formato de Análisis Seguro de Trabajo (AST)', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::ast($prepared, 'FT-AST-01')],
            ]],
            'limpieza' => ['recomendado' => true, 'documentos' => CleaningProgramTemplate::kit($prepared)],
            'alturas' => ['recomendado' => self::riskDetected($activity, ['altura', 'techo', 'andamio']), 'documentos' => [
                ['codigo' => 'PR-ALT-01', 'nombre' => 'Procedimiento para trabajo en alturas', 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::heights($prepared, 'PR-ALT-01')],
                ['codigo' => 'FT-ALT-01', 'nombre' => 'Formato de verificación previa para trabajo en alturas', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::heightsFormat($prepared, 'FT-ALT-01')],
            ]],
            'pesv' => ['recomendado' => PesvLibrary::isRecommended($tenant, $activity), 'documentos' => [
                ['codigo' => PesvLibrary::PLAN_CODE, 'nombre' => 'Plan Estratégico de Seguridad Vial (PESV)', 'fase' => 'Planear', 'tipo' => 'Plan', 'biblioteca_id' => PesvLibrary::PLAN_CODE, 'contenido' => PesvLibrary::planDraft($prepared)],
            ]],
            'espacios_confinados' => ['recomendado' => self::riskDetected($activity, ['confinado', 'tanque', 'silo', 'alcantar']), 'documentos' => [
                ['codigo' => 'PR-EC-01', 'nombre' => 'Procedimiento para trabajo en espacios confinados', 'tipo' => 'Procedimiento', 'contenido' => ProcedureTemplate::confined($prepared, 'PR-EC-01')],
                ['codigo' => 'FT-EC-01', 'nombre' => 'Formato de verificación y medición para espacios confinados', 'tipo' => 'Registro', 'contenido' => ProcedureTemplate::confinedFormat($prepared, 'FT-EC-01')],
            ]],
        ];
    }

    private static function activity(array $tenant): array
    {
        if (!empty($tenant['actividad_economica_id'])) {
            $activity = Database::first('SELECT * FROM actividades_economicas WHERE id = ?', [(int) $tenant['actividad_economica_id']]);
            if ($activity !== null) {
                return $activity;
            }
        }
        return [
            'codigo_ciuu' => 'GENERAL', 'descripcion' => 'Actividad económica de ' . $tenant['sector_economico'],
            'matriz_ipevr_base' => '[]',
        ];
    }

    private static function riskDetected(array $activity, array $terms): bool
    {
        $haystack = text_lower(json_encode($activity, JSON_UNESCAPED_UNICODE) ?: '');
        foreach ($terms as $term) {
            if (str_contains($haystack, text_lower($term))) {
                return true;
            }
        }
        return false;
    }
}
