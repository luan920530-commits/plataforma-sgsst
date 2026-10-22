<?php
namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Models\Empresa;
use App\Sgsst;
use App\Auditoria;

class EmpresaController extends BaseController
{
    public function index(): void
    {
        $empresas = Empresa::todasPorUsuario(Auth::id());
        $this->view('empresas/index', [
            'titulo'   => 'Empresas',
            'empresas' => $empresas,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function formNueva(): void
    {
        $this->view('empresas/form', [
            'titulo'  => 'Nueva empresa',
            'empresa' => null,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function guardar(): void
    {
        $this->verifyCsrf();

        $datos = $this->datosFormulario();
        $datos['usuario_id'] = Auth::id();

        $id = Empresa::crear($datos);
        Sgsst::inicializarEmpresa($id);
        Auditoria::registrar('empresa.creada', $id, 'empresa', $id, $datos['razon_social'] ?? '');

        flash('success', 'Empresa creada. Su SG-SST quedó inicializado: evaluación de estándares, plan anual base y plantillas listas en el Centro de cumplimiento.');
        $this->redirect(base_url("empresas/{$id}"));
    }

    public function ver(string $id): void
    {
        $empresa = Empresa::buscar((int) $id);
        if (!$empresa || $empresa['usuario_id'] != Auth::id()) {
            flash('error', 'Empresa no encontrada');
            $this->redirect(base_url('empresas'));
        }

        $analisis = Database::query(
            'SELECT * FROM analisis WHERE empresa_id = ? ORDER BY created_at DESC',
            [(int) $id]
        );

        $this->view('empresas/ver', [
            'titulo'   => $empresa['razon_social'],
            'empresa'  => $empresa,
            'analisis' => $analisis,
            'csrf'     => $this->csrfToken(),
        ]);
    }

    public function formEditar(string $id): void
    {
        $empresa = Empresa::buscar((int) $id);
        if (!$empresa || $empresa['usuario_id'] != Auth::id()) {
            flash('error', 'Empresa no encontrada');
            $this->redirect(base_url('empresas'));
        }

        $this->view('empresas/form', [
            'titulo'  => 'Editar empresa',
            'empresa' => $empresa,
            'csrf'    => $this->csrfToken(),
        ]);
    }

    public function actualizar(string $id): void
    {
        $this->verifyCsrf();

        $empresa = Empresa::buscar((int) $id);
        if (!$empresa || $empresa['usuario_id'] != Auth::id()) {
            flash('error', 'Empresa no encontrada');
            $this->redirect(base_url('empresas'));
        }

        Empresa::actualizar((int) $id, $this->datosFormulario());
        Auditoria::registrar('empresa.actualizada', (int) $id, 'empresa', (int) $id);
        flash('success', 'Empresa actualizada');
        $this->redirect(base_url("empresas/{$id}"));
    }

    private function datosFormulario(): array
    {
        return [
            'razon_social'         => trim($_POST['razon_social'] ?? ''),
            'nit'                  => trim($_POST['nit'] ?? ''),
            'ciiu'                 => trim($_POST['ciiu'] ?? ''),
            'actividad_economica'  => trim($_POST['actividad_economica'] ?? ''),
            'direccion'            => trim($_POST['direccion'] ?? ''),
            'ciudad'               => trim($_POST['ciudad'] ?? ''),
            'departamento'         => trim($_POST['departamento'] ?? ''),
            'telefono'             => trim($_POST['telefono'] ?? ''),
            'email'                => trim($_POST['email'] ?? ''),
            'arl'                  => trim($_POST['arl'] ?? ''),
            'nivel_riesgo'         => $_POST['nivel_riesgo'] ?? 'I',
            'representante_legal'  => trim($_POST['representante_legal'] ?? ''),
            'num_trabajadores'     => (int) ($_POST['num_trabajadores'] ?? 0),
            'aforo_max'            => (int) ($_POST['aforo_max'] ?? 0),
            'area_m2'              => (float) ($_POST['area_m2'] ?? 0),
            'num_pisos'            => (int) ($_POST['num_pisos'] ?? 1),
            'horarios'             => trim($_POST['horarios'] ?? ''),
            'areas_alto_riesgo'    => trim($_POST['areas_alto_riesgo'] ?? ''),
        ];
    }
}
