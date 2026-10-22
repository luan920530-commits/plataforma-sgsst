<?php
namespace App;

use ZipArchive;

/**
 * Extrae el contenido legible de un documento generado (DOCX/XLSX) y lo
 * presenta ya diligenciado con los datos de la empresa, para verlo e
 * imprimirlo en pantalla sin descargar Word/Excel.
 */
class DocumentoVista
{
    /** @return array<int,string> Párrafos (DOCX) o filas "celda | celda" (XLSX) */
    public static function extraer(string $ruta, array $empresa): array
    {
        if (!is_file($ruta)) return [];
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        GeneradorPlantilla::reiniciarSecuencias();
        if ($ext === 'docx') return self::deDocx($ruta, $empresa);
        if ($ext === 'xlsx') return self::deXlsx($ruta, $empresa);
        return [];
    }

    private static function deDocx(string $ruta, array $empresa): array
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) return [];
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) return [];
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) return [];
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $salida = [];
        // Párrafos y celdas de tabla en orden de aparición
        foreach ($xp->query('//w:body//w:p') as $p) {
            $texto = '';
            foreach ($xp->query('.//w:t', $p) as $t) $texto .= $t->nodeValue;
            $texto = trim(preg_replace('/\s+/', ' ', GeneradorPlantilla::reemplazarEnTexto($texto, $empresa)) ?? '');
            if ($texto !== '') $salida[] = $texto;
        }
        return array_slice($salida, 0, 400);
    }

    private static function deXlsx(string $ruta, array $empresa): array
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) return [];
        $compartidos = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $m)) {
            foreach ($m[1] as $si) {
                preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $t);
                $compartidos[] = html_entity_decode(implode('', $t[1]), ENT_XML1 | ENT_COMPAT, 'UTF-8');
            }
        }
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($hoja === false) return [];
        $salida = [];
        if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $hoja, $filas)) {
            foreach ($filas[1] as $fila) {
                $celdas = [];
                if (preg_match_all('/<c[^>]*?(?:\st="(?P<tipo>[^"]*)")?[^>]*>(?:<v>(?P<v>[^<]*)<\/v>|<is><t[^>]*>(?P<is>[^<]*)<\/t><\/is>)?/s', $fila, $cs, PREG_SET_ORDER)) {
                    foreach ($cs as $c) {
                        $valor = ($c['tipo'] ?? '') === 's' && isset($c['v']) && $c['v'] !== ''
                            ? ($compartidos[(int) $c['v']] ?? '')
                            : ($c['is'] ?? ($c['v'] ?? ''));
                        $valor = trim(html_entity_decode((string) $valor, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
                        if ($valor !== '') $celdas[] = GeneradorPlantilla::reemplazarEnTexto($valor, $empresa);
                    }
                }
                if ($celdas) $salida[] = implode('  |  ', $celdas);
                if (count($salida) >= 120) break;
            }
        }
        return $salida;
    }

    /** ¿El nombre del documento sugiere firma (políticas, asignaciones, actas…)? */
    public static function sugiereFirma(string $nombre): bool
    {
        $n = mb_strtoupper($nombre);
        foreach (['POLITICA', 'POLÍTICA', 'ASIGNACION', 'ASIGNACIÓN', 'OBJETIVOS', 'ACTA', 'RENDICION', 'RENDICIÓN', 'REGLAMENTO', 'COMPROMISO', 'DESIGNACION', 'DESIGNACIÓN', 'CONFORMACION', 'CONFORMACIÓN'] as $k) {
            if (str_contains($n, $k)) return true;
        }
        return false;
    }
}
