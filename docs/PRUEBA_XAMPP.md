# Prueba local en XAMPP

## Instalación limpia en Mac (desde cero)

> ⚠️ Si descargas el ZIP de GitHub, al descomprimirlo queda una subcarpeta
> (ej. `plataforma-sgsst-main/`). Los archivos del proyecto (`setup.php`, `public/`, `app/`…)
> deben quedar **directamente** dentro de `htdocs/ppprae/`, no dentro de otra carpeta.

```bash
# 1. Borra la instalación anterior (si existe) y crea la carpeta limpia
rm -rf /Applications/XAMPP/xamppfiles/htdocs/ppprae
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/ppprae

# 2. Copia el CONTENIDO del repo descargado dentro de ppprae (ojo: el contenido, no la carpeta)
#    Luego ubícate en la raíz del proyecto:
cd /Applications/XAMPP/xamppfiles/htdocs/ppprae
ls setup.php          # ← debe aparecer; si no, estás en la carpeta equivocada

# 3. Instala (crea storage/ y la base de datos automáticamente)
/Applications/XAMPP/xamppfiles/bin/php setup.php
#    …o simplemente:  bash instalar.sh

# 4. Entra a http://localhost/ppprae/public/ con admin@ppprae.local
#    y la contraseña inicial que mostró el instalador (se muestra UNA sola vez).
```

Notas:
- La carpeta `storage/` no trae base de datos en el repo (está en `.gitignore` a propósito);
  el instalador la crea. Por eso en una descarga fresca `storage/database.sqlite` **no existe**
  hasta correr `setup.php`, y borrarla simplemente no es necesario.
- Para **reinstalar de cero** con la app ya instalada: borra `storage/database.sqlite`
  y vuelve a correr `setup.php`.
- En una actualización (sin reinstalar) **no** borres `storage/`: ahí están tu BD y tus documentos.

## Instalación en Windows

1. Instala XAMPP con PHP 8.1+ y verifica en `php.ini` que estén activas: `extension=pdo_sqlite`, `extension=sqlite3`, `extension=zip`, `extension=mbstring` (quitar el `;` inicial) y reinicia Apache.
2. Copia la carpeta del proyecto a `C:\xampp\htdocs\ppprae` (deja fuera `storage/database.sqlite` si vienes de otra instalación y quieres empezar limpio).
3. Crea el archivo `.env` en la raíz del proyecto (copia de `.env.example`) con:
   ```
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost/ppprae/public
   SETUP_TOKEN=prueba123
   ```
4. Abre en el navegador: `http://localhost/ppprae/setup.php?token=prueba123`
   Anota la contraseña de administrador que muestra (se genera una sola vez). Si la BD ya existe, el instalador no cambia nada.
5. Entra a `http://localhost/ppprae/public/` e inicia sesión; la plataforma te obligará a cambiar la contraseña.
6. Recorrido de prueba sugerido:
   - Crear una empresa → entrar a **Centro de cumplimiento** → guardar la evaluación → revisar el plan anual.
   - **Matriz IPVR**: agregar un peligro ND 10 / NE 4 / NC 100 (debe salir Nivel I, no aceptable).
   - **Indicadores**: crear un periodo con horas y registrar un accidente desde Registros → verificar frecuencia/severidad.
   - **Formularios**: diligenciar una inspección de extintores con fecha compromiso vencida → debe aparecer en alertas.
   - Crear un usuario cliente (superadmin) → otorgarle acceso **Representante** → entrar con él: debe aparecer **🏢 Mi empresa** en la barra superior.
   - Copiar el enlace **🦺 de operarios** y abrirlo en una ventana de incógnito: enviar un reporte → debe llegar a Registros de esa empresa.
7. Si algo falla: con `APP_DEBUG=true` el error se ve en pantalla; copia el mensaje completo y la URL donde ocurrió.
