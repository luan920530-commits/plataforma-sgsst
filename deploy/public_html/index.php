<?php
declare(strict_types=1);

/* Cargador compatible con el hosting: valida lo básico antes de cargar la aplicación. */
$errors = [];
if (PHP_VERSION_ID < 80100) {
    $errors[] = 'La versión activa es PHP ' . PHP_VERSION . '. Selecciona PHP 8.1 o superior en DirectAdmin.';
}
foreach (['pdo_sqlite' => 'PDO SQLite', 'dom' => 'DOM', 'libxml' => 'libxml', 'json' => 'JSON', 'session' => 'Sesiones PHP', 'fileinfo' => 'Fileinfo'] as $extension => $label) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Falta la extensión ' . $label . ' (' . $extension . '). Habilítala en Select PHP Version.';
    }
}

$entry = dirname(__DIR__) . '/normia-sst/public/index.php';
if (!is_file($entry)) {
    $errors[] = 'No se encontró la aplicación privada. Extrae el ZIP completo en la carpeta del dominio, un nivel encima de public_html.';
}

if ($errors !== []) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Normia SST · Revisión del servidor</title>';
    echo '<style>body{margin:0;background:#f5f5f7;color:#172033;font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:720px;margin:8vh auto;padding:32px}section{background:#fff;border:1px solid #e7eaf0;border-radius:24px;padding:32px;box-shadow:0 16px 50px #22335512}h1{font-size:28px;line-height:1.2;margin:0 0 12px}p{color:#667085}li{margin:12px 0}code{background:#f2f4f7;padding:2px 6px;border-radius:6px}.brand{color:#0866d8;font-weight:700;margin-bottom:24px}</style></head><body><main><section><div class="brand">Normia SST</div><h1>El servidor necesita un ajuste</h1><p>La aplicación no se inició para evitar un error 500 genérico. Resuelve estos puntos y recarga:</p><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
    }
    echo '</ul><p>No necesitas Composer, Node.js, MySQL ni comandos de Terminal. Si el hosting no ofrece estas extensiones, solicita a soporte que habilite PHP 8.1+ con SQLite.</p></section></main></body></html>';
    exit;
}

require $entry;
