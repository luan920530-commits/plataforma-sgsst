# Prueba local en XAMPP (Windows)

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
