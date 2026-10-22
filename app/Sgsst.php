<?php
namespace App;

/** Lógica del módulo SG-SST: inicialización, puntaje y alertas por empresa. */
class Sgsst
{
    public static function inicializarEmpresa(int $empresaId): void
    {
        $existe = Database::first('SELECT COUNT(*) c FROM sgsst_evaluaciones WHERE empresa_id = ?', [$empresaId]);
        if ((int)($existe['c'] ?? 0) === 0) {
            foreach (SgsstCatalogo::estandares() as $e) {
                Database::execute(
                    'INSERT OR IGNORE INTO sgsst_evaluaciones (empresa_id, codigo, estado) VALUES (?, ?, ?)',
                    [$empresaId, $e['codigo'], 'no_cumple']
                );
            }
        }
        $acts = Database::first('SELECT COUNT(*) c FROM sgsst_actividades WHERE empresa_id = ?', [$empresaId]);
        if ((int)($acts['c'] ?? 0) === 0) {
            foreach (SgsstCatalogo::actividadesBase() as [$titulo, $ciclo, $meses]) {
                Database::execute(
                    'INSERT INTO sgsst_actividades (empresa_id, titulo, ciclo, fecha_inicio, fecha_vencimiento, origen) VALUES (?,?,?,?,?,?)',
                    [$empresaId, $titulo, $ciclo, date('Y-m-d'), date('Y-m-d', strtotime("+{$meses} months")), 'plantilla_base']
                );
            }
        }
    }

    public static function evaluaciones(int $empresaId): array
    {
        self::inicializarEmpresa($empresaId);
        $guardadas = [];
        foreach (Database::query('SELECT * FROM sgsst_evaluaciones WHERE empresa_id = ?', [$empresaId]) as $g) {
            $guardadas[$g['codigo']] = $g;
        }
        $out = [];
        foreach (SgsstCatalogo::estandares() as $e) {
            $out[] = array_merge($e, $guardadas[$e['codigo']] ?? [
                'estado'=>'no_cumple','evidencia'=>null,'observacion'=>null,'responsable'=>null,'fecha_compromiso'=>null,
            ]);
        }
        return $out;
    }

    /** Puntaje 0–100: cumple=1, parcial=0.5; no_aplica se excluye. */
    public static function puntaje(array $evaluaciones): array
    {
        $aplican = array_filter($evaluaciones, fn($e) => ($e['estado'] ?? '') !== 'no_aplica');
        if (!$aplican) return ['total'=>0.0,'por_ciclo'=>[]];
        $suma = 0.0; $ciclos = [];
        foreach ($aplican as $e) {
            $v = match($e['estado']) {'cumple'=>1.0,'parcial'=>0.5,default=>0.0};
            $suma += $v;
            $ciclos[$e['ciclo']]['suma'] = ($ciclos[$e['ciclo']]['suma'] ?? 0) + $v;
            $ciclos[$e['ciclo']]['n'] = ($ciclos[$e['ciclo']]['n'] ?? 0) + 1;
        }
        foreach ($ciclos as $c => $d) $ciclos[$c] = round($d['suma'] / $d['n'] * 100, 1);
        return ['total'=>round($suma / count($aplican) * 100, 1), 'por_ciclo'=>$ciclos];
    }

    public static function semaforo(float $puntaje): string
    {
        // Criterios orientativos Res. 0312/2019: crítico ≤60, moderado 61–85, aceptable >85
        return $puntaje <= 60 ? 'CRÍTICO' : ($puntaje <= 85 ? 'MODERADAMENTE ACEPTABLE' : 'ACEPTABLE');
    }
}
