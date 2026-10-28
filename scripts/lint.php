<?php
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'wasm'], true)) {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$directories = ['app', 'config', 'database', 'public', 'scripts', 'tests'];
$checked = 0;
$failed = [];
foreach ($directories as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        try {
            token_get_all((string) file_get_contents($file->getPathname()), TOKEN_PARSE);
            $checked++;
        } catch (ParseError $error) {
            $failed[] = $file->getPathname() . ': ' . $error->getMessage();
        }
    }
}
if ($failed !== []) {
    file_put_contents('php://stderr', implode("\n", $failed) . "\n");
    exit(1);
}
echo "Sintaxis PHP verificada en {$checked} archivos.\n";
