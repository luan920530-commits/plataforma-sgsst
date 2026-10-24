<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use RuntimeException;

final class Installer
{
    public static function run(): void
    {
        SchemaMigrator::migrate();
        CatalogSeeder::seed();
    }

    public static function needsInstallation(): bool
    {
        return !Database::isInstalled() || !self::hasInitialSuperAdmin();
    }

    public static function hasInitialSuperAdmin(): bool
    {
        if (!Database::isInstalled()) {
            return false;
        }
        return (int) (Database::first("SELECT COUNT(*) AS total FROM users WHERE rol = 'SuperAdmin'")['total'] ?? 0) > 0;
    }

    public static function installWithSuperAdmin(string $email, string $password, string $name): array
    {
        $lock = self::acquireInstallLock();
        try {
            if (self::hasInitialSuperAdmin()) {
                return ['created' => false];
            }
            self::run();
            return self::createInitialSuperAdmin($email, $password, $name);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function installDemo(): array
    {
        $lock = self::acquireInstallLock();
        try {
            if (self::hasInitialSuperAdmin()) {
                return ['created' => false];
            }
            self::run();
            DemoSeeder::seed();
            return ['created' => true, 'email' => 'superadmin@normia.co', 'demo' => true];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Comprobaciones legibles para el instalador web, sin modificar archivos. */
    public static function requirements(): array
    {
        $database = require BASE_PATH . '/config/database.php';
        $databasePath = (string) $database['path'];
        $databaseDirectory = dirname($databasePath);
        $databaseExists = is_file($databasePath);
        $databaseValid = $databaseExists && Database::isInstalled();
        $databaseInvalid = $databaseExists && (int) (@filesize($databasePath) ?: 0) > 0 && !$databaseValid;

        $checks = [
            [
                'label' => 'PHP 8.1 o superior',
                'ok' => PHP_VERSION_ID >= 80100,
                'detail' => 'Versión detectada: ' . PHP_VERSION,
            ],
            [
                'label' => 'SQLite mediante PDO',
                'ok' => extension_loaded('pdo_sqlite'),
                'detail' => extension_loaded('pdo_sqlite') ? 'Extensión pdo_sqlite disponible.' : 'Habilita pdo_sqlite en Select PHP Version.',
            ],
            [
                'label' => 'Documentos, sesiones y archivos',
                'ok' => extension_loaded('dom') && extension_loaded('libxml') && extension_loaded('json') && extension_loaded('session') && extension_loaded('fileinfo'),
                'detail' => 'Se requieren dom, libxml, json, session y fileinfo.',
            ],
            [
                'label' => 'Carpeta privada de base de datos',
                'ok' => self::directoryWritable($databaseDirectory) && (!$databaseExists || is_writable($databasePath)) && !$databaseInvalid,
                'detail' => $databaseInvalid
                    ? 'Ya existe un archivo no válido. Restaura una copia o retíralo manualmente; no se sobrescribirá.'
                    : ($databaseValid ? 'Base existente detectada: se conservarán sus datos.' : 'Se creará SQLite automáticamente fuera de public_html.'),
            ],
            [
                'label' => 'Carpeta privada de almacenamiento',
                'ok' => self::directoryWritable(BASE_PATH . '/storage'),
                'detail' => 'PHP debe poder escribir en storage para evidencias y registros.',
            ],
            [
                'label' => 'Archivos de aplicación y biblioteca',
                'ok' => is_readable(BASE_PATH . '/database/schema.sql') && is_readable(BASE_PATH . '/resources/sgsst-library/manifest.json'),
                'detail' => 'Esquema y 142 plantillas autocontenidas, sin ZIP documental.',
            ],
        ];

        return $checks;
    }

    public static function canInstall(): bool
    {
        foreach (self::requirements() as $check) {
            if (!$check['ok']) {
                return false;
            }
        }
        return true;
    }

    public static function createInitialSuperAdmin(?string $email = null, ?string $password = null, ?string $name = null): array
    {
        if (self::hasInitialSuperAdmin()) {
            return ['created' => false];
        }

        $email = text_lower(trim($email ?: (getenv('ADMIN_EMAIL') ?: 'admin@normia.local')));
        $password = $password ?: (getenv('ADMIN_PASSWORD') ?: self::randomPassword());
        $name = trim($name ?: (getenv('ADMIN_NAME') ?: 'Administrador de plataforma'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 10) {
            throw new RuntimeException('El correo o la contraseña inicial no cumple los requisitos mínimos.');
        }

        $id = User::create([
            'tenant_id' => null,
            'nombre' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => 'SuperAdmin',
        ]);

        return ['created' => true, 'id' => $id, 'email' => $email, 'password' => $password];
    }

    public static function defaultPlanId(): ?int
    {
        $plan = Database::first("SELECT id FROM planes_saas WHERE nombre = 'Profesional' AND activo = 1");
        return isset($plan['id']) ? (int) $plan['id'] : null;
    }

    private static function directoryWritable(string $directory): bool
    {
        if (is_dir($directory)) {
            return is_writable($directory);
        }
        $parent = dirname($directory);
        return is_dir($parent) && is_writable($parent);
    }

    private static function randomPassword(): string
    {
        return 'N!' . bin2hex(random_bytes(8)) . 'a9';
    }

    /** @return resource */
    private static function acquireInstallLock()
    {
        $directory = BASE_PATH . '/storage';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear la carpeta privada storage.');
        }
        $lock = @fopen($directory . '/.install.lock', 'c+');
        if ($lock === false) {
            throw new RuntimeException('No fue posible crear el bloqueo de instalación en storage.');
        }
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Hay otra instalación en curso. Espera un momento e inténtalo nuevamente.');
        }
        return $lock;
    }
}
