<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/bootstrap.php';

use App\Controllers\AccountController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\CapacitacionController;
use App\Controllers\CatalogoController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentGeneratorController;
use App\Controllers\EquipoController;
use App\Controllers\IndicadorController;
use App\Controllers\PHVAController;
use App\Controllers\PlanController;
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
$router->get('/phva', [PHVAController::class, 'index'], $tenantManagers);
$router->get('/plan-anual', [PHVAController::class, 'plan'], $tenantManagers);
$router->post('/plan-anual', [PHVAController::class, 'storePlan'], $tenantManagers);
$router->post('/plan-anual/{id}/estado', [PHVAController::class, 'planStatus'], $tenantManagers);
$router->get('/hallazgos', [PHVAController::class, 'findings'], $tenantManagers);
$router->post('/hallazgos', [PHVAController::class, 'storeFinding'], $tenantManagers);
$router->post('/hallazgos/{id}/estado', [PHVAController::class, 'findingStatus'], $tenantManagers);
$router->get('/matriz-ipevr', [PHVAController::class, 'matrix'], $tenantManagers);
$router->post('/matriz-ipevr', [PHVAController::class, 'storeHazard'], $tenantManagers);
$router->post('/matriz-ipevr/{id}/estado', [PHVAController::class, 'hazardStatus'], $tenantManagers);
$router->get('/capacitaciones', [CapacitacionController::class, 'index'], $tenantManagers);
$router->post('/capacitaciones', [CapacitacionController::class, 'store'], $tenantManagers);
$router->post('/capacitaciones/{id}/estado', [CapacitacionController::class, 'status'], $tenantManagers);
$router->get('/indicadores', [IndicadorController::class, 'index'], $tenantManagers);
$router->post('/indicadores', [IndicadorController::class, 'store'], $tenantManagers);
$router->get('/autoreportes', [ReporteController::class, 'index'], $tenantManagers);
$router->post('/autoreportes/{id}', [ReporteController::class, 'update'], $tenantManagers);
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
