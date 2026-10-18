<?php
namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/config.php';
            $path   = $config['db']['path'];

            // Crear carpeta storage si no existe
            $dir = dirname($path);
            if (!is_dir($dir)) mkdir($dir, 0775, true);

            try {
                self::$instance = new PDO("sqlite:{$path}");
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec('PRAGMA foreign_keys = ON');
                self::$instance->exec('PRAGMA journal_mode = WAL');
                self::$instance->exec('PRAGMA synchronous = NORMAL');
            } catch (PDOException $e) {
                die('Error de conexión a BD: ' . $e->getMessage());
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $r = $stmt->fetch();
        return $r ?: null;
    }

    public static function execute(string $sql, array $params = []): bool
    {
        return self::get()->prepare($sql)->execute($params);
    }

    public static function lastId(): int
    {
        return (int) self::get()->lastInsertId();
    }
}
