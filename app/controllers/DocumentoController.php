<?php
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\GeneradorPlan;

class DocumentoController extends BaseController
{
    /**
     * GET /analisis/{id}/wizard/paso7
     */
    public function paso7(string $id): void
    {
        $analisisId = (int) $id;
        $analisis   = $this->obtenerAnalisis($analisisId);

        $documentos = Database::query(
            'SELECT * FROM documentos WHERE analisis_id = ? ORDER BY created_at DESC',
            [$analisisId]
        );

        $this->view('wizard/paso7_generar', [
            'titulo'     => 'Paso 7 — Generar documentos',
            'analisis'   => $analisis,
            'documentos' => $documentos,
            'analisisId' => $analisisId,
            'paso'       => 7,
            'csrf'       => $this->csrfToken(),
        ]);
    }

    /**
     * POST /analisis/{id}/generar
     */
    public function generar(string $id): void
    {
        $this->verifyCsrf();
        $analisisId = (int) $id;
        $this->obtenerAnalisis($analisisId);

        try {
            $generador = new GeneradorPlan($analisisId);
            $resultado = $generador->generar();

            // Marcar análisis como generado
            Database::execute(
                'UPDATE analisis SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                ['generado', $analisisId]
            );

            flash('success', 'Documentos generados: ' . $resultado['nombre']);
            $this->redirect(base_url("analisis/{$analisisId}/descargar/{$resultado['nombre']}"));

        } catch (\Throwable $e) {
            flash('error', 'Error al generar: ' . $e->getMessage());
            $this->redirect(base_url("analisis/{$analisisId}/wizard/paso7"));
        }
    }

    /**
     * GET /analisis/{id}/descargar/{archivo}
     */
    public function descargar(string $id, string $archivo): void
    {
        $analisisId = (int) $id;
        $this->obtenerAnalisis($analisisId);

        // Sanitizar nombre de archivo (evitar path traversal)
        $archivo = basename($archivo);

        $config = require BASE_PATH . '/config/config.php';
        $ruta   = $config['storage']['documentos'] . $archivo;

        if (!file_exists($ruta)) {
            http_response_code(404);
            die('Archivo no encontrado: ' . htmlspecialchars($archivo));
        }

        // Detectar MIME
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'zip'  => 'application/zip',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf'  => 'application/pdf',
            default => 'application/octet-stream',
        };

        // Headers
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $archivo . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        readfile($ruta);
        exit;
    }

    private function obtenerAnalisis(int $id): array
    {
        $analisis = Database::first(
            'SELECT a.*, e.razon_social, e.nit
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE a.id = ? AND e.usuario_id = ?',
            [$id, Auth::id()]
        );

        if (!$analisis) {
            flash('error', 'Análisis no encontrado');
            $this->redirect(base_url('empresas'));
        }
        return $analisis;
    }
    /**
 * GET /documentos
 */
public function index(): void
{
    $documentos = Database::query(
        'SELECT d.*, a.version, a.fecha_elaboracion,
                e.razon_social, e.nit
         FROM documentos d
         JOIN analisis a ON a.id = d.analisis_id
         JOIN empresas e ON e.id = a.empresa_id
         WHERE e.usuario_id = ?
         ORDER BY d.created_at DESC',
        [Auth::id()]
    );

    // Stats
    $totalSize = 0;
    foreach ($documentos as $d) {
        if (file_exists($d['ruta'])) $totalSize += filesize($d['ruta']);
    }

    $this->view('documentos/index', [
        'titulo'     => 'Documentos',
        'documentos' => $documentos,
        'totalSize'  => $totalSize,
        'csrf'       => $this->csrfToken(),
    ]);
}

/**
 * POST /documentos/{id}/eliminar
 */
public function eliminar(string $id): void
{
    $this->verifyCsrf();

    $doc = Database::first(
        'SELECT d.* FROM documentos d
         JOIN analisis a ON a.id = d.analisis_id
         JOIN empresas e ON e.id = a.empresa_id
         WHERE d.id = ? AND e.usuario_id = ?',
        [(int) $id, Auth::id()]
    );

    if (!$doc) {
        flash('error', 'Documento no encontrado');
        $this->redirect(base_url('documentos'));
    }

    if (file_exists($doc['ruta'])) @unlink($doc['ruta']);
    Database::execute('DELETE FROM documentos WHERE id = ?', [(int) $id]);

    flash('success', 'Documento eliminado');
    $this->redirect(base_url('documentos'));
}
}
