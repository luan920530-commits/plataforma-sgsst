<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\FormularioCatalogo;
use App\Sgsst;

/** Fase C — Formatos del paquete como formularios web con seguimiento. */
class FormularioController extends BaseController
{
    /** @return array{0:array,1:?string} [empresa, permiso] */
    private function acceso(int $id): array
    {
        $e = Database::first('SELECT * FROM empresas WHERE id = ?', [$id]);
        $permiso = $e ? Sgsst::permiso($id, (int) Auth::id()) : null;
        if (!$e || $permiso === null) { flash('error', 'Empresa no encontrada'); $this->redirect(base_url('sgsst')); }
        return [$e, $permiso];
    }

    private function puedeGestionar(array $empresa, ?string $permiso): bool
    {
        return in_array($permiso, ['propietario', 'representante'], true);
    }

    /** GET /sgsst/empresa/{id}/formularios */
    public function index(string $id): void
    {
        [$empresa, $permiso] = $this->acceso((int) $id); $eid = (int) $empresa['id'];
        $conteos = [];
        foreach (Database::query('SELECT formato, COUNT(*) c, SUM(CASE WHEN estado != \'cerrado\' THEN 1 ELSE 0 END) abiertos FROM sgsst_formularios WHERE empresa_id=? GROUP BY formato', [$eid]) as $r) {
            $conteos[$r['formato']] = $r;
        }
        $this->view('formularios/index', [
            'titulo' => 'Formularios web SG-SST — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'formatos' => FormularioCatalogo::todos(), 'conteos' => $conteos,
            'recientes' => Database::query('SELECT * FROM sgsst_formularios WHERE empresa_id=? ORDER BY created_at DESC LIMIT 15', [$eid]),
            'puedeGestionar' => $this->puedeGestionar($empresa, $permiso), 'embed' => isset($_GET['embed']),
        ]);
    }

    /** GET /sgsst/empresa/{id}/formularios/{codigo} */
    public function diligenciar(string $id, string $codigo): void
    {
        [$empresa, $permiso] = $this->acceso((int) $id); $eid = (int) $empresa['id'];
        $formato = FormularioCatalogo::uno($codigo);
        if (!$formato) { http_response_code(404); die('Formulario no definido'); }
        if (!empty($formato['confidencial']) && !$this->puedeGestionar($empresa, $permiso)) {
            flash('error', 'Formulario confidencial: acceso restringido.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/formularios"));
        }
        $this->view('formularios/diligenciar', [
            'titulo' => $formato['titulo'] . ' — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'codigo' => $codigo, 'formato' => $formato,
            'embed' => isset($_GET['embed']), 'historial' => Database::query('SELECT * FROM sgsst_formularios WHERE empresa_id=? AND formato=? ORDER BY created_at DESC LIMIT 30', [$eid, $codigo]),
            'personal' => Database::query('SELECT id, nombre_completo, cargo FROM personal WHERE empresa_id=? AND activo=1 ORDER BY nombre_completo', [$eid]),
            'puedeGestionar' => $this->puedeGestionar($empresa, $permiso),
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** POST /sgsst/empresa/{id}/formularios/{codigo} */
    public function guardar(string $id, string $codigo): void
    {
        $this->verifyCsrf();
        [$empresa, $permiso] = $this->acceso((int) $id); $eid = (int) $empresa['id'];
        if (!$this->puedeGestionar($empresa, $permiso)) {
            flash('error', 'Tu acceso no permite diligenciar formularios.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/formularios"));
        }
        $formato = FormularioCatalogo::uno($codigo);
        if (!$formato) { http_response_code(404); die('Formulario no definido'); }

        $datos = []; $faltan = [];
        foreach ($formato['campos'] as [$clave, $etiqueta, $tipo, $opciones, $requerido]) {
            $valor = $_POST['campo'][$clave] ?? null;
            if ($tipo === 'multiple') $valor = is_array($valor) ? array_values(array_map('strval', $valor)) : [];
            elseif ($tipo === 'trabajador' && $valor) {
                $persona = Database::first('SELECT nombre_completo FROM personal WHERE id=? AND empresa_id=?', [(int) $valor, $eid]);
                $valor = $persona ? $persona['nombre_completo'] : trim((string) $valor);
            } else {
                $valor = is_string($valor) ? trim($valor) : $valor;
            }
            if ($requerido && ($valor === null || $valor === '' || $valor === [])) $faltan[] = $etiqueta;
            $datos[$clave] = $valor;
        }
        if ($faltan) {
            flash('error', 'Faltan campos obligatorios: ' . implode(', ', $faltan));
            $this->redirect(base_url("sgsst/empresa/{$eid}/formularios/{$codigo}"));
        }

        $fecha = $datos['fecha'] ?? date('Y-m-d');
        if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
        $vencimiento = $datos['fecha_compromiso'] ?? ($datos['fecha_limite'] ?? null);
        if (!is_string($vencimiento) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $vencimiento)) $vencimiento = null;

        Database::execute(
            'INSERT INTO sgsst_formularios (empresa_id, formato, datos, fecha, fecha_vencimiento, diligenciado_por) VALUES (?,?,?,?,?,?)',
            [$eid, $codigo, json_encode($datos, JSON_UNESCAPED_UNICODE), $fecha, $vencimiento, Auth::id()]
        );
        $nuevoId = Database::lastId();
        Auditoria::registrar('formulario.diligenciado', $eid, 'formulario', $nuevoId, $formato['titulo']);

        // Sincronizar con Registros de gestión cuando el formato lo define
        if (!empty($formato['registro_tipo'])) {
            $resumen = $datos['hallazgos'] ?? $datos['descripcion'] ?? $datos['tema'] ?? $datos['accion'] ?? '';
            Database::execute(
                'INSERT INTO sgsst_registros (empresa_id, tipo, titulo, descripcion, fecha, fecha_vencimiento, responsable, creado_por) VALUES (?,?,?,?,?,?,?,?)',
                [$eid, $formato['registro_tipo'], $formato['titulo'], is_string($resumen) ? $resumen : null, $fecha, $vencimiento,
                 isset($datos['responsable']) && is_string($datos['responsable']) ? $datos['responsable'] : null, Auth::id()]
            );
        }

        flash('success', 'Formulario guardado. Quedó en el historial con seguimiento' . ($vencimiento ? " y vencimiento {$vencimiento}" : '') . '.');
        $this->redirect(base_url("sgsst/empresa/{$eid}/formularios/{$codigo}"));
    }

    /** GET /sgsst/formularios/{id} — detalle imprimible */
    public function ver(string $id): void
    {
        $r = Database::first('SELECT * FROM sgsst_formularios WHERE id = ?', [(int) $id]);
        if (!$r) { http_response_code(404); die('Registro no encontrado'); }
        [$empresa, $permiso] = $this->acceso((int) $r['empresa_id']);
        $formato = FormularioCatalogo::uno($r['formato']);
        if (!$formato) { http_response_code(404); die('Formulario no definido'); }
        if (!empty($formato['confidencial']) && !$this->puedeGestionar($empresa, $permiso)) {
            http_response_code(403); die('Información confidencial: acceso restringido.');
        }
        Auditoria::registrar('formulario.consultado', (int) $r['empresa_id'], 'formulario', (int) $id, $formato['titulo']);
        $this->view('formularios/ver', [
            'titulo' => $formato['titulo'] . ' #' . $r['id'],
            'empresa' => $empresa, 'registro' => $r, 'formato' => $formato,
            'datos' => json_decode($r['datos'], true) ?: [],
            'puedeGestionar' => $this->puedeGestionar($empresa, $permiso),
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** POST /sgsst/formularios/{id}/estado */
    public function estado(string $id): void
    {
        $this->verifyCsrf();
        $r = Database::first('SELECT * FROM sgsst_formularios WHERE id = ?', [(int) $id]);
        if (!$r) { flash('error', 'Registro no encontrado'); $this->redirect(base_url('sgsst')); }
        [$empresa, $permiso] = $this->acceso((int) $r['empresa_id']);
        if (!$this->puedeGestionar($empresa, $permiso)) { flash('error', 'Sin permiso para cambiar el estado'); $this->redirect(base_url('sgsst')); }
        $estado = in_array($_POST['estado'] ?? '', ['abierto', 'en_gestion', 'cerrado'], true) ? $_POST['estado'] : 'abierto';
        Database::execute('UPDATE sgsst_formularios SET estado=?, updated_at=CURRENT_TIMESTAMP WHERE id=?', [$estado, (int) $id]);
        Auditoria::registrar('formulario.estado', (int) $r['empresa_id'], 'formulario', (int) $id, $estado);
        $this->redirect(base_url("sgsst/formularios/{$id}"));
    }

    /** GET /sgsst/empresa/{id}/formularios/{codigo}.csv — exportar respuestas del formato */
    public function exportar(string $id, string $codigo): void
    {
        [$empresa, $permiso] = $this->acceso((int) $id); $eid = (int) $empresa['id'];
        $formato = FormularioCatalogo::uno($codigo);
        if (!$formato) { http_response_code(404); die('Formulario no definido'); }
        if (!empty($formato['confidencial']) && !$this->puedeGestionar($empresa, $permiso)) { http_response_code(403); die('Confidencial'); }
        Auditoria::registrar('formulario.exportado', $eid, 'formulario', null, $formato['titulo']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $codigo . '_' . $eid . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $encabezados = ['ID', 'Fecha', 'Estado', 'Vencimiento'];
        foreach ($formato['campos'] as $c) $encabezados[] = $c[1];
        fputcsv($out, $encabezados, ';');
        foreach (Database::query('SELECT * FROM sgsst_formularios WHERE empresa_id=? AND formato=? ORDER BY fecha', [$eid, $codigo]) as $r) {
            $datos = json_decode($r['datos'], true) ?: [];
            $fila = [$r['id'], $r['fecha'], $r['estado'], $r['fecha_vencimiento']];
            foreach ($formato['campos'] as $c) {
                $v = $datos[$c[0]] ?? '';
                $fila[] = is_array($v) ? implode(', ', $v) : (string) $v;
            }
            fputcsv($out, $fila, ';');
        }
        fclose($out); exit;
    }
}
