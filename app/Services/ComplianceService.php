<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Documento;
use App\Models\RequisitoSeguimiento;
use App\Models\SoporteCumplimiento;
use App\Models\Tenant;
use InvalidArgumentException;

final class ComplianceService
{
    public static function board(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);
        $tracking = RequisitoSeguimiento::forTenant($tenantId);
        $supportCounts = SoporteCumplimiento::counts($tenantId);
        $documents = Documento::forTenant($tenantId);
        $items = [];
        foreach (self::definitions() as $key => $definition) {
            $signal = self::signal($definition['detector'], $tenantId, $documents);
            $item = $definition;
            $item['key'] = $key;
            $item['signal'] = $signal;
            $item['tracking'] = $tracking[$key] ?? null;
            $item['estado'] = $tracking[$key]['estado'] ?? ($signal['available'] ? 'En gestion' : 'Pendiente');
            $item['supports'] = $supportCounts[$key] ?? 0;
            $items[$key] = $item;
        }
        $stats = ['Pendiente' => 0, 'En gestion' => 0, 'Verificado' => 0, 'No aplica' => 0, 'soportes' => array_sum($supportCounts)];
        foreach ($items as $item) {
            $stats[$item['estado']]++;
        }
        return ['items' => $items, 'stats' => $stats, 'tenant' => $tenant];
    }

    public static function definition(string $key): array
    {
        $definitions = self::definitions();
        if (!isset($definitions[$key])) {
            throw new InvalidArgumentException('El requisito documental no existe.');
        }
        return $definitions[$key] + ['key' => $key];
    }

    public static function categories(): array
    {
        return ['Planear', 'Hacer', 'Verificar', 'Actuar'];
    }

    private static function definitions(): array
    {
        return [
            'responsabilidades_sst' => self::makeDefinition('Planear', 'Asignación de responsabilidades y recursos del SG-SST', 'Responsable SG-SST, funciones, recursos humanos, técnicos y financieros.', 'Decreto 1072, arts. 2.2.4.6.7 y 2.2.4.6.8; Res. 0312 según grupo.', 'ISO 45001: roles y recursos (5.3 y 7.1), referencia voluntaria.', '/documentos', ['document', ['responsab', 'recursos']]),
            'politica_sst' => self::makeDefinition('Planear', 'Política de Seguridad y Salud en el Trabajo', 'Política firmada, vigente, comunicada y coherente con los riesgos de la empresa.', 'Decreto 1072, art. 2.2.4.6.5; Res. 0312 según grupo.', 'ISO 45001: liderazgo y política (5.2), referencia voluntaria.', '/documentos', ['policy', ['política de seguridad', 'politica de seguridad']]),
            'evaluacion_inicial' => self::makeDefinition('Planear', 'Evaluación inicial del SG-SST', 'Línea base, prioridades, normatividad, peligros, salud, capacitación e indicadores.', 'Decreto 1072, art. 2.2.4.6.16; Res. 0312 según evidencias aplicables.', 'ISO 45001: planificación y evaluación del desempeño (6 y 9), referencia voluntaria.', '/documentos', ['document', ['evaluación inicial', 'evaluacion inicial']]),
            'plan_anual' => self::makeDefinition('Planear', 'Plan Anual de Trabajo', 'Objetivos, metas, actividades, responsables, cronograma, recursos y firma del empleador.', 'Decreto 1072, art. 2.2.4.6.17; Res. 0312 según grupo.', 'ISO 45001: objetivos y planificación (6.2), referencia voluntaria.', '/plan-anual', ['plan', []]),
            'matriz_legal' => self::makeDefinition('Planear', 'Matriz de requisitos legales y otros requisitos', 'Requisitos aplicables identificados, actualizados y con evaluación de cumplimiento responsable.', 'Decreto 1072, arts. 2.2.4.6.12 y 2.2.4.6.16.', 'ISO 45001: requisitos legales y otros requisitos (6.1.3), referencia voluntaria.', '/documentos', ['document', ['matriz legal', 'requisitos legales']]),
            'objetivos_indicadores' => self::makeDefinition('Planear', 'Objetivos e indicadores del SG-SST', 'Fichas técnicas, metas, mediciones de estructura, proceso y resultado.', 'Decreto 1072, arts. 2.2.4.6.18 a 2.2.4.6.22.', 'ISO 45001: objetivos y evaluación del desempeño (6.2 y 9.1), referencia voluntaria.', '/indicadores', ['indicators', []]),
            'capacitacion_anual' => self::makeDefinition('Hacer', 'Plan y registros de capacitación', 'Formación anual por necesidades y peligros, para niveles y modalidades de vinculación, con personal idóneo.', 'Decreto 1072, art. 2.2.4.6.11; Res. 0312 según grupo.', 'ISO 45001: competencia y toma de conciencia (7.2 y 7.3), referencia voluntaria.', '/capacitaciones', ['training', []]),
            'matriz_ipevr' => self::makeDefinition('Hacer', 'Identificación de peligros y valoración de riesgos', 'Matriz actualizada, participativa, para actividades rutinarias y no rutinarias y toda la población expuesta.', 'Decreto 1072, art. 2.2.4.6.15; Res. 0312 según grupo.', 'ISO 45001: peligros y riesgos (6.1.2), referencia voluntaria.', '/matriz-ipevr', ['hazards', []]),
            'procedimientos_controles' => self::makeDefinition('Hacer', 'Procedimientos y controles operacionales', 'Controles documentados según jerarquía, procedimientos, instrucciones y condiciones seguras.', 'Decreto 1072, arts. 2.2.4.6.12 y 2.2.4.6.24.', 'ISO 45001: planificación y control operacional (8.1), referencia voluntaria.', '/documentos', ['procedure', []]),
            'programas_pyp' => self::makeDefinition('Hacer', 'Programas de promoción y prevención', 'Programas basados en diagnóstico, con objetivos, metas, actividades, recursos y evaluación de eficacia.', 'Decreto 1072, arts. 2.2.4.6.17 y 2.2.4.6.24; Res. 0312 según grupo.', 'ISO 45001: controles y mejora (8 y 10), referencia voluntaria.', '/programas-prevencion', ['programs', []]),
            'condiciones_salud' => self::makeDefinition('Hacer', 'Diagnóstico de condiciones de salud y exámenes médicos', 'Caracterización, evaluaciones ocupacionales y custodia de historias por el profesional competente.', 'Decreto 1072, arts. 2.2.4.6.9, 2.2.4.6.12 y 2.2.4.6.24.', 'ISO 45001: apoyo y control operacional (7 y 8), referencia voluntaria.', '/phva', ['profiles', []]),
            'vigilancia_epidemiologica' => self::makeDefinition('Hacer', 'Vigilancia epidemiológica y programas específicos de salud', 'Sistemas de vigilancia según condiciones de salud y riesgos priorizados, cuando resulten aplicables.', 'Decreto 1072, arts. 2.2.4.6.12, 2.2.4.6.21 y 2.2.4.6.24.', 'ISO 45001: control operacional y seguimiento (8.1 y 9.1), referencia voluntaria.', '/programas-prevencion', ['surveillance', []]),
            'epp_suministro' => self::makeDefinition('Hacer', 'Suministro y control de EPP', 'Selección según riesgos, entrega sin costo, formación, mantenimiento, reposición y registros.', 'Decreto 1072, arts. 2.2.4.6.12, 2.2.4.6.13 y 2.2.4.6.24.', 'ISO 45001: controles operacionales (8.1), referencia voluntaria.', '/documentos', ['document', ['protección personal', 'proteccion personal', 'epp']]),
            'copasst_vigia' => self::makeDefinition('Hacer', 'COPASST o Vigía de SST', 'Conformación, funciones, capacitación, actas y seguimiento según el mecanismo que corresponda.', 'Decreto 1072, art. 2.2.4.6.10 y regulación especial aplicable.', 'ISO 45001: consulta y participación (5.4), referencia voluntaria.', '/documentos', ['document', ['copasst', 'comité paritario', 'vigía', 'vigia']]),
            'convivencia_laboral' => self::makeDefinition('Hacer', 'Comité de Convivencia Laboral', 'Conformación, ruta, actas y soportes; la política por sí sola no demuestra funcionamiento.', 'Regulación de convivencia laboral aplicable; Res. 0312 según grupo.', 'ISO 45001: participación y comunicación (5.4 y 7.4), referencia voluntaria.', '/documentos', ['manual', []]),
            'emergencia_plan' => self::makeDefinition('Hacer', 'Plan de prevención, preparación y respuesta ante emergencias', 'Amenazas, vulnerabilidad, recursos, procedimientos, evacuación, brigada, entrenamiento y ayuda mutua.', 'Decreto 1072, art. 2.2.4.6.25.', 'ISO 45001: preparación y respuesta ante emergencias (8.2), referencia voluntaria.', '/emergencias', ['emergency', []]),
            'emergencia_planos' => self::makeDefinition('Hacer', 'Plano de instalaciones y rutas de evacuación', 'Plano vigente con rutas, salidas, puntos de encuentro, equipos y señalización del centro de trabajo.', 'Decreto 1072, art. 2.2.4.6.25, numeral 8.', 'ISO 45001: preparación y respuesta ante emergencias (8.2), referencia voluntaria.', '/emergencias', ['manual', []]),
            'emergencia_brigada' => self::makeDefinition('Hacer', 'Brigada, formación y dotación', 'Conformación, roles, capacitación, entrenamiento, primeros auxilios y dotación según el riesgo.', 'Decreto 1072, art. 2.2.4.6.25, numeral 11.', 'ISO 45001: competencia y emergencias (7.2 y 8.2), referencia voluntaria.', '/emergencias', ['brigade', []]),
            'emergencia_equipos' => self::makeDefinition('Hacer', 'Inspección de equipos de emergencia', 'Inspecciones periódicas de alarmas, señalización, extintores, botiquines y demás recursos.', 'Decreto 1072, art. 2.2.4.6.25, numeral 12.', 'ISO 45001: emergencias y seguimiento (8.2 y 9.1), referencia voluntaria.', '/emergencias', ['resources', []]),
            'emergencia_simulacro' => self::makeDefinition('Hacer', 'Simulacro anual y acciones de mejora', 'Evidencia del simulacro, participación, evaluación, lecciones aprendidas y mejoras.', 'Decreto 1072, art. 2.2.4.6.25, numeral 10.', 'ISO 45001: emergencias y mejora (8.2 y 10), referencia voluntaria.', '/emergencias', ['drills', []]),
            'permisos_trabajo' => self::makeDefinition('Hacer', 'Permisos de trabajo de alto riesgo, cuando apliquen', 'Permisos, procedimientos, autorizaciones, mediciones, emergencias, cierre y conservación según la tarea.', 'Decreto 1072, art. 2.2.4.6.24; Res. 4272/2021 y Res. 491/2020 cuando apliquen.', 'ISO 45001: control operacional y emergencias (8.1 y 8.2), referencia voluntaria.', '/permisos-trabajo', ['permits', []]),
            'gestion_cambio' => self::makeDefinition('Hacer', 'Gestión del cambio', 'Evaluación de impactos, peligros y controles antes de cambios, con información y capacitación.', 'Decreto 1072, art. 2.2.4.6.26.', 'ISO 45001: gestión del cambio (8.1.3), referencia voluntaria.', '/documentos', ['document', ['gestión del cambio', 'gestion del cambio']]),
            'contratistas_adquisiciones' => self::makeDefinition('Hacer', 'Contratación, proveedores y adquisiciones', 'Criterios SST, información de riesgos, verificación, coordinación y registros aplicables.', 'Decreto 1072, arts. 2.2.4.6.27 y 2.2.4.6.28.', 'ISO 45001: compras, contratistas y externalización (8.1.4), referencia voluntaria.', '/documentos', ['document', ['contratación', 'contratacion', 'adquisiciones', 'proveedores']]),
            'autoreportes' => self::makeDefinition('Hacer', 'Autorreportes de condiciones de trabajo y salud', 'Mecanismo disponible, respuestas y uso de la información para intervenir condiciones.', 'Decreto 1072, arts. 2.2.4.6.14 y 2.2.4.6.16, parágrafo 3.', 'ISO 45001: participación y comunicación (5.4 y 7.4), referencia voluntaria.', '/autoreportes', ['reports', []]),
            'investigacion_eventos' => self::makeDefinition('Verificar', 'Reporte e investigación de incidentes, accidentes y enfermedades', 'Registros, investigaciones, causas, acciones y seguimiento según los eventos y requisitos aplicables.', 'Decreto 1072, arts. 2.2.4.6.12, 2.2.4.6.21 y 2.2.4.6.22.', 'ISO 45001: incidentes, no conformidad y acciones (10.2), referencia voluntaria.', '/hallazgos', ['accidents', []]),
            'auditoria_anual' => self::makeDefinition('Verificar', 'Auditoría anual del SG-SST', 'Programa, independencia del auditor, alcance, informe y acciones derivadas.', 'Decreto 1072, arts. 2.2.4.6.29 y 2.2.4.6.30.', 'ISO 45001: auditoría interna (9.2), referencia voluntaria.', '/documentos', ['document', ['auditoría', 'auditoria']]),
            'revision_direccion' => self::makeDefinition('Actuar', 'Revisión por la alta dirección', 'Revisión documentada de resultados, oportunidades, recursos y decisiones de mejora.', 'Decreto 1072, art. 2.2.4.6.31.', 'ISO 45001: revisión por la dirección (9.3), referencia voluntaria.', '/documentos', ['document', ['revisión por la alta', 'revision por la alta']]),
            'mejora_acpa' => self::makeDefinition('Actuar', 'Acciones preventivas, correctivas y de mejora', 'Hallazgos, responsables, cierre y evidencia de eficacia de las acciones.', 'Decreto 1072, arts. 2.2.4.6.21, 2.2.4.6.22 y 2.2.4.6.32.', 'ISO 45001: no conformidad, acción correctiva y mejora (10), referencia voluntaria.', '/hallazgos', ['findings', []]),
            'archivo_retencion' => self::makeDefinition('Actuar', 'Archivo, conservación y retención documental', 'Documentos legibles, identificables, accesibles, protegidos y reglas de retención según el registro.', 'Decreto 1072, arts. 2.2.4.6.12 y 2.2.4.6.13.', 'ISO 45001: información documentada (7.5), referencia voluntaria.', '/documentos', ['manual', []]),
        ];
    }

    private static function makeDefinition(string $phase, string $name, string $description, string $legal, string $iso, string $route, array $detector): array
    {
        return ['fase' => $phase, 'nombre' => $name, 'descripcion' => $description, 'referencia' => $legal, 'iso' => $iso, 'ruta' => $route, 'detector' => $detector];
    }

    private static function signal(array $detector, int $tenantId, array $documents): array
    {
        [$type, $terms] = $detector;
        if (in_array($type, ['document', 'policy', 'procedure'], true)) {
            $matches = [];
            foreach ($documents as $document) {
                if ($document['estado'] === 'Obsoleto') {
                    continue;
                }
                $name = text_lower($document['nombre_documento']);
                $matchesType = $type === 'document' || ($type === 'policy' && $document['tipo'] === 'Politica') || ($type === 'procedure' && $document['tipo'] === 'Procedimiento');
                $matchesTerm = $terms === [];
                foreach ($terms as $term) {
                    if (str_contains($name, text_lower($term))) {
                        $matchesTerm = true;
                        break;
                    }
                }
                if ($matchesType && $matchesTerm) {
                    $matches[] = $document;
                }
            }
            $approved = count(array_filter($matches, static fn($document) => $document['estado'] === 'Aprobado'));
            return self::result($matches !== [], $matches === [] ? 'Sin documento relacionado detectado' : count($matches) . ' documento(s) relacionado(s) · ' . $approved . ' aprobado(s)');
        }
        return match ($type) {
            'manual' => self::result(false, 'Requiere registro o soporte aportado por la empresa'),
            'plan' => self::countSignal($tenantId, 'plan_trabajo', 'actividad(es) del PAT'),
            'training' => self::countSignal($tenantId, 'capacitaciones', 'capacitación(es) programada(s) o realizada(s)'),
            'hazards' => self::countSignal($tenantId, 'peligros_ipevr', 'peligro(s) en la IPEVR'),
            'programs' => self::countSignal($tenantId, 'programas_prevencion', 'programa(s) preventivo(s)'),
            'profiles' => self::countSignal($tenantId, 'perfiles_riesgo', 'perfil(es) de riesgo o condiciones de salud'),
            'surveillance' => self::countSignal($tenantId, 'programas_prevencion', 'programa(s) de vigilancia', 'categoria = \'Vigilancia epidemiologica\''),
            'emergency' => self::countSignal($tenantId, 'planes_emergencia', 'plan(es) de emergencia'),
            'drills' => self::countSignal($tenantId, 'simulacros_emergencia', 'simulacro(s) realizado(s) en los últimos 12 meses', "estado = 'Realizado' AND fecha >= '" . date('Y-m-d', strtotime('-1 year')) . "'"),
            'permits' => self::countSignal($tenantId, 'permisos_trabajo', 'permiso(s) registrado(s)'),
            'reports' => self::countSignal($tenantId, 'autoreportes', 'autoreporte(s) registrado(s)'),
            'indicators' => self::countSignal($tenantId, 'indicadores', 'medición(es) de indicadores'),
            'accidents' => self::countSignal($tenantId, 'hallazgos_acpa', 'hallazgo(s) por accidente; valida investigaciones y soportes', "origen = 'Accidente'"),
            'findings' => self::countSignal($tenantId, 'hallazgos_acpa', 'acción(es) ACPA registrada(s)'),
            'brigade' => self::emergencyJsonSignal($tenantId, 'brigada_json', 'integrante(s) o equipo(s) de brigada registrado(s)'),
            'resources' => self::emergencyJsonSignal($tenantId, 'recursos_json', 'recurso(s) registrado(s); verifica inspecciones'),
            default => self::result(false, 'Requiere revisión manual'),
        };
    }

    private static function countSignal(int $tenantId, string $table, string $label, string $condition = ''): array
    {
        $allowed = ['plan_trabajo', 'capacitaciones', 'peligros_ipevr', 'programas_prevencion', 'perfiles_riesgo', 'planes_emergencia', 'simulacros_emergencia', 'permisos_trabajo', 'autoreportes', 'indicadores', 'hallazgos_acpa'];
        if (!in_array($table, $allowed, true)) {
            return self::result(false, 'Detector no disponible');
        }
        $sql = 'SELECT COUNT(*) AS total FROM ' . $table . ' WHERE tenant_id = ?' . ($condition !== '' ? ' AND ' . $condition : '');
        $count = (int) (Database::first($sql, [$tenantId])['total'] ?? 0);
        return self::result($count > 0, $count . ' ' . $label);
    }

    private static function emergencyJsonSignal(int $tenantId, string $field, string $label): array
    {
        if (!in_array($field, ['brigada_json', 'recursos_json'], true)) {
            return self::result(false, 'Detector no disponible');
        }
        $count = 0;
        foreach (Database::query('SELECT ' . $field . ' AS datos FROM planes_emergencia WHERE tenant_id = ?', [$tenantId]) as $row) {
            $decoded = json_decode($row['datos'], true);
            $count += is_array($decoded) ? count($decoded) : 0;
        }
        return self::result($count > 0, $count . ' ' . $label);
    }

    private static function result(bool $available, string $label): array
    {
        return ['available' => $available, 'label' => $label];
    }
}
