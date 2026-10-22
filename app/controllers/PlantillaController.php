<?php
namespace App\Controllers;

use App\Auth;
use App\Auditoria;
use App\Database;
use App\GeneradorPlantilla;
use App\PlantillasCatalogo;
use App\Sgsst;

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
        $this->view('sgsst/documentos', [
            'titulo' => 'Catálogo documental SG-SST — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'grupos' => PlantillasCatalogo::listar(),
            'generados' => $generados, 'soloLectura' => (bool)$empresa['solo_lectura'],
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
}
