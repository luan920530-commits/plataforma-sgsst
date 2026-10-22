<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\PlanSaaS;
use App\Models\User;
use RuntimeException;

final class Installer
{
    public static function run(): void
    {
        Database::initialize();
        CatalogSeeder::seed();
    }

    public static function createInitialSuperAdmin(?string $email = null, ?string $password = null, ?string $name = null): array
    {
        if ((int) (Database::first("SELECT COUNT(*) AS total FROM users WHERE rol = 'SuperAdmin'")['total'] ?? 0) > 0) {
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

    private static function randomPassword(): string
    {
        return 'N!' . bin2hex(random_bytes(8)) . 'a9';
    }
}
