<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Helpers.php';

use App\Router;
use App\Auth;
$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Sesión
session_name($config['session']['nombre']);
session_set_cookie_params([
    'lifetime' => $config['session']['lifetime'],
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

Auth::init();

$router = new Router();

// Públicas
$router->get('/login',  'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Protegidas
$router->group('auth', function (Router $r) {
    $r->get('/',          'DashboardController@index');
    $r->get('/dashboard', 'DashboardController@index');

    // Empresas
    $r->get('/empresas',              'EmpresaController@index');
    $r->get('/empresas/nueva',        'EmpresaController@formNueva');
    $r->post('/empresas',             'EmpresaController@guardar');
    $r->get('/empresas/{id}',         'EmpresaController@ver');
    $r->get('/empresas/{id}/editar',  'EmpresaController@formEditar');
    $r->post('/empresas/{id}',        'EmpresaController@actualizar');


    // ============ ANÁLISIS ============
    $r->get('/analisis',                    'AnalisisController@index');  // ← NUEVA
    $r->post('/analisis',                   'AnalisisController@crear');

    // Wizard
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
    
        // ============ DOCUMENTOS ============
        $r->get('/documentos',                    'DocumentoController@index');
        $r->post('/documentos/{id}/eliminar',     'DocumentoController@eliminar');

        // ============ PERSONAL (global) ============
        $r->get('/personal',                      'PersonalController@index');
        $r->get('/personal/empresa/{id}',         'PersonalController@porEmpresa');

        // ============ MEDEVAC ============
        $r->get('/medevac',                       'MedevacController@index');
        $r->get('/medevac/empresa/{id}',          'MedevacController@porEmpresa');
        $r->get('/medevac/empresa/{id}/exportar', 'MedevacController@exportar');

        // ============ BRIGADISTAS ============
        $r->get('/brigadistas',                   'BrigadistaController@index');
        $r->get('/brigadistas/empresa/{id}',      'BrigadistaController@porEmpresa');
        $r->post('/brigadistas/asignar',          'BrigadistaController@asignar');
        $r->post('/brigadistas/{id}/quitar',      'BrigadistaController@quitar');

        // ============ DASHBOARD STATS ============
        $r->get('/api/dashboard/stats',           'DashboardController@stats');
    // Wizard
    $r->get('/analisis/{id}/wizard/paso1',   'AnalisisController@paso1');
    $r->post('/analisis/{id}/wizard/paso1',  'AnalisisController@guardarPaso1');
    $r->get('/analisis/{id}/wizard/paso2',   'InventarioController@paso2');
    $r->post('/analisis/{id}/wizard/paso2',  'InventarioController@guardarPaso2');
    $r->get('/analisis/{id}/wizard/paso3',   'AnalisisController@paso3');
    $r->post('/analisis/{id}/wizard/paso3',  'AnalisisController@guardarPaso3');
    $r->get('/analisis/{id}/wizard/paso4',   'AnalisisController@paso4');
    $r->post('/analisis/{id}/wizard/paso4',  'AnalisisController@guardarPaso4');
    $r->get('/analisis/{id}/wizard/paso5',   'PersonalController@paso5');
    $r->post('/analisis/{id}/wizard/paso5',  'PersonalController@guardarPaso5');
    $r->get('/analisis/{id}/wizard/paso6',   'AnalisisController@paso6');
    $r->get('/analisis/{id}/wizard/paso7',   'DocumentoController@paso7');
    $r->post('/analisis/{id}/generar',       'DocumentoController@generar');
    $r->get('/analisis/{id}/descargar/{doc}', 'DocumentoController@descargar');

    // API interna
    $r->post('/api/respuestas',              'AnalisisController@guardarRespuesta');
    $r->get('/api/amenaza/{id}/promedios',   'AnalisisController@promedios');
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
