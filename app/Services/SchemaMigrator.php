<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Aplica migraciones aditivas sin borrar datos y marca la versión en SQLite. */
final class SchemaMigrator
{
    public const VERSION = 3;

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
        self::backfillEvidenceMetadata();

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
