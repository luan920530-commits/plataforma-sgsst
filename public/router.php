<?php
declare(strict_types=1);

// Router exclusivo para el servidor de desarrollo: php -S 0.0.0.0:8000 -t public public/router.php
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $file = realpath(__DIR__ . $path);
    if ($path !== '/' && $file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
