# Publicar Normia SST en GoogieHost / DirectAdmin

Dominio de destino: `normiasst.whf.bz`.

Esta guía evita exponer la base SQLite, `.env`, evidencias y documentos privados. No subas todo el repositorio directamente dentro de `public_html`.

## 1. Requisitos en el hosting

En DirectAdmin busca **Select PHP Version**, **PHP Manager** o la opción equivalente:

- PHP **8.1 o superior**; preferiblemente una versión estable que ofrezca el proveedor.
- Extensiones: `pdo_sqlite`, `sqlite3`, `dom` o `libxml`, `json`, `session` y `fileinfo`.
- `mbstring` recomendado.
- `zip` solo si posteriormente configuras la extracción opcional de originales de biblioteca.
- Apache o LiteSpeed con lectura de `.htaccess` y reescritura de URL habilitada.

Configura también HTTPS para `normiasst.whf.bz`. No uses credenciales reales por HTTP.

No necesitas Node.js, npm, Composer, MySQL ni phpMyAdmin. El CSS y JavaScript ya están compilados.

## 2. Estructura que debe quedar en el servidor

Entra en el Administrador de archivos y sube un nivel desde `public_html`, hasta:

```text
/domains/normiasst.whf.bz/
```

La estructura final debe ser:

```text
/domains/normiasst.whf.bz/
├── normia-sst/                  # Aplicación privada
│   ├── app/
│   ├── config/
│   ├── database/
│   │   └── schema.sql
│   ├── public/
│   │   └── index.php
│   ├── resources/
│   │   └── sgsst-library/
│   ├── scripts/
│   │   ├── install.php
│   │   └── migrate.php
│   ├── storage/
│   └── .env                     # Se crea manualmente; nunca se sube el local
└── public_html/                 # Única carpeta pública
    ├── index.php                # Cargador descrito abajo
    ├── .htaccess
    └── assets/
        ├── css/
        └── js/
```

Si al extraer un ZIP queda una carpeta intermedia como `plataforma-sgsst-main`, mueve su contenido hasta obtener exactamente `normia-sst/app`, `normia-sst/config`, etc.

### Archivos que no debes subir

- `.git/` y `.gitignore`.
- `node_modules/`.
- `database/sgsst.db` de demostración ni `database/testing.db`.
- `storage/logs/` y archivos temporales locales.
- `tests/`, `docs/` y archivos de desarrollo, salvo que desees conservarlos privadamente.
- Tu `.env` local.
- El ZIP documental eliminado anteriormente; no es necesario para las versiones editables.

## 3. Subir la aplicación privada

1. Crea `/domains/normiasst.whf.bz/normia-sst/`.
2. Sube y extrae allí la aplicación, conservando como mínimo `app`, `config`, `database`, `resources`, `scripts`, `storage` y `public/index.php`.
3. Verifica que `storage/` y `database/` estén fuera de `public_html`.
4. No ejecutes todavía el instalador con `--demo`.

## 4. Preparar `public_html`

Si el dominio será exclusivo para Normia SST, haz una copia del archivo de portada predeterminado del hosting y retira o renombra el `index.html` de bienvenida; algunos servidores lo muestran antes que `index.php`. No borres otros archivos que necesites conservar.

Copia desde el directorio `public/` del proyecto:

- `.htaccess` → `public_html/.htaccess`.
- `assets/` completo → `public_html/assets/`.

Crea o reemplaza `public_html/index.php` con exactamente este contenido:

```php
<?php
declare(strict_types=1);

require '/domains/normiasst.whf.bz/normia-sst/public/index.php';
```

Este cargador hace que la aplicación funcione desde `https://normiasst.whf.bz/` sin exponer la carpeta privada. No es necesario cambiar `BASE_PATH` en la aplicación.

No necesitas subir `public/router.php`: ese archivo es únicamente para el servidor local de desarrollo.

## 5. Crear `.env` de producción

Crea `/domains/normiasst.whf.bz/normia-sst/.env` con el siguiente contenido y reemplaza los valores de administrador. No uses las credenciales demo ni compartas este archivo.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_DEMO=false
APP_TIMEZONE=America/Bogota
APP_URL=https://normiasst.whf.bz
SGSST_DB_PATH=/domains/normiasst.whf.bz/normia-sst/database/sgsst.db
ADMIN_EMAIL=admin@tu-correo-real.co
ADMIN_PASSWORD=REEMPLAZA-POR-UNA-CLAVE-LARGA-Y-UNICA
ADMIN_NAME=Administrador Normia SST
```

`APP_URL` no lleva `/public` ni barra final porque el dominio queda publicado desde `public_html`. Esta URL también se utilizará en los enlaces y QR de autoreportes.

Deja `SGSST_LIBRARY_ZIP` sin configurar, salvo que tengas una copia privada del ZIP documental y sepas que necesitas recuperar originales.

## 6. Inicializar SQLite sin datos demo

Elige una de las siguientes alternativas.

### Alternativa A · Terminal de DirectAdmin

Si tu cuenta tiene Terminal, ejecuta:

```bash
php /domains/normiasst.whf.bz/normia-sst/scripts/install.php
```

No agregues `--demo`. Si `php` no corresponde a PHP 8.1+, usa el binario de la versión seleccionada, por ejemplo `/usr/local/bin/php`, según la configuración del proveedor.

### Alternativa B · Cron de un solo uso

Si no hay Terminal pero sí **Cron Jobs**, crea temporalmente una tarea para ejecutar:

```bash
/usr/local/bin/php /domains/normiasst.whf.bz/normia-sst/scripts/install.php
```

Ejecuta la tarea una sola vez, revisa el resultado y elimínala inmediatamente. Si el hosting utiliza otra ruta para PHP, solicita a soporte la ruta del binario PHP 8.1+.

### Alternativa C · Solo Administrador de archivos

Si no dispones de Terminal ni Cron, genera una base nueva en tu instalación local:

1. Usa una copia local sin base de datos previa.
2. Define localmente `ADMIN_EMAIL`, `ADMIN_PASSWORD` y `ADMIN_NAME`; usa los mismos valores que colocarás en el servidor.
3. No ejecutes el instalador con `--demo`.
4. Temporalmente deja `SGSST_DB_PATH` sin definir en el entorno local para que la base se cree en `database/sgsst.db`.
5. Con XAMPP en macOS ejecuta:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/php scripts/install.php
   ```

6. Espera a que termine el comando y cierra cualquier servidor local que esté usando esa base.
7. Sube el `database/sgsst.db` resultante a `/domains/normiasst.whf.bz/normia-sst/database/sgsst.db`.
8. Si todavía existen `sgsst.db-wal` o `sgsst.db-shm`, no descartes datos: realiza primero un checkpoint o copia de SQLite, o sube el conjunto completo siguiendo el procedimiento de respaldo. Lo habitual tras cerrar correctamente una instalación nueva es que solo necesites el `.db`.

Nunca subas una base con las cuentas `admin@andina.co`, `sst@andina.co` o la contraseña demo.

## 7. Permisos

PHP debe poder leer la aplicación y escribir en `database/` y `storage/`.

En alojamientos donde PHP se ejecuta con tu mismo usuario suelen bastar:

- Directorios: `755`.
- Archivos: `644`.
- `.env`: restringido según las opciones del hosting, idealmente `600` si PHP puede seguir leyéndolo.

Si aparece `attempt to write a readonly database`, no cambies todo a `777`. Revisa propietario/grupo con soporte y utiliza `775` para los directorios de escritura y `664` para la base únicamente si la configuración del hosting lo requiere.

## 8. Primera comprobación

Abre:

```text
https://normiasst.whf.bz/
```

Debe aparecer la pantalla de ingreso, sin `/public` al final.

1. Inicia sesión con el SuperAdmin creado durante la instalación.
2. Cambia la contraseña desde **Seguridad de la cuenta**.
3. Crea la empresa y sus usuarios desde la consola; no uses datos demo.
4. En **Autoreportes → Crear enlace o QR**, comprueba que la URL comience por `https://normiasst.whf.bz/reportar?token=`.
5. Verifica que el CSS y JavaScript carguen y que no existan enlaces a `localhost`.

## 9. Errores frecuentes

| Síntoma | Revisión |
|---|---|
| Aparece la bienvenida de GoogieHost | Retira o renombra el `index.html` predeterminado y confirma `public_html/index.php`. |
| HTTP 503 de instalación | La base no fue inicializada o `SGSST_DB_PATH` apunta a una ruta incorrecta. |
| `could not find driver` | Habilita `pdo_sqlite` y `sqlite3` para la versión de PHP activa. |
| `attempt to write a readonly database` | Permisos/propietario de `database/`, la `.db` y sus archivos WAL/SHM. |
| HTTP 500 | Consulta `normia-sst/storage/logs/app.log`; mantén `APP_DEBUG=false`. |
| CSS o JS no carga | Verifica `public_html/assets/css/app.css` y `public_html/assets/js/app.js`. |
| Rutas internas devuelven 404 | Verifica `public_html/.htaccess` y que el servidor permita reescritura. |
| Los QR muestran `localhost` o `/public` | Corrige `APP_URL=https://normiasst.whf.bz` y vuelve a generar el acceso. |
| No aparecen archivos `.env` o `.htaccess` | Activa la visualización de archivos ocultos en el Administrador de archivos. |

## 10. Futuras actualizaciones

Antes de reemplazar código:

1. Descarga una copia de `normia-sst/database/` y `normia-sst/storage/`.
2. Conserva el `.env` del servidor; no lo reemplaces con el local.
3. Sube los archivos de aplicación modificados sin borrar la base ni las evidencias.
4. Ejecuta, cuando la actualización lo indique:

   ```bash
   php /domains/normiasst.whf.bz/normia-sst/scripts/migrate.php
   ```

No ejecutes `scripts/install.php --demo` en producción.
