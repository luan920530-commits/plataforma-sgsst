<?php
/**
 * Instalador — SOLO para la primera instalación.
 * - CLI:  php setup.php
 * - Web:  define SETUP_TOKEN en el entorno y abre setup.php?token=TU_TOKEN
 * La contraseña inicial se toma de ADMIN_PASSWORD; si no existe, se genera una
 * aleatoria que se muestra UNA sola vez. El usuario deberá cambiarla al ingresar.
 * Si la base de datos ya existe, este script no hace nada.
 */
$config = require __DIR__ . '/config/config.php';
$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $token = getenv('SETUP_TOKEN') ?: '';
    if ($token === '' || !hash_equals($token, $_GET['token'] ?? '')) {
        http_response_code(403);
        exit('Instalador bloqueado. Define SETUP_TOKEN en el entorno o usa la CLI: php setup.php');
    }
}

foreach ([__DIR__ . '/storage', __DIR__ . '/storage/documentos', __DIR__ . '/storage/logs', __DIR__ . '/public/uploads/firmas'] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0775, true);
}

$dbPath = $config['db']['path'];
if (file_exists($dbPath)) {
    echo "La base de datos ya existe. Instalador sin cambios. Borra storage/database.sqlite solo si quieres reinstalar desde cero.\n";
    exit;
}

$pdo = new PDO("sqlite:{$dbPath}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

// Migraciones del módulo SG-SST (tablas nuevas + must_change_password)
require __DIR__ . '/app/Helpers.php';
\App\Migrator::run();

$password = getenv('ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
$stmt = $pdo->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol, must_change_password) VALUES (?,?,?,?,1)');
$stmt->execute([getenv('ADMIN_EMAIL') ?: 'admin@ppprae.local', password_hash($password, PASSWORD_DEFAULT), 'Administrador', 'superadmin']);

echo "Instalación completa.\nUsuario: " . (getenv('ADMIN_EMAIL') ?: 'admin@ppprae.local') . "\n";
echo "Contraseña inicial (se muestra una sola vez, cámbiala al ingresar): {$password}\n";
echo "IMPORTANTE: elimina o bloquea setup.php en el servidor de producción.\n";
