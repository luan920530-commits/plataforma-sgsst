<?php
declare(strict_types=1);

namespace App\Services;

/** Descargas editables por documento y paquete completo, con alternativa HTML cuando DOM no está disponible. */
final class DocumentPackageExporter
{
    public static function document(array $document): array
    {
        $base = self::filename($document['codigo_estandar'] . '_' . $document['nombre_documento']);
        if (DocxDocument::available()) {
            return [
                'filename' => $base . '.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'content' => DocxDocument::fromHtml($document['contenido_html']),
                'format' => 'docx',
            ];
        }
        return [
            'filename' => $base . '.html', 'mime' => 'text/html; charset=UTF-8',
            'content' => self::standaloneHtml($document), 'format' => 'html',
        ];
    }

    public static function package(array $tenant, array $documents): array
    {
        if ($documents === []) {
            throw new \RuntimeException('No hay documentos para exportar en este módulo.');
        }
        $zip = new ZipBuilder();
        $docx = DocxDocument::available();
        $phaseDirectories = ['Planear' => '01_Planear', 'Hacer' => '02_Hacer', 'Verificar' => '03_Verificar', 'Actuar' => '04_Actuar'];
        $usedNames = [];
        foreach ($documents as $document) {
            $directory = $phaseDirectories[$document['fase_phva']] ?? '05_Otros';
            $base = self::filename($document['codigo_estandar'] . '_' . $document['nombre_documento']);
            $candidate = $base;
            $suffix = 2;
            while (isset($usedNames[$directory . '/' . $candidate])) {
                $candidate = $base . '_' . $suffix++;
            }
            $usedNames[$directory . '/' . $candidate] = true;
            $path = $directory . '/' . $candidate . ($docx ? '.docx' : '.html');
            $content = $docx ? DocxDocument::fromHtml($document['contenido_html']) : self::standaloneHtml($document);
            $zip->add($path, $content);
        }
        $zip->add('00_Cobertura/cobertura_documental.csv', self::coverageCsv($documents));
        $zip->add('LEEME.txt', self::readme($tenant, $documents, $docx));
        return [
            'filename' => 'Paquete_SG-SST_' . self::filename((string) $tenant['nit']) . '.zip',
            'mime' => 'application/zip', 'content' => $zip->build(), 'format' => 'zip',
        ];
    }

    private static function standaloneHtml(array $document): string
    {
        return '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' . e($document['nombre_documento']) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:1100px;margin:32px auto;color:#172033;line-height:1.45}table{border-collapse:collapse;width:100%;margin:12px 0}th,td{border:1px solid #aab4c0;padding:7px;vertical-align:top}th{background:#eaf0f6}h1,h2,h3{color:#163b65}.document-notice{border:1px solid #d9a62e;padding:10px}</style></head><body>'
            . $document['contenido_html'] . '</body></html>';
    }

    private static function coverageCsv(array $documents): string
    {
        $csv = "\xEF\xBB\xBF" . self::csvRow(['Código', 'Documento', 'Tipo', 'Fase PHVA', 'Estado documental', 'Versión', 'Adaptación / implementación']);
        foreach ($documents as $document) {
            $csv .= self::csvRow([
                $document['codigo_estandar'], $document['nombre_documento'], $document['tipo'], $document['fase_phva'],
                $document['estado'], $document['version'], 'Pendiente de validación y evidencia por la empresa',
            ]);
        }
        return $csv;
    }

    private static function csvRow(array $values): string
    {
        return implode(';', array_map(static fn (mixed $value): string => '"' . str_replace('"', '""', (string) $value) . '"', $values)) . "\r\n";
    }

    private static function readme(array $tenant, array $documents, bool $docx): string
    {
        $approved = count(array_filter($documents, static fn (array $document): bool => $document['estado'] === 'Aprobado'));
        return "PAQUETE DE INICIO SG-SST\n"
            . 'Empresa: ' . $tenant['razonsocial'] . "\nNIT: " . $tenant['nit'] . "\n"
            . 'Régimen: ' . $tenant['grupo_estandares'] . " estándares\nDocumentos: " . count($documents) . "\n"
            . 'Estados registrados: ' . $approved . " aprobados y " . (count($documents) - $approved) . " en otro estado\n\n"
            . 'FORMATO: ' . ($docx ? 'DOCX editable sin macros.' : 'HTML editable porque DOM no está disponible en este hosting.') . "\n"
            . "La cobertura CSV separa el estado documental de la implementación.\n"
            . "La generación o descarga no acredita adaptación, aprobación, ejecución, mediciones, capacitaciones, inspecciones, firmas ni cumplimiento legal.\n"
            . "Valide cada anexo con los peligros, centros y tareas reales. Conserve los soportes en sus módulos y proteja la información clínica o de convivencia.\n";
    }

    private static function filename(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? 'documento';
        return trim(substr($value, 0, 120), '._-') ?: 'documento';
    }
}
