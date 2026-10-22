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
- Catálogo de 7, 21 y 60 estándares, planes SaaS, activación de empresas y auditoría global.

### Administración de empresa y responsable SG-SST

- Configuración de contacto y consulta de la clasificación aplicada.
- Biblioteca documental con filtros, edición saneada, versiones, aprobación, obsolescencia e impresión.
- Tablero PHVA y Plan Anual de Trabajo con responsables, fechas, presupuesto y avance.
- Hallazgos y acciones correctivas ACPA.
- Matriz IPEVR con cálculo orientativo `ND × NE × NC`, nivel de riesgo y controles.
- Programa de capacitaciones e indicadores con fórmulas y datos fuente.
- Directorio de empleados/contratistas y accesos por rol.
- Bandeja de autoreportes y respuestas de seguimiento.

### Portal Trabajador / Contratista

- Políticas aprobadas por su propia empresa.
- Próximas capacitaciones e inducción general.
- Consulta y firma de inducción con contraseña actual, fecha, IP, user-agent y hash de evidencia.
- Creación de autoreportes y consulta de las respuestas recibidas.
- Cambio de contraseña disponible también para los demás roles.

## Clasificación aplicada

| Condición | Esquema |
|---|---:|
| 1–10 trabajadores, riesgo I, II o III | 7 estándares |
| 11–50 trabajadores, riesgo I, II o III | 21 estándares |
| Más de 50 trabajadores | 60 estándares |
| Riesgo IV o V, independientemente de la dotación | 60 estándares |

El riesgo IV/V tiene prioridad sobre el número de trabajadores. La clasificación es una guía de configuración; la empresa debe revisar cualquier condición especial que le resulte aplicable.

Al finalizar el alta, una **única transacción** crea la empresa, su administrador y la configuración inicial: documentos del grupo correspondiente, políticas de SST/alcohol y drogas/convivencia, PAT, capacitaciones, IPEVR base, perfiles e inducción. Si algo falla, se revierte el conjunto. Una segunda ejecución no duplica la configuración.

## Requisitos

- PHP **8.1 o superior**.
- Extensiones PHP: `pdo_sqlite`, `sqlite3`, `dom`/`libxml`, `json` y `session`.
- `mbstring` es recomendado, aunque existen alternativas para instalaciones mínimas.
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

1. Configura un virtual host cuyo **DocumentRoot sea `public/`**, con HTTPS.
2. Copia `.env.example` a `.env` y define, como mínimo:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_DEMO=false
   APP_TIMEZONE=America/Bogota
   ADMIN_EMAIL=admin@tu-dominio.co
   ADMIN_PASSWORD=una-clave-inicial-larga-y-unica
   ADMIN_NAME=Administrador de plataforma
   # SGSST_DB_PATH=/ruta/privada/sgsst.db
   ```

3. Otorga escritura al usuario del servidor web sobre `database/` y `storage/`. No expongas esos directorios al DocumentRoot.
4. Ejecuta `php scripts/install.php` con un usuario que tenga los mismos permisos de la base. El comando mostrará el acceso inicial si no existe un SuperAdmin.
5. Inicia sesión y cambia la contraseña desde **Seguridad de la cuenta**.
6. Configura copias de seguridad de SQLite y conserva la base junto con sus archivos WAL durante el respaldo, o utiliza el mecanismo de backup de SQLite.

El archivo `.env` se carga con PHP puro. Las variables ya definidas por el sistema operativo tienen prioridad. Nunca confirmes `.env` en Git.

### Apache

`public/.htaccess` contiene las reglas de reescritura. Habilita `mod_rewrite` y permite `AllowOverride All` para el directorio público, o traslada esas reglas al virtual host.

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
- Aislamiento de documentos, autoreportes, usuarios y firmas entre tenants.
- Hash y verificación de contraseñas.

También se validaron los recorridos HTTP de los cuatro perfiles, CSRF, autorización por rol y acceso cruzado a documentos.

## Estilos

El CSS compilado ya está incluido en `public/assets/css/app.css`. Para modificarlo:

```bash
npm install
npm run build:css
# durante desarrollo: npm run watch:css
```

Las fuentes son del sistema; no hay llamadas a CDN, analítica ni servicios de terceros.

## Estructura

```text
app/
  Controllers/       Controladores por módulo y rol
  Core/              PDO, router, vistas, CSRF y entorno
  Models/            Consultas preparadas y aislamiento por tenant
  Services/          Catálogos, clasificación, instalación y generación
  Support/           Autenticación, validación, auditoría y helpers
  Views/             Layouts y vistas PHP
config/              Configuración de aplicación y SQLite
database/schema.sql  Esquema, restricciones e índices
public/
  index.php          Front controller
  router.php         Router para php -S
  assets/            CSS compilado y JavaScript propio
resources/css/       Fuente Tailwind
scripts/install.php  Instalador de producción/demo
tests/run.php        Pruebas funcionales de dominio y persistencia
```

## Seguridad y privacidad

- Consultas preparadas con PDO y `PRAGMA foreign_keys=ON` en cada conexión.
- `tenant_id` obligatorio en los registros operativos y filtros por empresa en las consultas.
- Autorización por rol en el router y validación de pertenencia antes de operar un registro.
- Contraseñas con `password_hash()` y `password_verify()`, regeneración de sesión y bloqueo temporal por intentos.
- Tokens CSRF en formularios, cookies HttpOnly/SameSite y sesiones con expiración por inactividad.
- Saneamiento del HTML documental y escape de datos en vistas.
- Cabeceras CSP, anti-framing, `nosniff` y política de referrer.
- Auditoría de acciones relevantes.

Los perfiles de salud y exámenes incluidos son orientaciones iniciales, no diagnósticos ni órdenes médicas. Evita registrar información clínica individual innecesaria. La firma de inducción es una evidencia de aceptación en la plataforma; no se presenta como firma digital avanzada ni reemplaza otras evidencias de formación exigibles.

## Referencia normativa principal

- [Resolución 0312 de 2019 — texto informativo de la Secretaría Jurídica Distrital](https://www.alcaldiabogota.gov.co/sisjur/normas/Norma1.jsp?i=82666).
- Decreto 1072 de 2015, Libro 2, Parte 2, Título 4, Capítulo 6.
- ISO 45001:2018, como referencia de mejora y pensamiento basado en riesgos; la aplicación no otorga certificación ISO.
