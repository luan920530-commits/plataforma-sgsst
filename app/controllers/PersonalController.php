<?php
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Models\Personal;

class PersonalController extends BaseController
{
    /**
     * GET /analisis/{id}/wizard/paso5
     */
    public function paso5(string $id): void
    {
        $analisisId = (int) $id;

        $analisis = Database::first(
            'SELECT a.*, e.id AS empresa_id_real, e.razon_social, e.num_trabajadores
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE a.id = ? AND e.usuario_id = ?',
            [$analisisId, Auth::id()]
        );

        if (!$analisis) {
            flash('error', 'Análisis no encontrado');
            $this->redirect(base_url('empresas'));
        }

        $personal = Database::query(
            'SELECT * FROM personal
             WHERE empresa_id = ? AND activo = 1
             ORDER BY nombre_completo',
            [(int) $analisis['empresa_id_real']]
        );

        $minimoBrigadistas    = (int) ceil(((int) $analisis['num_trabajadores']) * 0.20);
        $brigadistasActuales  = Personal::contarBrigadistas((int) $analisis['empresa_id_real']);

        $this->view('wizard/paso5_personal', [
            'titulo'              => 'Paso 5 — Personal / MEDEVAC',
            'analisis'            => $analisis,
            'personal'            => $personal,
            'minimoBrigadistas'   => $minimoBrigadistas,
            'brigadistasActuales' => $brigadistasActuales,
            'analisisId'          => $analisisId,
            'paso'                => 5,
            'csrf'                => $this->csrfToken(),
        ]);
    }

    /** POST wizard paso5: el personal se guarda con crear/actualizar; aquí solo se avanza. */
    public function guardarPaso5(string $id): void
    {
        $this->verifyCsrf();
        $this->redirect(base_url("analisis/{$id}/wizard/paso6"));
    }

    /**
     * POST /personal  →  crear nuevo
     */
    public function crear(): void
    {
        $this->verifyCsrf();

        $analisisId = (int) ($_POST['analisis_id'] ?? 0);
        $empresaId  = (int) ($_POST['empresa_id'] ?? 0);

        if (!$analisisId || !$empresaId) {
            flash('error', 'Datos incompletos');
            $this->redirect(base_url('empresas'));
        }

        // Validar que la empresa pertenece al usuario
        $empresa = Database::first(
            'SELECT id FROM empresas WHERE id = ? AND usuario_id = ?',
            [$empresaId, Auth::id()]
        );
        if (!$empresa) {
            flash('error', 'Empresa no autorizada');
            $this->redirect(base_url('empresas'));
        }

        $nombre = trim($_POST['nombre_completo'] ?? '');
        if ($nombre === '') {
            flash('error', 'El nombre es obligatorio');
            $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
        }

        // entrenado_brigada llega como array (checkboxes con [])
        $entrenamiento = $_POST['entrenado_brigada'] ?? null;
        if (is_array($entrenamiento)) {
            $entrenamiento = implode(', ', $entrenamiento);
        }

        $datos = [
            'nombre_completo'            => $nombre,
            'cedula'                     => trim($_POST['cedula'] ?? ''),
            'email'                      => trim($_POST['email'] ?? ''),
            'cargo'                      => trim($_POST['cargo'] ?? ''),
            'rh'                         => trim($_POST['rh'] ?? ''),
            'celular'                    => trim($_POST['celular'] ?? ''),
            'dependencia'                => trim($_POST['dependencia'] ?? ''),
            'entrenado_brigada'          => $entrenamiento,
            'eps'                        => trim($_POST['eps'] ?? ''),
            'afp'                        => trim($_POST['afp'] ?? ''),
            'contacto_emergencia_nombre' => trim($_POST['contacto_emergencia_nombre'] ?? ''),
            'contacto_emergencia_tel'    => trim($_POST['contacto_emergencia_tel'] ?? ''),
            'alergias'                   => trim($_POST['alergias'] ?? ''),
            'firma_url'                  => null,
        ];

        $personalId = Personal::crear($empresaId, $datos);

        // Subir firma si existe
        if (!empty($_FILES['firma']['tmp_name'])) {
            $rutaFirma = $this->guardarFirma($personalId, $_FILES['firma']);
            if ($rutaFirma) {
                Personal::actualizar($personalId, array_merge($datos, ['firma_url' => $rutaFirma]));
            }
        }

        flash('success', 'Personal agregado correctamente');
        $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
    }

    /**
     * POST /personal/{id}  →  actualizar
     */
    public function actualizar(string $id): void
    {
        $this->verifyCsrf();

        $analisisId = (int) ($_POST['analisis_id'] ?? 0);
        $personalId = (int) $id;

        $p = Personal::buscar($personalId);
        $dueno = $p ? \App\Database::first('SELECT id FROM empresas WHERE id = ? AND usuario_id = ?', [(int)$p['empresa_id'], \App\Auth::id()]) : null;
        if (!$p || !$dueno) {
            flash('error', 'Registro no encontrado');
            $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
        }

        $entrenamiento = $_POST['entrenado_brigada'] ?? null;
        if (is_array($entrenamiento)) {
            $entrenamiento = implode(', ', $entrenamiento);
        }

        $datos = [
            'nombre_completo'            => trim($_POST['nombre_completo'] ?? ''),
            'cedula'                     => trim($_POST['cedula'] ?? ''),
            'email'                      => trim($_POST['email'] ?? ''),
            'cargo'                      => trim($_POST['cargo'] ?? ''),
            'rh'                         => trim($_POST['rh'] ?? ''),
            'celular'                    => trim($_POST['celular'] ?? ''),
            'dependencia'                => trim($_POST['dependencia'] ?? ''),
            'entrenado_brigada'          => $entrenamiento,
            'eps'                        => trim($_POST['eps'] ?? ''),
            'afp'                        => trim($_POST['afp'] ?? ''),
            'contacto_emergencia_nombre' => trim($_POST['contacto_emergencia_nombre'] ?? ''),
            'contacto_emergencia_tel'    => trim($_POST['contacto_emergencia_tel'] ?? ''),
            'alergias'                   => trim($_POST['alergias'] ?? ''),
            'firma_url'                  => $p['firma_url'],
        ];

        if (!empty($_FILES['firma']['tmp_name'])) {
            $rutaFirma = $this->guardarFirma($personalId, $_FILES['firma']);
            if ($rutaFirma) $datos['firma_url'] = $rutaFirma;
        }

        Personal::actualizar($personalId, $datos);

        flash('success', 'Personal actualizado');
        $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
    }

    /**
     * POST /personal/{id}/eliminar
     */
    public function eliminar(string $id): void
    {
        $this->verifyCsrf();
        $analisisId = (int) ($_POST['analisis_id'] ?? 0);

        $p = Personal::buscar((int) $id);
        $dueno = $p ? \App\Database::first('SELECT id FROM empresas WHERE id = ? AND usuario_id = ?', [(int)$p['empresa_id'], \App\Auth::id()]) : null;
        if (!$p || !$dueno) {
            flash('error', 'Registro no encontrado');
            $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
        }
        Personal::eliminar((int) $id);

        flash('success', 'Registro eliminado');
        $this->redirect(base_url("analisis/{$analisisId}/wizard/paso5"));
    }

    /**
     * Helper: guarda la imagen de firma en /public/uploads/firmas/
     */
    private function guardarFirma(int $personalId, array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 2 * 1024 * 1024) return null; // 2MB máx

        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;

        $ext = match($info['mime']) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => null,
        };
        if (!$ext) return null;

        $dir = BASE_PATH . '/public/uploads/firmas/';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $nombre = "firma_{$personalId}_" . time() . ".{$ext}";
        $ruta   = $dir . $nombre;

        if (!move_uploaded_file($file['tmp_name'], $ruta)) return null;

        return 'uploads/firmas/' . $nombre;
    }
    /**
 * GET /personal
 * Lista todas las empresas con conteo de personal
 */
public function index(): void
{
    $empresas = Database::query(
        'SELECT e.*,
                (SELECT COUNT(*) FROM personal p
                 WHERE p.empresa_id = e.id AND p.activo = 1) AS total_personal,
                (SELECT COUNT(*) FROM personal p
                 WHERE p.empresa_id = e.id AND p.activo = 1
                 AND p.entrenado_brigada IS NOT NULL AND p.entrenado_brigada != "") AS total_brigadistas
         FROM empresas e
         WHERE e.usuario_id = ?
         ORDER BY e.razon_social',
        [Auth::id()]
    );

    $this->view('personal/index', [
        'titulo'   => 'Personal',
        'empresas' => $empresas,
    ]);
}

/**
 * GET /personal/empresa/{id}
 * Todos los trabajadores de una empresa
 */
public function porEmpresa(string $id): void
{
    $empresaId = (int) $id;

    $empresa = Database::first(
        'SELECT * FROM empresas WHERE id = ? AND usuario_id = ?',
        [$empresaId, Auth::id()]
    );

    if (!$empresa) {
        flash('error', 'Empresa no encontrada');
        $this->redirect(base_url('personal'));
    }

    $personal = Database::query(
        'SELECT * FROM personal
         WHERE empresa_id = ? AND activo = 1
         ORDER BY nombre_completo',
        [$empresaId]
    );

    $this->view('personal/empresa', [
        'titulo'   => 'Personal — ' . $empresa['razon_social'],
        'empresa'  => $empresa,
        'personal' => $personal,
    ]);
}
}
