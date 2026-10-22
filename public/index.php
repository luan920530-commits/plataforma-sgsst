<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Controllers\AccountController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CapacitacionController;
use App\Controllers\CatalogoController;
use App\Controllers\CumplimientoController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentGeneratorController;
use App\Controllers\EmergenciaController;
use App\Controllers\EquipoController;
use App\Controllers\IndicadorController;
use App\Controllers\PermisoController;
use App\Controllers\PHVAController;
use App\Controllers\PlanController;
use App\Controllers\ProgramaController;
use App\Controllers\ReporteController;
use App\Controllers\TenantController;
use App\Controllers\TrabajadorController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Router;
use App\Support\Auth;

if (!Database::isInstalled()) {
    http_response_code(503);
    $title = 'Normia SST necesita instalación';
    $contentView = BASE_PATH . '/app/Views/errors/install.php';
    require BASE_PATH . '/app/Views/layouts/error.php';
    exit;
}

Auth::init();
$router = new Router();

// Acceso público y sesión.
$router->get('/login', [AuthController::class, 'showLogin'], null);
$router->post('/login', [AuthController::class, 'login'], null);
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
$router->get('/documentos', [DocumentGeneratorController::class, 'index'], $tenantManagers);
$router->get('/documentos/{id}', [DocumentGeneratorController::class, 'show'], $tenantManagers);
$router->post('/documentos/{id}', [DocumentGeneratorController::class, 'update'], $tenantManagers);
$router->post('/documentos/{id}/aprobar', [DocumentGeneratorController::class, 'approve'], $tenantManagers);
$router->post('/documentos/{id}/obsoleto', [DocumentGeneratorController::class, 'obsolete'], $tenantManagers);
$router->get('/documentos/{id}/imprimir', [DocumentGeneratorController::class, 'print'], $tenantManagers);
$router->get('/documentos/{id}/versiones/{versionId}', [DocumentGeneratorController::class, 'version'], $tenantManagers);
$router->get('/phva', [PHVAController::class, 'index'], $tenantManagers);
$router->get('/plan-anual', [PHVAController::class, 'plan'], $tenantManagers);
$router->post('/plan-anual', [PHVAController::class, 'storePlan'], $tenantManagers);
$router->post('/plan-anual/{id}/estado', [PHVAController::class, 'planStatus'], $tenantManagers);
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
$router->get('/emergencias', [EmergenciaController::class, 'index'], $tenantManagers);
$router->get('/emergencias/nuevo', [EmergenciaController::class, 'create'], $tenantManagers);
$router->post('/emergencias', [EmergenciaController::class, 'store'], $tenantManagers);
$router->get('/emergencias/{id}', [EmergenciaController::class, 'show'], $tenantManagers);
$router->get('/emergencias/{id}/editar', [EmergenciaController::class, 'edit'], $tenantManagers);
$router->post('/emergencias/{id}', [EmergenciaController::class, 'update'], $tenantManagers);
$router->post('/emergencias/{id}/aprobar', [EmergenciaController::class, 'approve'], $tenantManagers);
$router->post('/emergencias/{id}/obsoleto', [EmergenciaController::class, 'obsolete'], $tenantManagers);
$router->post('/emergencias/{id}/eliminar', [EmergenciaController::class, 'delete'], $tenantManagers);
$router->post('/emergencias/{id}/simulacros', [EmergenciaController::class, 'storeDrill'], $tenantManagers);
$router->post('/emergencias/{id}/simulacros/{drillId}/estado', [EmergenciaController::class, 'drillStatus'], $tenantManagers);
$router->post('/emergencias/{id}/simulacros/{drillId}/eliminar', [EmergenciaController::class, 'deleteDrill'], $tenantManagers);

// Programas de promoción, prevención y vigilancia.
$router->get('/programas-prevencion', [ProgramaController::class, 'index'], $tenantManagers);
$router->get('/programas-prevencion/nuevo', [ProgramaController::class, 'create'], $tenantManagers);
$router->post('/programas-prevencion', [ProgramaController::class, 'store'], $tenantManagers);
$router->get('/programas-prevencion/{id}', [ProgramaController::class, 'show'], $tenantManagers);
$router->get('/programas-prevencion/{id}/editar', [ProgramaController::class, 'edit'], $tenantManagers);
$router->post('/programas-prevencion/{id}', [ProgramaController::class, 'update'], $tenantManagers);
$router->post('/programas-prevencion/{id}/aprobar', [ProgramaController::class, 'approve'], $tenantManagers);
$router->post('/programas-prevencion/{id}/obsoleto', [ProgramaController::class, 'obsolete'], $tenantManagers);
$router->post('/programas-prevencion/{id}/eliminar', [ProgramaController::class, 'delete'], $tenantManagers);
$router->post('/programas-prevencion/{id}/actividades', [ProgramaController::class, 'storeActivity'], $tenantManagers);
$router->post('/programas-prevencion/{id}/actividades/{activityId}', [ProgramaController::class, 'updateActivity'], $tenantManagers);
$router->post('/programas-prevencion/{id}/actividades/{activityId}/estado', [ProgramaController::class, 'activityStatus'], $tenantManagers);
$router->post('/programas-prevencion/{id}/actividades/{activityId}/eliminar', [ProgramaController::class, 'deleteActivity'], $tenantManagers);

// Panel de documentación, seguimiento y evidencias privadas.
$router->get('/cumplimiento-documental', [CumplimientoController::class, 'index'], $tenantManagers);
$router->post('/cumplimiento-documental/seguimiento', [CumplimientoController::class, 'updateTracking'], $tenantManagers);
$router->post('/cumplimiento-documental/soportes', [CumplimientoController::class, 'upload'], $tenantManagers);
$router->get('/cumplimiento-documental/soportes/{id}/descargar', [CumplimientoController::class, 'download'], $tenantManagers);
$router->post('/cumplimiento-documental/soportes/{id}/eliminar', [CumplimientoController::class, 'deleteSupport'], $tenantManagers);
$router->get('/equipo', [EquipoController::class, 'index'], $tenantManagers);
$router->post('/equipo/empleados', [EquipoController::class, 'storeEmployee'], $tenantManagers);
$router->post('/equipo/usuarios', [EquipoController::class, 'storeUser'], ['AdminEmpresa']);
$router->post('/equipo/empleados/{id}/estado', [EquipoController::class, 'employeeStatus'], ['AdminEmpresa']);
$router->post('/equipo/usuarios/{id}/estado', [EquipoController::class, 'userStatus'], ['AdminEmpresa']);
$router->get('/empresa/configuracion', [TenantController::class, 'settings'], ['AdminEmpresa']);
$router->post('/empresa/configuracion', [TenantController::class, 'updateSettings'], ['AdminEmpresa']);

// Portal simplificado de trabajadores y contratistas.
$worker = ['Trabajador'];
$router->get('/portal', [TrabajadorController::class, 'portal'], $worker);
$router->get('/portal/politicas/{id}', [TrabajadorController::class, 'policy'], $worker);
$router->post('/portal/reportes', [TrabajadorController::class, 'storeReport'], $worker);
$router->get('/portal/induccion', [TrabajadorController::class, 'induction'], $worker);
$router->post('/portal/induccion/firmar', [TrabajadorController::class, 'signInduction'], $worker);

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
