<?php
declare(strict_types=1);

namespace App\Services;

/** Formularios operativos con campos específicos; los valores se conservan íntegramente en JSON validado. */
final class OperationCatalog
{
    public static function modules(): array
    {
        return [
            'responsables' => self::definition('Responsables y recursos','users','Designaciones, competencia, dedicación, licencias y recursos del SG-SST.',['Designación','Evaluación de competencia','Asignación de recursos'],[
                self::field('formacion','Formación y experiencia','textarea',true), self::field('licencia','Licencia SST / soporte','text'),
                self::field('curso_50h_fecha','Curso 50 horas · fecha','date'), self::field('actualizacion_20h_fecha','Actualización 20 horas · fecha','date'),
                self::field('dedicacion','Dedicación y autoridad asignada','text',true), self::field('centro','Centro(s) de trabajo','text'),
                self::field('evaluacion_competencia','Evaluación de competencia y brechas','textarea'),
            ]),
            'afiliaciones' => self::definition('Afiliaciones y alto riesgo','shield','Coberturas EPS, ARL, pensiones, centros y actividades de alto riesgo.',['Afiliación','Verificación de cobertura','Alto riesgo / pensión especial'],[
                self::field('empleado_id','Persona del directorio','employee'), self::field('entidad','Entidad administradora','text',true),
                self::field('numero_afiliacion','Número / referencia de afiliación','text'), self::field('clase_riesgo','Clase de riesgo','select',false,['Por verificar','I','II','III','IV','V']),
                self::field('centro_trabajo','Centro de trabajo','text',true), self::field('actividad_alto_riesgo','Actividad de alto riesgo','select',false,['Por evaluar','Sí','No']),
                self::field('pension_especial','Cotización de pensión especial','select',false,['Por verificar','No aplica','Al día','Pendiente']),
                self::field('actividad_pension','Actividad y fundamento de alto riesgo','textarea'),
            ]),
            'comunicaciones' => self::definition('Comunicación y participación','activity','Comunicaciones, consultas, participación, barreras y retroalimentación.',['Comunicación','Consulta','Participación','Sugerencia'],[
                self::field('canal','Canal utilizado','text',true), self::field('audiencia','Población / partes interesadas','text',true),
                self::field('contenido','Contenido o tema consultado','textarea',true), self::field('retroalimentacion','Respuestas y retroalimentación recibida','textarea'),
                self::field('barreras','Barreras identificadas y ajustes','textarea'), self::field('sin_represalias','Se verificó ausencia de represalias','select',false,['Por verificar','Sí','No']),
            ]),
            'adquisiciones' => self::definition('Adquisiciones','briefcase','Evaluación SST de bienes y servicios antes de comprar y al recibir.',['Evaluación previa','Recepción y aceptación'],[
                self::field('proveedor','Proveedor','text',true), self::field('producto_servicio','Bien o servicio','text',true),
                self::field('criterios_sst','Criterios SST de selección','textarea',true), self::field('peligros','Peligros y requisitos asociados','textarea'),
                self::field('evaluacion_previa','Resultado de evaluación previa','textarea',true), self::field('aceptacion','Verificación en recepción / aceptación','textarea'),
            ]),
            'contratistas' => self::definition('Contratistas y proveedores','building','Selección, coordinación, evaluación y control de contratistas y subcontratistas.',['Selección','Coordinación','Evaluación de desempeño'],[
                self::field('contratista','Contratista / proveedor','text',true), self::field('nit','NIT / identificación','text'),
                self::field('servicio','Servicio y alcance','textarea',true), self::field('fecha_inicio','Inicio de contrato','date'), self::field('fecha_fin','Fin de contrato','date'),
                self::field('evaluacion_requisitos','Evaluación de SG-SST y requisitos','textarea',true), self::field('coordinador','Coordinador de contrato','text',true),
                self::field('controles','Controles coordinados y comunicados','textarea',true), self::field('subcontratistas','Subcontratistas y verificación','textarea'),
            ]),
            'cambios' => self::definition('Gestión del cambio','phva','Impacto de cambios internos, externos, temporales o permanentes antes de ejecutarlos.',['Cambio interno','Cambio externo','Cambio temporal','Cambio permanente'],[
                self::field('motivo','Motivo y alcance del cambio','textarea',true), self::field('impacto','Impacto en personas, procesos y SG-SST','textarea',true),
                self::field('peligros_nuevos','Peligros nuevos o modificados','textarea',true), self::field('controles','Controles antes del cambio','textarea',true),
                self::field('consulta','Consulta y participación de afectados','textarea',true), self::field('aprobacion','Autorización y condiciones','textarea'),
                self::field('revision_posterior','Criterio y fecha de revisión posterior','textarea'),
            ]),
            'higiene' => self::definition('Higiene y residuos','shield-plain','Agua, servicios sanitarios, limpieza y disposición de residuos con soportes.',['Agua potable','Servicios sanitarios','Residuo sólido','Residuo líquido','Residuo gaseoso','Limpieza y desinfección'],[
                self::field('ubicacion','Sede / ubicación','text',true), self::field('proveedor','Proveedor o gestor','text'),
                self::field('manifiesto','Manifiesto, certificado o referencia','text'), self::field('cantidad','Cantidad','number'),
                self::field('unidad','Unidad','text'), self::field('resultado_sanitario','Resultado sanitario / condición encontrada','textarea',true),
                self::field('disposicion','Tratamiento o disposición final','textarea'),
            ]),
            'mediciones' => self::definition('Mediciones y carcinógenos','chart','Mediciones físicas, químicas y biológicas, inventario de carcinógenos y toxicidad aguda.',['Medición ambiental','Inventario de sustancia','Seguimiento de exposición'],[
                self::field('categoria','Categoría','select',true,['Físico','Químico','Biológico','Carcinógeno o toxicidad aguda']),
                self::field('agente','Agente / sustancia / factor','text',true), self::field('cas','Número CAS','text'),
                self::field('metodo','Método y equipo','textarea',true), self::field('laboratorio','Laboratorio / profesional idóneo','text',true),
                self::field('informe','Informe y fecha de referencia','text',true), self::field('puntos','Puntos, puestos o grupos de exposición','textarea',true),
                self::field('resultado_numerico','Resultado numérico','number'), self::field('unidad','Unidad de medida','text'),
                self::field('limite','Límite / referencia numérica','number'), self::field('referencia_limite','Norma o referencia del límite','text'),
                self::field('interpretacion','Interpretación técnica','select',false,['No evaluada','Dentro del referente','Fuera del referente','No aplica']),
                self::field('expuestos','Número de personas expuestas','number'), self::field('controles','Controles y seguimiento requeridos','textarea',true),
            ]),
            'inspecciones' => self::definition('Inspecciones planeadas','clipboard','Listas de verificación, inspecciones a instalaciones, equipos y controles, hallazgos y cierre verificado.',['Inspección planeada','Inspección extraordinaria','Verificación de control'],[
                self::field('area','Área / sede','text',true), self::field('activo','Instalación, maquinaria o equipo','text',true),
                self::field('alcance','Alcance y criterios de la inspección','textarea',true),
                self::field('lista_nombre','Nombre / versión de la lista de verificación','text',true),
                self::field('checklist','Ítems de la lista: criterio, conformidad y observación por ítem','textarea',true),
                self::field('inspector','Responsable de inspeccionar','text',true), self::field('participante_copasst','Participante COPASST / vigía','text'),
                self::field('hallazgos','Hallazgos y condiciones observadas; registra “Sin hallazgos” si aplica','textarea',true), self::field('condicion_critica','Condición crítica','select',false,['Por verificar','No','Sí']),
                self::field('accion_inmediata','Acción inmediata de control','textarea'),
                self::field('estado_hallazgos','Situación de hallazgos','select',false,['Por verificar','Abiertos','Cerrados con soporte','Sin hallazgos']),
                self::field('seguimiento_cierre','Seguimiento y resultado de cierre','textarea'), self::field('fecha_verificacion_cierre','Fecha de verificación del cierre','date'),
            ]),
            'mantenimiento' => self::definition('Mantenimiento','settings','Mantenimiento preventivo y correctivo de instalaciones, máquinas y herramientas.',['Preventivo','Correctivo','Predictivo','Verificación técnica'],[
                self::field('activo','Activo / equipo','text',true), self::field('ubicacion','Ubicación','text',true),
                self::field('proveedor','Responsable técnico / proveedor','text',true), self::field('trabajo_realizado','Trabajo realizado','textarea',true),
                self::field('repuestos','Repuestos y especificaciones','textarea'), self::field('condicion_final','Condición final y pruebas','textarea',true),
                self::field('bloqueo','Bloqueo / control de energías aplicado','textarea'),
            ]),
            'epp' => self::definition('Elementos de protección personal','shield','Entrega, formación, reposición y verificación de EPP para trabajadores y contratistas.',['Entrega','Reposición','Verificación de uso','Devolución'],[
                self::field('empleado_id','Persona que recibe','employee',true), self::field('elemento','Elemento y características','text',true),
                self::field('norma_tecnica','Norma / certificación técnica','text',true), self::field('talla','Talla / ajuste','text'),
                self::field('cantidad','Cantidad','number',true), self::field('capacitacion_uso','Formación en uso, limitaciones y cuidado','textarea',true),
                self::field('verificacion_ajuste','Verificación de ajuste y estado','textarea',true), self::field('soporte_entrega','Referencia de constancia de entrega','text'),
            ]),
            'auditoria' => self::definition('Auditorías internas y externas','search','Programación, listas de verificación, objetividad, hallazgos y cierre de auditorías internas o externas.',['Planificación de auditoría','Auditoría interna','Auditoría externa','Seguimiento de auditoría'],[
                self::field('alcance','Alcance, procesos y centros','textarea',true), self::field('criterios','Criterios de auditoría','textarea',true),
                self::field('checklist','Programa / lista de verificación y evidencias a revisar','textarea',true),
                self::field('entidad_auditora','Entidad o equipo auditor','text',true), self::field('auditor','Auditor y competencia','text',true), self::field('independencia','Objetividad e independencia','textarea',true),
                self::field('copasst','Participación y comunicación al COPASST','textarea',true), self::field('hallazgos','Hallazgos y evidencias revisadas; registra “Sin hallazgos” si aplica','textarea',true),
                self::field('fortalezas','Fortalezas y oportunidades','textarea'), self::field('informe_referencia','Informe / referencia documental','text'),
                self::field('estado_hallazgos','Situación de hallazgos','select',false,['Por verificar','Abiertos','Cerrados con soporte','Sin hallazgos']),
                self::field('seguimiento_cierre','Seguimiento y resultado de cierre','textarea'), self::field('fecha_verificacion_cierre','Fecha de verificación del cierre','date'),
            ]),
            'revision-direccion' => self::definition('Revisión por la dirección','dashboard','Entradas, resultados, decisiones, recursos y acciones de la alta dirección.',['Revisión anual','Revisión extraordinaria','Seguimiento de decisiones'],[
                self::field('entradas','Entradas revisadas: desempeño, auditoría, incidentes, cambios, consulta y requisitos','textarea',true),
                self::field('resultados_indicadores','Resultados y tendencias analizados','textarea',true), self::field('auditoria','Resultados y alcance de auditoría','textarea',true),
                self::field('consulta','Consulta y participación de trabajadores','textarea',true), self::field('decisiones','Decisiones de la alta dirección','textarea',true),
                self::field('recursos_asignados','Recursos aprobados','textarea',true), self::field('oportunidades','Oportunidades de mejora y cambios','textarea',true),
            ]),
        ];
    }

    public static function get(string $module): ?array
    {
        return self::modules()[$module] ?? null;
    }

    private static function definition(string $title, string $icon, string $description, array $types, array $fields): array
    {
        return ['title'=>$title,'icon'=>$icon,'description'=>$description,'types'=>$types,'fields'=>$fields,'evidence_required'=>true];
    }

    private static function field(string $name, string $label, string $type, bool $required = false, array $options = []): array
    {
        return ['name'=>$name,'label'=>$label,'type'=>$type,'required'=>$required,'options'=>$options];
    }
}
