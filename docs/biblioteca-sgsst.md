# Informe de importación · SG-SST por estándares

> Informe técnico de la biblioteca suministrada por el usuario. La extracción y comparación no acreditan cumplimiento legal, implementación eficaz ni aprobación documental.

## 1. Fuente e integridad

- Archivo analizado: `SG-SST POR ESTANDARES-V.zip`.
- Distribución actual: el ZIP fue retirado del repositorio. Las versiones editables son autocontenidas; las nuevas extracciones de originales requieren una copia privada externa configurada mediante `SGSST_LIBRARY_ZIP`.
- SHA-256 histórico del ZIP: `5cff07963f698a57591424971ffc124a73e18b7aa5a4b4765871d94343b41068`.
- Tamaño del ZIP: 18,278,710 bytes.
- Integridad ZIP: correcta; sin cifrado ni rutas inseguras. No se ejecutaron macros ni objetos incrustados.
- Entradas útiles analizadas: **153** en 48 carpetas temáticas.
- Metadatos/temporales excluidos: **156** (`__MACOSX`, AppleDouble, un bloqueo de Office y `desktop.ini`).

## 2. Resultado de la comparación

- **142 plantillas únicas** implementadas: **88 documentos editables** y **54 formularios**.
- **11 duplicados exactos** consolidados sin perder sus referencias ni estándares.
- Cobertura: **60/60 estándares**.
- **37 plantillas** conservan **69 notas de conversión**; el detalle aparece en la sección 6 y en cada ficha de la aplicación.
- Formatos útiles de origen: 83 DOCX, 45 XLSX, 11 DOC, 9 XLS, 4 PPTX y 1 PDF.
- Formularios: **94 hojas**, **15.918 celdas con contenido** y **2.337 fórmulas** preservadas.
- Las plantillas previas no se reemplazaron. Cada elemento quedó relacionado con un módulo operativo y se genera como borrador independiente por tenant.

## 3. Tratamiento por formato

| Formato | Tratamiento en Normia | Original |
|---|---|---|
| DOCX | Texto, encabezados, pies, tablas, combinaciones y cuadros de texto; edición visual y versiones. | Descarga privada |
| DOC heredado | Extracción del flujo binario de Word, párrafos y tabulaciones convertidas en contenido editable. | Descarga privada |
| XLSX | Hojas, valores, fórmulas, combinaciones, validaciones y estilos básicos; respuestas por tenant. | Descarga privada |
| XLS heredado | Valores, fechas, estructura y combinaciones; fórmulas disponibles en el original. | Descarga privada |
| PPTX | Texto y tablas organizados por diapositiva. | Presentación original privada |
| PDF | Texto por página; revisión del original cuando sea necesario. | PDF original privado |

Las imágenes, logotipos, gráficos y elementos visuales no reproducidos permanecen en el archivo fuente. Las notas se listan más abajo; no se inventó contenido para sustituirlos.

## 4. Fórmulas

- Motor seguro para referencias, rangos y operaciones básicas, con `IF`, `COUNT`, `SUM`, `IFERROR`, `SUMIF`, `MAX` y `AVERAGE`.
- Las 2.337 fórmulas se procesaron sin excepciones. En 1.850 comparaciones numéricas con los resultados guardados por Excel no hubo diferencias.
- Cuatro fórmulas contienen el nombre definido `ALSO`; se conserva el resultado original y se señala la celda para revisión en lugar de inventar su significado:
  - MATRIZ IPEVR · OPERARIOS DE CONSTRUCCIÓN!X25.
  - MATRIZ IPEVR · VISITANTES!V9.
  - MATRIZ IPEVR · VISITANTES!V13.
  - MATRIZ IPEVR · VISITANTES!V16.
- Los XLS heredados conservan sus valores; el recálculo de sus fórmulas debe hacerse después de revisar el original.

## 5. Duplicados exactos consolidados

| Documento | Estándares relacionados | Ubicaciones |
|---|---:|---:|
| PROCEDIMIENTO INDUCCIÓN-REINDUCCIÓN | 10, 47 | 2 |
| PROCEDIMIENTO DE COMUNICACIÓN INTERNA Y EXTERNA | 19, 47 | 2 |
| PROCEDIMIENTO ADQUISICIÓN DE BIENES | 20, 47 | 2 |
| PROCEDIMIENTO PARA LA CONTRATACIÓN Y EVALUACIÓN DE PROVEEDORES Y CONTRATISTAS | 21, 47 | 2 |
| PROCEDIMIENTO GESTIÓN DEL CAMBIO | 22, 47 | 2 |
| PROGRAMA MEDICINA PREVENTIVA DEL TRABAJO | 24, 47 | 2 |
| PLAN MANEJO DE RESIDUOS | 31, 47 | 2 |
| PROCEDIMIENTO PARA LA IDENTIFICACIÓN DE PELIGROS, EVALUACIÓN Y VALORACIÓN DE RIESGOS | 41, 47 | 2 |
| PROCEDIMIENTO DE INSPECCIONES | 47, 48 | 2 |
| PROCEDIMIENTO MEDIDAS PREVENTIVAS Y CORRECTIVAS | 47, 57 | 2 |
| PROGRAMA MANTENIMIENTO DE INSTALACIONES, MAQUINAS, EQUIPOS Y HERRAMIENTAS | 47, 49 | 2 |

## 6. Notas de conversión

| Plantilla | Nota |
|---|---|
| CAPACITACION INDUCCIÓN Y RE INDUCCIÓN | 11 imagen(es) disponible(s) en la presentación original. |
| EVALUACIÓN INICIAL O AUTOEVALUACIÓN DEL SG-SST | EVALUACIÓN-AUTOEVALUACIÓN: 59 combinación(es) con contenido interno se muestran como celdas individuales para no ocultar datos. |
| PLAN ANUAL DE TRABAJO | 1: 0 imagen(es) y 1 gráfico(s) se conservan solo en el archivo original. |
| INSPECCIÓN PERIÓDICA CONTRATISTA EN OBRA | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| FORMATO GESTIÓN DEL CAMBIO | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| TABULACIÓN ENCUESTA P.S | TABULACION 2: 0 imagen(es) y 35 gráfico(s) se conservan solo en el archivo original. |
| PROGRAMA ESTILO DE VIDA Y ENTORNO SALUDABLE | 7 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| CONTROL DE RESIDUOS NO PELIGROSOS | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| DIAPOSITIVA RESIDUOS | 8 imagen(es) disponible(s) en la presentación original. |
| INVESTIGACIÓN DE A.T. | Combinación extensa C37:BI37 en 2 PARTE; revise el original. Combinación extensa C54:BI54 en 2 PARTE; revise el original. Combinación extensa C35:BI36 en 2 PARTE; revise el original. Combinación extensa C34:BI34 en 2 PARTE; revise el original. Combinación extensa C49:BI53 en 2 PARTE; revise el original. Combinación extensa C48:BI48 en 2 PARTE; revise el original. Combinación extensa C16:BI16 en 2 PARTE; revise el original. Combinación extensa C3:BI15 en 2 PARTE; revise el original. Combinación extensa C2:BI2 en 2 PARTE; revise el original. |
| INVESTIGACIÓN DE E.L | Combinación extensa C3:BH10 en 2 PARTE; revise el original. Combinación extensa C11:BH11 en 2 PARTE; revise el original. Combinación extensa C12:BH27 en 2 PARTE; revise el original. Combinación extensa C63:BH63 en 2 PARTE; revise el original. Combinación extensa C2:BH2 en 2 PARTE; revise el original. Combinación extensa C28:BH28 en 2 PARTE; revise el original. Combinación extensa C57:BH57 en 2 PARTE; revise el original. Combinación extensa C45:BH45 en 2 PARTE; revise el original. Combinación extensa C58:BH62 en 2 PARTE; revise el original. |
| INVESTIGACIÓN DE I.T. | Combinación extensa C12:BI27 en 2 PARTE; revise el original. Combinación extensa C63:BI63 en 2 PARTE; revise el original. Combinación extensa C28:BI28 en 2 PARTE; revise el original. Combinación extensa C45:BI45 en 2 PARTE; revise el original. Combinación extensa C58:BI62 en 2 PARTE; revise el original. Combinación extensa C3:BI10 en 2 PARTE; revise el original. Combinación extensa C57:BI57 en 2 PARTE; revise el original. Combinación extensa C2:BI2 en 2 PARTE; revise el original. Combinación extensa C11:BI11 en 2 PARTE; revise el original. |
| FICHA DE INDICADORES | Combinación extensa I116:BO117 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I59:BO60 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I181:BP182 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I153:BO154 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I30:BO31 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I2:BO3 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I87:BP88 en RESOLUCIÓN 0312-2019; revise el original. Combinación extensa I214:BP215 en RESOLUCIÓN 0312-2019; revise el original. RESOLUCIÓN 0312-2019: 0 imagen(es) y 8 gráfico(s) se conservan solo en el archivo original. |
| PROCEDIMIENTO PARA LA IDENTIFICACIÓN DE PELIGROS, EVALUACIÓN Y VALORACIÓN DE RIESGOS | 3 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| EJEMPLOS DE MEDIDAS DE INTERVENCIÓN Y CONTROLES EXISTENTES | CONTROLES EXISTENTES: 1 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| MATRIZ DE COMPATIBILIDAD DE SUSTANCIAS QUÍMICAS | MATRIZ DE COMPATIBILIDAD: 26 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| INSPECCIÓN DE CAMILLAS DE EMERGENCIA | Hoja1: 1 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| DIAPOSITIVA EPP | 42 imagen(es) disponible(s) en la presentación original. |
| ENTREGA DE DOTACIÓN Y EPP | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| INSPECCIÓN EPP PREOPERACIONAL | Hoja1: 31 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| INSPECCIÓN EPP | Hoja1: 31 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| MATRIZ EPP | Hoja1: 31 imagen(es) y 0 gráfico(s) se conservan solo en el archivo original. |
| ANÁLISIS DE VULNERABILIDAD | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| MATRIZ CONSOLIDADO DE AMENAZAS | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| MEDEVAC | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| PLAN DE PREVENCIÓN, PREPARACIÓN Y RESPUESTA ANTE EMERGENCIAS | 31 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| PLANEACIÓN SIMULACRO | 5 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| INFORME DE AUDITORIA | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| LISTA DE CHEQUEO DE AUDITORIA | Los archivos XLS heredados conservan valores y estructura; sus fórmulas originales se consultan en el archivo fuente. |
| REPORTE ACTOS Y CONDICIONES INSEGURAS | 2 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| ACTAS CONFORMACIÓN DEL COPASST | 4 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| MANUAL DE FUNCIONES DEL COPASST O VIGÍA | 1 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| PLAN DE MEJORAMIENTTO | Plan de Mejora SST: 0 imagen(es) y 1 gráfico(s) se conservan solo en el archivo original. |
| DIAPOSITIVAS COPASST | 10 imagen(es) disponible(s) en la presentación original. |
| ACTAS CONFORMACION COMITE CONVIVENCIA LABORAL | 4 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |
| PLAN DE CAPACITACIÓN ANUAL | 1: 0 imagen(es) y 1 gráfico(s) se conservan solo en el archivo original. |
| REGISTRO DE ASISTENCIA | 2 elemento(s) gráfico(s) se conservan en el original; la versión editable reproduce texto y tablas. |

## 7. Inventario completo y módulo relacionado

| N.º | Estándar(es) | Plantilla | Tipo | Módulo | Fórmulas | Fuentes |
|---:|---|---|---|---|---:|---:|
| 1 | 1 | ASIGNACION RESPONSABLE DE LA IMPLEMENTACION DEL SG-SST (DOCX) | Documento | Responsabilidades SG-SST | 0 | 1 |
| 2 | 1 | ASIGNACION RESPONSABLE DEL DISEÑO DEL SG-SST (DOCX) | Documento | Responsabilidades SG-SST | 0 | 1 |
| 3 | 10 | ACTA DE TEMARIO RECIBIDO EN LA INDUCCIÓN-REINDUCCIÓN (DOCX) | Documento | Inducción | 0 | 1 |
| 4 | 10 | CAPACITACION INDUCCIÓN Y RE INDUCCIÓN (PPTX) | Documento | Inducción | 0 | 1 |
| 5 | 10 | EVALUACIÓN INDUCCIÓN-REINDUCCIÓN (DOCX) | Documento | Inducción | 0 | 1 |
| 6 | 10, 47 | PROCEDIMIENTO INDUCCIÓN-REINDUCCIÓN (DOCX) | Documento | Inducción | 0 | 2 |
| 7 | 11 | CURSO VIRTUAL 50 HORAS SG-SST (DOCX) | Documento | Capacitaciones | 0 | 1 |
| 8 | 12 | POLITICA DE LA SEGURIDAD Y SALUD EN EL TRABAJO (DOCX) | Documento | Política SST | 0 | 1 |
| 9 | 13 | OBJETIVOS DEL SG-SST MEDIBLES (XLSX) | Formulario | Objetivos e indicadores | 5 | 1 |
| 10 | 13 | OBJETIVOS DEL SG-SST (DOCX) | Documento | Objetivos e indicadores | 0 | 1 |
| 11 | 14 | EVALUACIÓN INICIAL O AUTOEVALUACIÓN DEL SG-SST (XLSX) | Formulario | Evaluación inicial | 61 | 1 |
| 12 | 15 | PLAN ANUAL DE TRABAJO (XLSX) | Formulario | Plan Anual de Trabajo | 139 | 1 |
| 13 | 16 | LISTADO MAESTRO DE DOCUMENTOS (XLSX) | Formulario | Archivo documental | 0 | 1 |
| 14 | 16 | PROCEDIMIENTO ELABORACIÓN Y CONTROL DE DOCUMENTOS (DOCX) | Documento | Archivo documental | 0 | 1 |
| 15 | 17 | INFORME MENSUAL (DOCX) | Documento | Rendición de cuentas | 0 | 1 |
| 16 | 17 | INFORME MENSUAL (PDF) | Documento | Rendición de cuentas | 0 | 1 |
| 17 | 17 | RENDICION DE CUENTAS ANUAL (DOCX) | Documento | Rendición de cuentas | 0 | 1 |
| 18 | 18 | MATRIZ DE REQUISITOS LEGALES (XLSX) | Formulario | Matriz legal | 1 | 1 |
| 19 | 19 | MATRIZ COMUNICACIONES (XLSX) | Formulario | Comunicaciones | 0 | 1 |
| 20 | 19, 47 | PROCEDIMIENTO DE COMUNICACIÓN INTERNA Y EXTERNA (DOCX) | Documento | Comunicaciones | 0 | 2 |
| 21 | 2 | ASIGNACION DE RESPONSABILIDADES EN SST A LOS TRABAJADORES GENERAL (DOCX) | Documento | Responsabilidades SG-SST | 0 | 1 |
| 22 | 20 | MATRIZ DE REQUISITOS PARA ADQUIRIR BIENES Y SERVICIOS (XLSX) | Formulario | Contratistas y adquisiciones | 0 | 1 |
| 23 | 20, 47 | PROCEDIMIENTO ADQUISICIÓN DE BIENES (DOCX) | Documento | Contratistas y adquisiciones | 0 | 2 |
| 24 | 20 | PROCEDIMIENTO ADQUISICIÓN DE SERVICIOS (DOCX) | Documento | Contratistas y adquisiciones | 0 | 1 |
| 25 | 21 | BASE DE DATOS DE PROVEEDORES Y CONTRATISTAS (XLSX) | Formulario | Contratistas y adquisiciones | 0 | 1 |
| 26 | 21 | EVALUACION FINAL O PERIODICA CONTRATISTAS Y PROVEEDORES (XLSX) | Formulario | Contratistas y adquisiciones | 1 | 1 |
| 27 | 21 | EVALUACION SELECCION CONTRATISTA INDEPENDIENTE (XLSX) | Formulario | Contratistas y adquisiciones | 0 | 1 |
| 28 | 21 | EVALUACIÓN INICIAL PARA LA SELECCION DE PROVEEDORES Y CONTRATISTAS (XLSX) | Formulario | Contratistas y adquisiciones | 1 | 1 |
| 29 | 21 | INSCRIPCIÓN DE PROVEEDOR O CONTRATISTA (DOC) | Documento | Contratistas y adquisiciones | 0 | 1 |
| 30 | 21 | INSPECCIÓN PERIÓDICA CONTRATISTA EN OBRA (XLS) | Formulario | Contratistas y adquisiciones | 0 | 1 |
| 31 | 21, 47 | PROCEDIMIENTO PARA LA CONTRATACIÓN Y EVALUACIÓN DE PROVEEDORES Y CONTRATISTAS (DOCX) | Documento | Contratistas y adquisiciones | 0 | 2 |
| 32 | 22 | FORMATO GESTIÓN DEL CAMBIO (XLS) | Formulario | Gestión del cambio | 0 | 1 |
| 33 | 22, 47 | PROCEDIMIENTO GESTIÓN DEL CAMBIO (DOCX) | Documento | Gestión del cambio | 0 | 2 |
| 34 | 23 | ENCUESTA PERFIL SOCIODEMOGRAFICO Y MORBILIDAD SENTIDA (DOCX) | Documento | Condiciones de salud | 0 | 1 |
| 35 | 23 | TABULACIÓN ENCUESTA P.S (XLSX) | Formulario | Condiciones de salud | 64 | 1 |
| 36 | 24, 47 | PROGRAMA MEDICINA PREVENTIVA DEL TRABAJO (DOCX) | Documento | Programas preventivos | 0 | 2 |
| 37 | 25 | MANUAL DE FUNCIONES Y PERFILES DE CARGOS (DOCX) | Documento | Perfiles de cargo | 0 | 1 |
| 38 | 26 | PROCEDIMIENTO EXAMENES MEDICO OCUPACIONALES (DOCX) | Documento | Evaluaciones médicas | 0 | 1 |
| 39 | 26 | REMISION EXAMEN MEDICO OCUPACIONAL (XLSX) | Formulario | Evaluaciones médicas | 0 | 1 |
| 40 | 27 | Custodia de las historias clínicas (DOCX) | Documento | Custodia documental | 0 | 1 |
| 41 | 28 | ENTREGA RECOMENDACIONES O RESTRICCIONES MEDICAS (DOCX) | Documento | Condiciones de salud | 0 | 1 |
| 42 | 28 | SEGUIMIENTO A LAS RECOMENDACIONES O RESTRICCIONES MEDICO LABORALES (DOCX) | Documento | Condiciones de salud | 0 | 1 |
| 43 | 29 | PROGRAMA ESTILO DE VIDA Y ENTORNO SALUDABLE (DOCX) | Documento | Programas preventivos | 0 | 1 |
| 44 | 3 | ASIGNACION DE RECURSOS PARA EL SG-SST (DOCX) | Documento | Recursos SG-SST | 0 | 1 |
| 45 | 3 | PRESUPUESTO (XLSX) | Formulario | Recursos SG-SST | 65 | 1 |
| 46 | 30 | SERVICIOS DE HIGIENE (DOCX) | Documento | Programas preventivos | 0 | 1 |
| 47 | 31 | CONTROL DE RESIDUOS NO PELIGROSOS (XLS) | Formulario | Programas preventivos | 0 | 1 |
| 48 | 31 | CONTROL DE RESIDUOS PELIGROSOS (XLSX) | Formulario | Programas preventivos | 0 | 1 |
| 49 | 31 | DIAPOSITIVA RESIDUOS (PPTX) | Documento | Programas preventivos | 0 | 1 |
| 50 | 31 | EVALUACIÓN CAPACITACIÓN MANEJO DE RESIDUOS (DOCX) | Documento | Programas preventivos | 0 | 1 |
| 51 | 31 | INSPECCIÓN ÁREA DE ALMACENAMIENTO DE RESIDUOS (XLSX) | Formulario | Programas preventivos | 0 | 1 |
| 52 | 31 | PLAN ANUAL DE RESIDUOS (XLSX) | Formulario | Programas preventivos | 59 | 1 |
| 53 | 31, 47 | PLAN MANEJO DE RESIDUOS (DOCX) | Documento | Programas preventivos | 0 | 2 |
| 54 | 32, 33, 59 | ACTA REUNIÓN EQUIPO INVESTIGADOR (DOCX) | Documento | Investigación de eventos | 0 | 1 |
| 55 | 32, 33, 59 | INVESTIGACIÓN DE A.T. (XLSX) | Formulario | Investigación de eventos | 0 | 1 |
| 56 | 32, 33, 59 | INVESTIGACIÓN DE E.L (XLSX) | Formulario | Investigación de eventos | 0 | 1 |
| 57 | 32, 33, 59 | INVESTIGACIÓN DE I.T. (XLSX) | Formulario | Investigación de eventos | 0 | 1 |
| 58 | 32, 33, 59 | PROCEDIMIENTO DE REPORTE E INVESTIGACIÓN DE ENFERMEDAD LABORAL (DOCX) | Documento | Investigación de eventos | 0 | 1 |
| 59 | 32, 33, 59 | PROCEDIMIENTO DE REPORTE E INVESTIGACIÓN DE INCIDENTE Y ACCIDENTE LABORAL (DOC) | Documento | Investigación de eventos | 0 | 1 |
| 60 | 32, 33, 59 | RECOLECCIÓN DE EVIDENCIAS (DOCX) | Documento | Investigación de eventos | 0 | 1 |
| 61 | 34, 35, 36, 37, 38, 39, 40, 53 | FICHA DE INDICADORES (XLSX) | Formulario | Indicadores | 216 | 1 |
| 62 | 4 | AFILIACION AL SISTEMA DE SEGURIDAD SOCIAL INTEGRAL (DOCX) | Documento | Equipo y afiliaciones | 0 | 1 |
| 63 | 41, 47 | PROCEDIMIENTO PARA LA IDENTIFICACIÓN DE PELIGROS, EVALUACIÓN Y VALORACIÓN DE RIESGOS (DOCX) | Documento | Matriz IPEVR | 0 | 2 |
| 64 | 42 | EJEMPLOS DE MEDIDAS DE INTERVENCIÓN Y CONTROLES EXISTENTES (XLSX) | Formulario | Matriz IPEVR | 0 | 1 |
| 65 | 42 | ENCUESTA DE PELIGROS REALIZADA A TRABAJADORES (XLSX) | Formulario | Matriz IPEVR | 0 | 1 |
| 66 | 42 | FORMATO RECOLECCIÓN DE DATOS PARA ELABORAR LA MATRIZ (XLSX) | Formulario | Matriz IPEVR | 0 | 1 |
| 67 | 42 | MATRIZ IPEVR (XLSX) | Formulario | Matriz IPEVR | 292 | 1 |
| 68 | 43 | MATRIZ DE COMPATIBILIDAD DE SUSTANCIAS QUÍMICAS (XLSX) | Formulario | Matriz IPEVR | 0 | 1 |
| 69 | 43 | SUSTANCIAS CARCINÓGENAS O CON TOXICIDAD AGUDA (XLSX) | Formulario | Matriz IPEVR | 0 | 1 |
| 70 | 44 | MEDICIONES AMBIENTALES (DOCX) | Documento | Matriz IPEVR | 0 | 1 |
| 71 | 45, 46 | MEDIDAS DE PREV Y CONTROL FRENTE A PELIGROS Y RIESGOS IDENT (XLSX) | Formulario | Controles operacionales | 0 | 1 |
| 72 | 47 | MANUAL DEL SG-SST (DOC) | Documento | Procedimientos | 0 | 1 |
| 73 | 47 | PROCEDIMIENTO ADQUISICIÓN DE SERVICIOS (DOCX) | Documento | Procedimientos | 0 | 1 |
| 74 | 47 | PROCEDIMIENTO DE AUDITORIA INTERNA-EXTERNA (DOC) | Documento | Procedimientos | 0 | 1 |
| 75 | 47, 48 | PROCEDIMIENTO DE INSPECCIONES (DOCX) | Documento | Procedimientos | 0 | 2 |
| 76 | 47 | PROCEDIMIENTO DE REPORTE E INVESTIGACIÓN DE ENFERMEDAD LABORAL (DOCX) | Documento | Procedimientos | 0 | 1 |
| 77 | 47 | PROCEDIMIENTO DE REPORTE E INVESTIGACIÓN DE INCIDENTE Y ACCIDENTE LABORAL (DOC) | Documento | Procedimientos | 0 | 1 |
| 78 | 47 | PROCEDIMIENTO DE REVISIÓN POR LA ALTA DIRECCIÓN (DOCX) | Documento | Procedimientos | 0 | 1 |
| 79 | 47 | PROCEDIMIENTO ELABORACIÓN Y CONTROL DE DOCUMENTOS (DOCX) | Documento | Procedimientos | 0 | 1 |
| 80 | 47 | PROCEDIMIENTO EXAMENES MEDICO OCUPACIONALES (DOCX) | Documento | Procedimientos | 0 | 1 |
| 81 | 47 | PROCEDIMIENTO MANIPULACIÓN MANUAL DE CARGAS (DOCX) | Documento | Procedimientos | 0 | 1 |
| 82 | 47, 57 | PROCEDIMIENTO MEDIDAS PREVENTIVAS Y CORRECTIVAS (DOCX) | Documento | Procedimientos | 0 | 2 |
| 83 | 47 | PROGRAMA DE AUDITORIA (DOCX) | Documento | Procedimientos | 0 | 1 |
| 84 | 47 | PROGRAMA DE CAPACITACION (DOCX) | Documento | Procedimientos | 0 | 1 |
| 85 | 47 | PROGRAMA DE INSPECCIONES (DOCX) | Documento | Procedimientos | 0 | 1 |
| 86 | 47 | PROGRAMA ESTILO DE VIDA Y ENTORNO SALUDABLE (DOCX) | Documento | Procedimientos | 0 | 1 |
| 87 | 47, 49 | PROGRAMA MANTENIMIENTO DE INSTALACIONES, MAQUINAS, EQUIPOS Y HERRAMIENTAS (DOCX) | Documento | Procedimientos | 0 | 2 |
| 88 | 47 | REGLAMENTO DE HIGIENE Y SEGURIDAD INDUSTRIAL (DOCX) | Documento | Procedimientos | 0 | 1 |
| 89 | 48 | HALLAZGOS Y RECOMENDACIONES INSPECCION INSTALACIONES, ORDEN Y ASEO (DOCX) | Documento | Inspecciones | 0 | 1 |
| 90 | 48 | INSPECCION BIOMECÁNICA (XLSX) | Formulario | Inspecciones | 0 | 1 |
| 91 | 48 | INSPECCION DE EXTINTORES (XLSX) | Formulario | Inspecciones | 0 | 1 |
| 92 | 48 | INSPECCION DE INSTALACIONES ORDEN Y ASEO (XLSX) | Formulario | Inspecciones | 1 | 1 |
| 93 | 48 | INSPECCION PREOPERACIONAL Y LIMPIEZA DE MAQUINAS, EQUIPOS Y HERRAMIENTAS (XLSX) | Formulario | Inspecciones | 0 | 1 |
| 94 | 48 | INSPECCIÓN BOTIQUIN DE EMERGENCIAS (XLSX) | Formulario | Inspecciones | 0 | 1 |
| 95 | 48 | INSPECCIÓN DE CAMILLAS DE EMERGENCIA (XLSX) | Formulario | Inspecciones | 0 | 1 |
| 96 | 48 | PLAN ANUAL DE INSPECCIONES (XLSX) | Formulario | Inspecciones | 92 | 1 |
| 97 | 48 | PROGRAMA DE INSPECCIONES (DOCX) | Documento | Inspecciones | 0 | 1 |
| 98 | 49 | HOJA DE VIDA DE MAQUINAS, EQUIPOS Y HERRAMIENTAS (XLSX) | Formulario | Mantenimiento | 0 | 1 |
| 99 | 49 | PLAN ANUAL DE MANTENIMIENTO PREVENTIVO (XLSX) | Formulario | Mantenimiento | 1104 | 1 |
| 100 | 49 | REPORTE DE NOVEDADES DE INSTAL, MAQ, EQUI Y HERRA (XLSX) | Formulario | Mantenimiento | 0 | 1 |
| 101 | 5 | COTIZACION PENSION ESPECIAL TRABAJADORES DE ALTO RIESGO (DOCX) | Documento | Equipo y afiliaciones | 0 | 1 |
| 102 | 50 | DIAPOSITIVA EPP (PPTX) | Documento | Elementos de protección | 0 | 1 |
| 103 | 50 | ENTREGA DE DOTACIÓN Y EPP (XLS) | Formulario | Elementos de protección | 0 | 1 |
| 104 | 50 | INSPECCIÓN EPP PREOPERACIONAL (XLSX) | Formulario | Elementos de protección | 0 | 1 |
| 105 | 50 | INSPECCIÓN EPP (XLSX) | Formulario | Elementos de protección | 0 | 1 |
| 106 | 50 | MATRIZ EPP (XLSX) | Formulario | Elementos de protección | 0 | 1 |
| 107 | 51 | ANÁLISIS DE VULNERABILIDAD (XLS) | Formulario | Emergencias | 0 | 1 |
| 108 | 51 | LISTA DE CHEQUEO PARA ELABORAR PPPRAE (DOCX) | Documento | Emergencias | 0 | 1 |
| 109 | 51 | MATRIZ CONSOLIDADO DE AMENAZAS (XLS) | Formulario | Emergencias | 0 | 1 |
| 110 | 51 | MEDEVAC (XLS) | Formulario | Emergencias | 0 | 1 |
| 111 | 51 | PLAN DE PREVENCIÓN, PREPARACIÓN Y RESPUESTA ANTE EMERGENCIAS (DOCX) | Documento | Emergencias | 0 | 1 |
| 112 | 52 | ACTA CONFORMACIÓN BRIGADA Y COMITÉ DE EMERGENCIAS (DOC) | Documento | Emergencias | 0 | 1 |
| 113 | 52 | EVALUACIÓN SIMULACRO POR PARTE DEL OBSERVADOR (DOCX) | Documento | Emergencias | 0 | 1 |
| 114 | 52 | INFORME SIMULACRO REALIZADO O EMERGENCIA REAL PRESENTADA POR PARTE BRIGADISTAS (DOCX) | Documento | Emergencias | 0 | 1 |
| 115 | 52 | INFORME SIMULACRO POR PARTE DEL OBSERVADOR (DOCX) | Documento | Emergencias | 0 | 1 |
| 116 | 52 | INSCRIPCIÓN BRIGADISTA Y COMITÉ DE EMERGENCIAS (DOC) | Documento | Emergencias | 0 | 1 |
| 117 | 52 | MANUAL DE FUNCIONES BRIGADA Y COMITÉ DE EMERGENCIAS (DOCX) | Documento | Emergencias | 0 | 1 |
| 118 | 52 | PLANEACIÓN SIMULACRO (DOCX) | Documento | Emergencias | 0 | 1 |
| 119 | 54, 56 | ACTA APERTURA Y CIERRE AUDITORIA (DOC) | Documento | Auditoría anual | 0 | 1 |
| 120 | 54, 56 | INFORME DE AUDITORIA (XLS) | Formulario | Auditoría anual | 0 | 1 |
| 121 | 54, 56 | LISTA DE CHEQUEO DE AUDITORIA (XLS) | Formulario | Auditoría anual | 0 | 1 |
| 122 | 54, 56 | PLAN DE AUDITORIAS (DOC) | Documento | Auditoría anual | 0 | 1 |
| 123 | 54, 56 | PROCEDIMIENTO DE AUDITORIA INTERNA-EXTERNA (DOC) | Documento | Auditoría anual | 0 | 1 |
| 124 | 54, 56 | PROGRAMA DE AUDITORIA (DOCX) | Documento | Auditoría anual | 0 | 1 |
| 125 | 55, 58 | PROCEDIMIENTO DE REVISIÓN POR LA ALTA DIRECCIÓN (DOCX) | Documento | Revisión por la dirección | 0 | 1 |
| 126 | 55, 58 | REVISIÓN POR LA ALTA DIRECCIÓN (XLSX) | Formulario | Revisión por la dirección | 0 | 1 |
| 127 | 57 | REGISTRO DE ACCIONES PREVENTIVAS Y CORRECTIVAS (XLSX) | Formulario | Acciones correctivas | 0 | 1 |
| 128 | 57 | REPORTE ACTOS Y CONDICIONES INSEGURAS (DOCX) | Documento | Acciones correctivas | 0 | 1 |
| 129 | 6 | ACTA ASIGNACIÓN DE VIGÍA (DOCX) | Documento | COPASST / Vigía | 0 | 1 |
| 130 | 6 | ACTA DE REUNION (DOC) | Documento | COPASST / Vigía | 0 | 1 |
| 131 | 6 | ACTAS CONFORMACIÓN DEL COPASST (DOCX) | Documento | COPASST / Vigía | 0 | 1 |
| 132 | 6 | MANUAL DE FUNCIONES DEL COPASST O VIGÍA (DOCX) | Documento | COPASST / Vigía | 0 | 1 |
| 133 | 60 | PLAN DE MEJORAMIENTTO (XLSX) | Formulario | Plan de mejoramiento | 97 | 1 |
| 134 | 7 | DIAPOSITIVAS COPASST (PPTX) | Documento | Capacitaciones | 0 | 1 |
| 135 | 8 | ACTAS CONFORMACION COMITE CONVIVENCIA LABORAL (DOCX) | Documento | Convivencia laboral | 0 | 1 |
| 136 | 8 | FORMATO PARA INTERPONER QUEJA POR ACOSO LABORAL (DOCX) | Documento | Convivencia laboral | 0 | 1 |
| 137 | 8 | MANUAL DE CONVIVENCIA LABORAL (DOCX) | Documento | Convivencia laboral | 0 | 1 |
| 138 | 8 | MANUAL DE FUNCIONES DEL COMITE CONVIVENCIA LABORAL (DOCX) | Documento | Convivencia laboral | 0 | 1 |
| 139 | 8 | POLITICA DE CONVIVENCIA LABORAL (DOCX) | Documento | Convivencia laboral | 0 | 1 |
| 140 | 9 | PLAN DE CAPACITACIÓN ANUAL (XLSX) | Formulario | Capacitaciones | 139 | 1 |
| 141 | 9 | PROGRAMA DE CAPACITACION (DOCX) | Documento | Capacitaciones | 0 | 1 |
| 142 | 9 | REGISTRO DE ASISTENCIA (DOCX) | Documento | Capacitaciones | 0 | 1 |

## 8. Controles de implementación

- Aprobación humana y estado de borrador; ninguna importación marca cumplimiento automático.
- Aislamiento de documentos generados y respuestas por `tenant_id`.
- CSRF y autorización por rol en rutas de gestión y descarga.
- Originales fuera del DocumentRoot; extracción por índice ZIP y verificación de tamaño y SHA-256 antes de entregar.
- Saneamiento HTML, conservación segura de `rowspan`/`colspan`, texto plano en formularios y validación de coordenadas en PHP.
- Consulta operativa en `README.md` y pruebas automatizadas en `tests/run.php`.
