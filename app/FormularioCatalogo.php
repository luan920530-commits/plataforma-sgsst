<?php
namespace App;

/**
 * Fase C — Formatos del paquete SG-SST convertidos en formularios web.
 * Cada definición nace de un archivo real de plantillas/ (campo «archivo_origen»)
 * y puede sincronizar un registro de gestión al guardarse (campo «registro_tipo»).
 * Tipos de campo: texto, area (textarea), fecha, numero, seleccion, multiple (checkboxes),
 * trabajador (selector del personal de la empresa).
 */
class FormularioCatalogo
{
    public static function todos(): array
    {
        return [
            'inspeccion_instalaciones' => [
                'titulo' => 'Inspección de instalaciones, orden y aseo', 'estandar' => '48 · 4.2.2', 'registro_tipo' => 'inspeccion',
                'archivo_origen' => '48-INSPECCION A INSTALACIONES, MÁQUINAS Y EQUIPOS/INSPECCION DE INSTALACIONES ORDEN Y ASEO.xlsx',
                'descripcion' => 'Recorrido de verificación locativa con hallazgos, responsable y fecha de cierre.',
                'campos' => [
                    ['fecha', 'Fecha de la inspección', 'fecha', null, true],
                    ['area', 'Área / proceso inspeccionado', 'texto', null, true],
                    ['inspector', 'Quién inspecciona (nombre y cargo)', 'texto', null, true],
                    ['condiciones', 'Condiciones verificadas', 'multiple', ['Orden y aseo', 'Salidas y rutas de evacuación despejadas', 'Señalización visible', 'Instalaciones eléctricas en buen estado', 'Iluminación y ventilación adecuadas', 'Equipos de emergencia accesibles (extintor/botiquín)'], false],
                    ['hallazgos', 'Hallazgos y recomendaciones', 'area', null, true],
                    ['nivel_riesgo', 'Nivel de riesgo del hallazgo principal', 'seleccion', ['Bajo', 'Medio', 'Alto', 'Crítico'], true],
                    ['responsable', 'Responsable de corregir', 'texto', null, false],
                    ['fecha_compromiso', 'Fecha compromiso de cierre', 'fecha', null, false],
                ],
            ],
            'inspeccion_extintores' => [
                'titulo' => 'Inspección de extintores', 'estandar' => '48 · 5.1.1', 'registro_tipo' => 'inspeccion',
                'archivo_origen' => '48-INSPECCION A INSTALACIONES, MÁQUINAS Y EQUIPOS/INSPECCION DE EXTINTORES.xlsx',
                'descripcion' => 'Verificación mensual de extintores: ubicación, presión, sello, acceso y vencimiento.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['ubicacion', 'Ubicación del extintor', 'texto', null, true],
                    ['numero', 'Número / código del extintor', 'texto', null, false],
                    ['estado', 'Estado general', 'seleccion', ['Bueno', 'Requiere recarga', 'Requiere mantenimiento', 'Fuera de servicio'], true],
                    ['verificaciones', 'Verificaciones realizadas', 'multiple', ['Manómetro en rango (presión OK)', 'Sello/pasador completo', 'Manguera y boquilla en buen estado', 'Acceso libre y señalizado', 'Etiqueta y fecha de recarga vigentes'], false],
                    ['observaciones', 'Observaciones', 'area', null, false],
                    ['responsable', 'Responsable de la novedad', 'texto', null, false],
                    ['fecha_compromiso', 'Fecha compromiso', 'fecha', null, false],
                ],
            ],
            'inspeccion_epp' => [
                'titulo' => 'Inspección de EPP', 'estandar' => '50 · 4.2.6', 'registro_tipo' => 'inspeccion',
                'archivo_origen' => '50-ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL Y CAPACITACIÓN EN USO ADECUADO/INSPECCIÓN EPP.xlsx',
                'descripcion' => 'Inspección del uso y estado de los elementos de protección personal por trabajador/área.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['trabajador', 'Trabajador inspeccionado', 'trabajador', null, false],
                    ['area', 'Área', 'texto', null, false],
                    ['elementos', 'EPP verificados', 'multiple', ['Casco', 'Gafas', 'Guantes', 'Protección auditiva', 'Respirador/mascarilla', 'Botas de seguridad', 'Arnés / trabajo en alturas', 'Delantal u otro'], false],
                    ['resultado', 'Resultado', 'seleccion', ['Usa correctamente todo el EPP', 'Uso parcial / incorrecto', 'EPP en mal estado', 'No usa el EPP'], true],
                    ['accion', 'Acción tomada (capacitación, reposición, llamado de atención)', 'area', null, false],
                    ['fecha_compromiso', 'Fecha de seguimiento', 'fecha', null, false],
                ],
            ],
            'entrega_epp' => [
                'titulo' => 'Entrega de dotación y EPP', 'estandar' => '50 · 4.2.6', 'registro_tipo' => 'entrega_epp',
                'archivo_origen' => '50-ENTREGA DE ELEMENTOS DE PROTECCIÓN PERSONAL Y CAPACITACIÓN EN USO ADECUADO/ENTREGA DE DOTACIÓN Y EPP.xls',
                'descripcion' => 'Soporte de entrega de EPP al trabajador (equivale a la firma del formato en papel).',
                'campos' => [
                    ['fecha', 'Fecha de entrega', 'fecha', null, true],
                    ['trabajador', 'Trabajador que recibe', 'trabajador', null, true],
                    ['elementos', 'Elementos entregados', 'multiple', ['Casco', 'Gafas', 'Guantes', 'Protección auditiva', 'Respirador/mascarilla', 'Botas de seguridad', 'Dotación (uniforme)', 'Arnés', 'Otro'], true],
                    ['tallas', 'Tallas / referencias', 'texto', null, false],
                    ['motivo', 'Motivo', 'seleccion', ['Ingreso', 'Reposición por desgaste', 'Reposición por daño', 'Cambio de labor', 'Dotación periódica'], true],
                    ['compromiso', 'El trabajador confirma recepción y capacitación en uso adecuado', 'seleccion', ['Sí', 'No'], true],
                ],
            ],
            'asistencia_capacitacion' => [
                'titulo' => 'Registro de asistencia a capacitación', 'estandar' => '9 · 1.2.1', 'registro_tipo' => 'capacitacion',
                'archivo_origen' => '9-PROGRAMA DE CAPACITACION ANUAL/REGISTRO DE ASISTENCIA.docx',
                'descripcion' => 'Soporte de ejecución del programa anual de capacitación.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['tema', 'Tema de la capacitación', 'texto', null, true],
                    ['instructor', 'Instructor / entidad', 'texto', null, false],
                    ['duracion', 'Duración (horas)', 'numero', null, false],
                    ['asistentes', 'Asistentes (un nombre por línea)', 'area', null, true],
                    ['evaluacion', '¿Se evaluó la eficacia/comprensión?', 'seleccion', ['Sí', 'No', 'Pendiente'], false],
                ],
            ],
            'induccion' => [
                'titulo' => 'Inducción / reinducción en SST', 'estandar' => '10 · 1.2.2', 'registro_tipo' => 'capacitacion',
                'archivo_origen' => '10-INDUCCIÓN Y RE INDUCCIÓN EN SST/ACTA DE TEMARIO RECIBIDO EN LA INDUCCIÓN-REINDUCCIÓN.docx',
                'descripcion' => 'Constancia del temario de inducción o reinducción recibido por el trabajador.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['trabajador', 'Trabajador', 'trabajador', null, true],
                    ['tipo', 'Tipo', 'seleccion', ['Inducción (ingreso)', 'Reinducción (anual/por cambio)'], true],
                    ['temas', 'Temas recibidos', 'multiple', ['Política y objetivos del SG-SST', 'Peligros y riesgos de su puesto (matriz IPVR)', 'Uso de EPP', 'Plan de emergencias y rutas de evacuación', 'Reporte de incidentes/accidentes', 'COPASST/Vigía y comité de convivencia', 'Derechos y deberes en SST'], false],
                    ['resultado_evaluacion', 'Resultado de la evaluación (%)', 'numero', null, false],
                ],
            ],
            'encuesta_peligros' => [
                'titulo' => 'Encuesta de peligros a trabajadores', 'estandar' => '42 · 4.1.1', 'registro_tipo' => 'inspeccion',
                'archivo_origen' => '42-IDENTIFICACIÓN DE PELIGROS, EVALUACIÓN Y VALORACIÓN DE LOS RIESGOS/ENCUESTA DE PELIGROS REALIZADA A TRABAJADORES.xlsx',
                'descripcion' => 'Participación de los trabajadores en la identificación de peligros (insumo de la matriz GTC 45).',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['trabajador', 'Trabajador (opcional)', 'trabajador', null, false],
                    ['area', 'Área / puesto de trabajo', 'texto', null, true],
                    ['peligro', 'Peligro que identifica', 'area', null, true],
                    ['clasificacion', 'Clasificación GTC 45', 'seleccion', ['Biológico', 'Físico', 'Químico', 'Psicosocial', 'Biomecánico', 'Condiciones de seguridad', 'Fenómenos naturales', 'Otro'], true],
                    ['sugerencia', 'Medida de control que sugiere', 'area', null, false],
                ],
            ],
            'perfil_sociodemografico' => [
                'titulo' => 'Perfil sociodemográfico y morbilidad sentida', 'estandar' => '23 · 3.1.1', 'registro_tipo' => null, 'confidencial' => true,
                'archivo_origen' => '23-DESCRIPCIÓN SOCIODEMOGRÁFICA Y DIAGNÓSTICO DE CONDICIONES DE SALUD DE LOS TRABAJADORES/ENCUESTA PERFIL SOCIODEMOGRAFICO Y MORBILIDAD SENTIDA.docx',
                'descripcion' => 'Datos para el diagnóstico de condiciones de salud. Información sensible: acceso restringido (Ley 1581/2012).',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['trabajador', 'Trabajador', 'trabajador', null, true],
                    ['edad', 'Edad', 'numero', null, false],
                    ['sexo', 'Sexo', 'seleccion', ['Femenino', 'Masculino', 'Otro/prefiero no decir'], false],
                    ['escolaridad', 'Nivel de escolaridad', 'seleccion', ['Primaria', 'Bachillerato', 'Técnico', 'Tecnólogo', 'Universitario', 'Posgrado'], false],
                    ['antiguedad', 'Antigüedad en la empresa (años)', 'numero', null, false],
                    ['condiciones', 'Condiciones de salud que reporta (morbilidad sentida)', 'area', null, false],
                    ['habitos', 'Hábitos (actividad física, tabaco, alcohol — voluntario)', 'area', null, false],
                ],
            ],
            'remision_examen' => [
                'titulo' => 'Remisión a examen médico ocupacional', 'estandar' => '26 · 3.1.4', 'registro_tipo' => 'examen_medico',
                'archivo_origen' => '26-EVALUACIONES MEDICO OCUPACIONALES/REMISION EXAMEN MEDICO OCUPACIONAL.xlsx',
                'descripcion' => 'Remisión del trabajador a la IPS según profesiograma.',
                'campos' => [
                    ['fecha', 'Fecha de remisión', 'fecha', null, true],
                    ['trabajador', 'Trabajador', 'trabajador', null, true],
                    ['tipo_examen', 'Tipo de examen', 'seleccion', ['Ingreso', 'Periódico', 'Egreso', 'Post-incapacidad', 'Por cambio de ocupación'], true],
                    ['ips', 'IPS / entidad', 'texto', null, false],
                    ['examenes', 'Exámenes y paraclínicos remitidos', 'area', null, false],
                    ['fecha_compromiso', 'Fecha límite de realización', 'fecha', null, false],
                ],
            ],
            'evaluacion_proveedor' => [
                'titulo' => 'Evaluación y selección de proveedores/contratistas', 'estandar' => '21 · 2.10.1', 'registro_tipo' => null,
                'archivo_origen' => '21-EVALUACIÓN Y SELECCIÓN DE PROVEEDORES Y CONTRATISTAS/EVALUACION SELECCION CONTRATISTA INDEPENDIENTE.xlsx',
                'descripcion' => 'Evaluación con criterios de SST para la adquisición de bienes y servicios.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['proveedor', 'Proveedor / contratista', 'texto', null, true],
                    ['servicio', 'Bien o servicio', 'texto', null, true],
                    ['criterios', 'Criterios SST verificados', 'multiple', ['Afiliación a seguridad social del personal', 'Certificados de competencia/curso de alturas u otros', 'Entrega y uso de EPP', 'Procedimientos de trabajo seguro', 'SG-SST propio (contratistas)', 'Pólizas vigentes'], false],
                    ['puntaje', 'Puntaje total (0–100)', 'numero', null, false],
                    ['resultado', 'Resultado', 'seleccion', ['Aprobado', 'Aprobado con condiciones', 'No aprobado'], true],
                    ['observaciones', 'Observaciones', 'area', null, false],
                ],
            ],
            'gestion_cambio' => [
                'titulo' => 'Gestión del cambio', 'estandar' => '22 · 2.11.1', 'registro_tipo' => 'accion_mejora',
                'archivo_origen' => '22-GESTIÓN DEL CAMBIO/FORMATO GESTIÓN DEL CAMBIO.xls',
                'descripcion' => 'Evaluación de peligros antes de cambios en procesos, equipos, personal o instalaciones.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['descripcion', 'Descripción del cambio', 'area', null, true],
                    ['peligros', 'Nuevos peligros o riesgos identificados', 'area', null, false],
                    ['controles', 'Controles a implementar antes del cambio', 'area', null, false],
                    ['responsable', 'Responsable', 'texto', null, false],
                    ['fecha_compromiso', 'Fecha de implementación/verificación', 'fecha', null, false],
                ],
            ],
            'investigacion_incidente' => [
                'titulo' => 'Reporte e investigación de incidente/accidente', 'estandar' => '32/33 · 3.2.1–3.2.2', 'registro_tipo' => 'incidente',
                'archivo_origen' => '32, 33 Y 59-REPORTE E INVESTIGACIÓN DE INCIDENTE, ACCIDENTE Y ENFERMEDAD LABORAL/ACTA REUNIÓN EQUIPO INVESTIGADOR.docx',
                'descripcion' => 'Investigación conforme a la Res. 1401/2007 (equipo investigador, máx. 15 días).',
                'campos' => [
                    ['fecha', 'Fecha del evento', 'fecha', null, true],
                    ['tipo_evento', 'Tipo de evento', 'seleccion', ['Incidente', 'Accidente leve', 'Accidente grave', 'Accidente mortal', 'Enfermedad laboral'], true],
                    ['trabajador', 'Trabajador afectado', 'trabajador', null, false],
                    ['lugar', 'Lugar y tarea que realizaba', 'texto', null, false],
                    ['descripcion', 'Descripción del evento', 'area', null, true],
                    ['causas', 'Causas inmediatas y básicas identificadas', 'area', null, false],
                    ['acciones', 'Medidas de intervención y plan de acción', 'area', null, false],
                    ['equipo', 'Equipo investigador (nombres/cargos)', 'area', null, false],
                    ['fecha_compromiso', 'Fecha compromiso de cierre (máx. 15 días)', 'fecha', null, false],
                ],
            ],
            'acta_copasst' => [
                'titulo' => 'Acta de reunión COPASST / Vigía', 'estandar' => '6 · 1.1.6', 'registro_tipo' => 'reunion_copasst',
                'archivo_origen' => '6-CONFORMACIÓN DEL COPASST O ASIGNACIÓN VIGÍA/ACTA DE REUNION.doc',
                'descripcion' => 'Acta de la reunión mensual del COPASST o de la gestión del Vigía.',
                'campos' => [
                    ['fecha', 'Fecha de la reunión', 'fecha', null, true],
                    ['asistentes', 'Asistentes', 'area', null, true],
                    ['temas', 'Temas tratados', 'area', null, true],
                    ['compromisos', 'Compromisos, responsables y fechas', 'area', null, false],
                    ['fecha_compromiso', 'Fecha de seguimiento', 'fecha', null, false],
                ],
            ],
            'queja_acoso' => [
                'titulo' => 'Queja por presunto acoso laboral', 'estandar' => '8 · 1.1.8', 'registro_tipo' => null, 'confidencial' => true,
                'archivo_origen' => '8-CONFORMACION Y FUNCIONAMIENTO DEL COMITÉ DE CONVIVENCIA LABORAL/FORMATO PARA INTERPONER QUEJA POR ACOSO LABORAL.docx',
                'descripcion' => 'Canal del Comité de Convivencia Laboral (Ley 1010/2006). Información confidencial: acceso restringido.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['trabajador', 'Persona que presenta la queja', 'trabajador', null, false],
                    ['presunto_agresor', 'Presunto agresor (nombre/cargo)', 'texto', null, false],
                    ['hechos', 'Descripción de los hechos', 'area', null, true],
                    ['pruebas', 'Pruebas o testigos que aporta', 'area', null, false],
                ],
            ],
            'accion_correctiva' => [
                'titulo' => 'Acción preventiva / correctiva y de mejora', 'estandar' => '57 · 7.1.1', 'registro_tipo' => 'accion_mejora',
                'archivo_origen' => '57-ACCIONES PREVENTIVAS O CORRECTIVAS/REGISTRO DE ACCIONES PREVENTIVAS Y CORRECTIVAS.xlsx',
                'descripcion' => 'Tratamiento de hallazgos de auditorías, inspecciones, investigaciones y revisión por la dirección.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['fuente', 'Fuente', 'seleccion', ['Auditoría', 'Inspección', 'Investigación de incidente/accidente', 'Revisión por la alta dirección', 'Reporte de trabajador', 'Requisito legal', 'Otro'], true],
                    ['hallazgo', 'Hallazgo / no conformidad', 'area', null, true],
                    ['causa', 'Análisis de causa raíz', 'area', null, false],
                    ['accion', 'Acción a implementar', 'area', null, true],
                    ['responsable', 'Responsable', 'texto', null, false],
                    ['fecha_compromiso', 'Fecha compromiso', 'fecha', null, false],
                ],
            ],
            'control_residuos' => [
                'titulo' => 'Control de residuos (peligrosos y no peligrosos)', 'estandar' => '31 · 3.1.6', 'registro_tipo' => null,
                'archivo_origen' => '31-MANEJO DE RESIDUOS/CONTROL DE RESIDUOS PELIGROSOS.xlsx',
                'descripcion' => 'Registro de generación, almacenamiento y disposición de residuos.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['tipo_residuo', 'Tipo de residuo', 'seleccion', ['Peligroso', 'No peligroso aprovechable', 'No peligroso ordinario', 'Especial'], true],
                    ['descripcion', 'Descripción y cantidad (kg/unidades)', 'texto', null, true],
                    ['disposicion', 'Disposición / gestor autorizado', 'texto', null, false],
                    ['certificado', 'Certificado de disposición (N.°/ubicación)', 'texto', null, false],
                ],
            ],
            'simulacro' => [
                'titulo' => 'Planeación e informe de simulacro', 'estandar' => '52 · 5.1.2', 'registro_tipo' => 'simulacro',
                'archivo_origen' => '52-BRIGADA DE PREVENCIÓN, PREPARACIÓN Y RESPUESTA ANTE EMERGENCIA/PLANEACIÓN SIMULACRO.docx',
                'descripcion' => 'Simulacro de emergencias (mínimo uno al año): planeación, ejecución y lecciones aprendidas.',
                'campos' => [
                    ['fecha', 'Fecha del simulacro', 'fecha', null, true],
                    ['tipo_emergencia', 'Tipo de emergencia simulada', 'seleccion', ['Incendio', 'Sismo', 'Evacuación general', 'Emergencia médica', 'Derrame/emergencia química', 'Otro'], true],
                    ['participantes', 'Participantes (N.° y áreas)', 'texto', null, false],
                    ['tiempo_evacuacion', 'Tiempo de evacuación (minutos)', 'numero', null, false],
                    ['hallazgos', 'Hallazgos y aspectos por mejorar', 'area', null, false],
                    ['acciones', 'Acciones de mejora y responsables', 'area', null, false],
                    ['fecha_compromiso', 'Fecha compromiso de mejoras', 'fecha', null, false],
                ],
            ],
            'mantenimiento' => [
                'titulo' => 'Mantenimiento de instalaciones, máquinas y equipos', 'estandar' => '49 · 4.2.5', 'registro_tipo' => 'mantenimiento',
                'archivo_origen' => '49-MANTE PERIODICO DE LAS INSTALACIONES, MAQUINAS, EQUIPOS Y HERRAMIENTAS/PROGRAMA MANTENIMIENTO DE INSTALACIONES, MAQUINAS, EQUIPOS Y HERRAMIENTAS.docx',
                'descripcion' => 'Registro de mantenimientos preventivos y correctivos.',
                'campos' => [
                    ['fecha', 'Fecha', 'fecha', null, true],
                    ['equipo', 'Equipo / máquina / instalación', 'texto', null, true],
                    ['tipo_mantenimiento', 'Tipo', 'seleccion', ['Preventivo', 'Correctivo', 'Predictivo'], true],
                    ['descripcion', 'Trabajo realizado', 'area', null, false],
                    ['ejecutor', 'Ejecutado por', 'texto', null, false],
                    ['fecha_compromiso', 'Próximo mantenimiento', 'fecha', null, false],
                ],
            ],
        ];
    }

    public static function uno(string $codigo): ?array
    {
        return self::todos()[$codigo] ?? null;
    }
}
