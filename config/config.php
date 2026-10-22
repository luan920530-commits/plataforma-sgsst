<?php
// Configuración por entorno: define variables en el servidor (APP_ENV, APP_DEBUG, APP_URL)
// o crea un archivo .env en la raíz (no versionado). Nunca uses debug en producción.
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (getenv($k) === false) putenv("{$k}={$v}");
    }
}
$env = getenv('APP_ENV') ?: 'local';
return [
    'app' => [
        'nombre'   => 'Plataforma SG-SST',
        'env'      => $env,
        'url'      => getenv('APP_URL') ?: 'http://localhost/ppprae/public',
        'debug'    => filter_var(getenv('APP_DEBUG') ?: ($env !== 'production'), FILTER_VALIDATE_BOOL),
        'timezone' => getenv('APP_TIMEZONE') ?: 'America/Bogota',
        'moneda'   => 'COP',
    ],
    'db' => ['path' => getenv('DB_PATH') ?: __DIR__ . '/../storage/database.sqlite'],
    'storage' => [
        'documentos' => __DIR__ . '/../storage/documentos/',
        'firmas'     => __DIR__ . '/../public/uploads/firmas/',
    ],
    'session' => ['nombre' => 'PPPRAE_SESS', 'lifetime' => 7200],
];
