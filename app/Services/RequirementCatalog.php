<?php
declare(strict_types=1);

namespace App\Services;

/** Catálogo único de autoevaluación, referencias legales e ISO 45001. */
final class RequirementCatalog
{
    private const SOURCE_0312 = 'https://normograma.mintic.gov.co/mintic/compilacion/docs/resolucion_mtra_0312_2019.htm';

    public static function autoevaluation(): array
    {
        $weights = [
            0.5,0.5,0.5,0.5,0.5,0.5,0.5,0.5,2,2,2,
            1,1,1,2,2,1,2,1,1,2,1,
            1,1,1,1,1,1,1,1,1,2,2,1,1,1,1,1,1,1,
            4,4,3,4,2.5,2.5,2.5,2.5,2.5,2.5,5,5,
            1.25,1.25,1.25,1.25,2.5,2.5,2.5,2.5,
        ];
        $standards = CatalogData::fullStandards();
        $library = json_decode((string) file_get_contents(BASE_PATH . '/resources/starter-package/standards.json'), true, 512, JSON_THROW_ON_ERROR);
        $rows = [];
        foreach ($standards as $index => [$code, $name, $cycle]) {
            $topicKey = $library['standard_map'][$code] ?? '';
            $topic = $library['topics'][$topicKey] ?? [];
            $rows[] = [
                'tipo' => 'Autoevaluacion', 'item_codigo' => $code, 'ciclo' => $cycle, 'estandar' => $name,
                'norma_referencia' => 'Resolución 0312 de 2019; Decreto 1072 de 2015.',
                'articulo' => 'Art. 27 · ' . $code,
                'iso_clausula' => self::isoMap()[$code] ?? '',
                'criterio' => (string) ($topic['focus'] ?? $name),
                'evidencia_requerida' => implode(' · ', array_slice($topic['evidence'] ?? [], 0, 3)),
                'periodicidad' => self::periodicity($code), 'puntaje_maximo' => $weights[$index],
                'modulo_url' => self::moduleFor($code), 'fuente_oficial' => self::SOURCE_0312,
            ];
        }
        if (count($rows) !== 60 || abs(array_sum($weights) - 100) > 0.0001) {
            throw new \RuntimeException('El catálogo de autoevaluación no conserva los 60 ítems y 100 puntos oficiales.');
        }
        return $rows;
    }

    public static function applicableCodes(int $group): array
    {
        return match ($group) {
            7 => ['1.1.1','1.1.4','1.2.1','2.4.1','3.1.4','4.1.2','4.2.1'],
            21 => ['1.1.1','1.1.3','1.1.4','1.1.6','1.1.8','1.2.1','2.1.1','2.4.1','2.5.1','3.1.1','3.1.2','3.1.4','3.1.6','3.2.1','3.2.2','4.1.2','4.2.5','4.2.6','5.1.1','5.1.2','6.1.3'],
            60 => array_column(CatalogData::fullStandards(), 0),
            default => [],
        };
    }

    public static function legal(): array
    {
        $rows = [
            ['LEG-001','Decreto 1072 de 2015','Libro 2, Parte 2, Título 4, Capítulo 6','4.4; 5.1; 8.1','Implementar y mantener el SG-SST con ciclo PHVA, responsables y recursos.','Evaluación inicial, documentos vigentes y registros de ejecución.','Anual','/autoevaluacion','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=72173'],
            ['LEG-002','Resolución 0312 de 2019','Arts. 3, 9, 16 y 26 a 30','9.1; 10.3','Aplicar autoevaluación anual, plan de mejora, reporte a ARL e indicadores mínimos.','Autoevaluación cerrada, plan con eficacia y constancias reales de remisión.','Anual','/autoevaluacion',self::SOURCE_0312],
            ['LEG-003','Resolución 1843 de 2025','Evaluaciones médicas ocupacionales y custodia','7.1; 8.1; 9.1','Gestionar evaluaciones por exposición, conceptos funcionales, adaptación y custodia clínica reservada.','Conceptos y seguimiento permitido; historias clínicas únicamente bajo custodia autorizada.','Según exposición · máximo tres años cuando aplique','/salud','https://cancilleria.gov.co/normograma/compilacion/docs/resolucion_mtra_1843_2025.htm'],
            ['LEG-004','Decreto 728 de 2025','Salud mental y consumo de sustancias psicoactivas','5.1; 6.1; 8.1; 9.1','Intervenir desde evaluación psicosocial, organización del trabajo, sensibilización, canalización, crisis y seguimiento.','Diagnóstico agregado, plan, registros de intervención y evaluación de eficacia.','Anual y ante cambios','/salud/psicosocial','https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_0728_2025.htm'],
            ['LEG-005','Resolución 2764 de 2022','Batería, guía y protocolos de riesgo psicosocial','6.1; 8.1; 9.1','Aplicar instrumentos sin alteraciones por personal competente y gestionar resultados con reserva.','Versión del instrumento, participación, resultados agregados y plan; nunca respuestas individuales en biblioteca.','Según diagnóstico y norma','/salud/psicosocial','https://www.ins.gov.co/Normatividad/Resoluciones/RESOLUCION%202764%20DE%202022.pdf'],
            ['LEG-006','Decreto 1040 de 2026','Prevención de acoso, violencias y discriminación','5.1; 5.4; 8.1','Adoptar política, canales reservados, protección, no revictimización y articulación con SG-SST/CCL.','Política vigente, registros reservados, medidas y estadística anonimizada.','Anual y ante cada caso','/convivencia','https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_1040_2026.htm'],
            ['LEG-007','Resolución 3461 de 2025','Conformación y funcionamiento del CCL','5.4; 8.1','Operar el CCL con reuniones ordinarias mensuales, seguimiento, informes y actuación preventiva.','Conformación, actas, quorum, compromisos e informes en expediente reservado.','Mensual','/convivencia','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=262916'],
            ['LEG-008','Circular 027 de 2026 · MinTrabajo','Registro de autoevaluación 2025 y plan','9.1','Verificar registro SGRL y remisión a ARL; el plazo específico 31/07/2026 está vencido, sin eliminar el ciclo anual.','Fecha y radicado reales de registro/remisión; no se presume por exportar un archivo.','Anual','/autoevaluacion','https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=192912&dt=S'],
            ['LEG-009','Resolución 1401 de 2007','Investigación de incidentes y accidentes','10.2','Investigar eventos con equipo, causas, acciones, responsables y seguimiento.','Evento, investigación, causas, acciones y evaluación de eficacia.','Por evento','/eventos','https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=53497'],
            ['LEG-010','Resolución 4272 de 2021','Trabajo en alturas','8.1','Aplicar requisitos solo cuando la actividad y los peligros de alturas sean reales.','Evaluación, permisos, competencias, equipos, rescate y supervisión.','Por tarea y revisión','/permisos-trabajo','https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=120880'],
            ['LEG-011','Resolución 0491 de 2020','Espacios confinados','8.1','Controlar ingreso a espacios confinados identificados, con evaluación, permiso y rescate.','Identificación, medición atmosférica, permiso, vigía y plan de rescate.','Por ingreso','/permisos-trabajo','https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=90906'],
            ['LEG-012','Resolución 0773 de 2021','Comité de Seguridad Vial y PESV según aplicabilidad','5.1; 8.1','Definir aplicabilidad del PESV sin activar documentos sectoriales indiscriminadamente.','Análisis de aplicabilidad, PESV y registros del módulo contratado.','Anual','/pesv','https://www.icbf.gov.co/cargues/avance/compilacion/docs/resolucion_mtra_0773_2021.htm'],
            ['LEG-013','Ley 527 de 1999','Comercio electrónico y firma electrónica','7.5','Vincular identidad, consentimiento y contenido, con detección de alteraciones.','Reautenticación, consentimiento, hash, auditoría y verificación de integridad.','Por firma','/documentos','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=4276'],
            ['LEG-014','Decreto 2364 de 2012','Firma electrónica y certificados digitales','7.5','Distinguir firma electrónica de firma digital y no presumir acreditación del certificado.','Algoritmo, certificado, emisor, vigencia, custodia y estado de confianza explícito.','Por firma','/documentos','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=50583'],
            ['LEG-015','Ley 1562 de 2012','Sistema General de Riesgos Laborales','5.1; 8.1; 9.1','Gestionar afiliación, prevención, reporte y obligaciones del SGRL.','Afiliaciones, pagos, reportes y soportes trazables.','Mensual y por evento','/operacion/afiliaciones','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=48365'],
            ['LEG-016','Decreto 1295 de 1994','SGRL y afiliación','8.1; 9.1','Verificar cobertura y clasificación de riesgo por centro cuando corresponda.','Afiliaciones y soportes de clasificación/cobertura.','Anual y ante cambios','/operacion/afiliaciones','https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=2629'],
        ];
        return array_map(static fn(array $row): array => [
            'tipo' => 'Legal', 'item_codigo' => $row[0], 'ciclo' => 'Verificar', 'estandar' => $row[1],
            'norma_referencia' => $row[1], 'articulo' => $row[2], 'iso_clausula' => $row[3],
            'criterio' => $row[4], 'evidencia_requerida' => $row[5], 'periodicidad' => $row[6],
            'puntaje_maximo' => 0, 'modulo_url' => $row[7], 'fuente_oficial' => $row[8], 'aplica_sugerida' => 1,
        ], $rows);
    }

    public static function iso(): array
    {
        $items = [
            ['4.1','Contexto de la organización','Determinar cuestiones internas y externas que afectan el SG-SST.','Análisis de contexto actualizado y revisado.','/operacion/cambios'],
            ['4.2','Partes interesadas','Identificar necesidades y expectativas pertinentes de trabajadores y otras partes.','Matriz de partes, consultas y respuestas.','/operacion/comunicaciones'],
            ['4.3','Alcance del SG-SST','Definir límites, actividades, centros, trabajadores y control de la organización.','Alcance aprobado y coherente con la operación.','/documentos'],
            ['4.4','Procesos del SG-SST','Establecer, implementar, mantener y mejorar los procesos necesarios.','Mapa de procesos e interacciones con evidencias.','/phva'],
            ['5.1','Liderazgo y compromiso','Demostrar liderazgo, responsabilidad y apoyo de la alta dirección.','Decisiones, recursos, seguimiento y participación directiva.','/operacion/revision-direccion'],
            ['5.2','Política SST','Mantener política apropiada, comunicada y disponible.','Política vigente y prueba de comunicación.','/documentos'],
            ['5.3','Roles, responsabilidades y autoridad','Asignar y comunicar responsabilidades en todos los niveles.','Designaciones, perfiles y evaluación de cumplimiento.','/operacion/responsables'],
            ['5.4','Consulta y participación','Garantizar consulta y participación efectiva, sin represalias y con recursos.','Actas, consultas, participación y barreras tratadas.','/comites'],
            ['6.1','Riesgos y oportunidades','Planificar acciones para peligros, riesgos, oportunidades y requisitos.','Evaluaciones y acciones trazables a IPEVR/cambios.','/matriz-ipevr'],
            ['6.2','Objetivos y planificación','Definir objetivos medibles, recursos, responsables y evaluación.','Objetivos, PAT, indicadores y resultados.','/plan-anual'],
            ['7.1','Recursos','Determinar y proporcionar recursos necesarios.','Presupuesto, competencias, infraestructura y ejecución.','/operacion/responsables'],
            ['7.2','Competencia','Asegurar competencia con formación, experiencia y evaluación de eficacia.','Perfiles, idoneidad, asistencia y eficacia.','/capacitaciones'],
            ['7.3','Toma de conciencia','Verificar comprensión de política, peligros, incidentes y consecuencias.','Evaluaciones, entrevistas y participación.','/capacitaciones'],
            ['7.4','Comunicación','Definir qué, cuándo, con quién y cómo comunicar, considerando diversidad.','Comunicaciones y retroalimentación verificable.','/operacion/comunicaciones'],
            ['7.5','Información documentada','Controlar creación, revisión, acceso, integridad y conservación.','Versiones, permisos, evidencias y firmas verificables.','/documentos'],
            ['8.1','Planificación y control operacional','Controlar cambios, compras, contratistas y externalización según peligros.','Controles operacionales y coordinación.','/operacion/contratistas'],
            ['8.2','Preparación y respuesta ante emergencias','Preparar, probar y revisar respuestas con partes pertinentes.','Planes, simulacros, recursos y mejoras.','/emergencias'],
            ['9.1','Seguimiento, medición, análisis y evaluación','Medir desempeño, cumplimiento y eficacia con datos trazables.','Fuentes, cálculos, análisis y revisión.','/indicadores'],
            ['9.2','Auditoría interna','Planificar y realizar auditorías objetivas y documentadas.','Programa, alcance, auditor, hallazgos y acciones.','/operacion/auditoria'],
            ['9.3','Revisión por la dirección','Revisar idoneidad, adecuación, eficacia y oportunidades de mejora.','Entradas completas, decisiones, recursos y acciones.','/operacion/revision-direccion'],
            ['10.2','Incidente, no conformidad y acción correctiva','Reaccionar, investigar, corregir causas y revisar eficacia sin represalias.','Eventos, causas, ACPA y eficacia.','/eventos'],
            ['10.3','Mejora continua','Mejorar idoneidad, adecuación y eficacia del SG-SST.','Mejoras verificadas y resultados sostenidos.','/hallazgos'],
        ];
        return array_map(static fn(array $row): array => [
            'tipo' => 'ISO 45001', 'item_codigo' => 'ISO-' . $row[0], 'ciclo' => str_starts_with($row[0], '4') || str_starts_with($row[0], '5') || str_starts_with($row[0], '6') || str_starts_with($row[0], '7') ? 'Planear' : (str_starts_with($row[0], '8') ? 'Hacer' : (str_starts_with($row[0], '9') ? 'Verificar' : 'Actuar')),
            'estandar' => $row[1], 'norma_referencia' => 'ISO 45001:2018 · referencia voluntaria, no certificación automática',
            'articulo' => 'Cláusula ' . $row[0], 'iso_clausula' => $row[0], 'criterio' => $row[2],
            'evidencia_requerida' => $row[3], 'periodicidad' => 'Anual', 'puntaje_maximo' => 0,
            'modulo_url' => $row[4], 'fuente_oficial' => '', 'aplica_sugerida' => 0,
        ], $items);
    }

    private static function isoMap(): array
    {
        return [
            '1.1.1'=>'5.3; 7.1; 7.2','1.1.2'=>'5.3','1.1.3'=>'5.1; 7.1','1.1.4'=>'5.1; 8.1','1.1.5'=>'8.1; 9.1','1.1.6'=>'5.4','1.1.7'=>'5.4; 7.2','1.1.8'=>'5.4; 8.1','1.2.1'=>'7.2; 7.3','1.2.2'=>'7.2; 7.3','1.2.3'=>'7.2',
            '2.1.1'=>'5.1; 5.2','2.2.1'=>'6.2','2.3.1'=>'4.4; 6.1; 9.1','2.4.1'=>'6.2; 8.1','2.5.1'=>'7.5','2.6.1'=>'5.1; 7.4; 9.3','2.7.1'=>'6.1; 9.1','2.8.1'=>'5.4; 7.4','2.9.1'=>'8.1','2.10.1'=>'8.1','2.11.1'=>'6.1; 8.1',
            '3.1.1'=>'6.1; 9.1','3.1.2'=>'8.1','3.1.3'=>'7.1; 8.1','3.1.4'=>'8.1; 9.1','3.1.5'=>'7.5','3.1.6'=>'8.1','3.1.7'=>'8.1','3.1.8'=>'7.1; 8.1','3.1.9'=>'8.1','3.2.1'=>'10.2','3.2.2'=>'10.2','3.2.3'=>'9.1; 10.2','3.3.1'=>'9.1','3.3.2'=>'9.1','3.3.3'=>'9.1','3.3.4'=>'9.1','3.3.5'=>'9.1','3.3.6'=>'9.1',
            '4.1.1'=>'6.1','4.1.2'=>'5.4; 6.1','4.1.3'=>'6.1; 8.1','4.1.4'=>'9.1','4.2.1'=>'8.1','4.2.2'=>'8.1','4.2.3'=>'7.5; 8.1','4.2.4'=>'5.4; 9.1','4.2.5'=>'7.1; 8.1','4.2.6'=>'8.1','5.1.1'=>'8.2','5.1.2'=>'7.1; 7.2; 8.2',
            '6.1.1'=>'9.1','6.1.2'=>'9.2','6.1.3'=>'9.3','6.1.4'=>'5.4; 9.2','7.1.1'=>'10.2; 10.3','7.1.2'=>'9.3; 10.3','7.1.3'=>'10.2','7.1.4'=>'9.1; 10.2',
        ];
    }

    private static function moduleFor(string $code): string
    {
        $exact = [
            '1.1.1'=>'/operacion/responsables','1.1.2'=>'/operacion/responsables','1.1.3'=>'/operacion/responsables','1.1.4'=>'/operacion/afiliaciones','1.1.5'=>'/operacion/afiliaciones','1.1.6'=>'/comites','1.1.7'=>'/comites','1.1.8'=>'/convivencia','1.2.1'=>'/capacitaciones','1.2.2'=>'/capacitaciones','1.2.3'=>'/capacitaciones',
            '2.1.1'=>'/documentos','2.2.1'=>'/plan-anual','2.3.1'=>'/autoevaluacion','2.4.1'=>'/plan-anual','2.5.1'=>'/evidencias','2.6.1'=>'/operacion/revision-direccion','2.7.1'=>'/autoevaluacion?tipo=Legal','2.8.1'=>'/autoreportes','2.9.1'=>'/operacion/adquisiciones','2.10.1'=>'/operacion/contratistas','2.11.1'=>'/operacion/cambios',
            '3.1.1'=>'/salud','3.1.2'=>'/programas-prevencion','3.1.3'=>'/salud','3.1.4'=>'/salud','3.1.5'=>'/salud','3.1.6'=>'/salud','3.1.7'=>'/salud/psicosocial','3.1.8'=>'/operacion/higiene','3.1.9'=>'/operacion/higiene','3.2.1'=>'/eventos','3.2.2'=>'/eventos','3.2.3'=>'/eventos',
            '4.1.1'=>'/matriz-ipevr','4.1.2'=>'/matriz-ipevr','4.1.3'=>'/operacion/mediciones','4.1.4'=>'/operacion/mediciones','4.2.1'=>'/matriz-ipevr','4.2.2'=>'/operacion/inspecciones','4.2.3'=>'/procedimientos','4.2.4'=>'/operacion/inspecciones','4.2.5'=>'/operacion/mantenimiento','4.2.6'=>'/operacion/epp','5.1.1'=>'/emergencias','5.1.2'=>'/emergencias',
            '6.1.1'=>'/indicadores','6.1.2'=>'/operacion/auditoria','6.1.3'=>'/operacion/revision-direccion','6.1.4'=>'/operacion/auditoria','7.1.1'=>'/hallazgos','7.1.2'=>'/operacion/revision-direccion','7.1.3'=>'/eventos','7.1.4'=>'/plan-anual',
        ];
        return $exact[$code] ?? (str_starts_with($code, '3.3.') ? '/indicadores' : '/autoevaluacion');
    }

    private static function periodicity(string $code): string
    {
        return match ($code) {
            '1.1.6','1.1.8' => 'Mensual',
            '2.7.1','4.1.2','4.2.4' => 'Trimestral y ante cambios',
            '3.2.1','3.2.2','3.2.3','7.1.3' => 'Por evento',
            '3.3.1','3.3.2','3.3.6' => 'Mensual',
            '4.2.5','4.2.6' => 'Según programa y condición',
            default => 'Anual',
        };
    }
}
