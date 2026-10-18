<?php
// http://localhost/ppprae/setup.php
$config = require __DIR__ . '/config/config.php';

// 1. Crear carpetas
foreach ([
    __DIR__ . '/storage',
    __DIR__ . '/storage/documentos',
    __DIR__ . '/storage/logs',
    __DIR__ . '/public/uploads/firmas',
] as $dir) {
    if (!is_dir($dir)) mkdir($dir, 0775, true);
}

// 2. Crear SQLite y ejecutar schema
$dbPath = $config['db']['path'];
$nueva  = !file_exists($dbPath);
$pdo    = new PDO("sqlite:{$dbPath}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($nueva) {
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($sql);

    // Usuario admin por defecto
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (email, password_hash, nombre, rol) VALUES (?,?,?,?)');
    $stmt->execute(['admin@ppprae.local', $hash, 'Administrador', 'superadmin']);

    echo "<h1>✅ Instalación completa</h1>";
    echo "<p>Usuario: <b>admin@ppprae.local</b><br>Contraseña: <b>admin123</b></p>";
    echo "<p><a href='public/index.php'>Ir al login</a></p>";
} else {
    echo "<h1>ℹ️ La base de datos ya existe</h1>";
    echo "<p><a href='public/index.php'>Ir al login</a></p>";
    echo "<p>Si quieres reinstalar, borra <code>storage/database.sqlite</code>.</p>";
}
