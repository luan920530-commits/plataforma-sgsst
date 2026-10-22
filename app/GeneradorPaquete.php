<?php
namespace App;

/**
 * Al crear una empresa genera en su BD TODO el paquete documental que le exige
 * su tamaño/riesgo: una copia por archivo aplicable del paquete SG-SST,
 * diligenciada con los datos de la empresa (formatos modernos) o copiada tal
 * cual (formatos antiguos/material de consulta), en estado «pendiente_revision».
 * El cliente decide después: lo deja así (aceptar), lo regenera tras corregir
 * datos, o sube su versión corregida. Idempotente: no duplica el paquete.
 */
class GeneradorPaquete
{
    /** Carpetas (números de estándar) cubiertas por una carpeta del paquete. */
    public static function numerosDeCarpeta(string $numeroTexto): array
    {
        if (preg_match('/(\d+)\s+AL\s+(\d+)/i', $numeroTexto, $m)) {
            $nums = range((int)$m[1], (int)$m[2]);
            foreach (preg_match_all('/\d+/', str_replace($m[0], '', $numeroTexto), $extra) ? [$extra[0]] : [] as $e) {
                foreach ((array)$e as $n) $nums[] = (int)$n;
            }
            return $nums;
        }
        return array_map('intval', preg_match_all('/\d+/', $numeroTexto, $m) ? $m[0] : []);
    }

    public static function yaGenerado(int $empresaId): int
    {
        return (int)(Database::first('SELECT COUNT(*) c FROM sgsst_documentos WHERE empresa_id = ? AND carpeta IS NOT NULL', [$empresaId])['c'] ?? 0);
    }

    /** @return array{generados:int, copiados:int, total:int} */
    public static function generar(array $empresa, ?int $usuarioId, bool $forzar = false): array
    {
        $eid = (int)$empresa['id'];
        if (!$forzar && self::yaGenerado($eid) > 0) {
            return ['generados' => 0, 'copiados' => 0, 'total' => self::yaGenerado($eid)];
        }
        $req = Sgsst::requisitos($empresa);
        $aplicables = array_map('intval', $req['carpetas'] ?? []);
        $config = require BASE_PATH . '/config/config.php';
        $dirOriginales = $config['storage']['documentos'] . 'sgsst/' . $eid . '/originales/';
        if (!is_dir($dirOriginales)) mkdir($dirOriginales, 0775, true);

        $generados = 0; $copiados = 0;
        foreach (PlantillasCatalogo::listar() as $grupo) {
            $nums = self::numerosDeCarpeta((string)$grupo['numero']);
            if (!$nums || !array_intersect($nums, $aplicables)) continue;
            foreach ($grupo['archivos'] as $a) {
                $rutaOrigen = PlantillasCatalogo::resolver($a['relativo']);
                if (!$rutaOrigen) continue;
                try {
                    if (!empty($a['generable'])) {
                        $res = GeneradorPlantilla::generar($rutaOrigen, $empresa, $eid);
                        $rutaFinal = $res['ruta']; $nombreFinal = $res['nombre']; $generados++;
                    } else {
                        $nombreFinal = $a['nombre'];
                        $rutaFinal = $dirOriginales . $nombreFinal;
                        if (!file_exists($rutaFinal)) copy($rutaOrigen, $rutaFinal);
                        $copiados++;
                    }
                } catch (\Throwable) {
                    continue; // un archivo problemático no detiene el paquete completo
                }
                Database::execute(
                    "INSERT INTO sgsst_documentos (empresa_id, plantilla, nombre_archivo, ruta, hash_sha256, generado_por, carpeta, estado, version)
                     VALUES (?,?,?,?,?,?,?, 'pendiente_revision', 1)",
                    [$eid, $a['relativo'], $nombreFinal, $rutaFinal, is_file($rutaFinal) ? hash_file('sha256', $rutaFinal) : null,
                     $usuarioId, $grupo['carpeta']]
                );
            }
        }
        Auditoria::registrar('sgsst.paquete_generado', $eid, 'empresa', $eid,
            "Paquete documental grupo {$req['grupo']}: {$generados} diligenciados + {$copiados} originales");
        return ['generados' => $generados, 'copiados' => $copiados, 'total' => $generados + $copiados];
    }
}
