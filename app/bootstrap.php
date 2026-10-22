<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

App\Core\Env::load(BASE_PATH . '/.env');
require BASE_PATH . '/app/Support/helpers.php';

$config = $GLOBALS['config'] = require BASE_PATH . '/config/app.php';
date_default_timezone_set($GLOBALS['config']['timezone']);

if ($GLOBALS['config']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name($GLOBALS['config']['session']['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'");
    header('Cache-Control: no-store, private');

    set_exception_handler(static function (Throwable $exception) use ($config): void {
        error_log((string) $exception);
        http_response_code(500);
        $status = 500;
        $title = 'Algo no salió como esperábamos';
        $message = $config['debug']
            ? $exception->getMessage() . ' · ' . $exception->getFile() . ':' . $exception->getLine()
            : 'Presentamos un inconveniente temporal. Inténtalo nuevamente; el equipo técnico ya puede revisar el registro del error.';
        $contentView = BASE_PATH . '/app/Views/errors/error.php';
        $user = \App\Support\Auth::user();
        require BASE_PATH . '/app/Views/layouts/error.php';
        exit;
    });
}
