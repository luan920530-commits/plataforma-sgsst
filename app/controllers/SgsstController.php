<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\Sgsst;
use App\SgsstCatalogo;
use App\Indicadores;

class SgsstController extends BaseController
{
    private function empresaPropia(int $id): array
    {
        $e = Database::first('SELECT * FROM empresas WHERE id = ? AND usuario_id = ?', [$id, Auth::id()]);
        if (!$e) { flash('error', 'Empresa no encontrada o sin permiso de edición'); $this->redirect(base_url('sgsst')); }
        return $e;
    }

    /** Visible = propietario o cliente con acceso de lectura compartido. */
    private function empresaVisible(int $id): array
    {
        $e = Database::first('SELECT * FROM empresas WHERE id = ?', [$id]);
        if (!$e || !Sgsst::puedeVer($id, Auth::id())) { flash('error', 'Empresa no encontrada'); $this->redirect(base_url('sgsst')); }
        $e['solo_lectura'] = (int)$e['usuario_id'] !== (int)Auth::id();
        return $e;
    }

    /** GET /sgsst — centro de cumplimiento: todas las empresas y su avance */
    public function index(): void
    {
        $empresas = Sgsst::empresasVisibles(Auth::id());
        foreach ($empresas as &$e) {
            $ev = Sgsst::evaluaciones((int)$e['id']);
            $e['puntaje'] = Sgsst::puntaje($ev);
            $e['semaforo'] = Sgsst::semaforo($e['puntaje']['total']);
            $e['pendientes'] = (int)(Database::first(
                "SELECT COUNT(*) c FROM sgsst_actividades WHERE empresa_id=? AND estado IN ('pendiente','en_proceso') AND fecha_vencimiento < date('now')",
                [(int)$e['id']])['c'] ?? 0);
            $e['alertas'] = Sgsst::alertas((int)$e['id']);
            $e['abiertos'] = (int)(Database::first(
                "SELECT COUNT(*) c FROM sgsst_registros WHERE empresa_id=? AND estado IN ('abierto','en_gestion')",
                [(int)$e['id']])['c'] ?? 0);
        }
        unset($e);
        $this->view('sgsst/index', ['titulo'=>'Centro de cumplimiento SG-SST','empresas'=>$empresas]);
    }

    /** GET /sgsst/empresa/{id} — panel integral de la empresa */
    public function empresa(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id);
        $eid = (int)$empresa['id'];
        $evaluaciones = Sgsst::evaluaciones($eid);
        $puntaje = Sgsst::puntaje($evaluaciones);
        $this->view('sgsst/empresa', [
            'titulo' => 'SG-SST — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'evaluaciones' => $evaluaciones, 'puntaje' => $puntaje,
            'semaforo' => Sgsst::semaforo($puntaje['total']),
            'actividades' => Database::query('SELECT * FROM sgsst_actividades WHERE empresa_id=? ORDER BY fecha_vencimiento IS NULL, fecha_vencimiento', [$eid]),
            'registros' => Database::query('SELECT * FROM sgsst_registros WHERE empresa_id=? ORDER BY fecha DESC LIMIT 100', [$eid]),
            'documentosSgsst' => Database::query('SELECT * FROM sgsst_documentos WHERE empresa_id=? ORDER BY created_at DESC', [$eid]),
            'plantillas' => SgsstCatalogo::plantillas(), 'tiposRegistro' => SgsstCatalogo::tiposRegistro(),
            'auditoria' => Auditoria::porEmpresa($eid, 50),
            'analisis' => Database::query('SELECT * FROM analisis WHERE empresa_id=? ORDER BY created_at DESC', [$eid]),
            'alertas' => Sgsst::alertas($eid),
            'soloLectura' => (bool)$empresa['solo_lectura'],
            'periodos' => Indicadores::porEmpresa($eid),
            'resumenMatriz' => Database::query('SELECT nr_nivel, COUNT(*) c FROM sgsst_peligros WHERE empresa_id=? GROUP BY nr_nivel', [$eid]),
            'peligrosCriticos' => (int)(Database::first("SELECT COUNT(*) c FROM sgsst_peligros WHERE empresa_id=? AND nr_nivel IN ('I','II') AND estado != 'cerrado'", [$eid])['c'] ?? 0),
            'accesos' => Database::query('SELECT ea.id, ea.permiso, u.nombre, u.email, u.rol FROM empresa_accesos ea JOIN usuarios u ON u.id = ea.usuario_id WHERE ea.empresa_id=? ORDER BY u.nombre', [$eid]),
            'reporteToken' => Sgsst::tokenReporte($eid),
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** POST /sgsst/empresa/{id}/evaluacion */
    public function guardarEvaluacion(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        Sgsst::inicializarEmpresa($eid);
        foreach (($_POST['estado'] ?? []) as $codigo => $estado) {
            if (!in_array($estado, ['cumple','parcial','no_cumple','no_aplica'], true)) continue;
            Database::execute(
                'UPDATE sgsst_evaluaciones SET estado=?, evidencia=?, observacion=?, responsable=?, fecha_compromiso=?, actualizado_por=?, updated_at=CURRENT_TIMESTAMP WHERE empresa_id=? AND codigo=?',
                [$estado, trim($_POST['evidencia'][$codigo] ?? '') ?: null, trim($_POST['observacion'][$codigo] ?? '') ?: null,
                 trim($_POST['responsable'][$codigo] ?? '') ?: null, ($_POST['fecha_compromiso'][$codigo] ?? '') ?: null,
                 Auth::id(), $eid, (string)$codigo]
            );
        }
        Auditoria::registrar('sgsst.evaluacion_actualizada', $eid, 'empresa', $eid, 'Evaluación de estándares mínimos actualizada');
        flash('success', 'Evaluación SG-SST guardada. Puntaje recalculado.');
        $this->redirect(base_url("sgsst/empresa/{$eid}#evaluacion"));
    }

    /** POST /sgsst/empresa/{id}/actividades */
    public function crearActividad(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $titulo = trim($_POST['titulo'] ?? '');
        if ($titulo === '') { flash('error','La actividad requiere un título'); $this->redirect(base_url("sgsst/empresa/{$eid}#plan")); }
        Database::execute(
            'INSERT INTO sgsst_actividades (empresa_id,titulo,ciclo,responsable,fecha_inicio,fecha_vencimiento,evidencia) VALUES (?,?,?,?,?,?,?)',
            [$eid, $titulo, $_POST['ciclo'] ?? 'PLANEAR', trim($_POST['responsable'] ?? '') ?: null,
             ($_POST['fecha_inicio'] ?? '') ?: null, ($_POST['fecha_vencimiento'] ?? '') ?: null, trim($_POST['evidencia'] ?? '') ?: null]
        );
        Auditoria::registrar('sgsst.actividad_creada', $eid, 'actividad', Database::lastId(), $titulo);
        flash('success', 'Actividad agregada al plan de trabajo anual');
        $this->redirect(base_url("sgsst/empresa/{$eid}#plan"));
    }

    /** POST /sgsst/actividades/{id}/estado */
    public function estadoActividad(string $id): void
    {
        $this->verifyCsrf();
        $a = Database::first('SELECT act.* FROM sgsst_actividades act JOIN empresas e ON e.id=act.empresa_id WHERE act.id=? AND e.usuario_id=?', [(int)$id, Auth::id()]);
        if (!$a) { flash('error','Actividad no encontrada'); $this->redirect(base_url('sgsst')); }
        $estado = in_array($_POST['estado'] ?? '', ['pendiente','en_proceso','completada','vencida','cancelada'], true) ? $_POST['estado'] : 'pendiente';
        Database::execute('UPDATE sgsst_actividades SET estado=?, updated_at=CURRENT_TIMESTAMP WHERE id=?', [$estado, (int)$id]);
        Auditoria::registrar('sgsst.actividad_estado', (int)$a['empresa_id'], 'actividad', (int)$id, "Estado: {$estado}");
        $this->redirect(base_url("sgsst/empresa/{$a['empresa_id']}#plan"));
    }

    /** POST /sgsst/empresa/{id}/registros */
    public function crearRegistro(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $tipos = SgsstCatalogo::tiposRegistro();
        $tipo = array_key_exists($_POST['tipo'] ?? '', $tipos) ? $_POST['tipo'] : 'otro';
        $titulo = trim($_POST['titulo'] ?? '');
        if ($titulo === '') { flash('error','El registro requiere un título'); $this->redirect(base_url("sgsst/empresa/{$eid}#registros")); }
        $sev = in_array($_POST['severidad'] ?? '', ['baja','media','alta','critica'], true) ? $_POST['severidad'] : 'media';
        Database::execute(
            'INSERT INTO sgsst_registros (empresa_id,tipo,titulo,descripcion,fecha,fecha_vencimiento,responsable,severidad,evidencia,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$eid, $tipo, $titulo, trim($_POST['descripcion'] ?? '') ?: null, ($_POST['fecha'] ?? '') ?: date('Y-m-d'),
             ($_POST['fecha_vencimiento'] ?? '') ?: null, trim($_POST['responsable'] ?? '') ?: null, $sev,
             trim($_POST['evidencia'] ?? '') ?: null, Auth::id()]
        );
        Auditoria::registrar('sgsst.registro_creado', $eid, 'registro', Database::lastId(), "{$tipos[$tipo]}: {$titulo}");
        flash('success', 'Registro creado. Queda trazable en la auditoría de la empresa.');
        $this->redirect(base_url("sgsst/empresa/{$eid}#registros"));
    }

    /** POST /sgsst/registros/{id}/estado */
    public function estadoRegistro(string $id): void
    {
        $this->verifyCsrf();
        $r = Database::first('SELECT reg.* FROM sgsst_registros reg JOIN empresas e ON e.id=reg.empresa_id WHERE reg.id=? AND e.usuario_id=?', [(int)$id, Auth::id()]);
        if (!$r) { flash('error','Registro no encontrado'); $this->redirect(base_url('sgsst')); }
        $estado = in_array($_POST['estado'] ?? '', ['abierto','en_gestion','cerrado','vencido'], true) ? $_POST['estado'] : 'abierto';
        Database::execute('UPDATE sgsst_registros SET estado=? WHERE id=?', [$estado, (int)$id]);
        Auditoria::registrar('sgsst.registro_estado', (int)$r['empresa_id'], 'registro', (int)$id, "Estado: {$estado}");
        $this->redirect(base_url("sgsst/empresa/{$r['empresa_id']}#registros"));
    }

    /** POST /sgsst/empresa/{id}/periodos — datos de nómina para indicadores automáticos */
    public function guardarPeriodo(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $periodo = trim($_POST['periodo'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) { flash('error', 'Periodo inválido (AAAA-MM)'); $this->redirect(base_url("sgsst/empresa/{$eid}#indicadores")); }
        Database::execute(
            'INSERT INTO sgsst_periodos (empresa_id,periodo,trabajadores_promedio,horas_trabajadas,dias_perdidos,dias_cargados,accidentes_mortales,dias_ausencia_medica,dias_programados)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON CONFLICT(empresa_id, periodo) DO UPDATE SET trabajadores_promedio=excluded.trabajadores_promedio,
               horas_trabajadas=excluded.horas_trabajadas, dias_perdidos=excluded.dias_perdidos, dias_cargados=excluded.dias_cargados,
               accidentes_mortales=excluded.accidentes_mortales, dias_ausencia_medica=excluded.dias_ausencia_medica, dias_programados=excluded.dias_programados',
            [$eid, $periodo, (int)($_POST['trabajadores_promedio'] ?? 0), (float)($_POST['horas_trabajadas'] ?? 0),
             (int)($_POST['dias_perdidos'] ?? 0), (int)($_POST['dias_cargados'] ?? 0), (int)($_POST['accidentes_mortales'] ?? 0),
             (int)($_POST['dias_ausencia_medica'] ?? 0), (int)($_POST['dias_programados'] ?? 0)]
        );
        Auditoria::registrar('sgsst.periodo_actualizado', $eid, 'periodo', null, $periodo);
        flash('success', 'Periodo guardado. Los indicadores se recalcularon automáticamente con los registros del mes.');
        $this->redirect(base_url("sgsst/empresa/{$eid}#indicadores"));
    }

    /** POST /sgsst/empresa/{id}/accesos — compartir portal de solo lectura con un cliente */
    public function asignarAcceso(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $email = trim($_POST['email'] ?? '');
        $u = Database::first('SELECT id, rol FROM usuarios WHERE email = ? AND activo = 1', [$email]);
        if (!$u) { flash('error', 'No existe un usuario activo con ese correo. Un superadmin debe crearlo primero (Usuarios y roles).'); $this->redirect(base_url("sgsst/empresa/{$eid}#accesos")); }
        $permiso = ($_POST['permiso'] ?? 'lectura') === 'representante' ? 'representante' : 'lectura';
        Database::execute('INSERT INTO empresa_accesos (empresa_id, usuario_id, permiso, otorgado_por) VALUES (?,?,?,?)
            ON CONFLICT(empresa_id, usuario_id) DO UPDATE SET permiso = excluded.permiso, otorgado_por = excluded.otorgado_por',
            [$eid, (int)$u['id'], $permiso, Auth::id()]);
        Auditoria::registrar('sgsst.acceso_otorgado', $eid, 'usuario', (int)$u['id'], $email);
        flash('success', "Acceso «{$permiso}» otorgado a {$email}: ya aparece en su menú «Mi empresa»." );
        $this->redirect(base_url("sgsst/empresa/{$eid}#accesos"));
    }

    /** POST /sgsst/accesos/{id}/revocar */
    public function revocarAcceso(string $id): void
    {
        $this->verifyCsrf();
        $a = Database::first('SELECT ea.* FROM empresa_accesos ea JOIN empresas e ON e.id=ea.empresa_id WHERE ea.id=? AND e.usuario_id=?', [(int)$id, Auth::id()]);
        if (!$a) { flash('error', 'Acceso no encontrado'); $this->redirect(base_url('sgsst')); }
        Database::execute('DELETE FROM empresa_accesos WHERE id=?', [(int)$id]);
        Auditoria::registrar('sgsst.acceso_revocado', (int)$a['empresa_id'], 'usuario', (int)$a['usuario_id']);
        flash('success', 'Acceso revocado');
        $this->redirect(base_url("sgsst/empresa/{$a['empresa_id']}#accesos"));
    }

    /** GET /sgsst/empresa/{id}/plantilla/{codigo} — genera documento base (.doc editable) */
    public function generarPlantilla(string $id, string $codigo): void
    {
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $plantillas = SgsstCatalogo::plantillas();
        if (!isset($plantillas[$codigo])) { http_response_code(404); die('Plantilla no encontrada'); }
        if ($codigo === 'plan_emergencias') { $this->redirect(base_url("empresas/{$eid}")); }
        $p = $plantillas[$codigo];
        $config = require BASE_PATH . '/config/config.php';
        $dir = $config['storage']['documentos'];
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $nombre = 'SGSST_' . $codigo . '_' . preg_replace('/[^0-9]/', '', (string)$empresa['nit']) . '_' . date('Ymd_His') . '.doc';
        $ruta = $dir . $nombre;
        $secciones = '';
        foreach ($p['secciones'] as $i => $s) {
            $secciones .= '<h2>' . ($i + 1) . '. ' . htmlspecialchars($s) . '</h2>'
                . '<p>[Diligenciar: adapte este contenido a la realidad de la empresa. Registre aquí la información, responsables y evidencias correspondientes.]</p>';
        }
        $html = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>' . htmlspecialchars($p['titulo']) . '</title>'
            . '<style>body{font-family:Calibri,Arial,sans-serif;margin:3cm}h1{color:#1d4ed8}table{border-collapse:collapse}td{border:1px solid #999;padding:6px}</style></head><body>'
            . '<h1>' . htmlspecialchars($p['titulo']) . '</h1>'
            . '<table><tr><td><b>Empresa</b></td><td>' . htmlspecialchars($empresa['razon_social']) . '</td></tr>'
            . '<tr><td><b>NIT</b></td><td>' . htmlspecialchars($empresa['nit']) . '</td></tr>'
            . '<tr><td><b>Actividad económica</b></td><td>' . htmlspecialchars((string)($empresa['actividad_economica'] ?? '')) . ' (CIIU ' . htmlspecialchars((string)($empresa['ciiu'] ?? '')) . ')</td></tr>'
            . '<tr><td><b>Clase de riesgo / ARL</b></td><td>' . htmlspecialchars((string)($empresa['nivel_riesgo'] ?? '')) . ' / ' . htmlspecialchars((string)($empresa['arl'] ?? '')) . '</td></tr>'
            . '<tr><td><b>Representante legal</b></td><td>' . htmlspecialchars((string)($empresa['representante_legal'] ?? '')) . '</td></tr>'
            . '<tr><td><b>Fecha</b></td><td>' . date('d/m/Y') . '</td></tr>'
            . '<tr><td><b>Base legal</b></td><td>' . htmlspecialchars($p['norma']) . '</td></tr></table>'
            . $secciones
            . '<h2>Aprobación</h2><p>Firma: ' . htmlspecialchars($p['firma']) . '</p><p>Nombre y firma: ________________________________ &nbsp; Fecha: ____/____/________</p>'
            . '<p style="font-size:9pt;color:#666">Documento base generado por la Plataforma SG-SST el ' . date('d/m/Y H:i') . '. Debe ser adaptado y validado por el responsable del SG-SST y firmado antes de su uso oficial. Verifique siempre la norma vigente.</p>'
            . '</body></html>';
        file_put_contents($ruta, $html);
        Database::execute(
            'INSERT INTO sgsst_documentos (empresa_id, plantilla, nombre_archivo, ruta, hash_sha256, generado_por) VALUES (?,?,?,?,?,?)',
            [$eid, $codigo, $nombre, $ruta, hash_file('sha256', $ruta), Auth::id()]
        );
        Auditoria::registrar('sgsst.documento_generado', $eid, 'sgsst_documento', Database::lastId(), $p['titulo']);
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        readfile($ruta); exit;
    }

    /** GET /sgsst/empresa/{id}/informe — informe de cumplimiento imprimible (auditoría/ARL/MinTrabajo) */
    public function informe(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id); $eid = (int)$empresa['id'];
        $evaluaciones = Sgsst::evaluaciones($eid); $puntaje = Sgsst::puntaje($evaluaciones);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Informe SG-SST</title><style>body{font-family:Arial;margin:2cm}table{border-collapse:collapse;width:100%}td,th{border:1px solid #999;padding:5px;font-size:10pt}h1{color:#1d4ed8}@media print{button{display:none}}</style></head><body>';
        echo '<button onclick="window.print()">Imprimir / guardar PDF</button>';
        echo '<h1>Informe de cumplimiento SG-SST</h1><p><b>' . htmlspecialchars($empresa['razon_social']) . '</b> · NIT ' . htmlspecialchars($empresa['nit'])
            . '<br>Generado: ' . date('d/m/Y H:i') . ' · Puntaje de estándares mínimos: <b>' . $puntaje['total'] . '% (' . Sgsst::semaforo($puntaje['total']) . ')</b></p>';
        echo '<p style="font-size:9pt">Criterios orientativos Res. 0312/2019: ≤60% crítico, 61–85% moderadamente aceptable, &gt;85% aceptable. Si el puntaje es ≤60% debe remitirse reporte a la ARL y plan de mejoramiento; ≤85% exige plan de mejoramiento disponible para MinTrabajo.</p>';
        echo '<table><tr><th>Código</th><th>Ciclo</th><th>Estándar</th><th>Estado</th><th>Evidencia</th><th>Responsable</th><th>Compromiso</th></tr>';
        foreach ($evaluaciones as $e) {
            echo '<tr><td>' . htmlspecialchars($e['codigo']) . '</td><td>' . htmlspecialchars($e['ciclo']) . '</td><td>' . htmlspecialchars($e['nombre'])
                . '</td><td>' . htmlspecialchars((string)$e['estado']) . '</td><td>' . htmlspecialchars((string)($e['evidencia'] ?? ''))
                . '</td><td>' . htmlspecialchars((string)($e['responsable'] ?? '')) . '</td><td>' . htmlspecialchars((string)($e['fecha_compromiso'] ?? '')) . '</td></tr>';
        }
        echo '</table><p style="font-size:9pt">Elaborado con la Plataforma SG-SST. Documento de seguimiento: su validez oficial depende de las evidencias y firmas correspondientes.</p></body></html>';
        exit;
    }
}
