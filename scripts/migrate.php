<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;

try {
    if (!Database::isInstalled()) {
        throw new RuntimeException('La base aún no está instalada. Ejecuta php scripts/install.php primero.');
    }
    Database::initialize();

    // CREATE TABLE IF NOT EXISTS no agrega columnas a instalaciones anteriores.
    // Estas alteraciones son aditivas y conservan los autoreportes existentes.
    $ensureColumn = static function (string $table, string $column, string $definition): void {
        $columns = Database::query('PRAGMA table_info(' . $table . ')');
        foreach ($columns as $existing) {
            if ($existing['name'] === $column) {
                return;
            }
        }
        Database::execute('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    };
    $ensureColumn('autoreportes', 'acceso_reporte_id', 'INTEGER REFERENCES accesos_reporte(id) ON DELETE SET NULL');
    $ensureColumn('autoreportes', 'origen', "TEXT NOT NULL DEFAULT 'Portal'");
    $ensureColumn('autoreportes', 'nombre_externo', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('autoreportes', 'contacto_externo', "TEXT NOT NULL DEFAULT ''");
    $ensureColumn('autoreportes', 'anonimo', 'INTEGER NOT NULL DEFAULT 0');
    $ensureColumn('documentos_sgsst', 'biblioteca_id', 'TEXT');

    $violations = Database::query('PRAGMA foreign_key_check');
    if ($violations !== []) {
        throw new RuntimeException('La revisión de claves foráneas encontró inconsistencias. Restaura una copia y revisa los datos antes de continuar.');
    }
    $path = require BASE_PATH . '/config/database.php';
    echo "✓ Esquema actualizado sin eliminar los datos existentes.\n";
    echo "✓ Claves foráneas verificadas.\n";
    echo "Base de datos: {$path['path']}\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error de migración: {$exception->getMessage()}\n");
    exit(1);
}
