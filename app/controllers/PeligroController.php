<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\Gtc45;
use App\Sgsst;

/** Matriz de identificación de peligros y valoración de riesgos — GTC 45. */
class PeligroController extends BaseController
{
    private function empresaVisible(int $id): array
    {
        $e = Database::first('SELECT * FROM empresas WHERE id = ?', [$id]);
        if (!$e || !Sgsst::puedeVer($id, Auth::id())) { flash('error', 'Empresa no encontrada'); $this->redirect(base_url('sgsst')); }
        $e['solo_lectura'] = (int)$e['usuario_id'] !== (int)Auth::id();
        return $e;
    }

    private function empresaPropia(int $id): array
    {
        $e = Database::first('SELECT * FROM empresas WHERE id = ? AND usuario_id = ?', [$id, Auth::id()]);
        if (!$e) { flash('error', 'Solo el propietario puede modificar la matriz'); $this->redirect(base_url('sgsst')); }
        return $e;
    }

    /** GET /sgsst/empresa/{id}/matriz */
    public function matriz(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id); $eid = (int)$empresa['id'];
        $filtro = $_GET['nivel'] ?? '';
        $sql = 'SELECT * FROM sgsst_peligros WHERE empresa_id = ?';
        $params = [$eid];
        if (in_array($filtro, ['I','II','III','IV'], true)) { $sql .= ' AND nr_nivel = ?'; $params[] = $filtro; }
        $sql .= ' ORDER BY nr DESC, created_at DESC';
        $peligros = Database::query($sql, $params);
        $resumen = [];
        foreach (Database::query('SELECT nr_nivel, COUNT(*) c FROM sgsst_peligros WHERE empresa_id=? GROUP BY nr_nivel', [$eid]) as $r) {
            $resumen[$r['nr_nivel']] = (int)$r['c'];
        }
        $this->view('sgsst/matriz', [
            'titulo' => 'Matriz IPVR GTC 45 — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'peligros' => $peligros, 'resumen' => $resumen, 'filtro' => $filtro,
            'soloLectura' => (bool)$empresa['solo_lectura'],
            'deficiencias' => Gtc45::DEFICIENCIA, 'exposiciones' => Gtc45::EXPOSICION,
            'consecuencias' => Gtc45::CONSECUENCIA, 'clasificaciones' => Gtc45::CLASIFICACIONES,
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** POST /sgsst/empresa/{id}/matriz */
    public function crear(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $descripcion = trim($_POST['descripcion'] ?? '');
        if ($descripcion === '') { flash('error', 'Describe el peligro'); $this->redirect(base_url("sgsst/empresa/{$eid}/matriz")); }
        $nd = (int)($_POST['nd'] ?? 2); $ne = (int)($_POST['ne'] ?? 2); $nc = (int)($_POST['nc'] ?? 25);
        if (!isset(Gtc45::DEFICIENCIA[$nd])) $nd = 2;
        if (!isset(Gtc45::EXPOSICION[$ne])) $ne = 2;
        if (!isset(Gtc45::CONSECUENCIA[$nc])) $nc = 25;
        $ev = Gtc45::evaluar($nd, $ne, $nc);
        Database::execute(
            'INSERT INTO sgsst_peligros (empresa_id,proceso,zona,actividad,tarea,rutinaria,descripcion,clasificacion,efectos,
             control_fuente,control_medio,control_individuo,nd,ne,nc,np,np_nivel,nr,nr_nivel,aceptabilidad,
             medidas_intervencion,responsable,fecha_compromiso,creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$eid, trim($_POST['proceso'] ?? '') ?: null, trim($_POST['zona'] ?? '') ?: null,
             trim($_POST['actividad'] ?? '') ?: null, trim($_POST['tarea'] ?? '') ?: null,
             isset($_POST['rutinaria']) ? 1 : 0, $descripcion,
             in_array($_POST['clasificacion'] ?? '', Gtc45::CLASIFICACIONES, true) ? $_POST['clasificacion'] : 'Otro',
             trim($_POST['efectos'] ?? '') ?: null, trim($_POST['control_fuente'] ?? '') ?: null,
             trim($_POST['control_medio'] ?? '') ?: null, trim($_POST['control_individuo'] ?? '') ?: null,
             $nd, $ne, $nc, $ev['np'], $ev['np_nivel'], $ev['nr'], $ev['nr_nivel'], $ev['aceptabilidad'],
             trim($_POST['medidas_intervencion'] ?? '') ?: null, trim($_POST['responsable'] ?? '') ?: null,
             ($_POST['fecha_compromiso'] ?? '') ?: null, Auth::id()]
        );
        $nuevoId = Database::lastId();
        Auditoria::registrar('ipvr.peligro_creado', $eid, 'peligro', $nuevoId, "NR {$ev['nr']} nivel {$ev['nr_nivel']}: {$descripcion}");
        if ($ev['nr_nivel'] === 'I') {
            flash('warning', 'Peligro creado con nivel de riesgo I: NO ACEPTABLE. Requiere intervención inmediata (suspender la actividad si implica riesgo grave e inminente).');
        } else {
            flash('success', "Peligro valorado: NP {$ev['np']} ({$ev['np_nivel']}), NR {$ev['nr']} nivel {$ev['nr_nivel']} — {$ev['aceptabilidad']}");
        }
        $this->redirect(base_url("sgsst/empresa/{$eid}/matriz"));
    }

    /** POST /sgsst/peligros/{id}/estado */
    public function estado(string $id): void
    {
        $this->verifyCsrf();
        $p = Database::first('SELECT pe.* FROM sgsst_peligros pe JOIN empresas e ON e.id=pe.empresa_id WHERE pe.id=? AND e.usuario_id=?', [(int)$id, Auth::id()]);
        if (!$p) { flash('error', 'Peligro no encontrado'); $this->redirect(base_url('sgsst')); }
        $estado = in_array($_POST['estado'] ?? '', ['abierto','en_control','cerrado'], true) ? $_POST['estado'] : 'abierto';
        Database::execute('UPDATE sgsst_peligros SET estado=?, updated_at=CURRENT_TIMESTAMP WHERE id=?', [$estado, (int)$id]);
        Auditoria::registrar('ipvr.peligro_estado', (int)$p['empresa_id'], 'peligro', (int)$id, "Estado: {$estado}");
        $this->redirect(base_url("sgsst/empresa/{$p['empresa_id']}/matriz"));
    }

    /** POST /sgsst/peligros/{id}/eliminar */
    public function eliminar(string $id): void
    {
        $this->verifyCsrf();
        $p = Database::first('SELECT pe.* FROM sgsst_peligros pe JOIN empresas e ON e.id=pe.empresa_id WHERE pe.id=? AND e.usuario_id=?', [(int)$id, Auth::id()]);
        if (!$p) { flash('error', 'Peligro no encontrado'); $this->redirect(base_url('sgsst')); }
        Database::execute('DELETE FROM sgsst_peligros WHERE id=?', [(int)$id]);
        Auditoria::registrar('ipvr.peligro_eliminado', (int)$p['empresa_id'], 'peligro', (int)$id, (string)$p['descripcion']);
        flash('success', 'Peligro eliminado de la matriz');
        $this->redirect(base_url("sgsst/empresa/{$p['empresa_id']}/matriz"));
    }

    /** GET /sgsst/empresa/{id}/matriz.csv — exportable para auditoría/ARL */
    public function exportar(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id); $eid = (int)$empresa['id'];
        Auditoria::registrar('ipvr.matriz_exportada', $eid, 'empresa', $eid);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="matriz_ipvr_' . $eid . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
        fputcsv($out, ['Proceso','Zona','Actividad','Tarea','Rutinaria','Peligro','Clasificación','Efectos','Control fuente','Control medio','Control individuo','ND','NE','NP','NP nivel','NC','NR','NR nivel','Aceptabilidad','Medidas de intervención','Responsable','Fecha compromiso','Estado'], ';');
        foreach (Database::query('SELECT * FROM sgsst_peligros WHERE empresa_id=? ORDER BY nr DESC', [$eid]) as $p) {
            fputcsv($out, [$p['proceso'],$p['zona'],$p['actividad'],$p['tarea'],$p['rutinaria'] ? 'Sí' : 'No',$p['descripcion'],$p['clasificacion'],$p['efectos'],$p['control_fuente'],$p['control_medio'],$p['control_individuo'],$p['nd'],$p['ne'],$p['np'],$p['np_nivel'],$p['nc'],$p['nr'],$p['nr_nivel'],$p['aceptabilidad'],$p['medidas_intervencion'],$p['responsable'],$p['fecha_compromiso'],$p['estado']], ';');
        }
        fclose($out); exit;
    }
}
