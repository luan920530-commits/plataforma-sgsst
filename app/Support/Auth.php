<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Models\User;

final class Auth
{
    private static ?array $user = null;

    public static function init(): void
    {
        self::$user = null;
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $lastActivity = (int) ($_SESSION['_last_activity'] ?? time());
        $lifetime = (int) ($GLOBALS['config']['session']['lifetime'] ?? 7200);
        if (time() - $lastActivity > $lifetime) {
            self::clearSession();
            flash('warning', 'Tu sesión venció por inactividad. Inicia sesión nuevamente.');
            return;
        }

        $user = User::findActive((int) $_SESSION['user_id']);
        if ($user === null) {
            self::clearSession();
            flash('error', 'La cuenta o la empresa ya no se encuentra activa.');
            return;
        }

        self::$user = $user;
        $_SESSION['_last_activity'] = time();
    }

    public static function attempt(string $email, string $password): bool
    {
        if (self::lockedSeconds() > 0) {
            return false;
        }

        $user = User::findForAuthentication(text_lower(trim($email)));
        if ($user === null || !password_verify($password, $user['password'])) {
            $_SESSION['_login_attempts'] = (int) ($_SESSION['_login_attempts'] ?? 0) + 1;
            if ($_SESSION['_login_attempts'] >= 5) {
                $_SESSION['_login_locked_until'] = time() + 300;
                $_SESSION['_login_attempts'] = 0;
            }
            return false;
        }

        session_regenerate_id(true);
        $intended = $_SESSION['_intended'] ?? null;
        $_SESSION = [];
        if ($intended !== null) {
            $_SESSION['_intended'] = $intended;
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['_last_activity'] = time();
        unset($_SESSION['_login_attempts'], $_SESSION['_login_locked_until']);

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }
        User::markLogin((int) $user['id']);
        self::$user = User::findActive((int) $user['id']);
        return true;
    }

    public static function lockedSeconds(): int
    {
        $lockedUntil = (int) ($_SESSION['_login_locked_until'] ?? 0);
        if ($lockedUntil <= time()) {
            unset($_SESSION['_login_locked_until']);
            return 0;
        }
        return $lockedUntil - time();
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user['id'] ?? null;
    }

    public static function tenantId(): ?int
    {
        return isset(self::$user['tenant_id']) ? (int) self::$user['tenant_id'] : null;
    }

    public static function role(): ?string
    {
        return self::$user['rol'] ?? null;
    }

    public static function hasRole(array|string $roles): bool
    {
        return self::check() && in_array(self::role(), (array) $roles, true);
    }

    public static function canManageTenant(): bool
    {
        return self::hasRole(['AdminEmpresa', 'ResponsableSST']);
    }

    public static function logout(): void
    {
        self::clearSession();
        self::$user = null;
    }

    private static function clearSession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
