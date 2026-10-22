# Carpeta de plantillas del SG-SST

Deja aquí los archivos reales que debe contener un Sistema de Gestión (PDF, DOC, DOCX, XLS, XLSX, etc.).
Cuando termines, avisa en el chat: **"analiza las plantillas"**.

## Cómo organizarlas (sugerido; si ya vienen organizadas, déjalas como están)

- `01_Planear/` — política, objetivos, plan anual, evaluación inicial, responsabilidades, presupuesto…
- `02_Hacer/` — capacitación, matriz de peligros, EPP, exámenes médicos, mantenimiento, contratistas…
- `03_Verificar/` — indicadores, auditoría interna, revisión por la alta dirección…
- `04_Actuar/` — acciones correctivas/preventivas, plan de mejoramiento…
- `05_PPPRAE_Emergencias/` — plan de emergencias, análisis de vulnerabilidad, MEDEVAC, brigadas…
- `06_Formatos_Registros/` — formatos diligenciables: inspecciones, asistencia, entrega de EPP, actas…

También puedes dejarlas todas en la raíz de `plantillas/`; la clasificación se hace en el análisis.

## Qué pasará con estos archivos

1. **Inventario y verificación**: tipo, tamaño, legibilidad (que no sean escaneos dañados ni archivos con contraseña), duplicados y normas citadas.
2. **Clasificación legal**: cada archivo se cruza con el catálogo de estándares mínimos (Res. 0312/2019) y con los módulos de la plataforma (evaluación, matriz GTC 45, PPPRAE, indicadores…).
3. **Detección de campos variables**: razón social, NIT, representante legal, fechas, responsables, trabajadores, etc., para reemplazarlos por marcadores `{{empresa.razon_social}}`, `{{empresa.nit}}`, …
4. **Integración según el tipo de archivo**:
   - **DOCX/XLSX editables** → se convierten en plantillas generadoras: la plataforma los llena con los datos de la empresa y entrega el documento listo.
   - **PDF** → quedan como documentos de consulta obligatoria y/o se recrean como formato diligenciable dentro de la plataforma.
   - **Formatos de registro** → se convierten en formularios web (quedan en la base de datos, con seguimiento, vencimientos y auditoría, no solo como archivo suelto).
5. **Resultado**: catálogo documental maestro por empresa, con código, versión, norma, responsable, periodicidad y trazabilidad (hash SHA-256, quién lo generó, auditoría).

## Notas importantes

- Si un archivo tiene datos reales de una empresa o personas, la plataforma lo tratará como **plantilla estructural** (sin reutilizar esos datos).
- Solo integra archivos sobre los que tengas derecho de uso/adaptación.
- No subas archivos con contraseña ni comprimidos con clave; si vienen en ZIP/RAR, es mejor subirlos sueltos.
