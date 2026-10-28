<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** Plantilla única del kit PR/FT-LD; el mismo JSON alimenta la app y los archivos DOCX/XLSX. */
final class CleaningProgramTemplate
{
    public static function kit(array $tenant): array
    {
        $documents = [];
        foreach (self::data()['documents'] as $document) {
            $documents[] = [
                'codigo' => $document['code'],
                'nombre' => $document['title'],
                'fase' => $document['phase'],
                'tipo' => $document['type'],
                'contenido' => DocumentTemplate::layout(
                    $tenant,
                    $document['code'],
                    $document['title'],
                    $document['phase'],
                    self::sections($document['sections'])
                ),
            ];
        }
        return $documents;
    }

    private static function data(): array
    {
        static $data = null;
        if (is_array($data)) {
            return $data;
        }
        $path = BASE_PATH . '/resources/cleaning-program/program.json';
        $json = @file_get_contents($path);
        try {
            $decoded = $json === false ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('La plantilla de limpieza y desinfección no tiene un JSON válido.', 0, $exception);
        }
        if (!is_array($decoded) || ($decoded['kit'] ?? '') !== 'limpieza_desinfeccion' || !is_array($decoded['documents'] ?? null)) {
            throw new RuntimeException('No se pudo leer la plantilla del programa de limpieza y desinfección.');
        }
        foreach (['program', 'execution', 'verification'] as $key) {
            $document = $decoded['documents'][$key] ?? null;
            if (!is_array($document) || !isset($document['code'], $document['title'], $document['type'], $document['phase'])
                || !is_array($document['sections'] ?? null)) {
                throw new RuntimeException('El kit de limpieza y desinfección está incompleto.');
            }
        }
        $data = $decoded;
        return $data;
    }

    private static function sections(array $sections): string
    {
        $html = '';
        foreach ($sections as $section) {
            $html .= '<h2>' . e($section['title']) . '</h2>';
            foreach ($section['paragraphs'] ?? [] as $paragraph) {
                $html .= '<p>' . e($paragraph) . '</p>';
            }
            foreach (['unordered' => 'ul', 'ordered' => 'ol'] as $key => $tag) {
                if (!empty($section[$key])) {
                    $html .= '<' . $tag . '>';
                    foreach ($section[$key] as $item) {
                        $html .= '<li>' . e($item) . '</li>';
                    }
                    $html .= '</' . $tag . '>';
                }
            }
            if (isset($section['table'])) {
                $html .= self::table($section['table']);
            }
        }
        return $html;
    }

    private static function table(array $table): string
    {
        $headers = $table['headers'] ?? [];
        if (!is_array($headers) || $headers === []) {
            throw new RuntimeException('Una tabla del programa de limpieza no tiene encabezados.');
        }
        $rows = $table['rows'] ?? [];
        $blankRows = (int) ($table['blank_rows'] ?? 0);
        if ($blankRows < 0) {
            throw new RuntimeException('La cantidad de filas del formato no es válida.');
        }
        for ($index = 0; $index < $blankRows; $index++) {
            $rows[] = array_fill(0, count($headers), '');
        }
        $html = '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . e($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== count($headers)) {
                throw new RuntimeException('Una fila del formato de limpieza no coincide con sus encabezados.');
            }
            $html .= '<tr>';
            foreach ($row as $cell) {
                $value = trim((string) $cell);
                $html .= '<td>' . ($value === '' ? '<br>' : e($value)) . '</td>';
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table>';
    }
}
