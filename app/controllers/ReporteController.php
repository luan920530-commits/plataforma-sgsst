<?php
namespace App\Controllers;

use App\Auditoria;
use App\Database;

/**
 * Formulario PÚBLICO de reportes para operarios/empleados (sin login).
 * Cada empresa tiene un enlace único con token (cartelera, QR, WhatsApp…).
 * El reporte entra como registro del SG-SST de esa empresa y queda auditado.
 */
class ReporteController extends BaseController
{
    private function empresaPorToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        return Database::first('SELECT id, razon_social FROM empresas WHERE reporte_token = ?', [$token]);
    }

    /** GET /reporte/{token} */
    public function formulario(string $token): void
    {
        $empresa = $this->empresaPorToken($token);
        if (!$empresa) { http_response_code(404); die('Enlace de reportes no válido. Pide el enlace actualizado a tu empresa.'); }
        $this->view('portal/reporte', [
            'titulo' => 'Reportar — ' . $empresa['razon_social'],
            'empresa' => $empresa, 'token' => $token, 'csrf' => $this->csrfToken(), 'enviado' => false,
        ]);
    }

    /** POST /reporte/{token} */
    public function enviar(string $token): void
    {
        $this->verifyCsrf();
        $empresa = $this->empresaPorToken($token);
        if (!$empresa) { http_response_code(404); die('Enlace de reportes no válido.'); }
        // Anti-spam simple: 1 envío por minuto por sesión
        if (!empty($_SESSION['ultimo_reporte']) && time() - (int) $_SESSION['ultimo_reporte'] < 60) {
            flash('warning', 'Espera un minuto antes de enviar otro reporte.');
            $this->redirect(base_url("reporte/{$token}"));
        }
        $eid = (int) $empresa['id'];
        $tipos = [
            'condicion_insegura' => 'Condición insegura',
            'acto_inseguro' => 'Acto inseguro',
            'incidente' => 'Incidente (casi-accidente)',
            'accidente' => 'Accidente de trabajo',
            'sugerencia' => 'Sugerencia de mejora en SST',
        ];
        $tipoSel = array_key_exists($_POST['tipo'] ?? '', $tipos) ? $_POST['tipo'] : 'condicion_insegura';
        $descripcion = trim($_POST['descripcion'] ?? '');
        if (strlen($descripcion) < 10) {
            flash('error', 'Cuéntanos un poco más: la descripción debe tener al menos 10 caracteres.');
            $this->redirect(base_url("reporte/{$token}"));
        }
        $nombre = trim($_POST['nombre'] ?? '');
        $area = trim($_POST['area'] ?? '');
        $titulo = $tipos[$tipoSel] . ($area ? " — {$area}" : '');
        $detalle = $descripcion . ($nombre ? "\nReporta: {$nombre}" : "\nReporta: anónimo");
        Database::execute(
            'INSERT INTO sgsst_registros (empresa_id, tipo, titulo, descripcion, fecha, severidad, estado) VALUES (?,?,?,?,?,?,?)',
            [$eid, $tipoSel === 'accidente' ? 'accidente' : ($tipoSel === 'sugerencia' ? 'accion_mejora' : 'incidente'), $titulo, $detalle, date('Y-m-d'),
             in_array($_POST['severidad'] ?? '', ['baja','media','alta','critica'], true) ? $_POST['severidad'] : 'media', 'abierto']
        );
        $_SESSION['ultimo_reporte'] = time();
        Auditoria::registrar('reporte.operario_recibido', $eid, 'registro', Database::lastId(), $titulo);
        $this->view('portal/reporte', [
            'titulo' => 'Reporte recibido', 'empresa' => $empresa, 'token' => $token,
            'csrf' => $this->csrfToken(), 'enviado' => true,
        ]);
    }

    /** POST /sgsst/empresa/{id}/reporte-token — el propietario regenera el enlace (invalida el anterior) */
    public function regenerarToken(string $id): void
    {
        $this->verifyCsrf();
        $e = Database::first('SELECT id FROM empresas WHERE id = ? AND usuario_id = ?', [(int) $id, \App\Auth::id()]);
        if (!$e) { flash('error', 'Empresa no encontrada'); $this->redirect(base_url('sgsst')); }
        Database::execute('UPDATE empresas SET reporte_token = ? WHERE id = ?', [bin2hex(random_bytes(16)), (int) $id]);
        Auditoria::registrar('reporte.token_regenerado', (int) $id, 'empresa', (int) $id);
        flash('success', 'Enlace de reportes regenerado: comparte el nuevo con los operarios (el anterior dejó de funcionar).');
        $this->redirect(base_url("sgsst/empresa/{$id}#accesos"));
    }
}
