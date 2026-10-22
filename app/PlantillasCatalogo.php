<?php
namespace App;

/** Catálogo documental maestro: escanea plantillas/SG-SST_POR_ESTANDARES-V (60 estándares). */
class PlantillasCatalogo
{
    public static function rutaBase(): string
    {
        return BASE_PATH . '/plantillas/SG-SST_POR_ESTANDARES-V';
    }

    /** @return array<int, array{numero:string, carpeta:string, estandar:string, archivos:array}> */
    public static function listar(): array
    {
        $base = self::rutaBase();
        if (!is_dir($base)) return [];
        $grupos = [];
        foreach (scandir($base) ?: [] as $carpeta) {
            if ($carpeta === '.' || $carpeta === '..' || !is_dir($base . '/' . $carpeta)) continue;
            $archivos = [];
            foreach (scandir($base . '/' . $carpeta) ?: [] as $f) {
                $ruta = $base . '/' . $carpeta . '/' . $f;
                if (!is_file($ruta)) continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                $archivos[] = [
                    'nombre' => $f, 'relativo' => $carpeta . '/' . $f, 'ext' => $ext,
                    'kb' => (int) round(filesize($ruta) / 1024),
                    'tipo' => self::tipo($f, $ext),
                    'generable' => in_array($ext, ['docx', 'xlsx'], true),
                    'legado' => in_array($ext, ['doc', 'xls'], true),
                ];
            }
            usort($archivos, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
            $numero = preg_match('/^([\d,\sAYL]+)-/u', $carpeta, $m) ? trim($m[1]) : '';
            $grupos[] = [
                'numero' => $numero, 'carpeta' => $carpeta,
                'estandar' => trim(preg_replace('/^[\d,\sAYL]+-/u', '', $carpeta) ?? $carpeta),
                'archivos' => $archivos,
            ];
        }
        usort($grupos, fn($a, $b) => ((int)$a['numero'] <=> (int)$b['numero']) ?: strcmp($a['carpeta'], $b['carpeta']));
        return $grupos;
    }

    public static function tipo(string $nombre, string $ext): string
    {
        $n = mb_strtoupper($nombre);
        if ($ext === 'pptx' || $ext === 'pdf') return 'Material de consulta';
        if (str_contains($n, 'MATRIZ')) return 'Matriz';
        if (preg_match('/FORMATO|ACTA|REGISTRO|LISTADO|CONTROL|ENCUESTA|REMISI|EVALUACI|INSPECCI|RECOLECCI|ENTREGA|QUEJA/', $n)) return 'Formato / registro';
        if (str_contains($n, 'POLITICA') || str_contains($n, 'POLÍTICA')) return 'Política';
        if (str_contains($n, 'PROCEDIMIENTO') || str_contains($n, 'PROTOCOLO')) return 'Procedimiento';
        if (str_contains($n, 'PROGRAMA')) return 'Programa';
        if (str_contains($n, 'MANUAL')) return 'Manual';
        return 'Documento';
    }

    /** Resuelve una ruta relativa del catálogo impidiendo salir de la carpeta (anti path-traversal). */
    public static function resolver(string $relativo): ?string
    {
        $relativo = str_replace('\\', '/', $relativo);
        if (str_contains($relativo, '..')) return null;
        $ruta = self::rutaBase() . '/' . ltrim($relativo, '/');
        $real = realpath($ruta);
        $base = realpath(self::rutaBase());
        return ($real && $base && str_starts_with($real, $base) && is_file($real)) ? $real : null;
    }
}
