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

    /** Empresas visibles para un usuario: propias + compartidas en modo lectura (portal cliente). */
    public static function empresasVisibles(int $usuarioId): array
    {
        return Database::query(
            'SELECT e.*, CASE WHEN e.usuario_id = ? THEN 0 ELSE 1 END AS compartida
             FROM empresas e LEFT JOIN empresa_accesos ea ON ea.empresa_id = e.id AND ea.usuario_id = ?
             WHERE e.usuario_id = ? OR ea.id IS NOT NULL
             GROUP BY e.id ORDER BY e.razon_social',
            [$usuarioId, $usuarioId, $usuarioId]
        );
    }

    public static function puedeVer(int $empresaId, int $usuarioId): bool
    {
        return (bool) Database::first(
            'SELECT e.id FROM empresas e LEFT JOIN empresa_accesos ea ON ea.empresa_id = e.id AND ea.usuario_id = ?
             WHERE e.id = ? AND (e.usuario_id = ? OR ea.id IS NOT NULL)',
            [$usuarioId, $empresaId, $usuarioId]
        );
    }

    /**
     * Alertas de vencimientos y riesgos sin control: actividades, registros, compromisos
     * de la evaluación, peligros nivel I/II abiertos y vencimientos de inventario PPPRAE.
     * nivel: 'critica' (vencido / riesgo no aceptable), 'advertencia' (vence en ≤30 días).
     */
    public static function alertas(int $empresaId): array
    {
        $a = [];
        $hoy = date('Y-m-d'); $limite = date('Y-m-d', strtotime('+30 days'));
        $push = function (string $nivel, string $msg, ?string $fecha, string $ancla) use (&$a, $hoy) {
            $a[] = ['nivel' => $nivel, 'mensaje' => $msg, 'fecha' => $fecha, 'ancla' => $ancla,
                    'vencida' => $fecha !== null && $fecha < $hoy];
        };
        foreach (Database::query("SELECT titulo, fecha_vencimiento FROM sgsst_actividades WHERE empresa_id=? AND estado IN ('pendiente','en_proceso') AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ?", [$empresaId, $limite]) as $r) {
            $push($r['fecha_vencimiento'] < $hoy ? 'critica' : 'advertencia', 'Actividad del plan anual: ' . $r['titulo'], $r['fecha_vencimiento'], 'plan');
        }
        foreach (Database::query("SELECT titulo, fecha_vencimiento FROM sgsst_registros WHERE empresa_id=? AND estado IN ('abierto','en_gestion') AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= ?", [$empresaId, $limite]) as $r) {
            $push($r['fecha_vencimiento'] < $hoy ? 'critica' : 'advertencia', 'Registro por cerrar: ' . $r['titulo'], $r['fecha_vencimiento'], 'registros');
        }
        foreach (Database::query("SELECT codigo, fecha_compromiso FROM sgsst_evaluaciones WHERE empresa_id=? AND estado IN ('no_cumple','parcial') AND fecha_compromiso IS NOT NULL AND fecha_compromiso <= ?", [$empresaId, $limite]) as $r) {
            $push($r['fecha_compromiso'] < $hoy ? 'critica' : 'advertencia', 'Compromiso estándar ' . $r['codigo'] . ' sin cumplir', $r['fecha_compromiso'], 'evaluacion');
        }
        foreach (Database::query("SELECT descripcion, clasificacion, nr_nivel FROM sgsst_peligros WHERE empresa_id=? AND estado != 'cerrado' AND nr_nivel IN ('I','II')", [$empresaId]) as $r) {
            $push('critica', 'Peligro nivel ' . $r['nr_nivel'] . ' (' . ($r['clasificacion'] ?: 'sin clasificar') . '): ' . $r['descripcion'], null, 'matriz');
        }
        foreach (Database::query("SELECT b.elemento, b.fecha_vencimiento FROM inv_botiquin b JOIN analisis an ON an.id=b.analisis_id WHERE an.empresa_id=? AND b.fecha_vencimiento IS NOT NULL AND b.fecha_vencimiento <= ?", [$empresaId, $limite]) as $r) {
            $push($r['fecha_vencimiento'] < $hoy ? 'critica' : 'advertencia', 'Botiquín vence: ' . $r['elemento'], $r['fecha_vencimiento'], 'matriz');
        }
        usort($a, fn($x, $y) => (($x['vencida'] ? 0 : 1) <=> ($y['vencida'] ? 0 : 1)) ?: strcmp((string)$x['fecha'], (string)$y['fecha']));
        return $a;
    }

    /** Conteo de alertas críticas de todas las empresas visibles (campana del menú). */
    public static function conteoAlertasCriticas(int $usuarioId): int
    {
        $n = 0;
        foreach (self::empresasVisibles($usuarioId) as $e) {
            foreach (self::alertas((int)$e['id']) as $al) if ($al['nivel'] === 'critica') $n++;
        }
        return $n;
    }

    public static function semaforo(float $puntaje): string
    {
        // Criterios orientativos Res. 0312/2019: crítico ≤60, moderado 61–85, aceptable >85
        return $puntaje <= 60 ? 'CRÍTICO' : ($puntaje <= 85 ? 'MODERADAMENTE ACEPTABLE' : 'ACEPTABLE');
    }
}
