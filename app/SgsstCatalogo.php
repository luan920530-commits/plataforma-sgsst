<?php
namespace App;

/**
 * Catálogo base de cumplimiento SG-SST (Colombia).
 * Estándares mínimos orientativos según la Resolución 0312/2019 y el ciclo PHVA
 * del Decreto 1072/2015, Libro 2, Parte 2, Título 4, Capítulo 6.
 * La aplicabilidad exacta depende del N.° de trabajadores y la clase de riesgo:
 * por eso cada ítem puede marcarse "no_aplica" con justificación en observaciones.
 * Las plantillas son documentos base de partida: deben ser adaptadas y validadas
 * por el responsable del SG-SST (con licencia vigente cuando la norma lo exige)
 * y firmadas por el empleador. La plataforma no sustituye esa validación profesional.
 */
class SgsstCatalogo
{
    public static function estandares(): array
    {
        // [codigo, ciclo, nombre, evidencia esperada, base legal]
        $r = [
            ['1.1.1','PLANEAR','Asignación del responsable del SG-SST','Acta/designación firmada y licencia vigente (cuando aplique)','Res. 0312/2019'],
            ['1.1.2','PLANEAR','Asignación de responsabilidades en SST a todos los niveles','Matriz de roles y responsabilidades comunicada','Res. 0312/2019'],
            ['1.1.4','PLANEAR','Afiliación al Sistema General de Riesgos Laborales','Planillas PILA y certificados de afiliación','Ley 1562/2012'],
            ['1.1.6','PLANEAR','Conformación del COPASST o Vigía de SST','Acta de conformación y actas de reunión mensuales','Res. 2013/1986 · D.1072/2015'],
            ['1.1.7','PLANEAR','Capacitación del COPASST / Vigía','Certificados de capacitación','Res. 0312/2019'],
            ['1.1.8','PLANEAR','Conformación del Comité de Convivencia Laboral','Acta de conformación y actas trimestrales','Res. 652/2012'],
            ['1.2.1','PLANEAR','Programa de capacitación anual en SST','Programa aprobado con cronograma y registros de asistencia','Res. 0312/2019'],
            ['1.2.2','PLANEAR','Inducción y reinducción en SST','Registros de inducción de todo el personal','D. 1072/2015'],
            ['2.1.1','PLANEAR','Política de SST firmada, fechada y comunicada','Política firmada por el representante legal','D. 1072/2015 art. 2.2.4.6.7'],
            ['2.2.1','PLANEAR','Objetivos del SG-SST medibles y coherentes con la política','Documento de objetivos e indicadores','D. 1072/2015'],
            ['2.3.1','PLANEAR','Evaluación inicial del SG-SST','Evaluación inicial firmada (esta plataforma la genera)','Res. 0312/2019'],
            ['2.4.1','PLANEAR','Plan de trabajo anual firmado','Plan anual con metas, responsables, recursos y cronograma','D. 1072/2015'],
            ['2.5.1','PLANEAR','Conservación y control de documentos del SG-SST','Listado maestro de documentos y archivo organizado','D. 1072/2015 (20 años registros clave)'],
            ['2.6.1','PLANEAR','Rendición de cuentas anual del SG-SST','Informe/acta de rendición de cuentas','D. 1072/2015'],
            ['2.10.1','PLANEAR','Evaluación y selección de proveedores y contratistas con criterios SST','Procedimiento y evaluaciones aplicadas','Res. 0312/2019'],
            ['2.11.1','PLANEAR','Gestión del cambio','Procedimiento y registros de cambios evaluados','D. 1072/2015'],
            ['3.1.1','HACER','Perfil sociodemográfico y condiciones de salud','Diagnóstico de condiciones de salud actualizado','Res. 0312/2019'],
            ['3.1.2','HACER','Actividades de medicina preventiva, promoción y prevención','Programa y soportes de ejecución (profesiograma)','Res. 0312/2019'],
            ['3.1.4','HACER','Exámenes médicos ocupacionales (ingreso, periódicos, egreso)','Certificados de aptitud y custodia de historias clínicas','Res. 2346/2007'],
            ['3.2.1','HACER','Reporte de accidentes de trabajo y enfermedad laboral (FURAT/FUREL)','Reportes a ARL/EPS dentro de los 2 días hábiles','D. 1295/1994'],
            ['3.2.2','HACER','Investigación de incidentes, accidentes y enfermedad laboral','Informes de investigación con equipo investigador (máx. 15 días)','Res. 1401/2007'],
            ['3.3.1','VERIFICAR','Medición de indicadores: frecuencia, severidad, mortalidad, ausentismo','Tablero de indicadores actualizado','Res. 0312/2019'],
            ['4.1.1','HACER','Identificación de peligros, evaluación y valoración de riesgos (IPVR)','Matriz IPVR metodología GTC 45, actualizada anualmente','GTC 45 · D. 1072/2015'],
            ['4.2.1','HACER','Medidas de prevención y control implementadas','Evidencia de controles (eliminación → EPP)','Res. 0312/2019'],
            ['4.2.5','HACER','Mantenimiento periódico de instalaciones, equipos, máquinas y herramientas','Programa y registros de mantenimiento','Res. 0312/2019'],
            ['4.2.6','HACER','Entrega y reposición de Elementos de Protección Personal (EPP)','Registros de entrega firmados y matriz de EPP por cargo','Res. 0312/2019'],
            ['5.1.1','HACER','Plan de prevención, preparación y respuesta ante emergencias (PPPRAE)','Plan vigente (módulo PPPRAE de esta plataforma)','D. 1072/2015 · Res. 256/2014'],
            ['5.1.2','HACER','Brigada de prevención contra incendios y emergencias conformada y entrenada','Actas, certificados y registros de simulacros (mín. 1/año)','Res. 256/2014'],
            ['6.1.1','VERIFICAR','Definición y seguimiento de indicadores del SG-SST','Fichas técnicas de indicadores estructura/proceso/resultado','D. 1072/2015'],
            ['6.1.3','VERIFICAR','Revisión anual por la alta dirección','Acta de revisión con conclusiones y decisiones','D. 1072/2015'],
            ['6.1.4','VERIFICAR','Auditoría anual del SG-SST','Informe de auditoría (interna o con COPASST)','D. 1072/2015'],
            ['7.1.1','ACTUAR','Acciones preventivas y correctivas con base en resultados','Plan de mejoramiento con responsables y fechas','D. 1072/2015'],
            ['7.1.2','ACTUAR','Acciones de mejora por investigaciones y requisitos legales','Evidencia de cierre y verificación de eficacia','Res. 0312/2019'],
        ];
        return array_map(fn($x) => ['codigo'=>$x[0],'ciclo'=>$x[1],'nombre'=>$x[2],'evidencia_esperada'=>$x[3],'norma'=>$x[4]], $r);
    }

    public static function plantillas(): array
    {
        return [
            'politica' => ['titulo'=>'Política de Seguridad y Salud en el Trabajo','norma'=>'Decreto 1072/2015, art. 2.2.4.6.7','firma'=>'Representante legal','secciones'=>['Compromiso de la alta dirección','Prevención de lesiones y enfermedades laborales','Cumplimiento de requisitos legales aplicables','Consulta y participación de los trabajadores','Mejora continua del SG-SST','Comunicación y revisión anual de la política']],
            'objetivos' => ['titulo'=>'Objetivos e indicadores del SG-SST','norma'=>'Decreto 1072/2015','firma'=>'Representante legal y responsable SG-SST','secciones'=>['Objetivo general','Objetivos específicos medibles','Indicadores, metas y responsables','Seguimiento trimestral']],
            'plan_trabajo' => ['titulo'=>'Plan de trabajo anual del SG-SST','norma'=>'Decreto 1072/2015 · Res. 0312/2019','firma'=>'Representante legal y responsable SG-SST','secciones'=>['Metas anuales','Actividades y cronograma por ciclo PHVA','Responsables y recursos','Seguimiento y porcentaje de ejecución']],
            'acta_copasst' => ['titulo'=>'Acta de conformación del COPASST / Vigía de SST','norma'=>'Resolución 2013/1986 · Decreto 1072/2015','firma'=>'Empleador y representantes de los trabajadores','secciones'=>['Proceso de elección','Integrantes principales y suplentes','Funciones y periodo de vigencia','Compromisos de reunión mensual']],
            'matriz_peligros' => ['titulo'=>'Matriz de identificación de peligros y valoración de riesgos (GTC 45)','norma'=>'GTC 45 · Decreto 1072/2015','firma'=>'Responsable SG-SST','secciones'=>['Proceso, zona y actividad','Peligro: descripción y clasificación','Efectos posibles y controles existentes','Evaluación: ND, NE, NP, NC y nivel de riesgo','Criterios de aceptabilidad y medidas de intervención']],
            'programa_capacitacion' => ['titulo'=>'Programa anual de capacitación en SST','norma'=>'Resolución 0312/2019','firma'=>'Responsable SG-SST','secciones'=>['Temas por población objetivo','Cronograma anual','Metodología y evaluación de eficacia','Registros de asistencia']],
            'procedimiento_incidentes' => ['titulo'=>'Procedimiento de reporte e investigación de incidentes y accidentes','norma'=>'Resolución 1401/2007 · Decreto 1295/1994','firma'=>'Responsable SG-SST','secciones'=>['Reporte inmediato y FURAT (2 días hábiles)','Conformación del equipo investigador','Metodología de análisis de causas','Plan de acción y seguimiento (investigación máx. 15 días)']],
            'inspeccion' => ['titulo'=>'Formato de inspección de seguridad','norma'=>'Decreto 1072/2015','firma'=>'Inspector y responsable del área','secciones'=>['Condiciones locativas y orden y aseo','Equipos, herramientas y EPP','Hallazgos, nivel de riesgo y responsable','Fecha compromiso y verificación de cierre']],
            'entrega_epp' => ['titulo'=>'Registro de entrega de EPP','norma'=>'Resolución 0312/2019','firma'=>'Trabajador que recibe','secciones'=>['Elemento entregado, talla y cantidad','Fecha de entrega y reposición','Compromiso de uso y cuidado','Firma del trabajador']],
            'plan_emergencias' => ['titulo'=>'Plan de emergencias (PPPRAE) — generado en el módulo de Análisis','norma'=>'Decreto 1072/2015 · Resolución 256/2014','firma'=>'Representante legal','secciones'=>['Usa el asistente PPPRAE de esta plataforma: genera el ZIP completo con análisis de vulnerabilidad y MEDEVAC']],
            'acta_auditoria' => ['titulo'=>'Informe de auditoría interna del SG-SST','norma'=>'Decreto 1072/2015','firma'=>'Auditor y alta dirección','secciones'=>['Alcance y criterios','Hallazgos y no conformidades','Conclusiones','Plan de mejoramiento']],
            'accion_mejora' => ['titulo'=>'Formato de acción preventiva / correctiva y de mejora','norma'=>'Decreto 1072/2015 (ciclo PHVA — Actuar)','firma'=>'Responsable de ejecución','secciones'=>['Fuente y descripción del hallazgo','Análisis de causa raíz','Acción, responsable y fecha','Verificación de eficacia y cierre']],
            'rendicion_cuentas' => ['titulo'=>'Informe de rendición de cuentas y revisión por la alta dirección','norma'=>'Decreto 1072/2015','firma'=>'Representante legal','secciones'=>['Resultados de indicadores y auditorías','Estado de acciones y plan de trabajo','Decisiones y recursos para el siguiente periodo']],
        ];
    }

    public static function actividadesBase(): array
    {
        // [titulo, ciclo, meses desde hoy para vencer]
        return [
            ['Realizar evaluación inicial del SG-SST (Res. 0312/2019)','PLANEAR',1],
            ['Firmar y divulgar la Política y los objetivos de SST','PLANEAR',1],
            ['Aprobar y firmar el plan de trabajo anual','PLANEAR',1],
            ['Conformar/actualizar COPASST o Vigía y Comité de Convivencia','PLANEAR',2],
            ['Actualizar matriz de peligros IPVR (GTC 45)','HACER',2],
            ['Ejecutar exámenes médicos ocupacionales programados','HACER',3],
            ['Ejecutar programa de capacitación e inducciones','HACER',3],
            ['Actualizar plan de emergencias y realizar simulacro','HACER',6],
            ['Medir indicadores y ausentismo del periodo','VERIFICAR',3],
            ['Realizar auditoría anual del SG-SST','VERIFICAR',10],
            ['Revisión anual por la alta dirección y rendición de cuentas','VERIFICAR',11],
            ['Cerrar acciones preventivas/correctivas y plan de mejoramiento','ACTUAR',11],
        ];
    }

    public static function tiposRegistro(): array
    {
        return [
            'capacitacion'=>'Capacitación / inducción','inspeccion'=>'Inspección de seguridad',
            'incidente'=>'Incidente','accidente'=>'Accidente de trabajo','enfermedad'=>'Enfermedad laboral',
            'entrega_epp'=>'Entrega de EPP','examen_medico'=>'Examen médico ocupacional',
            'mantenimiento'=>'Mantenimiento','simulacro'=>'Simulacro','reunion_copasst'=>'Reunión COPASST/Vigía',
            'accion_mejora'=>'Acción de mejora','auditoria'=>'Auditoría','otro'=>'Otro registro',
        ];
    }
}
