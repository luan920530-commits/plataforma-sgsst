<?php
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'wasm'], true)) {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Services\DemoSeeder;
use App\Services\Installer;

$demo = in_array('--demo', $argv ?? [], true) || (bool) ($GLOBALS['config']['demo'] ?? false);

try {
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('La extensión pdo_sqlite es obligatoria. Habilítela en php.ini.');
    }

    Installer::run();
    if ($demo) {
        DemoSeeder::seed();
        echo "✓ Catálogos y datos de demostración instalados.\n";
    } else {
        $admin = Installer::installWithSuperAdmin();
        echo "✓ Base de datos y catálogos instalados.\n";
        if ($admin['created']) {
            echo "\nCredenciales iniciales (cámbielas después del primer ingreso):\n";
            echo "  Correo:     {$admin['email']}\n";
            echo "  Contraseña: {$admin['password']}\n";
        }
        if (!empty($admin['company']['access'])) {
            $company = $admin['company']['access'];
            echo "\nAcceso temporal de Extintores SCM (guárdelo; se exigirá cambiarlo al ingresar):\n";
            echo "  Correo:     {$company['email']}\n";
            echo "  Contraseña: {$company['password']}\n";
            echo "  Seguimiento: {$admin['company']['requirements']} pendientes · {$admin['company']['validations']} validaciones.\n";
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
    file_put_contents('php://stderr', "Error de instalación: {$exception->getMessage()}\n" . ($GLOBALS['config']['debug'] ? (string) $exception : ''));
    exit(1);
}
