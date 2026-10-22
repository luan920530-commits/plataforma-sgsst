<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\DemoSeeder;
use App\Services\Installer;

$demo = in_array('--demo', $argv, true);

try {
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('La extensión pdo_sqlite es obligatoria. Habilítela en php.ini.');
    }

    Installer::run();
    if ($demo) {
        DemoSeeder::seed();
        echo "✓ Catálogos y datos de demostración instalados.\n";
    } else {
        $admin = Installer::createInitialSuperAdmin();
        echo "✓ Base de datos y catálogos instalados.\n";
        if ($admin['created']) {
            echo "\nCredenciales iniciales (cámbielas después del primer ingreso):\n";
            echo "  Correo:     {$admin['email']}\n";
            echo "  Contraseña: {$admin['password']}\n";
        }
    }

    $path = require BASE_PATH . '/config/database.php';
    echo "\nBase de datos: {$path['path']}\n";
    if ($demo) {
        echo "\nAccesos demo · contraseña para todos: Normia2026!\n";
        echo "  SuperAdmin:      superadmin@normia.co\n";
        echo "  AdminEmpresa:    admin@andina.co\n";
        echo "  ResponsableSST:  sst@andina.co\n";
        echo "  Trabajador:      trabajador@andina.co\n";
        echo "\nNo utilice estas credenciales en producción.\n";
    }
    echo "✓ Instalación finalizada.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error de instalación: {$exception->getMessage()}\n");
    exit(1);
}
