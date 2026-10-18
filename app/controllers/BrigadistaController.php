<?php
namespace App\Controllers;

use App\Auth;
use App\Database;

class BrigadistaController extends BaseController
{
    /**
     * GET /brigadistas
     */
    public function index(): void
    {
        $empresas = Database::query(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM personal p
                     WHERE p.empresa_id = e.id AND p.activo = 1
                     AND p.entrenado_brigada IS NOT NULL AND p.entrenado_brigada != "") AS total_brigadistas,
                    (SELECT COUNT(*) FROM personal p
                     WHERE p.empresa_id = e.id AND p.activo = 1) AS total_personal
             FROM empresas e
             WHERE e.usuario_id = ?
             ORDER BY e.razon_social',
            [Auth::id()]
        );

        // Calcular mínimo requerido por empresa
        foreach ($empresas as &$e) {
            $e['minimo_brigadistas'] = (int) ceil(((int)($e['num_trabajadores'] ?? 0)) * 0.20);
            $e['cumple'] = $e['total_brigadistas'] >= $e['minimo_brigadistas'];
        }

        $totalBrigadistas = array_sum(array_column($empresas, 'total_brigadistas'));
        $totalRequeridos  = array_sum(array_column($empresas, 'minimo_brigadistas'));

        $this->view('brigadistas/index', [
            'titulo'           => 'Brigadistas',
            'empresas'         => $empresas,
            'totalBrigadistas' => $totalBrigadistas,
            'totalRequeridos'  => $totalRequeridos,
        ]);
    }

    /**
     * GET /brigadistas/empresa/{id}
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
            $this->redirect(base_url('brigadistas'));
        }

        // Brigadistas actuales
        $brigadistas = Database::query(
            'SELECT * FROM personal
             WHERE empresa_id = ? AND activo = 1
             AND entrenado_brigada IS NOT NULL AND entrenado_brigada != ""
             ORDER BY nombre_completo',
            [$empresaId]
        );

        // Personal NO brigadista (candidatos)
        $candidatos = Database::query(
            'SELECT * FROM personal
             WHERE empresa_id = ? AND activo = 1
             AND (entrenado_brigada IS NULL OR entrenado_brigada = "")
             ORDER BY nombre_completo',
            [$empresaId]
        );

        $minimo = (int) ceil(((int)($empresa['num_trabajadores'] ?? 0)) * 0.20);

        $this->view('brigadistas/empresa', [
            'titulo'      => 'Brigadistas — ' . $empresa['razon_social'],
            'empresa'     => $empresa,
            'brigadistas' => $brigadistas,
            'candidatos'  => $candidatos,
            'minimo'      => $minimo,
            'csrf'        => $this->csrfToken(),
        ]);
    }

    /**
     * POST /brigadistas/asignar
     */
    public function asignar(): void
    {
        $this->verifyCsrf();

        $personalId = (int) ($_POST['personal_id'] ?? 0);
        $empresaId  = (int) ($_POST['empresa_id'] ?? 0);
        $tipo       = trim($_POST['tipo'] ?? 'Primeros Auxilios');

        if (!$personalId || !$empresaId) {
            flash('error', 'Datos incompletos');
            $this->redirect(base_url('brigadistas'));
        }

        // Verificar que la empresa pertenece al usuario
        $empresa = Database::first(
            'SELECT id FROM empresas WHERE id = ? AND usuario_id = ?',
            [$empresaId, Auth::id()]
        );
        if (!$empresa) {
            flash('error', 'Empresa no autorizada');
            $this->redirect(base_url('brigadistas'));
        }

        Database::execute(
            'UPDATE personal SET entrenado_brigada = ? WHERE id = ? AND empresa_id = ?',
            [$tipo, $personalId, $empresaId]
        );

        flash('success', 'Persona asignada a la brigada');
        $this->redirect(base_url("brigadistas/empresa/{$empresaId}"));
    }

    /**
     * POST /brigadistas/{id}/quitar
     */
    public function quitar(string $id): void
    {
        $this->verifyCsrf();
        $empresaId = (int) ($_POST['empresa_id'] ?? 0);

        Database::execute(
            'UPDATE personal SET entrenado_brigada = NULL WHERE id = ?',
            [(int) $id]
        );

        flash('success', 'Persona removida de la brigada');
        $this->redirect(base_url("brigadistas/empresa/{$empresaId}"));
    }
}
