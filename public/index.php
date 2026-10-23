<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Controllers\AccountController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\BibliotecaController;
use App\Controllers\CapacitacionController;
use App\Controllers\CatalogoController;
use App\Controllers\CumplimientoController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentGeneratorController;
use App\Controllers\EmergenciaController;
use App\Controllers\EquipoController;
use App\Controllers\IndicadorController;
use App\Controllers\InstallController;
use App\Controllers\PermisoController;
use App\Controllers\PHVAController;
use App\Controllers\PlanController;
use App\Controllers\ProcedimientoController;
use App\Controllers\ProgramaController;
use App\Controllers\ReporteController;
use App\Controllers\ReportePublicoController;
use App\Controllers\TenantController;
use App\Controllers\TrabajadorController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Services\CatalogSeeder;
use App\Services\Installer;
use App\Services\SchemaMigrator;
use App\Support\Auth;

$installed = Database::isInstalled();
if ($installed && SchemaMigrator::migrateIfNeeded()) {
    CatalogSeeder::seed();
}
if (!$installed || !Installer::hasInitialSuperAdmin()) {
    $installer = new InstallController();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $installer->store();
    } else {
        $installer->show();
    }
    exit;
}

Auth::init();
$router = new Router();

// Acceso público y sesión.
$router->get('/login', [AuthController::class, 'showLogin'], null);
$router->post('/login', [AuthController::class, 'login'], null);
$router->get('/reportar', [ReportePublicoController::class, 'show'], null);
$router->post('/reportar', [ReportePublicoController::class, 'store'], null);
$router->get('/instalar', [InstallController::class, 'show'], null);
$router->post('/instalar', [InstallController::class, 'store'], null);
$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/cuenta/seguridad', [AccountController::class, 'security']);
$router->post('/cuenta/seguridad', [AccountController::class, 'updatePassword']);

// Panel global del SuperAdmin.
$super = ['SuperAdmin'];
$router->get('/admin/empresas', [TenantController::class, 'index'], $super);
$router->get('/admin/empresas/nueva', [TenantController::class, 'create'], $super);
$router->post('/admin/empresas', [TenantController::class, 'store'], $super);
$router->get('/admin/empresas/{id}', [TenantController::class, 'show'], $super);
$router->post('/admin/empresas/{id}/estado', [TenantController::class, 'setStatus'], $super);
$router->post('/admin/empresas/{id}/plan', [TenantController::class, 'setPlan'], $super);
$router->get('/admin/actividades', [CatalogoController::class, 'activities'], $super);
$router->post('/admin/actividades', [CatalogoController::class, 'storeActivity'], $super);
$router->post('/admin/actividades/{id}/estado', [CatalogoController::class, 'activityStatus'], $super);
$router->get('/admin/estandares', [CatalogoController::class, 'standards'], $super);
$router->get('/admin/planes', [PlanController::class, 'index'], $super);
$router->post('/admin/planes', [PlanController::class, 'store'], $super);
$router->post('/admin/planes/{id}', [PlanController::class, 'update'], $super);
$router->get('/admin/auditoria', [AuditoriaController::class, 'index'], $super);

// Portal de empresa y responsable SG-SST.
$tenantManagers = ['AdminEmpresa', 'ResponsableSST'];
$router->get('/documentos', [DocumentGeneratorController::class, 'index'], $tenantManagers, 'documentos');
$router->get('/documentos/{id}', [DocumentGeneratorController::class, 'show'], $tenantManagers, 'documentos');
$router->post('/documentos/{id}', [DocumentGeneratorController::class, 'update'], $tenantManagers, 'documentos');
$router->post('/documentos/{id}/aprobar', [DocumentGeneratorController::class, 'approve'], $tenantManagers, 'documentos');
$router->post('/documentos/{id}/obsoleto', [DocumentGeneratorController::class, 'obsolete'], $tenantManagers, 'documentos');
$router->get('/documentos/{id}/imprimir', [DocumentGeneratorController::class, 'print'], $tenantManagers, 'documentos');
$router->get('/documentos/{id}/versiones/{versionId}', [DocumentGeneratorController::class, 'version'], $tenantManagers, 'documentos');
$router->get('/procedimientos', [ProcedimientoController::class, 'index'], $tenantManagers);
$router->post('/procedimientos/generar', [ProcedimientoController::class, 'generate'], $tenantManagers);
$router->get('/pesv', [BibliotecaController::class, 'index'], $tenantManagers, 'pesv');
$router->get('/pesv/respuestas/{id}', [BibliotecaController::class, 'editResponse'], $tenantManagers, 'pesv');
$router->post('/pesv/respuestas/{id}', [BibliotecaController::class, 'updateResponse'], $tenantManagers, 'pesv');
$router->post('/pesv/respuestas/{id}/eliminar', [BibliotecaController::class, 'deleteResponse'], $tenantManagers, 'pesv');
$router->get('/pesv/{id}', [BibliotecaController::class, 'show'], $tenantManagers, 'pesv');
$router->post('/pesv/{id}/generar', [BibliotecaController::class, 'generate'], $tenantManagers, 'pesv');
$router->post('/pesv/{id}/respuestas', [BibliotecaController::class, 'storeResponse'], $tenantManagers, 'pesv');
$router->get('/biblioteca-sgsst', [BibliotecaController::class, 'index'], $tenantManagers);
$router->get('/biblioteca-sgsst/respuestas/{id}', [BibliotecaController::class, 'editResponse'], $tenantManagers);
$router->post('/biblioteca-sgsst/respuestas/{id}', [BibliotecaController::class, 'updateResponse'], $tenantManagers);
$router->post('/biblioteca-sgsst/respuestas/{id}/eliminar', [BibliotecaController::class, 'deleteResponse'], $tenantManagers);
$router->get('/biblioteca-sgsst/{id}', [BibliotecaController::class, 'show'], $tenantManagers);
$router->post('/biblioteca-sgsst/{id}/generar', [BibliotecaController::class, 'generate'], $tenantManagers);
$router->post('/biblioteca-sgsst/{id}/respuestas', [BibliotecaController::class, 'storeResponse'], $tenantManagers);
$router->get('/biblioteca-sgsst/{id}/original', [BibliotecaController::class, 'original'], $tenantManagers);
$router->get('/phva', [PHVAController::class, 'index'], $tenantManagers);
$router->get('/plan-anual', [PHVAController::class, 'plan'], $tenantManagers);
$router->post('/plan-anual', [PHVAController::class, 'storePlan'], $tenantManagers);
$router->get('/plan-anual/{id}', [PHVAController::class, 'showPlan'], $tenantManagers);
$router->get('/plan-anual/{id}/editar', [PHVAController::class, 'editPlan'], $tenantManagers);
$router->post('/plan-anual/{id}', [PHVAController::class, 'updatePlan'], $tenantManagers);
$router->post('/plan-anual/{id}/estado', [PHVAController::class, 'planStatus'], $tenantManagers);
$router->post('/plan-anual/{id}/eliminar', [PHVAController::class, 'deletePlan'], $tenantManagers);
$router->get('/hallazgos', [PHVAController::class, 'findings'], $tenantManagers);
$router->post('/hallazgos', [PHVAController::class, 'storeFinding'], $tenantManagers);
$router->post('/hallazgos/{id}/estado', [PHVAController::class, 'findingStatus'], $tenantManagers);
$router->get('/matriz-ipevr', [PHVAController::class, 'matrix'], $tenantManagers);
$router->post('/matriz-ipevr', [PHVAController::class, 'storeHazard'], $tenantManagers);
$router->get('/matriz-ipevr/{id}/editar', [PHVAController::class, 'editHazard'], $tenantManagers);
$router->post('/matriz-ipevr/{id}', [PHVAController::class, 'updateHazard'], $tenantManagers);
$router->post('/matriz-ipevr/{id}/estado', [PHVAController::class, 'hazardStatus'], $tenantManagers);
$router->post('/matriz-ipevr/{id}/eliminar', [PHVAController::class, 'deleteHazard'], $tenantManagers);
$router->get('/capacitaciones', [CapacitacionController::class, 'index'], $tenantManagers);
$router->post('/capacitaciones', [CapacitacionController::class, 'store'], $tenantManagers);
$router->get('/capacitaciones/{id}/editar', [CapacitacionController::class, 'edit'], $tenantManagers);
$router->post('/capacitaciones/{id}', [CapacitacionController::class, 'update'], $tenantManagers);
$router->post('/capacitaciones/{id}/estado', [CapacitacionController::class, 'status'], $tenantManagers);
$router->post('/capacitaciones/{id}/eliminar', [CapacitacionController::class, 'delete'], $tenantManagers);
$router->get('/indicadores', [IndicadorController::class, 'index'], $tenantManagers);
$router->post('/indicadores', [IndicadorController::class, 'store'], $tenantManagers);
$router->get('/autoreportes', [ReporteController::class, 'index'], $tenantManagers);
$router->get('/autoreportes/accesos', [ReporteController::class, 'accesses'], $tenantManagers);
$router->post('/autoreportes/accesos', [ReporteController::class, 'storeAccess'], $tenantManagers);
$router->post('/autoreportes/accesos/{id}/renovar', [ReporteController::class, 'renewAccess'], $tenantManagers);
$router->post('/autoreportes/accesos/{id}/revocar', [ReporteController::class, 'revokeAccess'], $tenantManagers);
$router->get('/autoreportes/{id}', [ReporteController::class, 'show'], $tenantManagers);
$router->post('/autoreportes/{id}', [ReporteController::class, 'update'], $tenantManagers);

// Permisos de trabajo y control operacional.
$router->get('/permisos-trabajo', [PermisoController::class, 'index'], $tenantManagers);
$router->get('/permisos-trabajo/nuevo', [PermisoController::class, 'create'], $tenantManagers);
$router->post('/permisos-trabajo', [PermisoController::class, 'store'], $tenantManagers);
$router->get('/permisos-trabajo/{id}', [PermisoController::class, 'show'], $tenantManagers);
$router->get('/permisos-trabajo/{id}/editar', [PermisoController::class, 'edit'], $tenantManagers);
$router->post('/permisos-trabajo/{id}', [PermisoController::class, 'update'], $tenantManagers);
$router->get('/permisos-trabajo/{id}/imprimir', [PermisoController::class, 'print'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/enviar', [PermisoController::class, 'submit'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/retirar', [PermisoController::class, 'withdraw'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/aprobar', [PermisoController::class, 'approve'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/activar', [PermisoController::class, 'activate'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/cerrar', [PermisoController::class, 'close'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/cancelar', [PermisoController::class, 'cancel'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/revisar', [PermisoController::class, 'revise'], $tenantManagers);
$router->post('/permisos-trabajo/{id}/eliminar', [PermisoController::class, 'delete'], $tenantManagers);

// Planes de emergencia y simulacros.
$router->get('/emergencias', [EmergenciaController::class, 'index'], $tenantManagers, 'emergencias');
$router->get('/emergencias/nuevo', [EmergenciaController::class, 'create'], $tenantManagers, 'emergencias');
$router->post('/emergencias', [EmergenciaController::class, 'store'], $tenantManagers, 'emergencias');
$router->get('/emergencias/{id}', [EmergenciaController::class, 'show'], $tenantManagers, 'emergencias');
$router->get('/emergencias/{id}/editar', [EmergenciaController::class, 'edit'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}', [EmergenciaController::class, 'update'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/aprobar', [EmergenciaController::class, 'approve'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/obsoleto', [EmergenciaController::class, 'obsolete'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/eliminar', [EmergenciaController::class, 'delete'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/simulacros', [EmergenciaController::class, 'storeDrill'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/simulacros/{drillId}/estado', [EmergenciaController::class, 'drillStatus'], $tenantManagers, 'emergencias');
$router->post('/emergencias/{id}/simulacros/{drillId}/eliminar', [EmergenciaController::class, 'deleteDrill'], $tenantManagers, 'emergencias');

// Programas de promoción, prevención y vigilancia.
$router->get('/programas-prevencion', [ProgramaController::class, 'index'], $tenantManagers, 'emergencias');
$router->get('/programas-prevencion/nuevo', [ProgramaController::class, 'create'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion', [ProgramaController::class, 'store'], $tenantManagers, 'emergencias');
$router->get('/programas-prevencion/{id}', [ProgramaController::class, 'show'], $tenantManagers, 'emergencias');
$router->get('/programas-prevencion/{id}/editar', [ProgramaController::class, 'edit'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}', [ProgramaController::class, 'update'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/aprobar', [ProgramaController::class, 'approve'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/obsoleto', [ProgramaController::class, 'obsolete'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/eliminar', [ProgramaController::class, 'delete'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/actividades', [ProgramaController::class, 'storeActivity'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/actividades/{activityId}', [ProgramaController::class, 'updateActivity'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/actividades/{activityId}/estado', [ProgramaController::class, 'activityStatus'], $tenantManagers, 'emergencias');
$router->post('/programas-prevencion/{id}/actividades/{activityId}/eliminar', [ProgramaController::class, 'deleteActivity'], $tenantManagers, 'emergencias');

// Panel de documentación, seguimiento y evidencias privadas.
$router->get('/cumplimiento-documental', [CumplimientoController::class, 'index'], $tenantManagers, 'emergencias');
$router->get('/cumplimiento-documental/gestion', [CumplimientoController::class, 'manage'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/acciones', [CumplimientoController::class, 'storeAction'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/acciones/{id}', [CumplimientoController::class, 'updateAction'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/acciones/{id}/eliminar', [CumplimientoController::class, 'deleteAction'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/seguimiento', [CumplimientoController::class, 'updateTracking'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/soportes', [CumplimientoController::class, 'upload'], $tenantManagers, 'emergencias');
$router->get('/cumplimiento-documental/soportes/{id}/descargar', [CumplimientoController::class, 'download'], $tenantManagers, 'emergencias');
$router->post('/cumplimiento-documental/soportes/{id}/eliminar', [CumplimientoController::class, 'deleteSupport'], $tenantManagers, 'emergencias');
$router->get('/equipo', [EquipoController::class, 'index'], $tenantManagers, 'compartido');
$router->post('/equipo/empleados', [EquipoController::class, 'storeEmployee'], $tenantManagers, 'compartido');
$router->post('/equipo/usuarios', [EquipoController::class, 'storeUser'], ['AdminEmpresa'], 'compartido');
$router->post('/equipo/empleados/{id}/estado', [EquipoController::class, 'employeeStatus'], ['AdminEmpresa'], 'compartido');
$router->post('/equipo/usuarios/{id}/estado', [EquipoController::class, 'userStatus'], ['AdminEmpresa'], 'compartido');
$router->get('/empresa/configuracion', [TenantController::class, 'settings'], ['AdminEmpresa'], 'compartido');
$router->post('/empresa/configuracion', [TenantController::class, 'updateSettings'], ['AdminEmpresa'], 'compartido');

// Portal simplificado de trabajadores y contratistas.
$worker = ['Trabajador'];
$router->get('/portal', [TrabajadorController::class, 'portal'], $worker, 'sgsst');
$router->get('/portal/politicas/{id}', [TrabajadorController::class, 'policy'], $worker, 'sgsst');
$router->post('/portal/reportes', [TrabajadorController::class, 'storeReport'], $worker, 'sgsst');
$router->get('/portal/induccion', [TrabajadorController::class, 'induction'], $worker, 'sgsst');
$router->post('/portal/induccion/firmar', [TrabajadorController::class, 'signInduction'], $worker, 'sgsst');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST' && !Csrf::verify($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
    http_response_code(419);
    $status = 419;
    $title = 'La sesión del formulario venció';
    $message = 'Por seguridad, vuelve a la página anterior, recarga el formulario e inténtalo nuevamente.';
    $contentView = BASE_PATH . '/app/Views/errors/error.php';
    $user = Auth::user();
    require BASE_PATH . '/app/Views/layouts/error.php';
    exit;
}

$router->dispatch($method, $_SERVER['REQUEST_URI'] ?? '/');
