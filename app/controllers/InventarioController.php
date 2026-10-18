<?php
namespace App\Controllers;

use App\Auth;
use App\Database;

class InventarioController extends BaseController
{
    public function paso2(string $id): void
    {
        $analisis = $this->obtenerAnalisis((int) $id);

        $extintores = Database::query(
            'SELECT * FROM inv_extintores WHERE analisis_id = ? ORDER BY id',
            [(int) $id]
        );
        $botiquin = Database::query(
            'SELECT * FROM inv_botiquin WHERE analisis_id = ? ORDER BY id',
            [(int) $id]
        );
        $otros = Database::query(
            'SELECT * FROM inv_otros WHERE analisis_id = ? ORDER BY categoria',
            [(int) $id]
        );

        $this->view('wizard/paso2_inventario', [
            'titulo'     => 'Paso 2 — Inventario',
            'analisis'   => $analisis,
            'extintores' => $extintores,
            'botiquin'   => $botiquin,
            'otros'      => $otros,
            'analisisId' => (int) $id,
            'paso'       => 2,
            'csrf'       => $this->csrfToken(),
        ]);
    }

    public function guardarPaso2(string $id): void
    {
        $this->verifyCsrf();
        // Lógica de guardado pendiente
        $this->redirect(base_url("analisis/{$id}/wizard/paso3"));
    }

    private function obtenerAnalisis(int $id): array
    {
        $analisis = Database::first(
            'SELECT a.*, e.razon_social, e.nit, e.num_trabajadores
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
}
