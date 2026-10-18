<?php
namespace App\Controllers;

use App\Auth;
use App\Database;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $usuarioId = Auth::id();

        // KPIs principales
        $totalEmpresas = (int) Database::first(
            'SELECT COUNT(*) c FROM empresas WHERE usuario_id = ?',
            [$usuarioId]
        )['c'];

        $totalAnalisis = (int) Database::first(
            'SELECT COUNT(*) c FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE e.usuario_id = ?',
            [$usuarioId]
        )['c'];

        $totalPersonal = (int) Database::first(
            'SELECT COUNT(*) c FROM personal p
             JOIN empresas e ON e.id = p.empresa_id
             WHERE e.usuario_id = ? AND p.activo = 1',
            [$usuarioId]
        )['c'];

        $totalBrigadistas = (int) Database::first(
            'SELECT COUNT(*) c FROM personal p
             JOIN empresas e ON e.id = p.empresa_id
             WHERE e.usuario_id = ? AND p.activo = 1
             AND p.entrenado_brigada IS NOT NULL AND p.entrenado_brigada != ""',
            [$usuarioId]
        )['c'];

        // Últimos análisis
        $analisisRecientes = Database::query(
            'SELECT a.*, e.razon_social, e.nit
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE e.usuario_id = ?
             ORDER BY a.created_at DESC
             LIMIT 5',
            [$usuarioId]
        );

        // Empresas (para grid o tabla)
        $empresas = Database::query(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM analisis a WHERE a.empresa_id = e.id) AS total_analisis,
                    (SELECT MAX(fecha_elaboracion) FROM analisis a WHERE a.empresa_id = e.id) AS ultimo_analisis
             FROM empresas e
             WHERE e.usuario_id = ?
             ORDER BY e.razon_social',
            [$usuarioId]
        );

        $this->view('dashboard', [
            'titulo'            => 'Dashboard',
            'totalEmpresas'     => $totalEmpresas,
            'totalAnalisis'     => $totalAnalisis,
            'totalPersonal'     => $totalPersonal,
            'totalBrigadistas'  => $totalBrigadistas,
            'analisisRecientes' => $analisisRecientes,
            'empresas'          => $empresas,
        ]);
    }

    /**
     * GET /api/dashboard/stats — para Chart.js
     */
    public function stats(): void
    {
        $usuarioId = Auth::id();

        // Conteo por nivel de riesgo (última versión de cada análisis)
        $niveles = Database::query(
            "SELECT am.nivel_riesgo, COUNT(*) AS c
             FROM amenazas am
             JOIN analisis a ON a.id = am.analisis_id
             JOIN empresas e ON e.id = a.empresa_id
             WHERE e.usuario_id = ? AND am.aplica = 1 AND am.nivel_riesgo IS NOT NULL
             GROUP BY am.nivel_riesgo",
            [$usuarioId]
        );

        // Vulnerabilidad promedio por amenaza
        $porAmenaza = Database::query(
            "SELECT am.nombre,
                    AVG(am.total_personas) AS personas,
                    AVG(am.total_recursos) AS recursos,
                    AVG(am.total_sistemas) AS sistemas
             FROM amenazas am
             JOIN analisis a ON a.id = am.analisis_id
             JOIN empresas e ON e.id = a.empresa_id
             WHERE e.usuario_id = ? AND am.aplica = 1
             GROUP BY am.nombre
             ORDER BY am.nombre",
            [$usuarioId]
        );

        // Análisis por mes (últimos 6)
        $porMes = Database::query(
            "SELECT strftime('%Y-%m', a.created_at) AS mes, COUNT(*) AS c
             FROM analisis a
             JOIN empresas e ON e.id = a.empresa_id
             WHERE e.usuario_id = ?
               AND a.created_at >= date('now', '-6 months')
             GROUP BY mes
             ORDER BY mes",
            [$usuarioId]
        );

        $this->json([
            'niveles'    => $niveles,
            'porAmenaza' => $porAmenaza,
            'porMes'     => $porMes,
        ]);
    }
}
