<?php
declare(strict_types=1);

namespace App\Services;

final class CsvExport
{
    public static function download(string $filename, array $headers, array $rows): never
    {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) . '"');
        header('Cache-Control: private, no-store');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($output, array_map([self::class, 'safe'], $row), ';');
        }
        fclose($output);
        exit;
    }

    private static function safe(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) return $value;
        $text = (string) $value;
        return preg_match('/^[\s]*[=+\-@]/', $text) ? "'" . $text : $text;
    }
}
