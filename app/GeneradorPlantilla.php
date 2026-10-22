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
        foreach ($entradas as $entrada) {
            $xml = $zip->getFromName($entrada);
            if ($xml === false) continue;
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
