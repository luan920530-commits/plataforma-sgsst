# Paquete de inicio SG-SST por empresa, régimen y peligros

Revisión de fuentes y cobertura: **2026-10-28**. Esta ampliación convierte el generador en un paquete documental específico y editable. **La disponibilidad de un borrador no demuestra adaptación, aprobación, implementación, ejecución ni cumplimiento legal.**

## 1. Resultado de la revisión

Antes de esta ampliación, el asistente creaba los estándares, tres políticas transversales cuando correspondía, peligros y perfiles base, Plan Anual, capacitaciones, inducción y kits de actividad, AST, limpieza, alturas, PESV y espacios confinados. La biblioteca importada tenía 142 plantillas para consulta manual, pero no formaba un paquete inicial seleccionado por empresa. Los desarrollos de los estándares eran mayormente transversales y faltaban formatos para ejecutar y evidenciar los programas.

Se conservaron los kits existentes y se agregaron:

- **60 temas técnicos** para las **88 filas de estándares** de los regímenes 7, 21 y 60, sin un desarrollo genérico de respaldo.
- **74 piezas documentales en 21 kits** dentro de la nueva biblioteca de inicio: 51 formatos, 11 procedimientos, 8 programas, 2 guías, 1 manual y 1 plan.
- Ampliación de CCL, alturas y espacios confinados.
- Guía de puesta en marcha, Manual SG-SST y matriz FT-COB-01 de cobertura y brechas.
- Complementación idempotente para empresas existentes.
- Descarga individual DOCX editable y paquete ZIP con documentos, cobertura CSV y archivo LEEME.

Los 74 recursos de la biblioteca no se cargan todos en todas las empresas. Se seleccionan por régimen, CIIU y peligros; los 60 temas de estándares tampoco son 60 documentos adicionales para cada tenant.

## 2. Fuentes de verdad en el código

| Recurso | Finalidad |
|---|---|
| `resources/starter-package/standards.json` | Enfoque, acciones, controles, evidencias y referente de cada tema de estándar; mapa exacto de 88 códigos. |
| `resources/starter-package/library.json` | Manifiesto de 21 kits y 74 piezas, selectores de aplicabilidad, tablas de formatos y referencias oficiales. |
| `StandardDocumentTemplate` | Renderizado específico de cada estándar. Falla si un código queda sin contenido, en lugar de ocultar el faltante con texto genérico. |
| `StarterPackage` | Selección por grupo 7/21/60, CIIU, peligros base y peligros reales de `peligros_ipevr`. |
| `StarterPackageTemplate` | Programas, guías, manual y formatos visuales con campos en blanco y casillas sin marcar. |
| `ProcedureLibrary` | Integración con los kits anteriores, generación manual/automática y complementación idempotente. |
| `DocumentGenerator::complement()` | Sincronización documental de tenants existentes sin repetir peligros, perfiles, planes o capacitaciones. |
| `DocxDocument` / `ZipBuilder` | OOXML y ZIP en PHP puro, sin macros y sin depender de `ZipArchive`. |

## 3. Composición del paquete

### Base transversal para todas las empresas SG-SST

- Estándares del régimen y políticas aplicables.
- Actividad económica: PR-ACT y FT-ACT.
- AST y programa de limpieza: FT-AST-01, PR-LD-01, FT-LD-01 y FT-LD-02.
- Inicio: GUI-INI-01, MAN-SST-01 y FT-COB-01.
- Gestión: designación/recursos, afiliaciones, diagnóstico y programa de capacitación, registro de formación, Plan Anual e IPEVR.
- Salud: evaluaciones ocupacionales y custodia clínica, psicosocial y ergonomía, con sus formatos no clínicos.
- Convivencia: PR-CCL-01 y FT-CCL-01 para actas ordinarias y datos agregados.
- Emergencias: plan, amenazas/vulnerabilidad/brigada/recursos y evaluación de simulacros.
- EPP: procedimiento, matriz de selección y registro de entrega/formación/inspección.

### Según régimen

- **21 y 60:** COPASST/vigía, archivo y retención, reporte e investigación de eventos, mantenimiento, inspecciones y revisión por la dirección.
- **60:** matriz legal, objetivos e indicadores, comunicaciones, contratistas, compras, cambios, perfiles cargo–exposición, residuos, carcinógenos, mediciones, auditoría, mejora y consolidado estadístico.

### Anexos condicionados

Químicos/SGA, biológico y cortopunzantes, zoonosis, agentes físicos, maquinaria y energías, electricidad, izajes, trabajos en caliente, alimentos, campo, educación superior, teletrabajo/trabajo en casa/desconexión, desplazamientos, plan de manejo de tránsito y violencias de terceros.

Alturas, espacios confinados y PESV conservan sus kits y reglas anteriores. La selección de alturas ya no confunde “almacenamiento en altura” con trabajo de personas en alturas. Un anexo sugerido debe validarse con la IPEVR y las tareas reales; si no aplica, se justifica en FT-COB-01.

## 4. Cobertura auditada: 21 CIIU × 3 regímenes

Conteos obtenidos mediante generación real en SQLite de **63 perfiles y 4.680 documentos**. Incluyen estándares, políticas, kits base y anexos indicados. No incluyen documentos operativos adicionales que una empresa existente o una demo pueda tener.

| CIIU | Actividad | 7 | 21 | 60 | Anexos seleccionados en el perfil base |
|---|---|---:|---:|---:|---|
| 0113 | Hortalizas, raíces y tubérculos | 46 | 66 | 118 | AGF, AGR, QUI |
| 0141 | Ganado bovino y búfalos | 45 | 65 | 117 | AGR, MAQ, ZOO |
| 1071 | Productos de panadería | 47 | 67 | 119 | ALM, MAQ, QUI |
| 1410 | Confección de prendas | 43 | 63 | 115 | AGF, MAQ |
| 2220 | Productos de plástico | 46 | 66 | 118 | AGF, MAQ, QUI |
| 2511 | Productos metálicos estructurales | 50 | 70 | 122 | AGF, IZA, MAQ, QUI, SOL |
| 4100 | Construcción de edificios | 47 | 67 | 119 | AGF, ALT, IZA, MAQ |
| 4210 | Carreteras y vías de ferrocarril | 45 | 65 | 117 | IZA, MAQ, PMT |
| 4321 | Instalaciones eléctricas | 43 | 63 | 115 | ALT, ELE |
| 4711 | Comercio no especializado | 40 | 60 | 112 | VIO |
| 4759 | Otros artículos domésticos | 40 | 60 | 112 | VIO |
| 4923 | Transporte de carga por carretera | 40 | 60 | 112 | PESV |
| 5210 | Almacenamiento y depósito | 44 | 64 | 116 | IZA, MAQ, PESV |
| 5511 | Hoteles | 43 | 63 | 115 | QUI, VIO |
| 5610 | Restaurantes | 43 | 63 | 115 | ALM, VIO |
| 6201 | Desarrollo de software | 41 | 61 | 113 | TEC |
| 7020 | Consultoría de gestión | 43 | 63 | 115 | TEC, VIA |
| 8121 | Limpieza interior de edificios | 42 | 62 | 114 | QUI |
| 8530 | Educación superior | 44 | 64 | 116 | EDU, TEC, VIO |
| 8610 | Hospitales y clínicas | 42 | 62 | 114 | BIO, VIO |
| 8621 | Práctica médica con internación | 42 | 62 | 114 | BIO, VIO |

**Siglas:** AGF agentes físicos; AGR campo; ALM alimentos; ALT alturas; BIO bioseguridad; EDU campus; ELE electricidad; IZA izajes; MAQ maquinaria; PMT manejo de tránsito; QUI químicos; SOL soldadura/caliente; TEC teletrabajo; VIA desplazamientos; VIO violencias de terceros; ZOO zoonosis.

Los conteos sin anexos son **39 / 59 / 111** para 7 / 21 / 60. Cada anexo agrega sus procedimientos y formatos. Por eso no existe una cifra única de “documentos legales obligatorios” para todas las empresas.

### Extintores SCM

El perfil real 4759 / 7 genera **40 borradores**: base de 39 más GUI-VIO-01. Conserva los documentos de limpieza y los 28 seguimientos IVC pendientes. No recibe maquinaria por la palabra “atrapamiento” en efectos de caída de objetos, ni químicos, alturas o confinados sin una tarea que los justifique. Las actividades técnicas, CIIU, soportes, responsables y datos de pruebas hidrostáticas continúan sujetos a validación documental; no se completaron por inferencia.

## 5. Reglas para no fabricar evidencias

- Los programas describen acciones por implementar; no las marcan ejecutadas.
- Los formatos nacen con celdas vacías y opciones `☐ Sí ☐ No ☐ N.A.` sin seleccionar.
- No se crean capacitaciones realizadas, asistencias, firmas, aptitudes, certificados, resultados de mediciones, simulacros, investigaciones, entregas de EPP ni cierres.
- FT-COB-01 separa disponibilidad, adaptación/aprobación e implementación/evidencia.
- Las historias clínicas, diagnósticos y expedientes individuales de convivencia no pertenecen a la biblioteca general.
- Las fuentes convertidas que conservan texto médico histórico quedan señaladas como **actualización normativa requerida**. Se pueden consultar para trazabilidad, pero el sistema bloquea la generación de nuevos borradores desde los documentos médicos históricos identificados; no se cambia el rótulo de artículos antiguos para hacerlos pasar por texto de 2025.
- Los permisos operativos se gestionan en su módulo y por tarea; un procedimiento general no autoriza trabajo de alto riesgo.
- Los módulos exclusivos PESVial y Emergencias/Vigilancia conservan su aislamiento; esta acción no les carga el SG-SST general.

## 6. Empresas existentes e idempotencia

La acción **Complementar paquete** está en Documentos y en Procedimientos:

1. Agrega los documentos faltantes del régimen, actividad y peligros actuales.
2. Actualiza únicamente borradores automáticos de versión 1 cuyo contenido cambió, archivando la versión anterior.
3. No sobrescribe documentos editados, versionados por el usuario, aprobados ni PESV existentes.
4. No vuelve a crear peligros, perfiles, Plan Anual, capacitaciones o inducciones.
5. Una segunda ejecución sin cambios produce cero altas y cero actualizaciones.

Para contenido antiguo que ya fue aprobado o editado, la empresa debe revisar la nueva referencia y decidir una actualización controlada. El sistema no la impone.

## 7. Edición y descarga

- Cada documento conserva el editor visual y el historial de versiones.
- **Descargar editable** genera DOCX sin macros. Conserva encabezados, párrafos, énfasis, listas, tablas, `colspan` y tablas anidadas del subconjunto HTML permitido.
- **Descargar paquete** genera ZIP con carpetas PHVA, documentos editables, `cobertura_documental.csv` y `LEEME.txt`.
- El ZIP se construye con PHP puro, sin `ZipArchive`. Si DOM no está disponible, se entrega HTML editable en lugar de fallar; el encabezado `X-Normia-Formato` identifica la alternativa.
- La cobertura CSV registra el estado documental, pero mantiene la implementación como pendiente de validación.

## 8. Criterios normativos incorporados

- **Decreto 1072 de 2015 y Resolución 0312 de 2019:** PHVA, conservación documental y regímenes 7/21/60; riesgo IV/V con hasta 50 trabajadores usa estándares completos según la clasificación ya implementada.
- **Resolución 1401 de 2007:** investigación de incidentes y accidentes, equipo, 15 días, causas inmediatas/básicas, acciones y remisión a ARL cuando corresponda. En accidente mortal se utiliza el formato de la ARL.
- **Resolución 1843 de 2025:** evaluaciones de preingreso, periódicas, cambio de ocupación, egreso, postincapacidad, retorno y seguimiento según corresponda; periodicidad justificada con límite de tres años para las periódicas, adaptación, conceptos funcionales y custodia clínica reservada. Derogó las Resoluciones 2346 de 2007 y 1918 de 2009, retiradas de las plantillas generadas.
- **Decreto 728 de 2025 y Resolución 2764 de 2022:** promoción de salud mental, prevención del consumo de sustancias psicoactivas, intervención basada en evaluación, canalización, apoyo en crisis y seguimiento; batería, guía y protocolos psicosociales como mínimos, sin alterarlos ni precargar resultados.
- **Decreto 1040 de 2026:** articulación de medidas frente a acoso, violencia y discriminación, canales reservados, no revictimización y rutas diferenciadas con SG-SST, talento humano y CCL.
- **Circular 027 de 2026:** autoevaluación y Plan de Mejoramiento con responsables, recursos y seguimiento. El plazo especial de registro 2025 y plan hasta el 31/07/2026 estaba vencido en la fecha de esta revisión; no se presenta como una fecha futura ni se presume reporte realizado. La obligación anual y los plazos vigentes deben verificarse.
- **Resolución 3461 de 2025:** CCL con reglamento propio, período de dos años, funciones preventivas, seguimiento mensual, informes trimestrales/anuales y procedimiento preventivo máximo de 65 días calendario no acumulativos. El CCL no determina si existe acoso. Acoso sexual y violencia por razones de género siguen la ruta especial y no conciliación.
- El texto oficial del artículo 3 de la Resolución 3461 describe “menos de 5”, “más de 5 y menos de 20” y “más de 20”. No clasifica expresamente exactamente 5 o 20; el borrador exige revisión jurídica/oficial y no extrapola una regla.
- **Resolución 4272 de 2021:** programa de prevención/protección contra caídas, roles, formación, sistemas, permiso y rescate.
- **Resoluciones 0491 y 2605 de 2020:** gestión de espacios confinados, inventario/evaluación, permiso, roles, aislamiento, mediciones, vigilancia y rescate.
- **Resolución 0773 de 2021 y Decretos 1496 de 2018 y 1630 de 2021:** SGA, FDS, etiquetado y gestión química según producto y exposición.
- **Resolución 2674 de 2013:** higiene, saneamiento, manipuladores, proceso y requisitos sanitarios de alimentos.
- **Leyes 2088 de 2021 y 2191 de 2022 y Decreto 1227 de 2022:** modalidades remotas y desconexión, sólo cuando resulten aplicables.

Las referencias y URL oficiales están en los dos manifiestos del paquete de inicio, actualizados a la versión **1.1.0**. Antes de aprobar se debe comprobar vigencia, modificaciones y regulación sectorial adicional. No se usa la Resolución 666 de 2020 como protocolo permanente genérico.

## 9. Verificación técnica

- **70/70 pruebas exitosas**, incluida la suite histórica y las comprobaciones de actualización normativa.
- **63/63 perfiles generados** y **4.680 documentos** verificados: códigos únicos, estándares exactos, anexos presentes/ausentes, estado Borrador y ausencia de verificaciones premarcadas.
- Complementación probada: restaura faltantes, actualiza sólo el borrador automático permitido, protege contenido editado/aprobado y no repite otras tablas.
- DOCX y ZIP abiertos como contenedores y XML válidos en una comprobación independiente; tabla anidada y `colspan` verificados.
- Extintores SCM: **40 documentos, 0 aprobados**, con pendientes reales intactos.
- Sintaxis de 242 archivos PHP, migración v4→v5 con 51 tablas y smoke HTTP de 16 vistas autenticadas sin errores 500.

Estas pruebas verifican software y cobertura documental. No certifican que una empresa haya implementado su SG-SST.
