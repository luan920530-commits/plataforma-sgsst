# Normia SST

Plataforma SaaS multi-tenant para organizar el Sistema de Gestión de Seguridad y Salud en el Trabajo (SG-SST) de empresas colombianas. Está construida con **PHP 8.1+ orientado a objetos, MVC propio y SQLite 3 mediante PDO**, sin frameworks de backend.

La interfaz separa la administración global del SaaS, la operación de cada empresa y el portal de trabajadores/contratistas. El diseño utiliza tarjetas, navegación responsiva, estados visuales y una estética inspirada en iOS; Tailwind se compila localmente y no se depende de un CDN en ejecución.

> **Alcance jurídico:** la clasificación y los catálogos toman como referencia la Resolución 0312 de 2019 y el ciclo PHVA del Decreto 1072 de 2015, con una organización compatible con ISO 45001. Los documentos generados son **borradores técnicos editables**, no una certificación automática de cumplimiento. La empresa debe validarlos, adaptarlos a sus procesos, aprobarlos y conservar sus evidencias con profesionales competentes.

## Funcionalidad

### Consola SuperAdmin

- Tablero global de empresas, usuarios, documentos y actividad.
- Alta de empresas mediante un asistente de tres pasos.
- Clasificación por número de trabajadores, riesgo ARL, actividad económica o método combinado.
- Catálogo precargado de 20 actividades CIIU con peligros base, perfil sociodemográfico y evaluaciones médicas sugeridas.
- Catálogo de 7, 21 y 60 estándares, cinco planes SaaS —tres SG-SST integrales, PESVial y Emergencias y Vigilancia—, activación de empresas y auditoría global.
- Condición administrativa **Pagado** o **Sugerido** por empresa y alcance de módulo validado en el servidor, no solamente mediante enlaces ocultos.

### Administración de empresa y responsable SG-SST

- Configuración de contacto y consulta de la clasificación aplicada.
- Biblioteca documental con filtros, **editor visual** para títulos, párrafos, listas y tablas, saneamiento en servidor, historial de versiones, aprobación, obsolescencia e impresión.
- Biblioteca de **procedimientos y formatos** por actividad CIIU, AST, trabajo en alturas y espacios confinados. Los kits se generan como borradores sin duplicados y se diferencian de los permisos de cada tarea.
- **Biblioteca SG-SST por estándares:** 142 plantillas únicas recuperadas del ZIP suministrado, con 88 documentos editables, 54 formularios por empresa, 60 estándares, módulos relacionados y descarga privada opcional de los originales DOC/DOCX/XLS/XLSX/PPTX/PDF.
- **PESV para transporte:** módulo propio con 24 pasos y 97 recursos únicos recuperados del paquete suministrado, índice editable generado automáticamente para actividades de transporte, formularios aislados por tenant, trazabilidad, versiones y aprobación humana.
- Tablero PHVA y Plan Anual de Trabajo con consulta individual, creación, edición completa, avance, fecha real de ejecución y eliminación auditada.
- Hallazgos y acciones correctivas ACPA.
- CRUD de matriz IPEVR con cálculo orientativo `ND × NE × NC`, nivel de riesgo y controles.
- CRUD de capacitaciones, cambio de estado e indicadores con fórmulas y datos fuente.
- Permisos de trabajo con borrador, solicitud, aprobación, activación, cierre/cancelación, revisiones e impresión; campos específicos para alturas y espacios confinados.
- Planes de emergencia por centro de trabajo con amenazas, contactos, brigada, recursos, evacuación, rescate, ayuda mutua, simulacros y documento versionado.
- **Centro transversal de evidencias físicas:** cargas privadas PDF/JPG/PNG de listas de asistencia, reportes, formularios, inspecciones, simulacros, fotografías y otros soportes; vínculos con su módulo de origen, filtros, descarga íntegra y revisión humana con fecha, responsable y observaciones.
- **Microapp visual de planos de emergencia:** lienzo SVG con cuadrícula y ajuste, muros, puertas, rutas, salidas, extintores, botiquines, alarmas, puntos de encuentro y textos; historial inmutable, control de versiones concurrentes y exportación PNG archivada como evidencia pendiente de revisión.
- Programas de promoción de la salud, prevención de accidentes/enfermedad laboral y vigilancia, con diagnóstico, objetivos, metas, presupuesto, actividades y evidencias.
- Panel de documentación pendiente con referencias normativas, seguimiento humano y evidencias privadas PDF/JPG/PNG, incluidos planos y rutas de evacuación.
- **Centro de gestión para cada requisito**, con acciones, usuarios responsables, recursos, fechas, evidencia de cierre y enlace al módulo operativo correspondiente; no redirige todo a la biblioteca.
- Directorio de empleados/contratistas y accesos por rol.
- Bandeja y detalle de autoreportes con respuestas de seguimiento.
- **Accesos HSE compartibles por URL o QR local**, con token aleatorio almacenado como hash, vencimiento, límite de usos, revocación y reportes identificados o anónimos sin crear una cuenta. El QR visible se descarga como PNG —GIF si no hay canvas— y puede renovarse sin reiniciar la vigencia ni los usos.

### Portal Trabajador / Contratista

- Políticas aprobadas por su propia empresa.
- Próximas capacitaciones e inducción general.
- Consulta y firma de inducción con contraseña actual, fecha, IP, user-agent y hash de evidencia.
- Creación de autoreportes y consulta de las respuestas recibidas.
- Cambio de contraseña disponible también para los demás roles.
- El portal general de trabajadores pertenece al SG-SST integral. Los planes PESVial y Emergencias y Vigilancia no lo cargan ni permiten crear ese rol; una cuenta heredada solo ve el aviso de alcance y la seguridad de su cuenta.

### Biblioteca SG-SST importada

La biblioteca convertida es autocontenida: **el ZIP fuente no se incluye en el repositorio** y no se necesita para usar los documentos ni los formularios. Su análisis produjo 153 referencias útiles, consolidadas en 142 plantillas por 11 duplicados exactos; otros 156 archivos eran metadatos o temporales de macOS/Office y no se trataron como documentos.

Desde **Biblioteca SG-SST** puedes:

1. Buscar por título, módulo o estándar y revisar las notas de conversión.
2. Generar un documento como borrador independiente para la empresa, editarlo visualmente y someterlo a aprobación humana.
3. Diligenciar formularios Excel con celdas combinadas, hojas y validaciones disponibles. Cada respuesta se guarda por tenant y puede actualizarse o eliminarse.
4. Recalcular referencias, rangos, operaciones básicas y las funciones `IF`, `COUNT`, `SUM`, `IFERROR`, `SUMIF`, `MAX` y `AVERAGE`. Una fórmula no compatible conserva el resultado original y queda señalada.
5. Descargar un original cuando exista una copia privada verificada. Para habilitar nuevas extracciones, conserva el ZIP fuera del repositorio y de `public/`, y define `SGSST_LIBRARY_ZIP=/ruta/privada/archivo.zip` en `.env`. PHP extrae únicamente la entrada solicitada, verifica tamaño y SHA-256 y almacena la copia fuera de `public/`. Sin esa configuración, las versiones editables siguen funcionando y la aplicación informa que el original no está disponible.

La biblioteca contiene 94 hojas, 15.918 celdas con contenido y 2.337 fórmulas. Las imágenes, gráficos o características no reproducidas se identifican en las notas de conversión; para consultar esos elementos se necesita la copia privada del original. El detalle está en `docs/biblioteca-sgsst.md`; los recursos convertidos están en `resources/sgsst-library/` y no requieren Office ni el ZIP en el servidor.

### Biblioteca PESV importada

El módulo **PESV · Seguridad vial** (`/pesv`) organiza el contenido de `pesvial.7z` en 24 pasos. La importación consolidó 114 ubicaciones en **97 recursos únicos**: 96 formularios y un procedimiento DOCX, con 17 duplicados exactos vinculados como alias. Conserva 14.143 celdas con valor y 5.820 fórmulas; el Plan de Trabajo Anual mantiene contenido real hasta la columna IW (257).

- Las empresas cuya actividad o sector corresponda a transporte reciben el kit PESV durante la generación inicial. También puede generarse manualmente desde **Procedimientos y formatos** o desde la portada PESV.
- El índice se crea como **borrador independiente por empresa**, con editor visual, historial, impresión y aprobación humana. Los formularios se diligencian y recalculan con el mismo motor de la biblioteca SG-SST.
- La auditoría no encontró partes de macros, ActiveX u OLE y ninguna se ejecutó. Los 113 Excel contenían vínculos externos: sus relaciones, nombres definidos externos, cachés y rutas no se copiaron. Por esta razón, el módulo PESV no ofrece descarga de los Excel originales.
- Solo se excluyeron 69.368 celdas vacías de formato residual fuera del área útil, sin recortar valores ni fórmulas. Las imágenes, gráficos y demás notas de conversión permanecen visibles para revisión.
- El DOCX se convierte de forma recursiva, conservando párrafos, énfasis de runs, tablas y tablas anidadas. La conversión no acredita cumplimiento ni sustituye la verificación de evidencias.

El detalle técnico y la reconstrucción están en [`docs/biblioteca-pesv.md`](docs/biblioteca-pesv.md). Los recursos de ejecución están en `resources/pesv-library/`; el 7z solo se necesita para volver a importar y no se incluye en el ZIP de hosting.

### Planes SaaS y alcance por módulo

| Plan | Módulo que carga la empresa |
|---|---|
| Esencial, Profesional o Empresarial | SG-SST integral, incluidos PESV, emergencias y portal de trabajadores. |
| PESVial | Kit PESV de 24 pasos, 97 recursos y documentos PESV. No genera PAT, IPEVR, inducción ni documentos SG-SST generales. |
| Emergencias y Vigilancia | Planes de emergencia, simulacros, programas de prevención/vigilancia, documentos y evidencias relacionados. No carga PESV ni el SG-SST general. |

- Al crear una empresa se genera automáticamente **solo el kit del plan**: índice PESV en PESVial; un plan de emergencias y un programa de vigilancia, ambos en borrador y con campos pendientes, en Emergencias y Vigilancia.
- **Pagado** y **Sugerido** son condiciones administrativas seleccionadas al asignar el plan; no representan una pasarela de pago y tienen exactamente el mismo alcance funcional.
- El cambio de plan se aplica en la siguiente solicitud, incluso con la sesión abierta. Los registros históricos no se borran, pero documentos, rutas, navegación, paneles y evidencias quedan filtrados por el nuevo módulo.
- Equipo, configuración de empresa y seguridad de cuenta son funciones transversales. Las rutas compartidas comprueban además que cada documento, requisito, soporte o acción pertenezca al módulo habilitado.
- Si una empresa sale del SG-SST integral, los enlaces públicos de autoreportes anteriores dejan de aceptar consultas de formulario y envíos; el consumo del token vuelve a comprobar el plan en la base de datos.

### Evidencias físicas y planos de emergencia

1. Entra como AdminEmpresa o ResponsableSST a **Evidencias**. También puedes usar **Adjuntar evidencia** desde un documento, reporte, capacitación, formulario, plan, simulacro o plano: ese acceso ya lleva el vínculo del registro.
2. Selecciona la categoría, fecha, descripción y un archivo PDF, JPG o PNG de hasta 10 MB. El archivo se guarda fuera de `public/`, con nombre aleatorio y SHA-256; la descarga vuelve a comprobar empresa y módulo.
3. Usa **Registrar revisión** para dejar estado, responsable, fecha y observaciones. `Revisado` significa que una persona registró su contraste, no que la plataforma certifique cumplimiento. Los soportes revisados se conservan y no pueden borrarse desde la interfaz.
4. En **Emergencias → abrir un plan → Planos de emergencia**, crea un plano y dibuja sobre el lienzo SVG. Guarda antes de exportar: cada guardado crea una versión inmutable y una pestaña obsoleta no puede sobrescribir otra más reciente.
5. **Exportar y archivar PNG** usa únicamente la última versión guardada y crea una evidencia pendiente de revisión. El editor es una herramienta de apoyo, no reemplaza un CAD profesional, un levantamiento en sitio ni la validación técnica/legal del plano.

### Compartir el formulario de autoreportes

1. Entra como AdminEmpresa o ResponsableSST a **Autoreportes → Generar acceso / QR**.
2. Define nombre, vencimiento y límite de usos; `0` significa sin límite de cantidad.
3. Copia la URL o descarga el QR inmediatamente. Por seguridad, el token completo solo aparece en esta pantalla. Si se pierde, usa **Renovar enlace y QR**: el token anterior se invalida y se conservan vencimiento y usos; para cambiar esos parámetros crea un acceso nuevo.
4. El navegador genera el QR localmente y descarga PNG; si canvas no está disponible, utiliza el GIF del generador local. Sin JavaScript permanece disponible la URL de texto y se muestra una explicación.
5. Los reportes externos llegan a la misma bandeja, identificados por origen. El formulario no permite consultar casos y los reportes anónimos no guardan nombre ni contacto.

## Clasificación aplicada

| Condición | Esquema |
|---|---:|
| 1–10 trabajadores, riesgo I, II o III | 7 estándares |
| 11–50 trabajadores, riesgo I, II o III | 21 estándares |
| Más de 50 trabajadores | 60 estándares |
| Riesgo IV o V, independientemente de la dotación | 60 estándares |

El riesgo IV/V tiene prioridad sobre el número de trabajadores. La clasificación es una guía de configuración; la empresa debe revisar cualquier condición especial que le resulte aplicable.

Al finalizar el alta, una **única transacción** crea la empresa, su administrador y la configuración inicial: documentos del grupo correspondiente, políticas, PAT, capacitaciones, IPEVR, perfiles, inducción y el kit base de procedimiento/formato de la actividad y AST. Si los peligros base identifican alturas o espacios confinados, también se sugiere y genera ese kit; para actividades de transporte se agrega el índice PESV como borrador. Si algo falla, se revierte el conjunto. Una segunda ejecución no duplica la configuración. Las empresas ya registradas pueden generar los kits pendientes desde **Procedimientos y formatos**, incluso los de alto riesgo cuando determinen que aplican.

## Requisitos

- PHP **8.1 o superior**.
- Extensiones PHP: `pdo_sqlite`, `sqlite3`, `dom`/`libxml`, `json` y `session`.
- `mbstring` es recomendado, aunque existen alternativas para instalaciones mínimas.
- `zip` es opcional y solo se necesita para extraer originales cuando se configura `SGSST_LIBRARY_ZIP`; las versiones editables y las copias privadas ya extraídas no lo requieren.
- Un servidor que apunte al directorio `public/`.
- Node.js 18+ solamente si se desea recompilar Tailwind; no es necesario para ejecutar la aplicación.

Comprueba las extensiones con:

```bash
php -v
php -m | grep -Ei 'pdo|sqlite|dom|libxml|session'
```

## Instalación local con datos demo

```bash
git clone <url-del-repositorio> plataforma-sgsst
cd plataforma-sgsst
cp .env.example .env
```

Para el entorno local, ajusta `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_DEMO=true
APP_TIMEZONE=America/Bogota
```

Después instala y arranca:

```bash
php scripts/install.php --demo
php -S 0.0.0.0:8000 -t public public/router.php
```

Abre `http://localhost:8000`. La base se crea en `database/sgsst.db`; este archivo está excluido de Git.

### Accesos demo

Contraseña para todas las cuentas: **`Normia2026!`**

| Perfil | Correo |
|---|---|
| SuperAdmin | `superadmin@normia.co` |
| AdminEmpresa | `admin@andina.co` |
| ResponsableSST | `sst@andina.co` |
| Trabajador | `trabajador@andina.co` |

Las tarjetas de acceso rápido solo aparecen con `APP_DEMO=true`. No uses estas credenciales ni habilites debug/demo en producción.

## Instalación de producción

### ZIP para GoogieHost / DirectAdmin

La forma recomendada es el paquete autocontenido `normia-sst-hosting.zip`:

1. Selecciona PHP 8.1+ y habilita SQLite/DOM en DirectAdmin.
2. Sube y extrae el ZIP en `/domains/normiasst.whf.bz/`, un nivel encima de `public_html`.
3. Abre el dominio y crea el SuperAdmin en el instalador web.

El paquete incluye `public_html`, la aplicación privada `normia-sst`, configuración de producción y assets compilados. Crea SQLite automáticamente, detecta la URL y no instala datos demo. No requiere Terminal, Cron, Composer, Node.js, MySQL ni edición manual de `.env`.

Sigue la guía [`docs/despliegue-googiehost.md`](docs/despliegue-googiehost.md) para la ubicación exacta, actualizaciones y diagnóstico. Para generar una copia nueva del paquete desde el repositorio:

```bash
python3 scripts/build_hosting_zip.py
```

El archivo se crea en `dist/normia-sst-hosting.zip` y queda excluido de Git.

### Instalación manual alternativa

Para un virtual host propio cuyo DocumentRoot sea `public/`, copia `.env.example` a `.env`, configura `APP_ENV=production`, `APP_DEBUG=false`, `APP_DEMO=false` y la URL pública. Otorga escritura a `database/` y `storage/` sin exponerlos al DocumentRoot y ejecuta `php scripts/install.php`. El comando mostrará el acceso inicial si no existe un SuperAdmin.

`APP_URL` puede ser la raíz del dominio o terminar en `/public` cuando esa sea realmente la URL pública. Si se omite, la aplicación deriva el esquema y host de la solicitud. El archivo `.env` se carga con PHP puro y nunca debe confirmarse en Git. Configura copias de seguridad de SQLite y sus archivos WAL.

### Apache

`public/.htaccess` contiene las reglas de reescritura. Habilita `mod_rewrite` y permite `AllowOverride All` para el directorio público, o traslada esas reglas al virtual host.

### Actualizar una instalación existente

Haz primero una copia de seguridad de la base y de `storage/uploads/`. Después, con la misma versión de PHP y los permisos adecuados:

```bash
php scripts/migrate.php
```

La migración ejecuta el esquema con `CREATE TABLE/INDEX IF NOT EXISTS`, agrega de forma idempotente las columnas de autoreportes, evidencias y alcance de módulos, crea respuestas de biblioteca, planos y sus versiones, completa los metadatos de soportes heredados y termina con `PRAGMA foreign_key_check`. La versión actual del esquema es **3**. Conserva los registros y archivos existentes, no borra la base ni crea datos demo. El arranque web la aplica automáticamente cuando `PRAGMA user_version` es anterior a la versión del código; el comando anterior queda como alternativa administrativa. Los procedimientos no se imponen masivamente durante la migración: cada empresa decide qué kits generar desde su módulo.

### XAMPP en macOS

Con el proyecto dentro de `/Applications/XAMPP/xamppfiles/htdocs/plataforma-sgsst`:

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/plataforma-sgsst
cp .env.example .env
/Applications/XAMPP/xamppfiles/bin/php -v
/Applications/XAMPP/xamppfiles/bin/php -m | grep -Ei 'pdo|sqlite|dom|libxml|session'
/Applications/XAMPP/xamppfiles/bin/php scripts/install.php --demo

# El propietario conserva acceso CLI y el grupo de Apache puede escribir.
sudo chown -R "$(whoami)":daemon database storage
sudo chmod -R ug+rwX database storage
```

Abre `http://localhost/plataforma-sgsst/public/`. No uses `phpMyAdmin`: la aplicación emplea SQLite, no MySQL. Para una ruta distinta, ajusta `SGSST_DB_PATH` en `.env` a una ubicación privada y escribible.

Si una página presenta HTTP 500:

1. Confirma que XAMPP ejecuta PHP 8.1+ y que `pdo_sqlite` está habilitado. Si decides habilitar la extracción de originales, activa también `extension=zip`, configura `SGSST_LIBRARY_ZIP` con una ruta privada y reinicia Apache. Ninguno de estos dos ajustes es necesario para las versiones editables.
2. Ejecuta `/Applications/XAMPP/xamppfiles/bin/php scripts/migrate.php` desde la raíz del proyecto.
3. Revisa permisos de lectura/escritura sobre la base, su directorio y los archivos `-wal`/`-shm`; SQLite necesita escribir también junto al archivo `.db`.
4. Consulta `storage/logs/app.log` cuando `APP_DEBUG=false`. En desarrollo local puedes usar temporalmente `APP_DEBUG=true` para ver la traza en la página; no lo dejes habilitado en producción.
5. Revisa también los logs de Apache/PHP de XAMPP. Un error concreto de traza es más útil que reinstalar o cambiar permisos indiscriminadamente.
6. Para cargas de hasta 10 MB, verifica que `upload_max_filesize` y `post_max_size` no sean inferiores; asigna a `post_max_size` un margen para los demás campos del formulario.

`storage/.htaccess` y `database/.htaccess` niegan acceso directo bajo Apache, pero la configuración recomendada sigue siendo exponer únicamente `public/`.

### Nginx

La regla esencial es:

```nginx
root /ruta/plataforma-sgsst/public;
index index.php;
location / {
    try_files $uri /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.1-fpm.sock;
}
```

Ajusta el socket a tu versión de PHP y bloquea el acceso directo a archivos sensibles según la configuración de tu servidor.

## Pruebas

```bash
php tests/run.php
```

La suite no requiere PHPUnit. Crea `database/testing.db`, instala datos aislados y elimina esa base al terminar; no utiliza la base productiva. Cubre:

- Esquema, índices y claves foráneas.
- Conteos exactos de 7/21/60 estándares y catálogo CIIU.
- Clasificación, riesgo e indicadores.
- Saneamiento HTML y rollback de transacciones.
- Generación documental y prevención de duplicados.
- Aislamiento de documentos, autoreportes, usuarios, IPEVR, capacitaciones, permisos, emergencias, programas y soportes entre tenants.
- Centro de evidencias por módulo, revisión humana, borrado condicional y conservación de soportes revisados.
- Editor de planos con nueve herramientas validadas, historial inmutable, aislamiento por empresa/plan y rechazo de guardados obsoletos.
- CRUD, historial de versiones, aprobación/cierre de permisos y simulacros.
- Generación y escape de documentos operativos, actividades preventivas y evidencias.
- Procedimientos/formatos CIIU, alturas y espacios confinados sin duplicados.
- Biblioteca PESV: 97 recursos, 24 pasos, PAT hasta IW, generación automática en transporte, versiones y respuestas aisladas.
- Las 5.820 fórmulas PESV procesadas sin referencias externas en los recursos editables.
- Accesos externos con token hash, anonimato, consumo, revocación y aislamiento; renovación QR sin reiniciar usos y cierre de tokens al cambiar de módulo.
- Planes PESVial y Emergencias y Vigilancia en condiciones Pagado/Sugerido, generación exclusiva y filtrado documental al cambiar de plan.
- Acciones de gestión con responsables, recursos y evidencia.
- Biblioteca importada: 142 recursos, deduplicación, cobertura de 60 estándares, generación por tenant y respuestas aisladas.
- Las 2.337 fórmulas Excel, compatibilidad numérica, fallback explícito y extracción opcional desde un ZIP externo con SHA-256 y caché privada.
- Verificación documental exclusivamente explícita y protección de rutas de archivos.
- Hash y verificación de contraseñas.

La suite actual contiene **49 pruebas**. También se validan recorridos HTTP de los perfiles, CSRF, autorización por rol, formularios, impresión y cargas/descargas con verificación SHA-256.

## Estilos

El CSS compilado ya está incluido en `public/assets/css/app.css`. Para modificarlo:

```bash
npm install
npm run build        # copia el generador QR local y compila Tailwind
# o solamente CSS: npm run build:css
# durante desarrollo: npm run watch:css
```

Las fuentes son del sistema; no hay llamadas a CDN, analítica ni servicios de terceros. El QR se genera en el navegador con `qrcode-generator` (MIT). La librería y su licencia quedan versionadas en `public/assets/js/vendor/` e incluidas en el ZIP; la URL no se envía a una API externa.

## Estructura

```text
app/
  Controllers/       Controladores por módulo y rol
  Core/              PDO, router, vistas, CSRF y entorno
  Models/            Consultas preparadas y aislamiento por tenant
  Services/          Catálogos, clasificación, instalación, generación y alcance por plan
  Support/           Autenticación, validación, auditoría y helpers
  Views/             Layouts y vistas PHP
config/              Configuración de aplicación y SQLite
database/schema.sql  Esquema, restricciones e índices
public/
  index.php          Front controller
  router.php         Router para php -S
  assets/            CSS compilado y JavaScript propio
resources/css/       Fuente Tailwind
resources/sgsst-library/ Manifiesto, documentos y formularios SG-SST convertidos
resources/pesv-library/  Manifiesto y 97 recursos PESV convertidos
docs/biblioteca-sgsst.md Inventario, comparación y notas de importación
scripts/install.php  Instalador de producción/demo
scripts/migrate.php  Migración no destructiva de instalaciones existentes
storage/uploads/     Evidencias privadas, fuera del DocumentRoot público
tests/run.php        Pruebas funcionales de dominio y persistencia
```

## Seguridad y privacidad

- Consultas preparadas con PDO y `PRAGMA foreign_keys=ON` en cada conexión.
- `tenant_id` obligatorio en los registros operativos y filtros por empresa en las consultas.
- Autorización por rol y módulo de plan en el router, validación de pertenencia antes de operar un registro y comprobación de alcance en documentos, requisitos, acciones y evidencias compartidas.
- Contraseñas con `password_hash()` y `password_verify()`, regeneración de sesión y bloqueo temporal por intentos.
- Tokens CSRF en formularios, incluido el formulario público, cookies HttpOnly/SameSite y sesiones con expiración por inactividad.
- Enlaces externos de 256 bits, almacenamiento del hash SHA-256 y no del token, consumo atómico del límite de usos, vencimiento, revocación, controles de frecuencia y campo trampa. El acceso público solo crea reportes; nunca lista información.
- Saneamiento del HTML documental y escape de datos en vistas.
- Cabeceras CSP, anti-framing, `nosniff` y política de referrer.
- Evidencias fuera de `public/`, nombres aleatorios, validación de firma binaria PDF/JPG/PNG, límite de 10 MB, hash SHA-256 y descarga autorizada por tenant; no se acepta la extensión ni el MIME del navegador como prueba del formato.
- Originales de la biblioteca fuera del DocumentRoot, extracción selectiva por índice ZIP, verificación de tamaño/SHA-256, respuestas JSON validadas por coordenada y sin ejecución de macros o fórmulas arbitrarias.
- Registros de error en `storage/logs/app.log` cuando debug está desactivado y el directorio es escribible.
- Auditoría de acciones relevantes.

Los perfiles de salud y exámenes incluidos son orientaciones iniciales, no diagnósticos ni órdenes médicas. Evita registrar información clínica individual innecesaria. La firma de inducción es una evidencia de aceptación en la plataforma; no se presenta como firma digital avanzada ni reemplaza otras evidencias de formación exigibles.

## Referencia normativa principal

- [Decreto 1072 de 2015 — Libro 2, Parte 2, Título 4, Capítulo 6](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=62506), especialmente capacitación, documentación, IPEVR, controles y emergencias.
- [Resolución 0312 de 2019 — texto informativo de la Secretaría Jurídica Distrital](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=82666).
- [Resolución 4272 de 2021 — trabajo en alturas](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=120880), cuando resulte aplicable.
- [Resolución 491 de 2020 — espacios confinados](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=90906), cuando resulte aplicable.
- [ISO 45001 explicada por ISO](https://www.iso.org/insights/iso-45001-explained), como referencia voluntaria de mejora y pensamiento basado en riesgos. La aplicación no evalúa conformidad certificable ni otorga certificación ISO; esta corresponde a organismos independientes.
