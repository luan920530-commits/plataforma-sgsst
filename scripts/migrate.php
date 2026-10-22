<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\CatalogSeeder;
use App\Services\SchemaMigrator;

try {
    if (!Database::isInstalled()) {
        throw new RuntimeException('La base aún no está instalada. Ejecuta php scripts/install.php primero.');
    }
    SchemaMigrator::migrate();
    CatalogSeeder::seed();

    $path = require BASE_PATH . '/config/database.php';
    echo "✓ Esquema actualizado sin eliminar los datos existentes.\n";
    echo "✓ Claves foráneas verificadas.\n";
    echo "Base de datos: {$path['path']}\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error de migración: {$exception->getMessage()}\n");
    exit(1);
}
