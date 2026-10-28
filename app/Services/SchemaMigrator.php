<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Aplica migraciones aditivas sin borrar datos y marca la versión en SQLite. */
final class SchemaMigrator
{
    public const VERSION = 5;

    public static function migrateIfNeeded(): bool
    {
        if (!Database::isInstalled() || self::version() >= self::VERSION) {
            return false;
        }

        $lock = self::acquireLock();
        try {
            if (self::version() >= self::VERSION) {
                return false;
            }
            self::migrate();
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function migrate(): void
    {
        // En una base existente, las columnas indexadas deben agregarse antes de ejecutar el esquema completo.
        self::ensureEvidenceColumns();
        Database::initialize();

        // CREATE TABLE IF NOT EXISTS no agrega columnas a instalaciones anteriores.
        self::ensureColumn('autoreportes', 'acceso_reporte_id', 'INTEGER REFERENCES accesos_reporte(id) ON DELETE SET NULL');
        self::ensureColumn('autoreportes', 'origen', "TEXT NOT NULL DEFAULT 'Portal'");
        self::ensureColumn('autoreportes', 'nombre_externo', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('autoreportes', 'contacto_externo', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('autoreportes', 'anonimo', 'INTEGER NOT NULL DEFAULT 0');
        self::ensureColumn('documentos_sgsst', 'biblioteca_id', 'TEXT');
        self::ensureColumn('planes_saas', 'modulo', "TEXT NOT NULL DEFAULT 'sgsst' CHECK (modulo IN ('sgsst','pesv','emergencias'))");
        self::ensureColumn('tenants', 'plan_condicion', "TEXT NOT NULL DEFAULT 'Pagado' CHECK (plan_condicion IN ('Pagado','Sugerido'))");
        self::ensureEvidenceColumns();
        self::ensureColumn('users', 'must_change_password', 'INTEGER NOT NULL DEFAULT 0 CHECK (must_change_password IN (0,1))');
        self::ensureOperationalColumns();
        self::migrateStandardCodes();
        $migration = @file_get_contents(BASE_PATH . '/database/migrations/004_ivc_sanitario.sql');
        if ($migration === false) {
            throw new RuntimeException('No se encontró la migración IVC y registros sanitarios.');
        }
        Database::connection()->exec($migration);
        $operationalMigration = @file_get_contents(BASE_PATH . '/database/migrations/005_operacion_sgsst.sql');
        if ($operationalMigration === false) {
            throw new RuntimeException('No se encontró la migración de operación SG-SST.');
        }
        Database::connection()->exec($operationalMigration);
        self::backfillEvidenceMetadata();
        self::backfillExplicitPermissions();

        $violations = Database::query('PRAGMA foreign_key_check');
        if ($violations !== []) {
            throw new RuntimeException('La revisión de claves foráneas encontró inconsistencias. Restaura una copia antes de continuar.');
        }

        Database::connection()->exec('PRAGMA user_version = ' . self::VERSION);
    }

    private static function version(): int
    {
        $row = Database::first('PRAGMA user_version');
        return (int) ($row['user_version'] ?? 0);
    }

    private static function ensureEvidenceColumns(): void
    {
        if (Database::first("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'soportes_cumplimiento'") === null) {
            return;
        }
        self::ensureColumn('soportes_cumplimiento', 'modulo', "TEXT NOT NULL DEFAULT 'sgsst' CHECK (modulo IN ('sgsst','pesv','emergencias'))");
        self::ensureColumn('soportes_cumplimiento', 'categoria', "TEXT NOT NULL DEFAULT 'Documento SG-SST'");
        self::ensureColumn('soportes_cumplimiento', 'titulo', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('soportes_cumplimiento', 'fecha_evidencia', 'TEXT');
        self::ensureColumn('soportes_cumplimiento', 'entidad_tipo', "TEXT NOT NULL DEFAULT 'requisito'");
        self::ensureColumn('soportes_cumplimiento', 'entidad_id', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('soportes_cumplimiento', 'entidad_referencia', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('soportes_cumplimiento', 'estado_revision', "TEXT NOT NULL DEFAULT 'Pendiente' CHECK (estado_revision IN ('Pendiente','Revisado'))");
        self::ensureColumn('soportes_cumplimiento', 'observaciones_revision', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('soportes_cumplimiento', 'revisado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL');
        self::ensureColumn('soportes_cumplimiento', 'revisado_at', 'TEXT');
        // SQLite no permite agregar una columna con DEFAULT CURRENT_TIMESTAMP mediante ALTER TABLE.
        self::ensureColumn('soportes_cumplimiento', 'updated_at', 'TEXT');
    }

    private static function backfillEvidenceMetadata(): void
    {
        if (Database::first("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'soportes_cumplimiento'") === null) {
            return;
        }
        Database::execute("UPDATE soportes_cumplimiento SET
            titulo = COALESCE(NULLIF(titulo, ''), NULLIF(descripcion, ''), nombre_original),
            fecha_evidencia = COALESCE(fecha_evidencia, date(created_at)),
            entidad_id = CASE WHEN entidad_tipo = 'requisito' AND entidad_id = '' THEN requisito_clave ELSE entidad_id END,
            entidad_referencia = CASE WHEN entidad_referencia = '' AND requisito_clave <> '' THEN 'Requisito: ' || requisito_clave ELSE entidad_referencia END,
            categoria = CASE WHEN requisito_clave <> '' AND categoria = 'Documento SG-SST' THEN 'Soporte de requisito' ELSE categoria END,
            updated_at = COALESCE(updated_at, created_at)");
        $keys = ['programas_pyp', 'vigilancia_epidemiologica', 'emergencia_plan', 'emergencia_planos', 'emergencia_brigada', 'emergencia_equipos', 'emergencia_simulacro'];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        Database::execute("UPDATE soportes_cumplimiento SET modulo = 'emergencias' WHERE requisito_clave IN ($placeholders)", $keys);
    }

    private static function ensureOperationalColumns(): void
    {
        $columns = [
            'plan_trabajo' => [
                ['objetivo', "TEXT NOT NULL DEFAULT ''"], ['meta', "TEXT NOT NULL DEFAULT ''"],
                ['indicador', "TEXT NOT NULL DEFAULT ''"], ['recursos', "TEXT NOT NULL DEFAULT ''"],
                ['eficacia_resultado', "TEXT NOT NULL DEFAULT ''"], ['verificado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL'], ['verificado_at', 'TEXT'],
            ],
            'hallazgos_acpa' => [
                ['tipo_accion', "TEXT NOT NULL DEFAULT 'Correctiva'"], ['causa_raiz', "TEXT NOT NULL DEFAULT ''"],
                ['recursos', "TEXT NOT NULL DEFAULT ''"], ['fecha_ejecucion', 'TEXT'],
                ['eficacia_resultado', "TEXT NOT NULL DEFAULT ''"], ['eficacia_fecha', 'TEXT'],
                ['verificado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL'], ['verificado_at', 'TEXT'],
            ],
            'capacitaciones' => [
                ['publico_objetivo', "TEXT NOT NULL DEFAULT ''"], ['competencia', "TEXT NOT NULL DEFAULT ''"],
                ['contenido', "TEXT NOT NULL DEFAULT ''"], ['idoneidad_instructor', "TEXT NOT NULL DEFAULT ''"],
                ['evaluacion_aprendizaje', "TEXT NOT NULL DEFAULT ''"], ['eficacia', "TEXT NOT NULL DEFAULT ''"],
                ['fecha_eficacia', 'TEXT'], ['verificado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL'], ['verificado_at', 'TEXT'],
            ],
            'indicadores' => [
                ['fuente_numerador', "TEXT NOT NULL DEFAULT ''"], ['fuente_denominador', "TEXT NOT NULL DEFAULT ''"],
                ['responsable', "TEXT NOT NULL DEFAULT ''"], ['observaciones', "TEXT NOT NULL DEFAULT ''"],
                ['verificado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL'], ['verificado_at', 'TEXT'],
            ],
            'peligros_ipevr' => [
                ['metodologia', "TEXT NOT NULL DEFAULT 'GTC 45'"], ['participantes', "TEXT NOT NULL DEFAULT ''"],
                ['fecha_evaluacion', 'TEXT'], ['proxima_revision', 'TEXT'],
                ['jerarquia_controles', "TEXT NOT NULL DEFAULT ''"], ['verificado_por', 'INTEGER REFERENCES users(id) ON DELETE SET NULL'], ['verificado_at', 'TEXT'],
            ],
        ];
        foreach ($columns as $table => $definitions) {
            foreach ($definitions as [$column, $definition]) {
                self::ensureColumn($table, $column, $definition);
            }
        }
    }

    /** Corrige la numeración del artículo 27 sin perder respuestas asociadas por código. */
    private static function migrateStandardCodes(): void
    {
        $indicatorCodes = [
            ['3.2.4', '3.3.1', 'Medición de la frecuencia de la accidentalidad'],
            ['3.2.5', '3.3.2', 'Medición de la severidad de la accidentalidad'],
            ['3.2.6', '3.3.3', 'Medición de la mortalidad por accidentes de trabajo'],
            ['3.2.7', '3.3.4', 'Medición de la prevalencia de enfermedad laboral'],
            ['3.2.8', '3.3.5', 'Medición de la incidencia de enfermedad laboral'],
            ['3.2.9', '3.3.6', 'Medición del ausentismo por causa médica'],
        ];
        foreach ($indicatorCodes as [$old, $new, $name]) {
            Database::execute('UPDATE estandares_minimos SET codigo = ?, nombre = ? WHERE grupo = 60 AND codigo = ?', [$new, $name, $old]);
        }
        $controlCodes = [
            ['4.2.5', '4.2.6', 'Entrega de elementos de protección personal (EPP)'],
            ['4.2.4', '4.2.5', 'Mantenimiento periódico de instalaciones, equipos, máquinas y herramientas'],
            ['4.2.3', '4.2.4', 'Inspecciones sistemáticas a instalaciones, maquinaria o equipos'],
            ['4.2.2', '4.2.3', 'Elaboración de procedimientos, instructivos, fichas y protocolos'],
            ['4.2.1', '4.2.2', 'Verificación de la aplicación de medidas de prevención y control por los trabajadores'],
            ['4.1.5', '4.2.1', 'Implementación de medidas de prevención y control frente a peligros y riesgos'],
        ];
        foreach ($controlCodes as [$old, $new, $name]) {
            Database::execute('UPDATE estandares_minimos SET codigo = ?, nombre = ? WHERE grupo = 60 AND codigo = ?', [$new, $name, $old]);
        }
    }

    private static function backfillExplicitPermissions(): void
    {
        foreach (['matriz.verificar', 'documentos.firmar', 'salud.reservada', 'convivencia.reservada'] as $permission) {
            Database::execute(
                "INSERT OR IGNORE INTO usuario_permisos (tenant_id, user_id, permiso, otorgado_por)
                 SELECT tenant_id, id, ?, id FROM users WHERE rol = 'AdminEmpresa'",
                [$permission]
            );
        }
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        $columns = Database::query('PRAGMA table_info(' . $table . ')');
        foreach ($columns as $existing) {
            if ($existing['name'] === $column) {
                return;
            }
        }
        Database::execute('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    /** @return resource */
    private static function acquireLock()
    {
        $directory = BASE_PATH . '/storage';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear la carpeta privada storage.');
        }
        $lock = @fopen($directory . '/.migration.lock', 'c+');
        if ($lock === false) {
            throw new RuntimeException('No fue posible crear el bloqueo de migración en storage.');
        }
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('No fue posible bloquear la migración. Inténtalo nuevamente.');
        }
        return $lock;
    }
}
