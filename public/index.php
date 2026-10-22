<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers.php';

use App\Router;
use App\Auth;
use App\Migrator;

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Cabeceras de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (($config['app']['env'] ?? '') === 'production') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Sesión endurecida
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name($config['session']['nombre']);
session_set_cookie_params([
    'lifetime' => $config['session']['lifetime'],
    'path'     => '/',
    'httponly' => true,
    'secure'   => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

Migrator::run();
Auth::init();

// Contraseña temporal: solo puede cambiarla o cerrar sesión
if (Auth::check() && Auth::mustChangePassword()) {
    $pathActual = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    if (!str_contains($pathActual, 'cambiar-password') && !str_contains($pathActual, 'logout')) {
        redirect(base_url('cambiar-password'));
    }
}

$router = new Router();

// Públicas
$router->get('/login',  'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->post('/logout', 'AuthController@logout');

// Protegidas
$router->group('auth', function (Router $r) {
    $r->get('/',          'DashboardController@index');
    $r->get('/dashboard', 'DashboardController@index');
    $r->get('/cambiar-password',  'AuthController@showCambiarPassword');
    $r->post('/cambiar-password', 'AuthController@cambiarPassword');

    // Empresas
    $r->get('/empresas',              'EmpresaController@index');
    $r->get('/empresas/nueva',        'EmpresaController@formNueva');
    $r->post('/empresas',             'EmpresaController@guardar');
    $r->get('/empresas/{id}',         'EmpresaController@ver');
    $r->get('/empresas/{id}/editar',  'EmpresaController@formEditar');
    $r->post('/empresas/{id}',        'EmpresaController@actualizar');

    // ============ SG-SST integral ============
    $r->get('/sgsst',                                  'SgsstController@index');
    $r->get('/sgsst/empresa/{id}',                     'SgsstController@empresa');
    $r->post('/sgsst/empresa/{id}/evaluacion',         'SgsstController@guardarEvaluacion');
    $r->post('/sgsst/empresa/{id}/actividades',        'SgsstController@crearActividad');
    $r->post('/sgsst/actividades/{id}/estado',         'SgsstController@estadoActividad');
    $r->post('/sgsst/empresa/{id}/registros',          'SgsstController@crearRegistro');
    $r->post('/sgsst/registros/{id}/estado',           'SgsstController@estadoRegistro');
    $r->get('/sgsst/empresa/{id}/plantilla/{codigo}',  'SgsstController@generarPlantilla');
    $r->get('/sgsst/empresa/{id}/informe',             'SgsstController@informe');

    // Usuarios y roles (el controlador exige superadmin)
    $r->get('/usuarios',          'UsuarioController@index');
    $r->post('/usuarios',         'UsuarioController@crear');
    $r->post('/usuarios/{id}',    'UsuarioController@actualizar');

    // Análisis PPPRAE
    $r->get('/analisis',                    'AnalisisController@index');
    $r->post('/analisis',                   'AnalisisController@crear');
    $r->get('/analisis/{id}/wizard/paso1',  'AnalisisController@paso1');
    $r->post('/analisis/{id}/wizard/paso1', 'AnalisisController@guardarPaso1');
    $r->get('/analisis/{id}/wizard/paso2',  'InventarioController@paso2');
    $r->post('/analisis/{id}/wizard/paso2', 'InventarioController@guardarPaso2');
    $r->get('/analisis/{id}/wizard/paso3',  'AnalisisController@paso3');
    $r->post('/analisis/{id}/wizard/paso3', 'AnalisisController@guardarPaso3');
    $r->get('/analisis/{id}/wizard/paso4',  'AnalisisController@paso4');
    $r->post('/analisis/{id}/wizard/paso4', 'AnalisisController@guardarPaso4');
    $r->get('/analisis/{id}/wizard/paso5',  'PersonalController@paso5');
    $r->post('/analisis/{id}/wizard/paso5', 'PersonalController@guardarPaso5');
    $r->get('/analisis/{id}/wizard/paso6',  'AnalisisController@paso6');
    $r->get('/analisis/{id}/wizard/paso7',  'DocumentoController@paso7');
    $r->post('/analisis/{id}/generar',      'DocumentoController@generar');
    $r->post('/analisis/{id}/recalcular',   'AnalisisController@recalcular');
    $r->get('/analisis/{id}/descargar/{doc}','DocumentoController@descargar');

    // Documentos
    $r->get('/documentos',                'DocumentoController@index');
    $r->post('/documentos/{id}/eliminar', 'DocumentoController@eliminar');

    // Personal (global + acciones del wizard paso 5, antes sin ruta → 404)
    $r->get('/personal',                  'PersonalController@index');
    $r->get('/personal/empresa/{id}',     'PersonalController@porEmpresa');
    $r->post('/personal',                 'PersonalController@crear');
    $r->post('/personal/{id}',            'PersonalController@actualizar');
    $r->post('/personal/{id}/eliminar',   'PersonalController@eliminar');

    // MEDEVAC
    $r->get('/medevac',                       'MedevacController@index');
    $r->get('/medevac/empresa/{id}',          'MedevacController@porEmpresa');
    $r->get('/medevac/empresa/{id}/exportar', 'MedevacController@exportar');

    // Brigadistas
    $r->get('/brigadistas',                   'BrigadistaController@index');
    $r->get('/brigadistas/empresa/{id}',      'BrigadistaController@porEmpresa');
    $r->post('/brigadistas/asignar',          'BrigadistaController@asignar');
    $r->post('/brigadistas/{id}/quitar',      'BrigadistaController@quitar');

    // API interna
    $r->get('/api/dashboard/stats',           'DashboardController@stats');
    $r->post('/api/respuestas',               'AnalisisController@guardarRespuesta');
    $r->get('/api/amenaza/{id}/promedios',    'AnalisisController@promedios');
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
