# Normia SST en GoogieHost: subir un ZIP y arrancar

Dominio: `normiasst.whf.bz`.

El paquete `normia-sst-hosting.zip` incluye el cargador público, la aplicación privada, el entorno de producción, las 142 plantillas SG-SST, los 97 recursos PESV y un instalador web. **No necesitas Terminal, Cron, Composer, Node.js, MySQL, phpMyAdmin ni crear manualmente `.env`.**

## Instalación en cuatro pasos

### 1. Selecciona PHP

En **DirectAdmin → Select PHP Version** o equivalente:

- Selecciona **PHP 8.1 o superior**.
- Habilita `pdo_sqlite`, `sqlite3`, `dom`, `libxml`, `json`, `session` y `fileinfo`.

El cargador comprueba estos requisitos y muestra el ajuste faltante en lugar de un error 500 genérico.

### 2. Sube el ZIP en la ubicación correcta

En el Administrador de archivos entra en:

```text
/domains/normiasst.whf.bz/
```

Esta carpeta contiene `public_html`. **Sube el ZIP aquí, un nivel encima de `public_html`; no lo subas dentro de `public_html`.**

### 3. Extrae el ZIP

Extrae `normia-sst-hosting.zip` en esa misma carpeta. El resultado debe ser:

```text
/domains/normiasst.whf.bz/
├── public_html/
│   ├── index.php
│   ├── .htaccess
│   └── assets/
└── normia-sst/
    ├── app/
    ├── config/
    ├── database/
    ├── public/index.php
    ├── resources/
    ├── storage/
    └── .env
```

El `.env` incluido solo contiene configuración no secreta de producción. La URL se detecta del dominio y la base se creará automáticamente en `normia-sst/database/sgsst.db`, fuera de la carpeta pública.

Activa la visualización de archivos ocultos si no ves `.htaccess` o `.env`.

### 4. Abre el sitio y crea el acceso

Abre:

```text
https://normiasst.whf.bz/
```

Abre el sitio inmediatamente después de extraerlo para crear tú la cuenta principal. La primera pantalla:

1. Comprueba PHP, extensiones, archivos y permisos.
2. Crea SQLite y los catálogos sin datos demo.
3. Te pide nombre, correo y contraseña para el SuperAdmin.
4. Bloquea el instalador y habilita el inicio de sesión.

No existe una contraseña predeterminada. Después de ingresar, crea la empresa y sus usuarios desde la consola.

## Corregir el error 500 de una publicación anterior

Si ya existía una instalación:

1. **Antes de reemplazar archivos**, descarga una copia de `normia-sst/database/` y `normia-sst/storage/` si contienen información que debas conservar.
2. Sube y extrae el ZIP nuevo en `/domains/normiasst.whf.bz/`, aceptando reemplazar el código anterior.
3. No reemplaces ni elimines manualmente `normia-sst/database/sgsst.db` con una base local o demo.
4. Abre nuevamente el dominio. Si el esquema es anterior, la migración aditiva se ejecuta automáticamente una sola vez y conserva los registros.

El ZIP **no contiene `sgsst.db`**, por lo que su extracción no incluye una base que sobrescriba la existente. Aun así, la copia previa es indispensable antes de una actualización.

Si queda un `index.html` de bienvenida del hosting, el nuevo `.htaccess` da prioridad a `index.php`; no necesitas borrar otros archivos sin revisar su contenido.

## Actualizaciones posteriores

1. Descarga una copia de `database/` y `storage/`.
2. Extrae el ZIP nuevo en la misma carpeta del dominio y reemplaza los archivos de código.
3. Conserva siempre la base y las evidencias del servidor.
4. Abre el sitio: las migraciones pendientes se aplican al arrancar.

No ejecutes el instalador con `--demo` en producción. El instalador web no permite crear otra cuenta inicial cuando ya existe un SuperAdmin.

## Permisos

En GoogieHost, PHP suele ejecutarse con el usuario de la cuenta:

- Carpetas: `755`.
- Archivos: `644`.
- `database/` y `storage/` deben ser escribibles por PHP.

No uses `777`. Si el instalador marca un problema de escritura, revisa propietario y grupo con soporte; solo usa `775` en esas carpetas si el proveedor lo indica.

## Diagnóstico rápido

| Mensaje o síntoma | Acción |
|---|---|
| “La versión activa es PHP…” | Cambia a PHP 8.1+ y recarga. |
| “Falta la extensión…” | Habilita la extensión indicada en Select PHP Version. |
| “No se encontró la aplicación privada” | Extraíste en una ubicación incorrecta; `normia-sst` y `public_html` deben quedar al mismo nivel. |
| “Carpeta privada… no escribible” | Revisa permisos/propietario de `normia-sst/database` y `normia-sst/storage`. |
| La instalación pide retirar un archivo no válido | No lo sobrescribas: restaura una copia válida o retíralo manualmente si estás seguro de que no contiene datos. |
| CSS o JS no carga | Comprueba `public_html/assets/css/app.css` y `public_html/assets/js/app.js`. |
| Las rutas internas devuelven 404 | Confirma que `public_html/.htaccess` se extrajo y que el servidor permite reescritura. |
| Error después del arranque | Revisa `normia-sst/storage/logs/app.log`; mantén `APP_DEBUG=false`. |

## HTTPS y datos reales

Activa el certificado SSL para `normiasst.whf.bz` antes de registrar información real o compartir URL/QR. El instalador permite terminar la configuración inicial si el hosting todavía está por HTTP, pero muestra la advertencia correspondiente.

La aplicación no publica la base SQLite, `.env`, evidencias ni documentos privados porque permanecen fuera de `public_html`.
