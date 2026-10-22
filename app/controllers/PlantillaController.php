<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\GeneradorPlantilla;
use App\PlantillasCatalogo;
use App\Sgsst;
use App\GeneradorPaquete;
use App\DocumentoVista;

/** Catálogo documental maestro por empresa (plantillas reales de los 60 estándares). */
class PlantillaController extends BaseController
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
        if (!$e) { flash('error', 'Solo el propietario puede generar documentos'); $this->redirect(base_url('sgsst')); }
        return $e;
    }

    private function servir(string $ruta, string $nombre): void
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
            'pdf' => 'application/pdf', default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nombre) . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta); exit;
    }

    /** GET /sgsst/empresa/{id}/documentos — catálogo maestro */
    public function catalogo(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id); $eid = (int)$empresa['id'];
        $generados = Database::query('SELECT * FROM sgsst_documentos WHERE empresa_id=? ORDER BY created_at DESC LIMIT 50', [$eid]);
        $paquete = Database::query('SELECT * FROM sgsst_documentos WHERE empresa_id=? AND carpeta IS NOT NULL ORDER BY carpeta, nombre_archivo', [$eid]);
        $this->view('sgsst/documentos', [
            'titulo' => 'Catálogo documental SG-SST — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'grupos' => PlantillasCatalogo::listar(),
            'generados' => $generados, 'paquete' => $paquete,
            'soloLectura' => !in_array(Sgsst::permiso($eid, (int) Auth::id()), ['propietario', 'representante'], true),
            'csrf' => $this->csrfToken(),
        ]);
    }

    /** GET /sgsst/empresa/{id}/documentos/descargar?f=carpeta/archivo */
    public function descargarOriginal(string $id): void
    {
        $empresa = $this->empresaVisible((int)$id);
        $ruta = PlantillasCatalogo::resolver((string)($_GET['f'] ?? ''));
        if (!$ruta) { http_response_code(404); die('Archivo no encontrado en el catálogo'); }
        Auditoria::registrar('plantilla.descarga_original', (int)$empresa['id'], 'plantilla', null, basename($ruta));
        $this->servir($ruta, basename($ruta));
    }

    /** POST /sgsst/empresa/{id}/documentos/generar — documento diligenciado con datos de la empresa */
    public function generar(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int)$id); $eid = (int)$empresa['id'];
        $ruta = PlantillasCatalogo::resolver((string)($_POST['f'] ?? ''));
        if (!$ruta) { flash('error', 'Plantilla no encontrada'); $this->redirect(base_url("sgsst/empresa/{$eid}/documentos")); }
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx'], true)) {
            flash('warning', 'Este archivo está en formato antiguo (' . $ext . '): por ahora solo se puede descargar el original. La conversión a formato moderno está en la fase de limpieza documental.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        try {
            $res = GeneradorPlantilla::generar($ruta, $empresa, $eid);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo generar: ' . $e->getMessage());
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        Database::execute(
            'INSERT INTO sgsst_documentos (empresa_id, plantilla, nombre_archivo, ruta, hash_sha256, generado_por) VALUES (?,?,?,?,?,?)',
            [$eid, basename($ruta), $res['nombre'], $res['ruta'], hash_file('sha256', $res['ruta']), Auth::id()]
        );
        Auditoria::registrar('plantilla.documento_generado', $eid, 'sgsst_documento', Database::lastId(), $res['nombre']);
        flash('success', 'Documento generado con los datos de la empresa. Revisa los campos restantes (firmas, responsables específicos) antes de usarlo oficialmente.');
        $this->servir($res['ruta'], $res['nombre']);
    }

    /** GET /sgsst/documentos-generados/{id}/descargar */
    public function descargarGenerado(string $id): void
    {
        $d = Database::first(
            'SELECT doc.* FROM sgsst_documentos doc JOIN empresas e ON e.id = doc.empresa_id
             WHERE doc.id = ? AND (e.usuario_id = ? OR EXISTS (SELECT 1 FROM empresa_accesos ea WHERE ea.empresa_id = e.id AND ea.usuario_id = ?))',
            [(int)$id, Auth::id(), Auth::id()]
        );
        if (!$d || !is_file($d['ruta'])) { http_response_code(404); die('Documento no encontrado'); }
        Auditoria::registrar('plantilla.descarga_generado', (int)$d['empresa_id'], 'sgsst_documento', (int)$id, $d['nombre_archivo']);
        $this->servir($d['ruta'], $d['nombre_archivo']);
    }


    private function permisoGestion(int $empresaId): ?string
    {
        $permiso = Sgsst::permiso($empresaId, (int) Auth::id());
        return in_array($permiso, ['propietario', 'representante'], true) ? $permiso : null;
    }

    private function documentoGestionable(string $id): array
    {
        $d = Database::first('SELECT * FROM sgsst_documentos WHERE id = ?', [(int) $id]);
        if (!$d || !$this->permisoGestion((int) $d['empresa_id'])) {
            flash('error', 'Documento no encontrado o sin permiso para gestionarlo');
            $this->redirect(base_url('sgsst'));
        }
        return $d;
    }

    /** GET /sgsst/documentos/{id}/ver — página a detalle, diligenciada e imprimible */
    public function verDocumento(string $id): void
    {
        $d = Database::first(
            'SELECT doc.* FROM sgsst_documentos doc JOIN empresas e ON e.id = doc.empresa_id
             WHERE doc.id = ? AND (e.usuario_id = ? OR EXISTS (SELECT 1 FROM empresa_accesos ea WHERE ea.empresa_id = e.id AND ea.usuario_id = ?))',
            [(int) $id, Auth::id(), Auth::id()]
        );
        if (!$d) { http_response_code(404); die('Documento no encontrado'); }
        $empresa = Database::first('SELECT * FROM empresas WHERE id = ?', [(int) $d['empresa_id']]);
        $puedeEditar = (bool) $this->permisoGestion((int) $d['empresa_id']);
        $personal = $puedeEditar ? Database::query('SELECT nombre_completo, cedula FROM personal WHERE empresa_id = ? AND activo = 1 ORDER BY nombre_completo', [(int) $d['empresa_id']]) : [];
        $tituloDoc = preg_replace('/\s+—\s+.*$/', '', pathinfo($d['nombre_archivo'], PATHINFO_FILENAME)) ?? $d['nombre_archivo'];
        $this->view('sgsst/documento_detalle', [
            'titulo' => 'Documento — ' . $tituloDoc, 'csrf' => $this->csrfToken(),
            'doc' => $d, 'empresa' => $empresa, 'personal' => $personal, 'puedeEditar' => $puedeEditar,
            'tituloDoc' => mb_strtoupper(trim($tituloDoc)),
            'parrafos' => DocumentoVista::extraer($d['ruta'], $empresa),
            'sugiereFirma' => DocumentoVista::sugiereFirma($d['nombre_archivo']),
        ]);
    }

    /** POST /sgsst/documentos/{id}/detalle — encabezado, firmas y notas del HSE */
    public function guardarDetalle(string $id): void
    {
        $this->verifyCsrf();
        $d = $this->documentoGestionable($id);
        Database::execute(
            'UPDATE sgsst_documentos SET codigo_documento = ?, fecha_elaboracion = ?, requiere_firma = ?,
             firma_resp_nombre = ?, firma_resp_cc = ?, firma_rep_nombre = ?, firma_rep_cc = ?, notas_hse = ? WHERE id = ?',
            [trim($_POST['codigo_documento'] ?? '') ?: null,
             ($_POST['fecha_elaboracion'] ?? '') ?: date('Y-m-d'),
             isset($_POST['requiere_firma']) ? 1 : 0,
             trim($_POST['firma_resp_nombre'] ?? '') ?: null, trim($_POST['firma_resp_cc'] ?? '') ?: null,
             trim($_POST['firma_rep_nombre'] ?? '') ?: null, trim($_POST['firma_rep_cc'] ?? '') ?: null,
             trim($_POST['notas_hse'] ?? '') ?: null, (int) $d['id']]
        );
        Auditoria::registrar('documento.detalle_actualizado', (int) $d['empresa_id'], 'sgsst_documento', (int) $d['id']);
        flash('success', 'Encabezado, firmas y notas guardados. La vista del documento ya los refleja.');
        $this->redirect(base_url("sgsst/documentos/{$d['id']}/ver"));
    }

    /** POST /sgsst/empresa/{id}/documentos/paquete — generar el paquete completo (empresas ya creadas) */
    public function generarPaquete(string $id): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPropia((int) $id); $eid = (int) $empresa['id'];
        $fresca = Database::first('SELECT * FROM empresas WHERE id = ?', [$eid]) ?: $empresa;
        $res = GeneradorPaquete::generar($fresca, Auth::id());
        if (($res['total'] ?? 0) === 0) {
            flash('warning', 'No se generó el paquete: verifica que la carpeta plantillas/ esté completa en el servidor.');
        } elseif (($res['generados'] + $res['copiados']) === 0) {
            flash('success', 'Esta empresa ya tenía su paquete documental generado (' . $res['total'] . ' documentos).');
        } else {
            flash('success', "Paquete documental generado: {$res['generados']} diligenciados + {$res['copiados']} originales, en estado «pendiente de revisión» para el cliente.");
        }
        $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
    }

    /** POST /sgsst/documentos/{id}/aceptar — el cliente decide dejarlo así */
    public function aceptarDocumento(string $id): void
    {
        $this->verifyCsrf();
        $d = $this->documentoGestionable($id);
        Database::execute("UPDATE sgsst_documentos SET estado='aceptado' WHERE id=?", [(int) $id]);
        Auditoria::registrar('documento.aceptado', (int) $d['empresa_id'], 'sgsst_documento', (int) $id, $d['nombre_archivo']);
        flash('success', 'Documento aceptado: queda como versión oficial de la empresa.');
        $this->redirect(base_url("sgsst/empresa/{$d['empresa_id']}/documentos"));
    }

    /** POST /sgsst/documentos/{id}/regenerar — nueva versión con los datos actuales de la empresa */
    public function regenerarDocumento(string $id): void
    {
        $this->verifyCsrf();
        $d = $this->documentoGestionable($id);
        $eid = (int) $d['empresa_id'];
        $empresa = Database::first('SELECT * FROM empresas WHERE id = ?', [$eid]);
        $rutaOrigen = $d['plantilla'] ? PlantillasCatalogo::resolver((string) $d['plantilla']) : null;
        if (!$empresa || !$rutaOrigen || !in_array(strtolower(pathinfo($rutaOrigen, PATHINFO_EXTENSION)), ['docx', 'xlsx'], true)) {
            flash('error', 'Este documento no se puede regenerar automáticamente (formato antiguo): descárgalo, corrígelo y sube tu versión.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        try { $res = GeneradorPlantilla::generar($rutaOrigen, $empresa, $eid); }
        catch (\Throwable $e) { flash('error', 'No se pudo regenerar: ' . $e->getMessage()); $this->redirect(base_url("sgsst/empresa/{$eid}/documentos")); }
        Database::execute(
            "UPDATE sgsst_documentos SET nombre_archivo=?, ruta=?, hash_sha256=?, estado='pendiente_revision', version=COALESCE(version,1)+1 WHERE id=?",
            [$res['nombre'], $res['ruta'], hash_file('sha256', $res['ruta']), (int) $id]
        );
        Auditoria::registrar('documento.regenerado', $eid, 'sgsst_documento', (int) $id, $res['nombre']);
        flash('success', 'Documento regenerado con los datos actuales de la empresa (nueva versión). Revísalo y acéptalo si queda bien.');
        $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
    }

    /** POST /sgsst/documentos/{id}/subir — el cliente sube su versión corregida del documento */
    public function subirCorreccion(string $id): void
    {
        $this->verifyCsrf();
        $d = $this->documentoGestionable($id);
        $eid = (int) $d['empresa_id'];
        $file = $_FILES['archivo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 15 * 1024 * 1024) {
            flash('error', 'Archivo inválido (máximo 15 MB).');
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx', 'doc', 'xls', 'pdf'], true)) {
            flash('error', 'Solo se permiten documentos DOC/DOCX, XLS/XLSX o PDF.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        $config = require BASE_PATH . '/config/config.php';
        $dir = $config['storage']['documentos'] . 'sgsst/' . $eid . '/corregidos/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $nombre = pathinfo($d['nombre_archivo'], PATHINFO_FILENAME) . ' — corregido v' . ((int)($d['version'] ?? 1) + 1) . ' ' . date('Ymd_His') . '.' . $ext;
        $nombre = preg_replace('/[^A-Za-z0-9áéíóúÁÉÍÓÚñÑ _.\-]/u', '', $nombre) ?? ('corregido.' . $ext);
        $ruta = $dir . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $ruta)) {
            flash('error', 'No se pudo guardar el archivo subido.');
            $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
        }
        Database::execute(
            "UPDATE sgsst_documentos SET nombre_archivo=?, ruta=?, hash_sha256=?, estado='corregido', version=COALESCE(version,1)+1 WHERE id=?",
            [$nombre, $ruta, hash_file('sha256', $ruta), (int) $id]
        );
        Auditoria::registrar('documento.correccion_subida', $eid, 'sgsst_documento', (int) $id, $nombre);
        flash('success', 'Tu versión corregida quedó guardada como documento de la empresa. Gracias por revisarlo.');
        $this->redirect(base_url("sgsst/empresa/{$eid}/documentos"));
    }
}
