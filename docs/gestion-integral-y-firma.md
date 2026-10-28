# Gestión integral SG-SST, madurez, evidencias y firma electrónica

Revisión funcional y de fuentes: **2026-10-28**. Este documento describe controles implementados en la aplicación. **No certifica que una empresa cumpla la ley ni ISO 45001, no reemplaza la revisión profesional/jurídica y no convierte borradores o registros precargados en evidencias de ejecución.**

## 1. Matriz única y autoevaluación

Ruta principal: `/autoevaluacion`.

La matriz relaciona, por ítem:

- norma y artículo;
- estándar mínimo y fase PHVA;
- cláusula ISO 45001 cuando corresponda;
- criterio y evidencia esperada;
- responsable, periodicidad y vencimiento;
- nivel de madurez, evaluación y puntaje;
- justificación, verificación, eficacia y mejora;
- enlace al módulo operativo y al centro de evidencias.

`RequirementCatalog` separa:

- **60 ítems de autoevaluación**, con **100 puntos** y distribución 25/60/5/10 por grupos de estándares;
- **16 referencias legales** adicionales;
- **22 referencias ISO 45001** de las cláusulas 4 a 10, sin peso en la autoevaluación legal.

Las referencias legales e ISO no se suman como si fueran ítems oficiales ponderados. La aplicabilidad 7/21/60 se confirma por una persona; en los regímenes reducidos no se redistribuyen pesos para simular un puntaje completo.

### Niveles y validación

| Nivel | Regla implementada |
|---|---|
| Sin iniciar | No acredita documentación ni ejecución. |
| Documentado | Exige al menos una evidencia vinculada al requisito. |
| Implementado | Exige evidencia revisada, responsable, nota y fecha de implementación. |
| Verificado | Conserva lo anterior y exige permiso `matriz.verificar` y nota de verificación. |
| Eficaz | Exige resultado medido y fecha de evaluación posterior, además de verificación autorizada. |
| No aplica | Exige justificación técnica y aprobación de una cuenta con `matriz.verificar`. |

**Cumple sólo se admite en Verificado o Eficaz.** Un conteo de archivos, una capacitación programada o un documento generado no elevan automáticamente la madurez. El cierre de la autoevaluación no registra por sí solo el resultado en SGRL ni ante la ARL: las fechas y el radicado sólo se diligencian cuando ocurrieron realmente.

## 2. Operación con evidencia y verificación humana

Rutas: `/operaciones` y `/operacion/{modulo}`.

Los módulos disponibles son:

1. responsables, competencias y recursos;
2. afiliaciones y actividades de alto riesgo;
3. comunicaciones, consulta y participación;
4. adquisiciones;
5. contratistas y proveedores;
6. gestión del cambio;
7. higiene y residuos;
8. mediciones y carcinógenos;
9. inspecciones planeadas;
10. mantenimiento;
11. EPP;
12. auditoría interna;
13. revisión por la dirección.

Cada registro conserva campos específicos en JSON validado, responsable, recursos, fechas, estado, resultado y vínculo con evidencias. Los registros nacen **Pendiente**. La ejecución y la verificación son actos diferentes; `Verificado` requiere evidencia revisada y autorización humana. La exportación refleja los datos registrados, no crea resultados.

Salud/vigilancias, capacitación, ACPA, indicadores, IPEVR, comités, eventos, emergencias y PESV conservan sus módulos propios; no se duplican como tablas genéricas cuando ya existe una operación específica.

## 3. Eventos, capacitación, ACPA, indicadores e IPEVR

### Incidentes, accidentes y enfermedades laborales

Ruta: `/eventos`.

La base `eventos_sst` y sus tablas relacionadas son independientes de `hallazgos_acpa`. Permiten registrar personas, testigos, investigación, causas, días perdidos, acciones y eficacia. Las estadísticas se alimentan de esos eventos reales. Un hallazgo ACPA no se transforma implícitamente en accidente ni viceversa.

### Capacitación y participación

Ruta: `/capacitaciones`.

- “Realizada” requiere asistencia confirmada y evidencia revisada.
- La eficacia se evalúa después, con fecha y verificador.
- La programación, el contenido o la idoneidad declarada del instructor no prueban asistencia ni aprendizaje.

### Hallazgos y acciones ACPA

Ruta: `/hallazgos`.

- El cierre exige causa raíz, eficacia **Eficaz**, fecha, verificador y evidencia revisada.
- “No eficaz” mantiene el hallazgo abierto.
- Editar o reabrir invalida la eficacia anterior para evitar cerrar con una comprobación que ya no corresponde al contenido.

### Indicadores

Ruta: `/indicadores`.

Se conservan fuente del numerador y denominador, responsable, observaciones y soporte. La verificación exige fuentes y evidencia revisada; editar reinicia la verificación. Un denominador cero no se interpreta como desempeño satisfactorio.

### IPEVR

Ruta: `/matriz-ipevr`.

La ficha incluye metodología, participantes, fecha de evaluación, próxima revisión y jerarquía de controles. El cambio a **Controlado** exige verificación humana con evidencia revisada vinculada como `peligro:{id}`. Editar el peligro lo reabre y reinicia la verificación. El cálculo ND × NE × NC orienta la priorización; no sustituye el juicio técnico ni la participación de los trabajadores.

## 4. Datos clínicos, psicosociales y convivencia

Rutas: `/salud`, `/salud/psicosocial`, `/convivencia` y soportes reservados.

Permisos independientes:

- `salud.reservada`: conceptos, restricciones/recomendaciones funcionales, seguimiento y resultados psicosociales agregados;
- `convivencia.reservada`: quejas, medidas, reuniones CCL y soportes reservados;
- `matriz.verificar`: validaciones y eficacia;
- `documentos.firmar`: firma electrónica documental.

Reglas de minimización:

- no almacenar historias clínicas completas en la biblioteca general;
- registrar sólo conceptos, restricciones o recomendaciones funcionales y seguimiento necesario;
- mantener respuestas individuales, resultados clínicos y expedientes de quejas bajo custodia y acceso restringido;
- usar estadísticas agregadas/anonimizadas en tableros y exportaciones;
- no precargar resultados de baterías psicosociales, diagnósticos, aptitudes ni eficacia.

El COPASST/Vigía tiene gestión propia en `/comites`, con miembros, reuniones y quórum confirmado. El CCL no se mezcla con el COPASST ni se le atribuye decidir si existió acoso laboral.

## 5. Firma electrónica documental

Ruta: detalle de cada documento aprobado, sección **Firmas electrónicas**.

`DocumentSignature` implementa:

1. documento dentro del tenant y estado **Aprobado**;
2. permiso `documentos.firmar` y cuenta activa;
3. reautenticación con la contraseña actual;
4. consentimiento expreso asociado al contenido;
5. una firma por cuenta y versión;
6. hash **SHA-256** del contenido;
7. payload con tenant, documento, código, versión, firmante, consentimiento y fecha UTC;
8. sello **HMAC-SHA256** con clave privada y `key_id`;
9. auditoría, IP y user-agent;
10. archivo histórico de versiones y verificación posterior del contenido, sello y vínculo.

La clave puede configurarse con `APP_SIGNATURE_KEY` —mínimo 32 caracteres— o generarse fuera de `public/` en `storage/security/electronic-signing.key` con permisos restringidos. La clave y las bases no se incluyen en Git ni en los paquetes de despliegue. En producción se debe proteger la clave, incluirla en la política de respaldo y custodia, y valorar su rotación sin invalidar el archivo histórico.

**Alcance:** es una firma electrónica reforzada con integridad verificable. **No emite certificados, no valida una cadena de confianza y no se presenta como firma digital certificada.** La aprobación y la firma son pasos distintos; ninguno certifica cumplimiento legal. La terminología de inducción y del asistente también distingue “firma electrónica” de “firma digital”.

Referencias de análisis: [Ley 527 de 1999](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=4276) y [Decreto 2364 de 2012](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=50583), sobre identidad, consentimiento, control, confiabilidad e integridad. Su aplicación concreta debe revisarse según el documento y el mecanismo probatorio requerido.

## 6. Actualización normativa del paquete de inicio

Los manifiestos `resources/starter-package/library.json` y `standards.json` están en versión **1.1.0**. El paquete contiene 21 kits y 74 piezas documentales seleccionables por régimen, actividad y peligros; todos se generan como borradores y sin ejecuciones precargadas.

### Fuentes incorporadas

- [Resolución 1843 de 2025](https://cancilleria.gov.co/normograma/compilacion/docs/resolucion_mtra_1843_2025.htm): evaluaciones médicas ocupacionales, modalidades, periodicidad justificada, adaptación y reserva/custodia. Derogó R2346/2007 y R1918/2009; esas referencias se retiraron del contenido generado.
- [Decreto 728 de 2025](https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_0728_2025.htm): promoción de salud mental y prevención de problemas/trastornos mentales y consumo de sustancias psicoactivas en el trabajo.
- [Resolución 2764 de 2022](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=127124): batería, guía y protocolos psicosociales como mínimos; no alterar instrumentos ni precargar resultados.
- [Decreto 1040 de 2026](https://normograma.mintic.gov.co/mintic/compilacion/docs/decreto_1040_2026.htm): acoso, violencia y discriminación en el ámbito laboral, con articulación de rutas y protección.
- [Circular 027 de 2026](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=192912&dt=S): autoevaluación y Plan de Mejoramiento. El plazo específico hasta **31/07/2026** estaba vencido en esta revisión; no se muestra como fecha futura ni se presume reporte realizado.
- [Resolución 3461 de 2025](https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=262916): CCL, reuniones, funciones, seguimiento e informes. El texto oficial usa **días calendario**, máximo **65 días calendario** para el procedimiento preventivo y tiempos no acumulativos. Se conservaron esos términos; no se cambiaron a días hábiles.

El artículo 3 de R3461/2025 describe “menos de 5”, “más de 5 y menos de 20” y “más de 20”. No clasifica expresamente exactamente 5 o 20; el borrador lo advierte y exige revisión jurídica/oficial en lugar de extrapolar.

### Recursos convertidos históricos

Los recursos originales convertidos se conservan para trazabilidad. Los documentos médicos y la matriz que todavía contienen artículos de R2346/R1918 quedaron marcados como **actualización normativa requerida**. La aplicación bloquea la generación de nuevos borradores desde esos documentos históricos identificados y muestra una ruta a la gestión actualizada. No se cambia el rótulo de un artículo antiguo para presentarlo como si fuera el texto de 2025.

En el formulario PESV `PESV-7721AA56D741` se actualizó únicamente el valor de la celda `APLICACION PRUEBAS !A28` para remitir a R1843/2025 y exigir verificación de idoneidad y soportes. Se conservaron hojas, celdas, estilos, combinaciones y fórmulas; el formato no acredita aptitud.

### Decisión judicial pendiente de fuente oficial

Las fuentes secundarias consultadas mencionan el radicado **11001-03-25-000-2025-00470-00 (2829-2025)** y una decisión de 01/10/2026 relacionada con el CCL. Al no contarse con el texto oficial completo, **no se incorporó una conclusión jurídica definitiva ni se alteró el procedimiento con base sólo en esas fuentes**. Debe verificarse el fallo, sus partes, alcance, vigencia y eventuales efectos antes de modificar reglas o documentos.

## 7. Evidencias y seguridad

`EvidenceLinker` admite vínculos reales con `capacitacion:{id}`, `hallazgo:{id}`, `indicador:{id}` y `peligro:{id}`, además de los módulos anteriores. La resolución comprueba tenant y alcance de módulo. Los archivos permanecen fuera de `public/`, con descarga autorizada, hash y revisión humana.

Los permisos se evalúan en servidor. Las rutas de trabajador, empresa y SuperAdmin permanecen separadas. Las estadísticas y exportaciones sensibles no deben exponer expedientes individuales.

## 8. Verificación realizada

- **70 pruebas funcionales:** 70 exitosas, 0 fallidas.
- Sintaxis validada en **242 archivos PHP**.
- Migración v4→v5: datos preservados, **51 tablas**, columnas y claves foráneas válidas.
- Auditoría documental: **21 actividades × 3 regímenes**, 63 perfiles y **4.680 borradores** contextualizados.
- Extintores SCM: **40 borradores**, 28 seguimientos pendientes, 7 validaciones; sin aprobaciones ni ejecuciones inventadas.
- Smoke HTTP en PHP 8.1 WASM/MEMFS: login, **16 vistas autenticadas**, token CSRF y bloqueo histórico, sin errores 500. El smoke detectó y permitió corregir el helper ausente `form_errors()`.
- Los ZIP de producción se validan por CRC y exclusiones; no incluyen bases, sesiones, claves ni credenciales.

Estas pruebas verifican comportamiento de software y cobertura de plantillas. No acreditan implementación SG-SST, resultados de salud, ejecución de programas, reportes oficiales ni certificación ISO.
