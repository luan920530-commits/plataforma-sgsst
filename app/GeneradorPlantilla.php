<?php
namespace App;

use ZipArchive;

/**
 * Diligencia una plantilla real (.docx/.xlsx) con los datos de la empresa.
 * Estrategias, en orden:
 *  1) Marcadores explícitos {{EMPRESA}}, {{NIT}}, {{REPRESENTANTE_LEGAL}}, {{ACTIVIDAD}},
 *     {{CIUDAD}}, {{FECHA}}, {{ANIO}} (para cuando las plantillas se vayan normalizando).
 *  2) Secuencias de X (XXX, XXXXX…) de documentos conocidos (Política, Objetivos):
 *     se reemplazan en orden por empresa → NIT → actividad/año → representante legal.
 *  3) Residuos de ejemplo detectados (p. ej. «Coca Cola» al final de Política/Objetivos).
 * Las líneas de firma (______) se conservan: el documento se firma en físico/electrónico.
 * El archivo original NUNCA se modifica: se trabaja sobre una copia en storage/.
 */
class GeneradorPlantilla
{
    private static int $ix = 0;
    private static int $iu = 0;

    /** Valores de la empresa en texto plano (sin escapar). */
    public static function valores(array $empresa): array
    {
        return [
            'empresa' => (string)($empresa['razon_social'] ?? ''), 'nit' => (string)($empresa['nit'] ?? ''),
            'representante' => (string)($empresa['representante_legal'] ?? ''), 'representante_cc' => (string)($empresa['representante_cc'] ?? ''),
            'resp_sgsst' => (string)($empresa['resp_sgsst_nombre'] ?? ''), 'resp_sgsst_cc' => (string)($empresa['resp_sgsst_cc'] ?? ''),
            'actividad' => (string)($empresa['actividad_economica'] ?? ''), 'ciudad' => (string)($empresa['ciudad'] ?? ''),
            'direccion' => (string)($empresa['direccion'] ?? ''), 'arl' => (string)($empresa['arl'] ?? ''),
            'fecha' => date('d/m/Y'), 'anio' => date('Y'),
        ];
    }

    /**
     * Reemplaza en un texto los espacios en blanco de las plantillas (XXXX, ____, 20xx,
     * Coca Cola) por los datos reales. Los contadores son secuenciales por documento.
     * Las líneas de firma (solo guiones bajos) se conservan intactas.
     */
    public static function reemplazarEnTexto(string $texto, array $empresa): string
    {
        $v = self::valores($empresa);
        $texto = strtr($texto, [
            '{{EMPRESA}}' => $v['empresa'], '{{RAZON_SOCIAL}}' => $v['empresa'], '{{NIT}}' => $v['nit'],
            '{{REPRESENTANTE_LEGAL}}' => $v['representante'], '{{ACTIVIDAD}}' => $v['actividad'],
            '{{ACTIVIDAD_ECONOMICA}}' => $v['actividad'], '{{CIUDAD}}' => $v['ciudad'], '{{DIRECCION}}' => $v['direccion'],
            '{{TELEFONO}}' => (string)($empresa['telefono'] ?? ''), '{{EMAIL}}' => (string)($empresa['email'] ?? ''),
            '{{ARL}}' => $v['arl'], '{{NIVEL_RIESGO}}' => (string)($empresa['nivel_riesgo'] ?? ''),
            '{{NUM_TRABAJADORES}}' => (string)($empresa['num_trabajadores'] ?? ''), '{{FECHA}}' => $v['fecha'],
            '{{ANIO}}' => $v['anio'], '{{AÑO}}' => $v['anio'], 'Coca Cola' => $v['empresa'],
        ]);
        $seqX = [$v['empresa'], $v['nit'], $v['actividad'], $v['empresa'], $v['representante'], $v['anio'], $v['ciudad'], $v['resp_sgsst'], $v['arl'], $v['direccion']];
        $texto = preg_replace_callback('/[Xx]{3,}/', function ($m) use ($seqX, $v) {
            $valor = $seqX[self::$ix] ?? $v['empresa'];
            self::$ix++;
            return $valor !== '' ? $valor : $m[0];
        }, $texto) ?? $texto;
        // Espacios con guiones bajos SOLO si el párrafo tiene más texto (no tocar líneas de firma)
        if (trim(str_replace(['_', ' '], '', $texto)) !== '') {
            $seqU = [$v['resp_sgsst'], $v['resp_sgsst_cc'], $v['representante'], $v['representante_cc'], $v['ciudad'], $v['fecha'], $v['anio'], $v['empresa']];
            $texto = preg_replace_callback('/_{4,}/', function ($m) use ($seqU) {
                $valor = $seqU[self::$iu] ?? '';
                self::$iu++;
                return $valor !== '' ? $valor : $m[0];
            }, $texto) ?? $texto;
        }
        $texto = str_ireplace('20xx', $v['anio'], $texto);
        return $texto;
    }

    /** Reinicia las secuencias (al empezar cada documento). */
    public static function reiniciarSecuencias(): void { self::$ix = 0; self::$iu = 0; }

    /**
     * DOCX: los XXXX de las plantillas vienen partidos en varios <w:t> (runs),
     * por eso un str_replace sobre el XML no los veía. Aquí se une el texto de
     * cada párrafo, se reemplaza y se devuelve al primer run.
     */
    private static function procesarDocx(string $xml, array $empresa): string
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) return $xml;
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        foreach ($xp->query('//w:p') as $p) {
            $ts = $xp->query('.//w:t', $p);
            if ($ts->length === 0) continue;
            $texto = '';
            foreach ($ts as $t) $texto .= $t->nodeValue;
            if (!preg_match('/[Xx]{3,}|_{4,}|\{\{|Coca Cola|20xx/i', $texto)) continue;
            $nuevo = self::reemplazarEnTexto($texto, $empresa);
            if ($nuevo === $texto) continue;
            $ts->item(0)->nodeValue = $nuevo;
            for ($i = 1; $i < $ts->length; $i++) $ts->item($i)->nodeValue = '';
        }
        return $dom->saveXML() ?: $xml;
    }

    public static function generar(string $rutaPlantilla, array $empresa, int $empresaId): array
    {
        $config = require BASE_PATH . '/config/config.php';
        $dir = $config['storage']['documentos'] . 'sgsst/' . $empresaId . '/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $ext = strtolower(pathinfo($rutaPlantilla, PATHINFO_EXTENSION));
        $base = pathinfo($rutaPlantilla, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[^A-Za-z0-9áéíóúÁÉÍÓÚñÑ _-]/u', '', $base) ?? 'documento');
        $nombre = $base . ' — ' . ($empresa['razon_social'] ?? 'empresa') . ' ' . date('Ymd_His') . '.' . $ext;
        $nombre = preg_replace('/[^A-Za-z0-9áéíóúÁÉÍÓÚñÑ _.\-]/u', '', $nombre) ?? ('documento.' . $ext);
        $destino = $dir . $nombre;
        if (!copy($rutaPlantilla, $destino)) throw new \RuntimeException('No se pudo copiar la plantilla');

        $zip = new ZipArchive();
        if ($zip->open($destino) !== true) throw new \RuntimeException('No se pudo abrir el documento generado');

        $datos = [
            '{{EMPRESA}}' => (string)($empresa['razon_social'] ?? ''),
            '{{RAZON_SOCIAL}}' => (string)($empresa['razon_social'] ?? ''),
            '{{NIT}}' => (string)($empresa['nit'] ?? ''),
            '{{REPRESENTANTE_LEGAL}}' => (string)($empresa['representante_legal'] ?? ''),
            '{{ACTIVIDAD}}' => (string)($empresa['actividad_economica'] ?? ''),
            '{{ACTIVIDAD_ECONOMICA}}' => (string)($empresa['actividad_economica'] ?? ''),
            '{{CIUDAD}}' => (string)($empresa['ciudad'] ?? ''),
            '{{DIRECCION}}' => (string)($empresa['direccion'] ?? ''),
            '{{TELEFONO}}' => (string)($empresa['telefono'] ?? ''),
            '{{EMAIL}}' => (string)($empresa['email'] ?? ''),
            '{{ARL}}' => (string)($empresa['arl'] ?? ''),
            '{{NIVEL_RIESGO}}' => (string)($empresa['nivel_riesgo'] ?? ''),
            '{{NUM_TRABAJADORES}}' => (string)($empresa['num_trabajadores'] ?? ''),
            '{{FECHA}}' => date('d/m/Y'),
            '{{ANIO}}' => date('Y'), '{{AÑO}}' => date('Y'),
        ];

        // Escapar para XML: un & o < en la razón social rompería el documento
        foreach ($datos as $k => $v) $datos[$k] = htmlspecialchars((string) $v, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        $entradas = $ext === 'docx'
            ? ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml']
            : ['xl/sharedStrings.xml'];

        $nombreArchivo = mb_strtoupper(basename($rutaPlantilla));
        self::reiniciarSecuencias();
        foreach ($entradas as $entrada) {
            $xml = $zip->getFromName($entrada);
            if ($xml === false) continue;
            if ($ext === 'docx') { $zip->addFromString($entrada, self::procesarDocx($xml, $empresa)); continue; }
            $xml = strtr($xml, $datos);
            $xml = str_replace('Coca Cola', $datos['{{EMPRESA}}'], $xml);
            if (str_contains($nombreArchivo, 'POLITICA') || str_contains($nombreArchivo, 'POLÍTICA')) {
                $xml = self::secuenciaX($xml, [
                    $datos['{{EMPRESA}}'], $datos['{{NIT}}'], $datos['{{ACTIVIDAD}}'],
                    $datos['{{EMPRESA}}'], $datos['{{REPRESENTANTE_LEGAL}}'], '',
                ]);
            } elseif (str_contains($nombreArchivo, 'OBJETIVOS DEL SG-SST.docx')) {
                $xml = self::secuenciaX($xml, [
                    $datos['{{EMPRESA}}'], $datos['{{NIT}}'], date('Y'),
                    $datos['{{REPRESENTANTE_LEGAL}}'], '',
                ]);
            } elseif ($ext === 'xlsx' && str_contains($nombreArchivo, 'PLAN ANUAL')) {
                $xml = preg_replace('/\b20(19|20|21|22|23|24|25)\b/', date('Y'), $xml) ?? $xml;
            }
            $zip->addFromString($entrada, $xml);
        }
        $zip->close();
        return ['ruta' => $destino, 'nombre' => $nombre];
    }

    /** Reemplaza las corridas de 4+ X/x en orden por los valores dados (las sobrantes quedan intactas). */
    private static function secuenciaX(string $xml, array $valores): string
    {
        $i = 0;
        return preg_replace_callback('/[Xx]{4,}/', function ($m) use (&$i, $valores) {
            if ($i >= count($valores)) return $m[0];
            return htmlspecialchars((string)$valores[$i++], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }, $xml) ?? $xml;
    }
}
