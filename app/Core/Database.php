<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = require BASE_PATH . '/config/database.php';
        $path = $config['path'];
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio de la base de datos.');
        }

        self::$connection = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::$connection->exec('PRAGMA busy_timeout = ' . (int) $config['busy_timeout']);
        self::$connection->exec('PRAGMA journal_mode = WAL');
        self::$connection->exec('PRAGMA synchronous = NORMAL');

        return self::$connection;
    }

    public static function query(string $sql, array $params = []): array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function isInstalled(): bool
    {
        $config = require BASE_PATH . '/config/database.php';
        if (!is_file($config['path'])) {
            return false;
        }

        try {
            return self::first("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'tenants'") !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public static function initialize(): void
    {
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('No se encontró database/schema.sql.');
        }
        self::connection()->exec($schema);
    }

    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
