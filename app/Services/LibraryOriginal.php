<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class LibraryOriginal
{
    public static function ensureAvailable(array $template): string
    {
        if (!preg_match('/^BIB-[A-F0-9]{12}$/', $template['id'])) {
            throw new RuntimeException('Identificador de original no válido.');
        }
        $extension = text_lower($template['extension']);
        if (!in_array($extension, ['docx', 'doc', 'xlsx', 'xls', 'pptx', 'pdf'], true)) {
            throw new RuntimeException('Tipo de original no permitido.');
        }
        $directory = BASE_PATH . '/storage/sgsst-library/originals';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el almacenamiento privado de la biblioteca.');
        }
        $path = $directory . '/' . $template['id'] . '.' . $extension;
        if (is_file($path) && hash_equals($template['sha256'], hash_file('sha256', $path))) {
            return $path;
        }
        $zipPath = trim((string) (getenv('SGSST_LIBRARY_ZIP') ?: ''));
        if ($zipPath === '') {
            throw new RuntimeException('El ZIP fuente no se incluye en el repositorio. Las versiones editables siguen disponibles; para habilitar nuevas extracciones, configura SGSST_LIBRARY_ZIP con una copia privada fuera de public/.');
        }
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            throw new RuntimeException('No se encontró o no se puede leer el ZIP privado configurado en SGSST_LIBRARY_ZIP. Las versiones editables siguen disponibles.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Habilita la extensión zip de PHP para recuperar los archivos originales. Las versiones editables siguen disponibles.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('No se encontró o no se pudo abrir el ZIP fuente de la biblioteca.');
        }
        try {
            $index = (int) ($template['sources'][0]['zip_index'] ?? -1);
            $content = $index >= 0 ? $zip->getFromIndex($index) : false;
            if ($content === false || $content === '') {
                throw new RuntimeException('El original no está disponible dentro del ZIP fuente.');
            }
        } finally {
            $zip->close();
        }
        if (strlen($content) !== (int) $template['bytes'] || !hash_equals($template['sha256'], hash('sha256', $content))) {
            throw new RuntimeException('La verificación SHA-256 del original no coincide; no se entregará el archivo.');
        }
        $temporary = tempnam($directory, '.original-');
        if ($temporary === false || file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('No fue posible guardar el original en almacenamiento privado.');
        }
        @chmod($temporary, 0640);
        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('No fue posible finalizar la extracción del original.');
        }
        return $path;
    }

    public static function mime(string $extension): string
    {
        return match (text_lower($extension)) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
