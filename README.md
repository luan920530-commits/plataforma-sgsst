# Plataforma SG-SST

Plataforma web (PHP + SQLite, sin framework) para **administrar, gestionar, crear y auditar el Sistema de Gestión de Seguridad y Salud en el Trabajo (SG-SST)** de empresas en Colombia, conforme al Decreto 1072/2015 y la Resolución 0312/2019. Incluye como módulo el **PPPRAE** (plan de emergencias, Res. 256/2014).

## Qué hace

- **Centro de cumplimiento SG-SST** (`/sgsst`): al registrar una empresa se inicializa automáticamente su SG-SST:
  - Evaluación inicial de **estándares mínimos** (catálogo por ciclo PHVA, con evidencia, responsable y fecha compromiso) y **puntaje automático** con semáforo de la Res. 0312/2019 (≤60 % crítico, 61–85 % moderadamente aceptable, >85 % aceptable).
  - **Plan de trabajo anual base** con 12 actividades, estados y alertas de vencimiento.
  - **Registros de gestión**: capacitaciones, inspecciones, incidentes/accidentes, EPP, exámenes médicos, mantenimientos, simulacros, COPASST, auditorías y acciones de mejora.
  - **12 plantillas base editables** (.doc) con los datos de la empresa (política, objetivos, plan anual, acta COPASST, matriz GTC 45, procedimiento de investigación, EPP, auditoría, rendición de cuentas, etc.), registradas con hash SHA-256.
  - **Informe de cumplimiento imprimible** por empresa (para auditoría, ARL o Ministerio de Trabajo).
  - **Auditoría automática**: quién hizo qué, sobre qué empresa y cuándo.
- **Módulo PPPRAE**: asistente de 7 pasos (empresa → inventario → amenazas → vulnerabilidad → personal/brigada → revisión → generación) que produce un ZIP con 5 documentos (lista de chequeo, análisis de vulnerabilidad, matriz consolidada, MEDEVAC y plan completo).
- **Matriz IPVR interactiva (GTC 45)**: NP = ND × NE y NR = NP × NC automáticos, nivel de aceptabilidad, medidas de intervención, filtro por nivel, exportación CSV y alertas de peligros nivel I/II sin cerrar.
- **Indicadores automáticos (Res. 0312/2019)**: por periodo mensual, frecuencia y severidad de accidentalidad (× 240.000 HHT), ILI, % de accidentes mortales y % de ausentismo por causa médica; los accidentes/enfermedades se cuentan solos desde los registros.
- **Alertas de vencimientos**: actividades del plan, registros, compromisos de la evaluación y botiquines (vencidos y próximos 30 días), con contador de críticas en la campana del menú.
- **Portal del cliente (solo lectura)**: el propietario comparte el SG-SST de una empresa con un usuario cliente, que consulta cumplimiento, matriz, indicadores e informes sin poder modificar nada.
- **Interfaz estilo macOS**: cristal translúcido, tipografía del sistema, iconos con color, notificaciones toast animadas y campana de alertas.
- **Catálogo documental maestro (plantillas reales)**: los 153 archivos del paquete «SG-SST POR ESTANDARES-V» (60 estándares) están integrados por empresa en `/sgsst/empresa/{id}/documentos`: buscar, descargar el original y **generar copia diligenciada** (DOCX/XLSX) con razón social, NIT, representante legal, actividad, ciudad y fecha; cada documento generado queda con hash SHA-256 y en la auditoría. Análisis completo en `docs/ANALISIS_PLANTILLAS.md`.
- **Usuarios y roles** (solo superadmin): `superadmin`, `consultor` y `cliente`, con aislamiento de datos por propietario y cambio forzado de contraseña temporal.

> ⚠️ Las plantillas y el catálogo son **bases de partida**: la aplicabilidad de cada estándar depende del número de trabajadores y la clase de riesgo. Todo documento debe adaptarse a la empresa, ser validado por el responsable del SG-SST (con licencia cuando la norma lo exige) y firmarse. La plataforma organiza y hace trazable el cumplimiento; no reemplaza la validación profesional ni la verificación de la norma vigente.

## Instalación

Requisitos: PHP 8.1+ con extensiones `pdo_sqlite`, `zip`, `mbstring`; servidor Apache/Nginx o el servidor embebido de PHP.

```bash
cp .env.example .env        # ajusta APP_URL y SETUP_TOKEN
php setup.php               # crea la BD y el usuario administrador (contraseña mostrada una sola vez)
php -S localhost:8000 -t public   # desarrollo; en producción apunta el virtual host a /public
```

En producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS, y elimina o bloquea `setup.php`. La contraseña inicial debe cambiarse en el primer ingreso (la plataforma lo exige).

## Seguridad (Fase 1 aplicada)

- Sin credenciales por defecto conocidas: contraseña inicial aleatoria o por entorno.
- `setup.php` bloqueado sin `SETUP_TOKEN` (y sin efecto si la BD ya existe).
- Base de datos y documentos generados **fuera de Git** (`storage/` ignorado; contiene datos personales y de salud — Ley 1581/2012).
- Debug controlado por entorno; cabeceras de seguridad; cookie de sesión `HttpOnly`/`SameSite` (+`Secure` con HTTPS); logout por POST con CSRF; CSRF en todos los formularios.
- Autorización por propietario en todos los módulos y auditoría de accesos y cambios.

Las migraciones son automáticas e idempotentes (`app/Migrator.php`): las instalaciones existentes ganan las tablas SG-SST sin perder datos.

## Estructura

```
app/            Núcleo: Router, Auth, Database, Migrator, Auditoría, catálogo SG-SST, generadores DOCX/XLSX
app/controllers Controladores (SG-SST, PPPRAE, empresas, personal, usuarios…)
app/Models/     Modelos PPPRAE originales
views/          Vistas (Tailwind + Alpine por CDN)
public/         Único punto de entrada web (index.php)
config/         Configuración por entorno
schema.sql      Esquema base (instalación nueva)
storage/        BD SQLite y documentos generados (NO versionado)
```

## Hoja de ruta

1. **Fase 1 (esta entrega):** seguridad, roles, rutas reparadas, migraciones, auditoría y núcleo SG-SST integral.
2. **Fase 2 (esta entrega):** matriz IPVR (GTC 45) interactiva, indicadores automáticos, alertas de vencimientos, portal cliente de solo lectura e interfaz macOS. Pruebas: `php tests/Gtc45Test.php` y `php tests/IndicadoresTest.php`.
3. **Fase 3:** almacenamiento externo y respaldos automáticos, notificaciones, firma electrónica, reportes ARL/MinTrabajo, migración opcional a MariaDB/PostgreSQL para multiusuario intensivo.
