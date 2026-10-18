<?php
namespace App;

class Auth
{
    private static ?array $user = null;

    public static function init(): void
    {
        if (isset($_SESSION['user_id'])) {
            self::$user = Database::first(
                'SELECT id, email, nombre, rol FROM usuarios WHERE id = ? AND activo = 1',
                [$_SESSION['user_id']]
            );
        }
    }

    public static function check(): bool  { return self::$user !== null; }
    public static function user(): ?array { return self::$user; }
    public static function id(): ?int     { return self::$user['id'] ?? null; }

    public static function login(string $email, string $password): bool
    {
        $u = Database::first('SELECT * FROM usuarios WHERE email = ? AND activo = 1', [$email]);
        if (!$u || !password_verify($password, $u['password_hash'])) return false;

        session_regenerate_id(true);
        $_SESSION['user_id'] = $u['id'];
        self::$user = $u;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }
}
