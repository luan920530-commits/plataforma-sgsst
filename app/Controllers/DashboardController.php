<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Models\ActividadEconomica;
use App\Models\Auditoria;
use App\Models\Autoreporte;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\EstandarMinimo;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\Peligro;
use App\Models\PlanTrabajo;
use App\Models\Tenant;
use App\Services\PesvLibrary;
use App\Support\Auth;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        if (Auth::role() === 'Trabajador') {
            redirect('/portal');
        }
        if (Auth::role() === 'SuperAdmin') {
            $this->admin();
            return;
        }
        $this->tenant();
    }

    private function admin(): void
    {
        $this->view('admin/dashboard', [
            'title' => 'Vista general de la plataforma',
            'stats' => Tenant::globalStats(),
            'users' => (int) (Database::first('SELECT COUNT(*) AS total FROM users WHERE activo = 1')['total'] ?? 0),
            'documents' => (int) (Database::first('SELECT COUNT(*) AS total FROM documentos_sgsst')['total'] ?? 0),
            'activitiesCount' => ActividadEconomica::count(),
            'standardCounts' => EstandarMinimo::counts(),
            'tenants' => array_slice(Tenant::all(), 0, 6),
            'audit' => Auditoria::recent(7),
        ]);
    }

    private function tenant(): void
    {
        $tenantId = $this->tenantId();
        $documentStats = Documento::stats($tenantId);
        $planStats = PlanTrabajo::stats($tenantId);
        $tenant = Tenant::find($tenantId);
        $activity = !empty($tenant['actividad_economica_id']) ? ActividadEconomica::find((int) $tenant['actividad_economica_id']) : null;
        $this->view('tenant/dashboard', [
            'title' => 'Resumen del SG-SST',
            'tenant' => $tenant,
            'pesvRecommended' => PesvLibrary::isRecommended($tenant ?? [], $activity),
            'pesvDocument' => Documento::findByCode(PesvLibrary::PLAN_CODE, $tenantId),
            'documentStats' => $documentStats,
            'planStats' => $planStats,
            'hazardStats' => Peligro::stats($tenantId),
            'openFindings' => Hallazgo::openCount($tenantId),
            'openReports' => Autoreporte::openCount($tenantId),
            'upcoming' => PlanTrabajo::upcoming($tenantId, 5),
            'trainings' => Capacitacion::upcoming($tenantId, 4),
            'indicators' => Indicador::latest($tenantId, 3),
            'recentDocuments' => array_slice(Documento::forTenant($tenantId), 0, 5),
            'documentProgress' => ((int) ($documentStats['total'] ?? 0)) > 0 ? round(((int) $documentStats['aprobados'] / (int) $documentStats['total']) * 100) : 0,
            'planProgress' => ((int) ($planStats['total'] ?? 0)) > 0 ? round(((int) $planStats['completadas'] / (int) $planStats['total']) * 100) : 0,
        ]);
    }
}
