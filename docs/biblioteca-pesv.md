# Biblioteca PESV · importación y uso

## Alcance

El paquete `pesvial.7z` se analizó como insumo documental, sin ejecutar macros, código OOXML, vínculos ni aplicaciones de Office. El resultado es una biblioteca local para el módulo **PESV · Seguridad vial** de la aplicación.

La conversión produce borradores y formularios de trabajo. No determina por sí sola la aplicabilidad legal, no verifica evidencias y no equivale a aprobación, certificación o cumplimiento. Cada empresa debe contrastar el contenido con su operación, flota, desplazamientos, contratistas y requisitos vigentes.

## Inventario de la fuente actual

| Dato | Resultado |
|---|---:|
| Archivo | `pesvial.7z` |
| SHA-256 | `6156304dad8bb578f3c6571ebce36047fd6961d118a8fb494f146d9cc5a93fdf` |
| Tamaño | 5.417.420 bytes |
| Ubicaciones útiles | 114 |
| Excel / Word | 113 XLSX / 1 DOCX |
| Pasos identificados | 24 |
| Recursos únicos | 97 |
| Duplicados exactos consolidados | 17 |
| Formularios / documentos en la salida | 96 / 1 |

La deduplicación usa SHA-256 del archivo completo. Las 114 ubicaciones se conservan como `sources` en el manifiesto, de modo que un recurso repetido sigue relacionado con todos los pasos donde aparecía.

## Seguridad y tratamiento de vínculos

| Comprobación | Resultado |
|---|---:|
| Ejecución de macros o código | Ninguna |
| Partes de macros, ActiveX u OLE detectadas | 0 |
| Excel de origen con vínculos externos | 113 |
| Partes `externalLinks` excluidas | 15.762 |
| Objetivos externos únicos identificados y no copiados | 84 |
| Referencias externas en nombres definidos excluidas | 65.181 |
| Fórmulas externas o de riesgo residual en los formularios convertidos | 0 |

Las 65.181 referencias de nombres definidos no se contabilizan como fórmulas de hoja. El escáner separa `xl/workbook.xml` de las hojas para no inflar ese indicador.

Los recursos de ejecución son JSON y HTML locales. No contienen las relaciones externas, cachés ni rutas heredadas. El módulo PESV no ofrece descarga de los Excel originales; el 7z tampoco se agrega al paquete de hosting.

## Conservación del contenido

- **14.143 celdas con valor** y **5.820 fórmulas locales** preservadas.
- **192 hojas** recuperadas en los formularios.
- El `PLAN DE TRABAJO ANUAL.xlsx` conserva contenido real hasta **IW**, columna 257, incluida `IW52`. No se aplica un límite arbitrario de 200 columnas.
- Las columnas renderizadas se limitan al área útil comprobada, no a un número fijo. En el PAT se muestran 35 columnas distribuidas hasta IW.
- Se excluyeron **69.368 celdas vacías de formato residual** fuera del área útil. La matriz de control de documentos que llegaba a XFD solo por estilo vacío quedó en 9 columnas útiles, sin eliminar valores ni fórmulas.
- Las fórmulas compatibles se recalculan al guardar. Las no compatibles conservan el resultado original y se señalan para revisión; no se sustituyen silenciosamente por cero.
- Imágenes, gráficos y extensiones de Office no reproducidas generan advertencias. La salida actual registra 4 imágenes y 72 gráficos en las estadísticas de origen.

El único Word es `PROCEDIMIENTO DE REPORTE, INVESTIGACION Y SEGUIMIENTO.docx`, en el paso 13. Su conversión recorre bloques recursivamente: aplica el formato de párrafos y runs dentro de celdas y procesa tablas anidadas. La salida actual contiene 184 párrafos y 5 tablas; las 4 imágenes permanecen anunciadas como pendientes de contraste con la fuente.

## Funcionamiento en la aplicación

1. **Portada `/pesv`:** métricas, seguridad de la conversión, 24 pasos, búsqueda y filtros por tipo/paso.
2. **Aplicabilidad:** sector o actividad de transporte, incluido CIIU 4923, marca el kit como sugerido. El módulo sigue disponible para revisión en otras empresas sin afirmar que aplique automáticamente.
3. **Generación inicial:** el asistente agrega `PESV-INDICE` para transporte dentro de la transacción existente. Las empresas ya creadas pueden generarlo desde **Procedimientos y formatos** o desde `/pesv`.
4. **Documento editable:** el índice relaciona los 24 pasos y las plantillas, se guarda por tenant como borrador y usa editor visual, versiones, impresión y aprobación humana.
5. **Formularios:** cada respuesta tiene empresa, plantilla, título, datos, estado, autor y fechas. Las consultas y mutaciones incluyen `tenant_id`.
6. **Trazabilidad:** cada recurso muestra código, paso, archivo relativo, SHA-256, alias y advertencias. Los enlaces operativos llevan al módulo correspondiente o al paso PESV específico, no genéricamente al repositorio.
7. **Seguridad web:** rutas limitadas a AdminEmpresa y ResponsableSST, CSRF en mutaciones y saneamiento del HTML documental.

## Archivos

- `scripts/import_pesv.py`: extracción validada, auditoría OOXML, deduplicación y conversión reproducible.
- `resources/pesv-library/manifest.json`: inventario, alias, seguridad, métricas y rutas de recursos.
- `resources/pesv-library/forms/`: 96 formularios JSON.
- `resources/pesv-library/content/`: procedimiento HTML editable.
- `app/Services/PesvLibrary.php`: acceso de solo lectura a los recursos y construcción del índice.
- `app/Views/tenant/pesv/`: portada del módulo; las vistas de edición se comparten con la biblioteca SG-SST mediante una ruta base explícita.

## Reconstrucción

Python solo se necesita para regenerar los recursos, no para ejecutar la aplicación:

```bash
python3 -m pip install py7zr openpyxl python-docx
python3 scripts/import_pesv.py
```

El importador exige el inventario esperado de 114 archivos, valida las rutas del 7z y usa una extracción temporal. Para una reconstrucción limpia puede eliminarse primero `resources/pesv-library/` en una copia de trabajo, nunca la fuente.

Validación funcional y empaquetado:

```bash
php tests/run.php
python3 scripts/build_hosting_zip.py
```

El ZIP de hosting incluye `resources/pesv-library/`, pero excluye bases de datos, credenciales, dependencias y `pesvial.7z`.
