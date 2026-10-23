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
use App\Services\ModuleAccess;
use App\Services\PesvLibrary;
use App\Support\Auth;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        if (Auth::role() === 'Trabajador') {
            if (ModuleAccess::current() !== ModuleAccess::SGST) {
                $this->view('trabajador/modulo-restringido', [
                    'title' => 'Acceso de trabajador no incluido',
                    'tenant' => Tenant::find($this->tenantId()),
                    'module' => ModuleAccess::current(),
                ]);
                return;
            }
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
        $module = ModuleAccess::forTenant($tenantId);
        if ($module === ModuleAccess::PESV) {
            $this->pesv($tenantId);
            return;
        }
        if ($module === ModuleAccess::EMERGENCIAS) {
            $this->emergencies($tenantId);
            return;
        }
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

    private function pesv(int $tenantId): void
    {
        $documents = Documento::forTenant($tenantId, ['modulo' => ModuleAccess::PESV]);
        $this->view('tenant/dashboard-pesv', [
            'title' => 'Resumen PESVial',
            'tenant' => Tenant::find($tenantId),
            'libraryStats' => PesvLibrary::stats(),
            'planDocument' => Documento::findByCode(PesvLibrary::PLAN_CODE, $tenantId),
            'documentStats' => Documento::stats($tenantId, ModuleAccess::PESV),
            'recentDocuments' => array_slice($documents, 0, 6),
        ]);
    }

    private function emergencies(int $tenantId): void
    {
        $plans = Database::query('SELECT * FROM planes_emergencia WHERE tenant_id = ? ORDER BY updated_at DESC, id DESC', [$tenantId]);
        $programs = Database::query('SELECT * FROM programas_prevencion WHERE tenant_id = ? ORDER BY anio DESC, id DESC', [$tenantId]);
        $drills = Database::first(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN estado = 'Realizado' THEN 1 ELSE 0 END) AS realizados,
                    SUM(CASE WHEN estado = 'Programado' THEN 1 ELSE 0 END) AS programados
             FROM simulacros_emergencia WHERE tenant_id = ?",
            [$tenantId]
        ) ?? [];
        $this->view('tenant/dashboard-emergencias', [
            'title' => 'Resumen de Emergencias y Vigilancia',
            'tenant' => Tenant::find($tenantId),
            'plans' => array_slice($plans, 0, 4),
            'programs' => array_slice($programs, 0, 4),
            'planCount' => count($plans),
            'programCount' => count($programs),
            'surveillanceCount' => count(array_filter($programs, static fn(array $program): bool => $program['categoria'] === 'Vigilancia epidemiologica')),
            'drills' => $drills,
            'documentStats' => Documento::stats($tenantId, ModuleAccess::EMERGENCIAS),
        ]);
    }
}
