<?php
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'wasm'], true)) {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
$testDatabase = BASE_PATH . '/database/testing.db';
foreach ([$testDatabase, $testDatabase . '-shm', $testDatabase . '-wal'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
putenv('SGSST_DB_PATH=' . $testDatabase);
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\View;
use App\Models\AccesoReporte;
use App\Models\Autoreporte;
use App\Models\BibliotecaRespuesta;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\Evidencia;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\Induccion;
use App\Models\IvcActa;
use App\Models\IvcRequerimiento;
use App\Models\Peligro;
use App\Models\PermisoTrabajo;
use App\Models\PlanEmergencia;
use App\Models\PlanSaaS;
use App\Models\PlanTrabajo;
use App\Models\PlanoEmergencia;
use App\Models\ProgramaActividad;
use App\Models\ProgramaPrevencion;
use App\Models\RequisitoAccion;
use App\Models\RequisitoSeguimiento;
use App\Models\SanitarioRegistro;
use App\Models\ActividadEconomica;
use App\Models\SimulacroEmergencia;
use App\Models\SoporteCumplimiento;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\ComplianceService;
use App\Services\DocumentGenerator;
use App\Services\EvidenceLinker;
use App\Services\EvidenceStorage;
use App\Services\ExcelCalculator;
use App\Services\ExtintoresScmSeeder;
use App\Services\FloorPlan;
use App\Services\Installer;
use App\Services\LibraryOriginal;
use App\Services\ModuleAccess;
use App\Services\OperationalDocument;
use App\Services\PesvLibrary;
use App\Services\SgsstLibrary;
use App\Services\SanitaryCatalog;
use App\Services\SchemaMigrator;
use App\Services\ProcedureLibrary;
use App\Services\TenantModuleGenerator;
use App\Support\Auth;
use App\Support\Indicator;
use App\Support\Risk;
use App\Support\Sanitizer;

final class TestFailure extends RuntimeException {}
function expectTrue(bool $condition, string $message = 'La condición esperada no se cumplió.'): void {
    if (!$condition) throw new TestFailure($message);
}
function expectSame(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new TestFailure(($message !== '' ? $message . ' · ' : '') . 'Esperado: ' . var_export($expected, true) . '; obtenido: ' . var_export($actual, true));
    }
}
function tenantByNit(string $nit): array {
    $tenant = Database::first('SELECT * FROM tenants WHERE nit = ?', [$nit]);
    if ($tenant === null) throw new TestFailure('No existe el tenant de prueba ' . $nit);
    return $tenant;
}

function permitTestData(int $tenantId): array {
    return [
        'tenant_id' => $tenantId, 'tipo' => 'Alturas', 'tarea' => 'Mantenimiento de prueba', 'ubicacion' => 'Cubierta',
        'descripcion' => 'Trabajo controlado de prueba', 'fecha_inicio' => date('Y-m-d\TH:i', strtotime('-1 hour')),
        'fecha_fin' => date('Y-m-d\TH:i', strtotime('+8 hours')), 'solicitante_id' => null,
        'solicitante_nombre' => 'Solicitante Prueba', 'solicitante_documento' => '123',
        'ejecutantes' => 'Trabajador autorizado', 'responsable_trabajo' => 'Responsable SST',
        'peligros' => 'Caída de altura', 'controles' => 'Línea de vida', 'epp' => 'Arnés',
        'equipos_herramientas' => 'Kit inspeccionado', 'autorreporte_condiciones' => 'Sin novedad',
        'altura_trabajo' => 4.5, 'coordinador_alturas' => 'Coordinador', 'responsable_emergencias' => 'Brigadista',
        'procedimiento_rescate' => 'Plan de rescate', 'vigia_espacio' => '', 'mediciones_atmosfericas' => '',
        'formacion_personal' => 'Formación vigente', 'observaciones' => '',
    ];
}
function emergencyTestData(int $tenantId): array {
    return [
        'tenant_id' => $tenantId, 'titulo' => 'Plan <script>Prueba</script>', 'centro_trabajo' => 'Sede de prueba',
        'direccion' => 'Bogotá', 'alcance' => 'Todas las personas y turnos',
        'amenazas_json' => json_encode([['nombre' => 'Incendio', 'probabilidad' => 'Media', 'impacto' => 'Alto', 'controles' => 'Extintores']], JSON_UNESCAPED_UNICODE),
        'contactos_json' => json_encode([['nombre' => 'Bomberos', 'tipo' => 'Externo', 'telefono' => '119']], JSON_UNESCAPED_UNICODE),
        'brigada_json' => json_encode([['nombre' => 'Brigadista', 'rol' => 'Evacuación', 'contacto' => '123']], JSON_UNESCAPED_UNICODE),
        'recursos_json' => json_encode([['nombre' => 'Extintor', 'cantidad' => '1', 'ubicacion' => 'Pasillo', 'estado' => 'Disponible', 'inspeccion' => 'Vigente']], JSON_UNESCAPED_UNICODE),
        'rutas_evacuacion' => 'Ruta norte', 'puntos_encuentro' => 'Punto azul',
        'procedimientos' => 'Alarmar, evacuar y verificar', 'plan_rescate' => 'Primeros auxilios',
        'ayuda_mutua' => 'Bomberos', 'responsable_plan' => 'Responsable SST', 'telefono_alarma' => '123',
        'proximo_simulacro' => date('Y-m-d', strtotime('+6 months')), 'fecha_revision' => date('Y-m-d', strtotime('+1 year')),
    ];
}
function programTestData(int $tenantId): array {
    return [
        'tenant_id' => $tenantId, 'categoria' => 'Prevencion de accidentes', 'nombre' => 'Programa de prueba',
        'anio' => (int) date('Y'), 'diagnostico' => 'Riesgo priorizado', 'objetivo_general' => 'Reducir exposición',
        'objetivos_especificos' => 'Intervenir controles', 'poblacion' => 'Trabajadores', 'peligros_relacionados' => 'Seguridad',
        'metas_indicadores' => '100% de actividades con evidencia', 'responsable' => 'Responsable SST',
        'recursos' => 'Horas e inspecciones', 'presupuesto' => 1000, 'fecha_inicio' => date('Y-01-01'), 'fecha_fin' => date('Y-12-31'),
    ];
}

function floorPlanTestDesign(string $suffix = 'A'): array
{
    return [
        'format' => 1, 'width' => 1000, 'height' => 650, 'grid' => 20,
        'elements' => [
            ['id' => 'wall_' . $suffix, 'tool' => 'wall', 'x' => 40, 'y' => 80, 'x2' => 420, 'y2' => 80, 'label' => 'Muro norte', 'color' => '#334155', 'rotation' => 0, 'stroke' => 10],
            ['id' => 'route_' . $suffix, 'tool' => 'route', 'x' => 100, 'y' => 200, 'x2' => 420, 'y2' => 200, 'label' => 'Ruta principal', 'color' => '#15803d', 'rotation' => 0, 'stroke' => 8],
            ['id' => 'exit_' . $suffix, 'tool' => 'exit', 'x' => 460, 'y' => 200, 'label' => 'SALIDA', 'color' => '#15803d', 'rotation' => 0, 'stroke' => 8],
            ['id' => 'text_' . $suffix, 'tool' => 'text', 'x' => 80, 'y' => 120, 'label' => 'Sede de prueba', 'color' => '#334155', 'rotation' => 0, 'stroke' => 18],
        ],
    ];
}

function evidenceTestData(int $tenantId, string $key, string $module, int $userId): array
{
    $stored = substr(hash('sha256', $key), 0, 32) . '.png';
    return [
        'tenant_id' => $tenantId, 'requisito_clave' => '', 'modulo' => $module,
        'categoria' => 'Reporte o informe', 'titulo' => 'EVID-SCOPE · ' . $key,
        'fecha_evidencia' => '2026-10-23', 'entidad_tipo' => 'general', 'entidad_id' => '',
        'entidad_referencia' => 'Archivo general de prueba', 'nombre_original' => $key . '.png',
        'nombre_almacenado' => $stored, 'ruta_relativa' => 'tenant_' . $tenantId . '/' . $stored,
        'mime' => 'image/png', 'extension' => 'png', 'tamano' => 68,
        'hash_sha256' => hash('sha256', $key), 'descripcion' => 'Registro sintético de prueba',
        'estado_revision' => 'Pendiente', 'subido_por' => $userId,
    ];
}

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[$name] = $callback; };

try {
    $demoInstallation = Installer::installDemo();
    if (!($demoInstallation['created'] ?? false)) {
        throw new TestFailure('La instalación demo inicial no se completó.');
    }
    $main = tenantByNit('901.245.780-3');
    $construction = tenantByNit('900.812.447-1');
    $bakery = tenantByNit('901.552.019-8');
    $mainId = (int) $main['id'];
    $constructionId = (int) $construction['id'];

    $test('Esquema, claves foráneas y catálogos', static function (): void {
        expectSame(33, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
        expectSame(1, (int) Database::first('PRAGMA foreign_keys')['foreign_keys']);
        expectSame([], Database::query('PRAGMA foreign_key_check'));
        expectSame(21, (int) Database::first('SELECT COUNT(*) AS n FROM actividades_economicas')['n']);
        expectTrue((int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL")['n'] >= 20);
    });

    $test('Instalación demo: perfiles, empresas y plano editable', static function () use ($mainId): void {
        expectSame(3, (int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n']);
        expectSame(4, (int) Database::first('SELECT COUNT(*) AS n FROM users')['n']);
        $plan = Database::first('SELECT * FROM planes_emergencia WHERE tenant_id = ? AND titulo = ?', [$mainId, 'Plan de emergencia · sede de demostración']);
        expectTrue($plan !== null && (int) $plan['documento_id'] > 0, 'La demo debe incluir el plan y su documento.');
        $drawings = PlanoEmergencia::forPlan((int) $plan['id'], $mainId);
        expectSame(1, count($drawings));
        $design = FloorPlan::normalize($drawings[0]['datos_json']);
        expectSame(11, count($design['elements']));
        $byTool = array_column($design['elements'], null, 'tool');
        expectSame('#f9a800', $byTool['iso_w012']['color']);
        expectSame(false, Installer::installDemo()['created'], 'Una segunda instalación demo no debe duplicar ni reemplazar datos.');
        expectSame(1, count(PlanEmergencia::forTenant($mainId)));
        expectSame(3, (int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n']);
    });

    foreach ([7, 21, 60] as $group) {
        $test("Catálogo exacto de {$group} estándares", static function () use ($group): void {
            expectSame($group, (int) Database::first('SELECT COUNT(*) AS n FROM estandares_minimos WHERE grupo = ?', [$group])['n']);
            expectSame($group, (int) Database::first('SELECT COUNT(DISTINCT codigo) AS n FROM estandares_minimos WHERE grupo = ?', [$group])['n']);
            expectTrue((int) Database::first("SELECT COUNT(*) AS n FROM estandares_minimos WHERE grupo = ? AND fase_phva NOT IN ('Planear','Hacer','Verificar','Actuar')", [$group])['n'] === 0);
        });
    }

    $test('Clasificación 7/21/60 y prioridad de riesgo IV/V', static function (): void {
        expectSame(7, ClassificationService::groupFor(1, 'I'));
        expectSame(7, ClassificationService::groupFor(10, 'III'));
        expectSame(21, ClassificationService::groupFor(11, 'I'));
        expectSame(21, ClassificationService::groupFor(50, 'III'));
        expectSame(60, ClassificationService::groupFor(51, 'I'));
        expectSame(60, ClassificationService::groupFor(1, 'IV'));
        expectSame(60, ClassificationService::groupFor(10, 'V'));
        expectSame(60, ClassificationService::groupFor(50, 'IV'));
    });

    $test('Cálculo ND × NE × NC y umbrales', static function (): void {
        expectSame(['probability' => 40, 'score' => 400, 'level' => 'Alto'], Risk::calculate(10, 4, 10));
        expectSame('Critico', Risk::calculate(10, 4, 25)['level']);
        expectSame('Medio', Risk::calculate(2, 2, 10)['level']);
        expectSame('Bajo', Risk::calculate(2, 1, 10)['level']);
        expectSame(['probability' => null, 'score' => null, 'level' => 'Medio'], Risk::calculate(null, 2, 10));
    });

    $test('Fórmulas de indicadores y denominador cero', static function (): void {
        expectSame(240000, Indicator::definition('Frecuencia')['factor']);
        expectSame(3.21, Indicator::calculate(18, 560, 100));
        expectSame(0.0, Indicator::calculate(18, 0, 100));
    });

    $test('Saneamiento de documentos HTML', static function (): void {
        $clean = Sanitizer::html('<p onclick="alert(1)">Contenido <strong>válido</strong></p><script>alert(1)</script><iframe src="https://invalid.example"></iframe>');
        expectTrue(str_contains($clean, '<strong>válido</strong>'));
        expectTrue(!str_contains($clean, '<script') && !str_contains($clean, 'onclick') && !str_contains($clean, '<iframe'));
        $layout = Sanitizer::html('<article class="sst-document" onclick="alert(1)"><section class="document-body" style="color:red"><h2>Sección</h2></section></article>');
        expectTrue(str_contains($layout, '<article class="sst-document">') && str_contains($layout, '<section class="document-body">'));
        expectTrue(!str_contains($layout, 'onclick') && !str_contains($layout, 'style='));
        $spans = Sanitizer::html('<table><tr><td colspan="2" rowspan="3" onclick="x">Celda</td></tr></table>');
        expectTrue(str_contains($spans, 'colspan="2"') && str_contains($spans, 'rowspan="3"') && !str_contains($spans, 'onclick'));
    });

    $test('Rollback integral de transacciones', static function (): void {
        $before = (int) Database::first('SELECT COUNT(*) AS n FROM planes_saas')['n'];
        try {
            Database::transaction(static function (): void {
                Database::execute("INSERT INTO planes_saas (nombre, precio_mensual) VALUES ('No debe persistir', 1)");
                throw new RuntimeException('Fallo controlado');
            });
        } catch (RuntimeException $exception) {
            expectSame('Fallo controlado', $exception->getMessage());
        }
        expectSame($before, (int) Database::first('SELECT COUNT(*) AS n FROM planes_saas')['n']);
    });

    foreach ([[$main, 21], [$construction, 60], [$bakery, 7]] as [$tenant, $group]) {
        $test("Generación completa para grupo {$group}", static function () use ($tenant, $group): void {
            $id = (int) $tenant['id'];
            expectSame($group, (int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst d WHERE d.tenant_id = ? AND EXISTS (SELECT 1 FROM estandares_minimos e WHERE e.codigo = d.codigo_estandar AND e.grupo = ?)', [$id, $group])['n']);
            expectTrue((int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst WHERE tenant_id = ?', [$id])['n'] >= $group + 2);
            expectSame(12, (int) Database::first('SELECT COUNT(*) AS n FROM plan_trabajo WHERE tenant_id = ?', [$id])['n']);
            expectSame(6, (int) Database::first('SELECT COUNT(*) AS n FROM capacitaciones WHERE tenant_id = ?', [$id])['n']);
            expectTrue((int) Database::first('SELECT COUNT(*) AS n FROM peligros_ipevr WHERE tenant_id = ?', [$id])['n'] >= 3);
            expectSame(1, (int) Database::first('SELECT COUNT(*) AS n FROM inducciones WHERE tenant_id = ?', [$id])['n']);
            expectSame(2, (int) Database::first('SELECT COUNT(*) AS n FROM perfiles_riesgo WHERE tenant_id = ?', [$id])['n']);
            expectTrue(!empty(Database::first('SELECT configuracion_generada_at FROM tenants WHERE id = ?', [$id])['configuracion_generada_at']));
        });
    }

    $test('Generación idempotente: no duplica configuración', static function () use ($mainId): void {
        $before = (int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst WHERE tenant_id = ?', [$mainId])['n'];
        $blocked = false;
        try { DocumentGenerator::generate($mainId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La segunda generación debía ser rechazada.');
        expectSame($before, (int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst WHERE tenant_id = ?', [$mainId])['n']);
    });

    $test('Aislamiento de documentos entre tenants', static function () use ($mainId, $constructionId): void {
        $document = Database::first('SELECT id FROM documentos_sgsst WHERE tenant_id = ? LIMIT 1', [$mainId]);
        expectTrue(Documento::findForTenant((int) $document['id'], $mainId) !== null);
        expectSame(null, Documento::findForTenant((int) $document['id'], $constructionId));
        expectSame([], Documento::forTenant($constructionId, ['q' => "' OR 1=1 --"]));
    });

    $test('Aislamiento de autoreportes y usuarios', static function () use ($mainId, $constructionId): void {
        $report = Database::first('SELECT id, user_id FROM autoreportes WHERE tenant_id = ? LIMIT 1', [$mainId]);
        expectSame(null, Autoreporte::findForTenant((int) $report['id'], $constructionId));
        expectSame([], Autoreporte::forUser($constructionId, (int) $report['user_id']));
        expectSame(null, User::findForTenant((int) $report['user_id'], $constructionId));
    });

    $test('CRUD y aislamiento de peligros IPEVR', static function () use ($mainId, $constructionId): void {
        $data = [
            'tenant_id' => $mainId, 'proceso' => 'Prueba', 'cargo' => 'Equipo', 'peligro' => 'Peligro temporal',
            'clasificacion' => 'Seguridad', 'efectos' => 'Lesión', 'controles_existentes' => 'Señalización',
            'nivel_deficiencia' => 10, 'nivel_exposicion' => 4, 'nivel_probabilidad' => 40,
            'nivel_consecuencia' => 10, 'nivel_riesgo' => 'Alto', 'medidas_control' => 'Eliminación',
        ];
        $id = Peligro::create($data);
        expectSame(null, Peligro::findForTenant($id, $constructionId));
        $data['peligro'] = 'Peligro editado';
        $data['nivel_deficiencia'] = 2; $data['nivel_exposicion'] = 2; $data['nivel_probabilidad'] = 4; $data['nivel_riesgo'] = 'Medio';
        Peligro::update($id, $mainId, $data);
        $updated = Peligro::findForTenant($id, $mainId);
        expectSame('Peligro editado', $updated['peligro']);
        expectSame(4, (int) $updated['nivel_probabilidad']);
        expectSame('Medio', $updated['nivel_riesgo']);
        Peligro::delete($id, $mainId);
        expectSame(null, Peligro::findForTenant($id, $mainId));
    });

    $test('CRUD y aislamiento de capacitaciones', static function () use ($mainId, $constructionId): void {
        $data = ['tenant_id' => $mainId, 'tema' => 'Formación temporal', 'descripcion' => 'Prueba', 'responsable' => 'SST', 'fecha_programada' => '2026-12-15', 'duracion_horas' => 2, 'modalidad' => 'Virtual'];
        $id = Capacitacion::create($data);
        expectSame(null, Capacitacion::findForTenant($id, $constructionId));
        $data['tema'] = 'Formación editada'; $data['duracion_horas'] = 3;
        Capacitacion::update($id, $mainId, $data);
        expectSame('Formación editada', Capacitacion::findForTenant($id, $mainId)['tema']);
        expectSame(3.0, (float) Capacitacion::findForTenant($id, $mainId)['duracion_horas']);
        Capacitacion::setStatus($id, $mainId, 'Realizada');
        expectSame('Realizada', Capacitacion::findForTenant($id, $mainId)['estado']);
        Capacitacion::delete($id, $mainId);
        expectSame(null, Capacitacion::findForTenant($id, $mainId));
    });

    $test('Plan anual: CRUD, ejecución y aislamiento por tenant', static function () use ($mainId, $constructionId): void {
        $data = [
            'tenant_id' => $mainId, 'actividad' => 'Actividad PAT temporal', 'responsable' => 'Responsable SST',
            'fecha_programada' => '2026-12-15', 'presupuesto' => 100000, 'fase_phva' => 'Planear',
        ];
        $id = PlanTrabajo::create($data);
        expectSame(null, PlanTrabajo::findForTenant($id, $constructionId));
        $data['actividad'] = 'Actividad PAT editada';
        $data['responsable'] = 'Coordinador SG-SST';
        $data['presupuesto'] = 250000;
        $data['fase_phva'] = 'Hacer';
        $data['estado'] = 'Completado';
        $data['fecha_ejecucion'] = '2026-12-14';
        PlanTrabajo::update($id, $constructionId, $data);
        expectSame('Actividad PAT temporal', PlanTrabajo::findForTenant($id, $mainId)['actividad']);
        PlanTrabajo::update($id, $mainId, $data);
        $updated = PlanTrabajo::findForTenant($id, $mainId);
        expectSame('Actividad PAT editada', $updated['actividad']);
        expectSame('Coordinador SG-SST', $updated['responsable']);
        expectSame(250000.0, (float) $updated['presupuesto']);
        expectSame('Hacer', $updated['fase_phva']);
        expectSame('2026-12-14', $updated['fecha_ejecucion']);
        PlanTrabajo::updateStatus($id, $mainId, 'Completado');
        expectSame('2026-12-14', PlanTrabajo::findForTenant($id, $mainId)['fecha_ejecucion'], 'No debe reemplazar la fecha al guardar nuevamente el mismo estado.');
        PlanTrabajo::updateStatus($id, $mainId, 'En Proceso');
        expectSame(null, PlanTrabajo::findForTenant($id, $mainId)['fecha_ejecucion']);
        PlanTrabajo::updateStatus($id, $mainId, 'Completado', '2026-12-16');
        expectSame('2026-12-16', PlanTrabajo::findForTenant($id, $mainId)['fecha_ejecucion']);
        PlanTrabajo::delete($id, $constructionId);
        expectTrue(PlanTrabajo::findForTenant($id, $mainId) !== null, 'Otro tenant no debe eliminar la actividad.');
        PlanTrabajo::delete($id, $mainId);
        expectSame(null, PlanTrabajo::findForTenant($id, $mainId));
    });

    $test('Formularios recuperan errores y valores después de validar', static function () use ($mainId): void {
        $activity = PlanTrabajo::forTenant($mainId)[0];
        $sessionBefore = $_SESSION ?? [];
        $_SESSION = [
            'user_id' => 2, '_last_activity' => time(),
            '_errors' => ['actividad' => 'Error visible de prueba'],
            '_old' => ['actividad' => 'Texto recuperado de prueba'],
        ];
        Auth::init();
        ob_start();
        try {
            View::render('tenant/phva/plan-form', ['title' => 'Prueba de validación', 'activity' => $activity]);
            $html = ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            Auth::logout();
            $_SESSION = $sessionBefore;
            throw $exception;
        }
        $sessionCleared = !isset($_SESSION['_errors'], $_SESSION['_old']);
        $globalsCleared = !array_key_exists('errors', $GLOBALS) && !array_key_exists('oldInput', $GLOBALS);
        Auth::logout();
        $_SESSION = $sessionBefore;
        expectTrue(str_contains($html, 'Error visible de prueba'), 'El error de validación debe llegar al formulario.');
        expectTrue(str_contains($html, 'Texto recuperado de prueba'), 'El valor digitado debe recuperarse después de validar.');
        expectTrue($sessionCleared, 'Los datos temporales de validación deben consumirse una sola vez.');
        expectTrue($globalsCleared, 'Los datos de la vista no deben permanecer en el ámbito global.');
    });

    $test('Detalle de autoreporte con aislamiento', static function () use ($mainId, $constructionId): void {
        $row = Database::first('SELECT id FROM autoreportes WHERE tenant_id = ? LIMIT 1', [$mainId]);
        $detail = Autoreporte::findDetailedForTenant((int) $row['id'], $mainId);
        expectTrue($detail !== null && !empty($detail['reportante']) && !empty($detail['email']));
        expectSame(null, Autoreporte::findDetailedForTenant((int) $row['id'], $constructionId));
    });

    $test('Procedimientos y formatos por actividad y alto riesgo', static function () use ($mainId, $constructionId): void {
        $created = ProcedureLibrary::generate($mainId, ['alturas', 'espacios_confinados']);
        expectSame(4, $created);
        expectSame(0, ProcedureLibrary::generate($mainId, ['alturas', 'espacios_confinados']));
        $documents = Documento::forTenant($mainId, ['q' => 'espacios confinados']);
        expectTrue(count($documents) >= 2);
        expectTrue(str_contains($documents[0]['contenido_html'], 'mediciones atmosféricas') || str_contains($documents[1]['contenido_html'], 'mediciones atmosféricas'));
        $height = Database::first("SELECT * FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar = 'PR-ALT-01'", [$mainId]);
        expectTrue($height !== null && $height['tipo'] === 'Procedimiento');
        expectSame(null, Documento::findForTenant((int) $height['id'], $constructionId));
        expectTrue(Database::first("SELECT id FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar = 'PR-ALT-01'", [$constructionId]) !== null, 'La actividad de construcción debía sugerir alturas en el onboarding.');
        expectTrue(Database::first("SELECT id FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar = 'FT-AST-01'", [$mainId]) !== null);
        expectTrue(Database::first("SELECT id FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar = 'PR-ACT-6201'", [$mainId]) !== null);
    });

    $test('Accesos externos: token hash, anonimato, consumo y tenant', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $accessId = AccesoReporte::create([
            'tenant_id' => $mainId, 'nombre' => 'Prueba externa', 'token_hash' => hash('sha256', $token),
            'token_hint' => 'prueba', 'expira_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'max_usos' => 1, 'created_by' => (int) $admin['id'],
        ]);
        $access = AccesoReporte::findByToken($token);
        expectTrue(AccesoReporte::isUsable($access));
        $reportId = Database::transaction(static function () use ($access): int {
            expectTrue(AccesoReporte::consume((int) $access['id']));
            return Autoreporte::create([
                'tenant_id' => (int) $access['tenant_id'], 'user_id' => (int) $access['created_by'],
                'acceso_reporte_id' => (int) $access['id'], 'origen' => 'Enlace externo', 'anonimo' => 1,
                'tipo' => 'Sugerencia', 'descripcion' => 'Reporte externo anónimo de prueba',
                'ubicacion' => 'Prueba', 'fecha_evento' => date('Y-m-d'),
            ]);
        });
        $detail = Autoreporte::findDetailedForTenant($reportId, $mainId);
        expectSame('Reporte anónimo', $detail['reportante']);
        expectSame('', $detail['email']);
        expectSame('Enlace externo', $detail['origen']);
        expectSame(null, Autoreporte::findDetailedForTenant($reportId, $constructionId));
        expectTrue(!AccesoReporte::isUsable(AccesoReporte::findByToken($token)), 'El segundo uso debía quedar bloqueado.');
        AccesoReporte::revoke($accessId, $mainId);
        expectSame(0, (int) AccesoReporte::findForTenant($accessId, $mainId)['activo']);
        expectSame(null, AccesoReporte::findForTenant($accessId, $constructionId));
    });

    $test('QR de autoreportes: renovación revoca el token anterior sin reiniciar usos', static function () use ($mainId, $constructionId): void {
        expectTrue(is_file(BASE_PATH . '/public/assets/js/vendor/qrcode.js') && is_file(BASE_PATH . '/public/assets/js/vendor/qrcode.LICENSE.txt'), 'El generador QR y su licencia deben quedar versionados para los clones y el ZIP.');
        $admin = User::findForAuthentication('admin@andina.co');
        $tokenA = 'A' . str_repeat('a', 42);
        $tokenB = 'B' . str_repeat('b', 42);
        $tokenC = 'C' . str_repeat('c', 42);
        $id = AccesoReporte::create([
            'tenant_id' => $mainId, 'nombre' => 'Acceso renovable de prueba', 'token_hash' => hash('sha256', $tokenA),
            'token_hint' => 'Aaaaaa…aaaa', 'expira_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'max_usos' => 2, 'created_by' => (int) $admin['id'],
        ]);
        expectTrue(AccesoReporte::isUsable(AccesoReporte::findByToken($tokenA)));
        expectTrue(AccesoReporte::isUsable(AccesoReporte::findForTenant($id, $mainId)), 'La renovación debe recibir también el estado de la empresa y del plan.');
        expectTrue(AccesoReporte::rotateToken($id, $mainId, hash('sha256', $tokenB), 'Bbbbbb…bbbb'));
        expectSame(null, AccesoReporte::findByToken($tokenA), 'El token anterior debe quedar invalidado.');
        expectTrue(AccesoReporte::isUsable(AccesoReporte::findByToken($tokenB)));
        expectTrue(AccesoReporte::consume($id));
        expectTrue(!AccesoReporte::rotateToken($id, $constructionId, hash('sha256', 'X' . str_repeat('x', 42)), 'Xxxxxx…xxxx'));
        expectTrue(AccesoReporte::rotateToken($id, $mainId, hash('sha256', $tokenC), 'Cccccc…cccc'));
        $current = AccesoReporte::findForTenant($id, $mainId);
        expectSame(1, (int) $current['usos'], 'La renovación no debe reiniciar el consumo.');
        expectSame(hash('sha256', $tokenC), $current['token_hash']);
        expectTrue($current['token_hash'] !== $tokenC && !str_contains($current['token_hint'], $tokenC));
        expectTrue(AccesoReporte::isUsable(AccesoReporte::findByToken($tokenC)));
        $tenant = Tenant::find($mainId);
        $pesvPlanId = (int) Database::first("SELECT id FROM planes_saas WHERE nombre = 'PESVial'")['id'];
        Tenant::setPlan($mainId, $pesvPlanId, 'Sugerido');
        expectTrue(!AccesoReporte::isUsable(AccesoReporte::findByToken($tokenC)), 'Un plan especializado debe cerrar el autoreporte público.');
        expectTrue(!AccesoReporte::consume($id), 'El consumo debe bloquearse también si el plan cambia durante el envío.');
        expectSame(1, (int) AccesoReporte::findForTenant($id, $mainId)['usos']);
        Tenant::setPlan($mainId, $tenant['plan_id'] !== null ? (int) $tenant['plan_id'] : null, $tenant['plan_condicion']);
        expectTrue(AccesoReporte::isUsable(AccesoReporte::findByToken($tokenC)));
    });

    $test('Módulos de gestión: responsable, recursos y evidencia', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $id = RequisitoAccion::create([
            'tenant_id' => $mainId, 'requisito_clave' => 'responsabilidades_sst',
            'titulo' => 'Asignar responsable y recursos de prueba', 'descripcion' => 'Formalizar funciones',
            'responsable_id' => (int) $admin['id'], 'recursos' => 'Horas y presupuesto',
            'fecha_compromiso' => date('Y-m-d'), 'created_by' => (int) $admin['id'],
        ]);
        expectSame(null, RequisitoAccion::findForTenant($id, $constructionId));
        $board = ComplianceService::board($mainId);
        expectSame(1, $board['items']['responsabilidades_sst']['acciones']);
        expectSame(1, $board['items']['responsabilidades_sst']['acciones_abiertas']);
        RequisitoAccion::updateManagement($id, $mainId, 'Completado', 'Acta y presupuesto verificados');
        expectSame(0, ComplianceService::board($mainId)['items']['responsabilidades_sst']['acciones_abiertas']);
        RequisitoAccion::delete($id, $mainId);
        expectSame(null, RequisitoAccion::findForTenant($id, $mainId));
    });

    $test('Historial inmutable de versiones documentales', static function () use ($mainId, $constructionId): void {
        $document = Documento::forTenant($mainId)[0];
        $original = $document['contenido_html'];
        Documento::saveDraft((int) $document['id'], $mainId, '<p>Contenido nuevo de prueba</p>');
        $current = Documento::findForTenant((int) $document['id'], $mainId);
        expectSame((int) $document['version'] + 1, (int) $current['version']);
        $versions = Documento::versions((int) $document['id'], $mainId);
        expectTrue(count($versions) >= 1);
        expectSame($original, $versions[0]['contenido_html']);
        expectSame(null, Documento::findVersionForTenant((int) $versions[0]['id'], (int) $document['id'], $constructionId));
    });

    $test('Permisos de trabajo: edición, aprobación, cierre y revisión', static function () use ($mainId, $constructionId): void {
        $data = permitTestData($mainId);
        $id = PermisoTrabajo::create($data);
        expectSame(null, PermisoTrabajo::findForTenant($id, $constructionId));
        $data['tarea'] = 'Trabajo editado';
        PermisoTrabajo::update($id, $mainId, $data);
        expectSame('Trabajo editado', PermisoTrabajo::findForTenant($id, $mainId)['tarea']);
        $admin = User::findForAuthentication('admin@andina.co');
        PermisoTrabajo::approve($id, $mainId, (int) $admin['id'], 'Administrador', '123');
        expectSame('Aprobado', PermisoTrabajo::findForTenant($id, $mainId)['estado']);
        PermisoTrabajo::close($id, $mainId, (int) $admin['id'], 'Trabajo terminado y área despejada');
        expectSame('Cerrado', PermisoTrabajo::findForTenant($id, $mainId)['estado']);
        $revision = PermisoTrabajo::findForTenant($id, $mainId);
        $revision['tenant_id'] = $mainId; $revision['version'] = 2; $revision['revisado_de'] = $id;
        $revisionId = PermisoTrabajo::create($revision);
        expectSame(2, (int) PermisoTrabajo::findForTenant($revisionId, $mainId)['version']);
        expectSame(2, count(PermisoTrabajo::revisions($id, $mainId)));
        $third = PermisoTrabajo::findForTenant($revisionId, $mainId);
        $third['tenant_id'] = $mainId; $third['version'] = 3; $third['revisado_de'] = $revisionId;
        $thirdId = PermisoTrabajo::create($third);
        expectSame(3, count(PermisoTrabajo::revisions($thirdId, $mainId)));
    });

    $test('Emergencias: documento, escape, versión y simulacro', static function () use ($mainId, $constructionId): void {
        $data = emergencyTestData($mainId);
        [$id, $documentId] = Database::transaction(static function () use ($data, $mainId): array {
            $id = PlanEmergencia::create($data);
            $documentId = OperationalDocument::createEmergency($id, $mainId);
            PlanEmergencia::linkDocument($id, $mainId, $documentId);
            return [$id, $documentId];
        });
        expectSame(null, PlanEmergencia::findForTenant($id, $constructionId));
        $data['titulo'] = 'Plan actualizado <b>seguro</b>';
        PlanEmergencia::update($id, $mainId, $data);
        OperationalDocument::refreshEmergency($id, $mainId);
        $plan = PlanEmergencia::findForTenant($id, $mainId);
        expectSame(2, (int) $plan['version']);
        expectTrue(str_contains($plan['contenido_html'], '&lt;b&gt;seguro&lt;/b&gt;'));
        expectTrue(!str_contains($plan['contenido_html'], '<script>'));
        expectSame(1, count(Documento::versions($documentId, $mainId)));
        $drillId = SimulacroEmergencia::create(['tenant_id' => $mainId, 'plan_id' => $id, 'fecha' => date('Y-m-d'), 'tipo' => 'Evacuación', 'participantes' => 20, 'responsable' => 'Brigada', 'resultado' => 'Ejecutado', 'acciones_mejora' => 'Señalización', 'estado' => 'Realizado']);
        expectTrue($drillId > 0);
        expectSame(1, count(SimulacroEmergencia::forPlan($id, $mainId)));
    });

    $test('Programas preventivos: actividades, evidencia y documento', static function () use ($mainId, $constructionId): void {
        $data = programTestData($mainId);
        [$id, $documentId] = Database::transaction(static function () use ($data, $mainId): array {
            $id = ProgramaPrevencion::create($data);
            $documentId = OperationalDocument::createProgram($id, $mainId);
            ProgramaPrevencion::linkDocument($id, $mainId, $documentId);
            return [$id, $documentId];
        });
        expectSame(null, ProgramaPrevencion::findForTenant($id, $constructionId));
        $activityId = ProgramaActividad::create(['tenant_id' => $mainId, 'programa_id' => $id, 'actividad' => 'Inspección de prueba', 'responsable' => 'SST', 'fecha_programada' => date('Y-m-d'), 'indicador' => 'Ejecutadas / programadas', 'meta' => '100%', 'recursos' => 'Lista']);
        OperationalDocument::refreshProgram($id, $mainId);
        $program = ProgramaPrevencion::findForTenant($id, $mainId);
        expectSame(2, (int) $program['version']);
        expectTrue(str_contains($program['contenido_html'], 'Inspección de prueba'));
        ProgramaActividad::setStatus($activityId, $mainId, 'Completado', 'Acta e inspección firmada');
        expectSame('Completado', ProgramaActividad::findForTenant($activityId, $mainId)['estado']);
        expectSame(null, ProgramaActividad::findForTenant($activityId, $constructionId));
        OperationalDocument::refreshProgram($id, $mainId);
        expectSame(3, (int) ProgramaPrevencion::findForTenant($id, $mainId)['version']);
    });

    $test('Tablero documental requiere verificación humana explícita', static function () use ($mainId, $constructionId): void {
        $board = ComplianceService::board($mainId);
        expectTrue(isset($board['items']['emergencia_planos'], $board['items']['programas_pyp'], $board['items']['permisos_trabajo']));
        expectSame('Pendiente', $board['items']['emergencia_planos']['estado']);
        expectSame('En gestion', $board['items']['emergencia_plan']['estado']);
        expectSame(0, count(array_filter($board['items'], static fn($item) => $item['estado'] === 'Verificado')));
        $admin = User::findForAuthentication('admin@andina.co');
        RequisitoSeguimiento::save($mainId, 'emergencia_planos', ['estado' => 'Verificado', 'responsable' => 'SST', 'fecha_limite' => '', 'observaciones' => 'Plano revisado en la sede'], (int) $admin['id']);
        $supportId = SoporteCumplimiento::create(['tenant_id' => $mainId, 'requisito_clave' => 'emergencia_planos', 'nombre_original' => 'plano.png', 'nombre_almacenado' => str_repeat('a', 32) . '.png', 'ruta_relativa' => 'tenant_' . $mainId . '/' . str_repeat('a', 32) . '.png', 'mime' => 'image/png', 'extension' => 'png', 'tamano' => 100, 'hash_sha256' => str_repeat('b', 64), 'descripcion' => 'Prueba', 'subido_por' => (int) $admin['id']]);
        $updated = ComplianceService::board($mainId);
        expectSame('Verificado', $updated['items']['emergencia_planos']['estado']);
        expectSame(1, $updated['items']['emergencia_planos']['supports']);
        expectSame(null, SoporteCumplimiento::findForTenant($supportId, $constructionId));
        expectTrue(SoporteCumplimiento::delete($supportId, $mainId), 'Un soporte pendiente sí puede borrarse.');
    });

    $test('Rutas privadas de evidencias y tipos permitidos', static function () use ($mainId): void {
        expectSame('application/pdf', EvidenceStorage::mime('pdf'));
        expectSame('image/png', EvidenceStorage::mime('png'));
        $blocked = false;
        try { EvidenceStorage::path($mainId, '../database/sgsst.db'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La ruta manipulada debía rechazarse.');
        expectSame(10485760, EvidenceStorage::MAX_BYTES);
    });

    $test('Editor de planos: 332 señales ISO, 32 objetos CAD y validación estricta', static function (): void {
        $tools = FloorPlan::tools();
        $catalog = FloorPlan::toolCatalog();
        $cadCatalog = FloorPlan::cadCatalog();
        expectSame(373, count($tools), 'Deben persistir 332 tipos ISO, 32 objetos CAD y 9 tipos base/heredados.');
        expectSame(337, count($catalog), 'El catálogo ISO debe conservar 332 señales y 5 herramientas base, sin alias duplicados.');
        expectSame(32, count($cadCatalog));
        expectSame([6, 4, 4, 5, 7, 6], array_map(
            static fn(string $category): int => count(array_filter($cadCatalog, static fn(array $item): bool => $item['category'] === $category)),
            array_keys(FloorPlan::cadCategories())
        ));
        expectTrue(isset($cadCatalog['cad_table'], $cadCatalog['cad_toilet'], $cadCatalog['cad_kitchen_sink'], $cadCatalog['cad_drill_press'], $cadCatalog['cad_forklift'], $cadCatalog['cad_elevator'], $cadCatalog['cad_ramp']));
        foreach ($cadCatalog as $cadObject) {
            expectTrue(str_contains($cadObject['svg'], 'currentColor') && !str_contains($cadObject['svg'], '<script'), 'Cada CAD debe ser vectorial, local y editable.');
        }
        expectTrue(str_contains(asset('css/app.css'), '?v='), 'Los assets deben versionarse para evitar CSS/JS obsoleto en hosting.');
        expectSame([73, 19, 72, 81, 87], array_map(
            static fn(string $category): int => count(array_filter($catalog, static fn(array $item): bool => $item['category'] === $category)),
            array_keys(FloorPlan::categories())
        ));
        expectSame('E002', FloorPlan::signCode('exit'));
        expectSame('W089', FloorPlan::signCode('iso_w089'));
        expectSame(null, FloorPlan::signCode('iso_e999'));
        expectSame('Salida de emergencia (izquierda)', $catalog['iso_e001']['name']);
        $design = ['format' => 1, 'width' => 1000, 'height' => 650, 'grid' => 20, 'elements' => []];
        foreach ($tools as $index => $tool) {
            $x = 40 + ($index % 40) * 20;
            $element = [
                'id' => 'element_' . $index, 'tool' => $tool, 'x' => $x, 'y' => 80 + intdiv($index, 40) * 20,
                'label' => $tool === 'text' ? 'Texto visible' : 'Elemento ' . $tool,
                'color' => $tool === 'cad_forklift' ? '#ca8a04' : '#334155', 'rotation' => 0, 'stroke' => 8, 'html' => '<script>no se conserva</script>',
            ];
            if (in_array($tool, ['wall', 'route'], true)) {
                $element['x2'] = $x + 100;
                $element['y2'] = 80;
            }
            $design['elements'][] = $element;
        }
        $normalized = FloorPlan::normalize(FloorPlan::encode($design));
        expectSame(count($tools), count($normalized['elements']));
        expectSame(1000, $normalized['width']);
        expectTrue(!array_key_exists('html', $normalized['elements'][0]), 'El formato no debe conservar markup arbitrario.');
        $byTool = array_column($normalized['elements'], null, 'tool');
        expectSame('#f9a800', $byTool['iso_w012']['color'], 'Las advertencias deben conservar su color normalizado.');
        expectSame('#b71f2e', $byTool['alarm']['color'], 'Los alias antiguos deben adoptar el color de su familia.');
        expectSame('#ca8a04', $byTool['cad_forklift']['color'], 'La maquinaria debe aceptar amarillo editable.');
        expectTrue(!array_key_exists('html', $byTool['cad_forklift']), 'Los objetos CAD no deben conservar markup arbitrario.');
        $invalid = ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', FloorPlan::encode(FloorPlan::defaults())];
        $duplicate = floorPlanTestDesign();
        $duplicate['elements'][1]['id'] = $duplicate['elements'][0]['id'];
        $invalid[] = FloorPlan::encode($duplicate);
        $unknown = floorPlanTestDesign();
        $unknown['elements'][0]['tool'] = 'script';
        $invalid[] = FloorPlan::encode($unknown);
        $unknownIso = floorPlanTestDesign();
        $unknownIso['elements'][0]['tool'] = 'iso_e999';
        $invalid[] = FloorPlan::encode($unknownIso);
        $unknownCad = floorPlanTestDesign();
        $unknownCad['elements'][0]['tool'] = 'cad_script';
        $invalid[] = FloorPlan::encode($unknownCad);
        $outside = floorPlanTestDesign();
        $outside['elements'][2]['x'] = 1001;
        $invalid[] = FloorPlan::encode($outside);
        $arrayLabel = floorPlanTestDesign();
        $arrayLabel['elements'][3]['label'] = ['no escalar'];
        $invalid[] = FloorPlan::encode($arrayLabel);
        $zeroLength = floorPlanTestDesign();
        $zeroLength['elements'][0]['x2'] = $zeroLength['elements'][0]['x'];
        $zeroLength['elements'][0]['y2'] = $zeroLength['elements'][0]['y'];
        $invalid[] = FloorPlan::encode($zeroLength);
        $blocked = 0;
        foreach ($invalid as $json) {
            try { FloorPlan::normalize($json); } catch (DomainException) { $blocked++; }
        }
        expectSame(count($invalid), $blocked, 'Todos los diseños inválidos deben rechazarse.');
    });

    $test('Centro de evidencias: vínculos reales, revisión humana y alcance por módulo', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $document = null;
        foreach (Documento::forTenant($mainId) as $candidate) {
            if (!ModuleAccess::documentMatches($candidate, ModuleAccess::PESV) && !ModuleAccess::documentMatches($candidate, ModuleAccess::EMERGENCIAS)) {
                $document = $candidate;
                break;
            }
        }
        expectTrue($document !== null, 'Debe existir un documento SG-SST general para vincular.');
        $link = EvidenceLinker::resolve('documento:' . $document['id'], $mainId, ModuleAccess::SGST);
        expectSame('documento', $link['entidad_tipo']);
        expectSame(ModuleAccess::SGST, $link['modulo']);
        expectSame('/documentos/' . $document['id'], EvidenceLinker::url(['entidad_tipo' => 'documento', 'entidad_id' => (string) $document['id']]));
        $crossTenant = false;
        try { EvidenceLinker::resolve('documento:' . $document['id'], $constructionId, ModuleAccess::SGST); } catch (DomainException) { $crossTenant = true; }
        expectTrue($crossTenant, 'No debe vincularse un documento de otra empresa.');
        $crossModule = false;
        try { EvidenceLinker::resolve('general:', $mainId, ModuleAccess::PESV, ModuleAccess::SGST); } catch (DomainException) { $crossModule = true; }
        expectTrue($crossModule, 'Un plan PESVial no debe crear evidencia SG-SST general.');

        $sgsstId = Evidencia::create(array_merge(evidenceTestData($mainId, 'sgsst', ModuleAccess::SGST, (int) $admin['id']), $link));
        $pesvId = Evidencia::create(evidenceTestData($mainId, 'pesv', ModuleAccess::PESV, (int) $admin['id']));
        $emergencyId = Evidencia::create(evidenceTestData($mainId, 'emergency', ModuleAccess::EMERGENCIAS, (int) $admin['id']));
        expectSame('Pendiente', Evidencia::findForTenant($sgsstId, $mainId, ModuleAccess::SGST)['estado_revision']);
        expectSame(null, Evidencia::findForTenant($sgsstId, $constructionId, ModuleAccess::SGST));
        expectSame(3, count(Evidencia::forTenant($mainId, ModuleAccess::SGST, ['q' => 'EVID-SCOPE'])));
        $pesvRows = Evidencia::forTenant($mainId, ModuleAccess::PESV, ['q' => 'EVID-SCOPE']);
        $emergencyRows = Evidencia::forTenant($mainId, ModuleAccess::EMERGENCIAS, ['q' => 'EVID-SCOPE']);
        expectSame(1, count($pesvRows));
        expectSame(ModuleAccess::PESV, $pesvRows[0]['modulo']);
        expectSame(1, count($emergencyRows));
        expectSame(ModuleAccess::EMERGENCIAS, $emergencyRows[0]['modulo']);
        expectSame(1, Evidencia::countForEntity($mainId, 'documento', (string) $document['id']));
        Evidencia::review($sgsstId, $mainId, 'Revisado', 'Contenido y fecha contrastados por SST', (int) $admin['id']);
        $reviewed = Evidencia::findForTenant($sgsstId, $mainId, ModuleAccess::SGST);
        expectSame('Revisado', $reviewed['estado_revision']);
        expectSame((int) $admin['id'], (int) $reviewed['revisado_por']);
        expectTrue($reviewed['revisado_at'] !== null && $reviewed['observaciones_revision'] !== '');
        expectTrue(!Evidencia::delete($sgsstId, $mainId), 'El borrado condicional debe rechazar una evidencia revisada.');
        expectTrue(Evidencia::findForTenant($sgsstId, $mainId, ModuleAccess::SGST) !== null, 'El modelo no debe borrar una evidencia revisada.');
        Evidencia::review($sgsstId, $mainId, 'Pendiente', '', (int) $admin['id']);
        foreach ([$sgsstId, $pesvId, $emergencyId] as $id) {
            expectTrue(Evidencia::delete($id, $mainId), 'La evidencia pendiente debe borrarse exactamente una vez.');
            expectSame(null, Evidencia::findForTenant($id, $mainId, ModuleAccess::SGST));
            expectTrue(!Evidencia::delete($id, $mainId), 'Un segundo borrado no debe reportar éxito.');
        }
    });

    $test('Planos de emergencia: versiones inmutables, vínculos y cascada tenant', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $planId = PlanEmergencia::create(emergencyTestData($mainId));
        $firstDesign = floorPlanTestDesign('A');
        $drawingId = PlanoEmergencia::create([
            'tenant_id' => $mainId, 'plan_id' => $planId, 'nombre' => 'Plano sede prueba',
            'escala' => 'Referencial · validar en sitio', 'descripcion' => 'Primer piso sintético',
            'datos_json' => FloorPlan::encode(FloorPlan::normalize(FloorPlan::encode($firstDesign))), 'created_by' => (int) $admin['id'],
        ]);
        expectTrue($drawingId > 0);
        expectSame(1, (int) PlanoEmergencia::findForTenant($drawingId, $mainId)['version']);
        expectSame(null, PlanoEmergencia::findForTenant($drawingId, $constructionId));
        expectSame([], PlanoEmergencia::forPlan($planId, $constructionId));
        $secondDesign = floorPlanTestDesign('B');
        $version = PlanoEmergencia::update($drawingId, $mainId, [
            'nombre' => 'Plano sede actualizado', 'escala' => 'Referencial revisado', 'descripcion' => 'Segunda versión',
            'datos_json' => FloorPlan::encode(FloorPlan::normalize(FloorPlan::encode($secondDesign))), 'created_by' => (int) $admin['id'],
            'expected_version' => 1,
        ]);
        expectSame(2, $version);
        $staleRejected = false;
        try {
            PlanoEmergencia::update($drawingId, $mainId, [
                'nombre' => 'Guardado desde pestaña obsoleta', 'escala' => 'Referencial', 'descripcion' => 'No debe sobrescribir',
                'datos_json' => FloorPlan::encode(FloorPlan::normalize(FloorPlan::encode($firstDesign))),
                'created_by' => (int) $admin['id'], 'expected_version' => 1,
            ]);
        } catch (DomainException) {
            $staleRejected = true;
        }
        expectTrue($staleRejected, 'Una versión base obsoleta no debe sobrescribir el trabajo más reciente.');
        expectSame(2, (int) PlanoEmergencia::findForTenant($drawingId, $mainId)['version']);
        $versions = PlanoEmergencia::versions($drawingId, $mainId);
        expectSame(2, count($versions));
        expectSame(2, (int) $versions[0]['version']);
        expectSame(1, (int) $versions[1]['version']);
        expectTrue(str_contains($versions[1]['datos_json'], 'wall_A'));
        expectTrue(!str_contains($versions[1]['datos_json'], 'wall_B'));
        expectSame(null, PlanoEmergencia::findVersion((int) $versions[1]['id'], $drawingId, $constructionId));
        $link = EvidenceLinker::resolve('plano:' . $drawingId, $mainId, ModuleAccess::EMERGENCIAS);
        expectSame(ModuleAccess::EMERGENCIAS, $link['modulo']);
        expectSame('/planos-emergencia/' . $drawingId . '/editar', EvidenceLinker::url(['entidad_tipo' => 'plano', 'entidad_id' => (string) $drawingId]));
        PlanEmergencia::delete($planId, $mainId);
        expectSame(null, PlanoEmergencia::findForTenant($drawingId, $mainId));
        expectSame([], PlanoEmergencia::versions($drawingId, $mainId));
        expectSame([], Database::query('PRAGMA foreign_key_check'));
    });

    $test('Biblioteca SG-SST: 142 plantillas, 153 referencias y 60 estándares', static function (): void {
        $items = SgsstLibrary::all();
        $stats = SgsstLibrary::stats();
        expectSame(142, count($items));
        expectSame(153, $stats['entries']);
        expectSame(11, $stats['duplicates']);
        expectSame(60, $stats['standards']);
        expectSame(88, $stats['documents']);
        expectSame(54, $stats['forms']);
        $duplicates = 0;
        foreach ($items as $item) {
            expectSame($item['id'], SgsstLibrary::find((int) $item['number'])['id']);
            expectTrue(is_file(BASE_PATH . '/resources/sgsst-library/' . $item['content']));
            expectTrue($item['sources'] !== []);
            $duplicates += (int) $item['duplicate_entries'];
            if ($item['kind'] === 'document') {
                $content = SgsstLibrary::content($item);
                expectTrue(trim(strip_tags($content)) !== '' && !str_contains(text_lower($content), '<script'));
            } else {
                expectTrue(SgsstLibrary::form($item)['sheets'] !== []);
            }
        }
        expectSame(11, $duplicates);
    });

    $test('Biblioteca documental: borrador aislado e idempotente', static function () use ($mainId, $constructionId): void {
        $template = SgsstLibrary::find(1);
        $content = Sanitizer::html(SgsstLibrary::content($template));
        $data = ['tenant_id' => $mainId, 'codigo' => $template['code'], 'nombre' => $template['title'], 'fase' => $template['phase'], 'contenido' => $content, 'tipo' => $template['document_type'], 'biblioteca_id' => $template['id']];
        $id = Documento::createOperationalIfMissing($data);
        expectTrue($id !== null && $id > 0);
        expectSame(null, Documento::createOperationalIfMissing($data));
        $document = Documento::findForTenant($id, $mainId);
        expectSame('Borrador', $document['estado']);
        expectSame($template['id'], $document['biblioteca_id']);
        expectSame(null, Documento::findForTenant($id, $constructionId));
        expectSame(null, Documento::findByLibrary($template['id'], $constructionId));
    });

    $test('Respuestas de biblioteca: CRUD y aislamiento por tenant', static function () use ($mainId, $constructionId): void {
        $template = null;
        foreach (SgsstLibrary::all() as $item) {
            if ($item['kind'] === 'form') { $template = $item; break; }
        }
        $admin = User::findForAuthentication('admin@andina.co');
        $json = json_encode(['values' => [], 'calculated' => [], 'unsupported' => []], JSON_THROW_ON_ERROR);
        $id = BibliotecaRespuesta::create(['tenant_id' => $mainId, 'plantilla_id' => $template['id'], 'titulo' => 'Registro aislado', 'datos_json' => $json, 'estado' => 'Borrador', 'created_by' => $admin['id']]);
        expectTrue($id > 0);
        expectSame(null, BibliotecaRespuesta::findForTenant($id, $constructionId));
        BibliotecaRespuesta::update($id, $mainId, ['titulo' => 'Registro actualizado', 'datos_json' => $json, 'estado' => 'Completado']);
        expectSame('Completado', BibliotecaRespuesta::findForTenant($id, $mainId)['estado']);
        expectSame(1, BibliotecaRespuesta::counts($mainId)[$template['id']]);
        BibliotecaRespuesta::delete($id, $mainId);
        expectSame(null, BibliotecaRespuesta::findForTenant($id, $mainId));
    });

    $test('Biblioteca PESV: 97 recursos, 24 pasos y contenido íntegro', static function (): void {
        $items = PesvLibrary::all();
        $stats = PesvLibrary::stats();
        expectSame(97, count($items));
        expectSame(114, $stats['source_entries']);
        expectSame(17, $stats['duplicate_entries']);
        expectSame(96, $stats['forms']);
        expectSame(1, $stats['documents']);
        expectSame(24, $stats['steps']);
        expectSame(5820, $stats['formulas']);
        expectSame(0, $stats['security']['macros_activex_ole_files']);
        expectSame(0, $stats['security']['risk_formulas_detected']);
        expectSame(113, $stats['security']['files_with_external_links']);
        expectSame(15762, $stats['security']['external_link_parts_excluded']);
        expectSame(range(1, 24), array_map(static fn(array $step): int => (int) $step['step'], PesvLibrary::steps()));
        $duplicates = 0;
        $values = 0;
        foreach ($items as $item) {
            expectSame($item['id'], PesvLibrary::find((int) $item['number'])['id']);
            expectSame($item['number'], PesvLibrary::findById($item['id'])['number']);
            expectTrue(is_file(BASE_PATH . '/resources/pesv-library/' . $item['content']));
            expectTrue($item['sources'] !== []);
            $duplicates += (int) $item['duplicate_entries'];
            $values += (int) ($item['stats']['cells'] ?? 0);
            if ($item['kind'] === 'document') {
                $content = PesvLibrary::content($item);
                expectTrue(trim(strip_tags($content)) !== '' && !str_contains(text_lower($content), '<script'));
                expectSame(1, preg_match('/<td[^>]*>.*<strong>.*<\/strong>.*<\/td>/s', $content), 'El Word debe conservar el formato de runs dentro de tablas.');
            } else {
                $form = PesvLibrary::form($item);
                expectTrue($form['sheets'] !== []);
                foreach ($form['sheets'] as $sheet) {
                    foreach ($sheet['cells'] as $cell) {
                        if (isset($cell['f'])) expectSame(0, preg_match('/\[[0-9]+\]|\b(?:WEBSERVICE|RTD|DDE|CALL|SHELL|EXEC)\s*\(/i', $cell['f']));
                    }
                }
            }
        }
        expectSame(17, $duplicates);
        expectSame(14143, $values);
        $pat = null;
        $matrix = null;
        foreach ($items as $item) {
            if ($item['title'] === 'PLAN DE TRABAJO ANUAL') $pat = $item;
            if (str_starts_with($item['title'], 'MATRIZ DE CONTROL DE DOCUMENTOS')) $matrix = $item;
        }
        expectTrue($pat !== null && $matrix !== null);
        $patSheet = PesvLibrary::form($pat)['sheets'][0];
        expectSame(257, max($patSheet['columns']));
        expectTrue(isset($patSheet['cells']['IW52']));
        $matrixSheets = PesvLibrary::form($matrix)['sheets'];
        $matrixColumns = max(array_map(static fn(array $sheet): int => max($sheet['columns'] ?: [0]), $matrixSheets));
        expectSame(9, $matrixColumns);
        expectTrue((int) $matrix['stats']['residual_format_cells_excluded'] > 0);
    });

    $test('PESV en transporte: generación automática, trazabilidad e idempotencia', static function () use ($mainId): void {
        expectTrue(PesvLibrary::isRecommended(['sector_economico' => 'Transporte']));
        expectTrue(PesvLibrary::isRecommended(['sector_economico' => 'Servicios'], ['codigo_ciuu' => '4923', 'descripcion' => 'Transporte de carga por carretera']));
        expectTrue(!PesvLibrary::isRecommended(['sector_economico' => 'Servicios'], ['codigo_ciuu' => '6201', 'descripcion' => 'Desarrollo de software']));
        $activity = Database::first('SELECT * FROM actividades_economicas WHERE codigo_ciuu = ?', ['4923']);
        expectTrue($activity !== null);
        $transportId = Tenant::create([
            'razonsocial' => 'Transporte Prueba PESV S.A.S.', 'nit' => '901.999.001-7', 'sector_economico' => 'Transporte',
            'actividad_economica_id' => (int) $activity['id'], 'numero_trabajadores' => 20, 'nivel_riesgo' => 'V',
            'grupo_estandares' => 60, 'metodo_configuracion' => 'Combinado', 'plan_id' => null,
            'representante_legal' => 'Responsable de prueba', 'correo_contacto' => 'pesv@example.test',
        ]);
        $keys = ProcedureLibrary::automaticKeys($transportId);
        expectTrue(in_array('pesv', $keys, true));
        expectTrue(!in_array('pesv', ProcedureLibrary::automaticKeys($mainId), true));
        $summary = DocumentGenerator::generate($transportId);
        expectTrue($summary['pesv']);
        $document = Documento::findByCode(PesvLibrary::PLAN_CODE, $transportId);
        expectTrue($document !== null);
        expectSame('Borrador', $document['estado']);
        expectSame('Planear', $document['fase_phva']);
        expectSame(PesvLibrary::PLAN_CODE, $document['biblioteca_id']);
        expectTrue(str_contains($document['contenido_html'], 'Paso 24'));
        expectTrue(!str_contains($document['contenido_html'], '0 plantilla(s)'), 'Los pasos con recursos deduplicados deben conservar sus alias.');
        expectTrue(str_contains($document['contenido_html'], 'Transporte Prueba PESV S.A.S.'));
        expectTrue(strlen($document['contenido_html']) < 300000);
        expectSame(null, Documento::findByCode(PesvLibrary::PLAN_CODE, $mainId));
        expectSame(null, Documento::findForTenant((int) $document['id'], $mainId));
        expectSame(0, ProcedureLibrary::generate($transportId, ['pesv']));
        Documento::saveDraft((int) $document['id'], $transportId, $document['contenido_html'] . '<p>Revisión operativa de prueba.</p>');
        expectSame(2, (int) Documento::findByCode(PesvLibrary::PLAN_CODE, $transportId)['version']);
        expectSame(1, count(Documento::versions((int) $document['id'], $transportId)));
    });

    $test('Planes SaaS: catálogo PESVial y Emergencias con autorización por módulo', static function (): void {
        $pesv = Database::first("SELECT * FROM planes_saas WHERE nombre = 'PESVial'");
        $emergency = Database::first("SELECT * FROM planes_saas WHERE nombre = 'Emergencias y Vigilancia'");
        expectTrue($pesv !== null && $emergency !== null);
        expectSame(ModuleAccess::PESV, $pesv['modulo']);
        expectSame(ModuleAccess::EMERGENCIAS, $emergency['modulo']);
        expectSame(['Pagado', 'Sugerido'], ModuleAccess::conditions());
        expectTrue(ModuleAccess::allows(ModuleAccess::PESV, ModuleAccess::PESV));
        expectTrue(ModuleAccess::allows(ModuleAccess::PESV, ModuleAccess::SGST));
        expectTrue(!ModuleAccess::allows(ModuleAccess::SGST, ModuleAccess::PESV));
        expectTrue(!ModuleAccess::allows(ModuleAccess::EMERGENCIAS, ModuleAccess::PESV));
        expectTrue(ModuleAccess::allows(ModuleAccess::EMERGENCIAS, ModuleAccess::EMERGENCIAS));
        expectTrue(ModuleAccess::allows(ModuleAccess::SHARED, ModuleAccess::PESV));
    });

    $test('Plan PESVial pagado: genera solo PESV y restringe documentos al cambiar de plan', static function (): void {
        $plan = PlanSaaS::find((int) Database::first("SELECT id FROM planes_saas WHERE nombre = 'PESVial'")['id']);
        $otherPlan = PlanSaaS::find((int) Database::first("SELECT id FROM planes_saas WHERE nombre = 'Emergencias y Vigilancia'")['id']);
        $tenantId = Tenant::create([
            'razonsocial' => 'Empresa exclusiva PESVial', 'nit' => '901.999.101-1', 'sector_economico' => 'Servicios',
            'actividad_economica_id' => null, 'numero_trabajadores' => 12, 'nivel_riesgo' => 'II',
            'grupo_estandares' => 21, 'metodo_configuracion' => 'Riesgo', 'plan_id' => (int) $plan['id'], 'plan_condicion' => 'Pagado',
            'representante_legal' => 'Responsable PESV', 'correo_contacto' => 'pesvial@example.test',
        ]);
        $summary = TenantModuleGenerator::generate($tenantId);
        expectSame(ModuleAccess::PESV, $summary['modulo']);
        expectSame(1, $summary['documentos']);
        expectSame(0, $summary['estandares']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM plan_trabajo WHERE tenant_id = ?', [$tenantId])['n']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM capacitaciones WHERE tenant_id = ?', [$tenantId])['n']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM peligros_ipevr WHERE tenant_id = ?', [$tenantId])['n']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM inducciones WHERE tenant_id = ?', [$tenantId])['n']);
        $documents = Documento::forTenant($tenantId);
        expectSame(1, count($documents));
        expectSame(PesvLibrary::PLAN_CODE, $documents[0]['codigo_estandar']);
        expectTrue(str_contains($documents[0]['contenido_html'], 'Paso 24'));
        expectSame(1, count(Documento::forTenant($tenantId, ['modulo' => ModuleAccess::PESV])));
        expectSame([], Documento::forTenant($tenantId, ['modulo' => ModuleAccess::EMERGENCIAS]));
        expectSame(null, Documento::findForTenant((int) $documents[0]['id'], $tenantId, ModuleAccess::EMERGENCIAS));
        expectTrue(ModuleAccess::documentMatches($documents[0], ModuleAccess::PESV));
        expectTrue(!ModuleAccess::documentMatches($documents[0], ModuleAccess::EMERGENCIAS));
        expectSame(ModuleAccess::PESV, ModuleAccess::forTenant($tenantId));
        Tenant::setPlan($tenantId, (int) $otherPlan['id'], 'Sugerido');
        expectSame(ModuleAccess::EMERGENCIAS, ModuleAccess::forTenant($tenantId));
        expectSame([], Documento::forTenant($tenantId, ['modulo' => ModuleAccess::EMERGENCIAS]), 'Un documento PESV histórico no debe aparecer en Emergencias.');
        Tenant::setPlan($tenantId, (int) $plan['id'], 'Pagado');
        expectSame(1, count(Documento::forTenant($tenantId, ['modulo' => ModuleAccess::PESV])));
    });

    $test('Plan Emergencias sugerido: genera plan y vigilancia sin cargar SG-SST general', static function (): void {
        $plan = PlanSaaS::find((int) Database::first("SELECT id FROM planes_saas WHERE nombre = 'Emergencias y Vigilancia'")['id']);
        $tenantId = Tenant::create([
            'razonsocial' => 'Empresa exclusiva Emergencias', 'nit' => '901.999.102-9', 'sector_economico' => 'Servicios',
            'actividad_economica_id' => null, 'numero_trabajadores' => 8, 'nivel_riesgo' => 'I',
            'grupo_estandares' => 7, 'metodo_configuracion' => 'Riesgo', 'plan_id' => (int) $plan['id'], 'plan_condicion' => 'Sugerido',
            'representante_legal' => 'Responsable Emergencias', 'correo_contacto' => 'emergencias@example.test',
        ]);
        $summary = TenantModuleGenerator::generate($tenantId);
        expectSame(ModuleAccess::EMERGENCIAS, $summary['modulo']);
        expectSame(2, $summary['documentos']);
        expectSame(0, $summary['plan_trabajo']);
        expectSame(1, count(PlanEmergencia::forTenant($tenantId)));
        $programs = ProgramaPrevencion::forTenant($tenantId);
        expectSame(1, count($programs));
        expectSame('Vigilancia epidemiologica', $programs[0]['categoria']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM peligros_ipevr WHERE tenant_id = ?', [$tenantId])['n']);
        expectSame(0, (int) Database::first('SELECT COUNT(*) AS n FROM inducciones WHERE tenant_id = ?', [$tenantId])['n']);
        $documents = Documento::forTenant($tenantId, ['modulo' => ModuleAccess::EMERGENCIAS]);
        expectSame(2, count($documents));
        foreach ($documents as $document) {
            expectTrue(ModuleAccess::documentMatches($document, ModuleAccess::EMERGENCIAS));
            expectSame('Borrador', $document['estado']);
        }
        expectSame([], Documento::forTenant($tenantId, ['modulo' => ModuleAccess::PESV]));
        $board = ComplianceService::board($tenantId, ModuleAccess::EMERGENCIAS);
        expectSame(7, count($board['items']));
        expectTrue(isset($board['items']['emergencia_plan'], $board['items']['vigilancia_epidemiologica']));
        expectTrue(!$board['items']['emergencia_brigada']['signal']['available'], 'Una brigada pendiente no debe contarse como evidencia registrada.');
        expectTrue(!$board['items']['emergencia_equipos']['signal']['available'], 'Un inventario pendiente no debe contarse como recurso registrado.');
        expectTrue(!isset($board['items']['politica_sst'], $board['items']['matriz_ipevr']));
        $blocked = false;
        try { ComplianceService::definition('politica_sst', ModuleAccess::EMERGENCIAS); } catch (InvalidArgumentException) { $blocked = true; }
        expectTrue($blocked, 'Un requisito general no debe quedar disponible en el plan especializado.');
        Tenant::setPlan($tenantId, (int) $plan['id'], 'Pagado');
        expectSame(ModuleAccess::EMERGENCIAS, ModuleAccess::forTenant($tenantId), 'Pagado y Sugerido deben conservar el mismo alcance.');
    });

    $test('Respuestas PESV: persistencia y aislamiento por tenant', static function () use ($mainId, $constructionId): void {
        $template = null;
        foreach (PesvLibrary::all() as $item) {
            if ($item['kind'] === 'form') { $template = $item; break; }
        }
        expectTrue($template !== null);
        $form = PesvLibrary::form($template);
        $data = ExcelCalculator::calculate($form, ExcelCalculator::initialValues($form));
        $admin = User::findForAuthentication('admin@andina.co');
        $id = BibliotecaRespuesta::create([
            'tenant_id' => $mainId, 'plantilla_id' => $template['id'], 'titulo' => 'Registro PESV aislado',
            'datos_json' => json_encode($data, JSON_THROW_ON_ERROR), 'estado' => 'Borrador', 'created_by' => $admin['id'],
        ]);
        expectTrue($id > 0);
        expectSame(null, BibliotecaRespuesta::findForTenant($id, $constructionId));
        expectSame(1, BibliotecaRespuesta::counts($mainId)[$template['id']]);
        BibliotecaRespuesta::update($id, $mainId, ['titulo' => 'Registro PESV actualizado', 'datos_json' => json_encode($data, JSON_THROW_ON_ERROR), 'estado' => 'Completado']);
        expectSame('Completado', BibliotecaRespuesta::findForTenant($id, $mainId)['estado']);
        BibliotecaRespuesta::delete($id, $mainId);
        expectSame(null, BibliotecaRespuesta::findForTenant($id, $mainId));
    });

    $test('Calculador PESV: 5.820 fórmulas procesadas sin referencias externas', static function (): void {
        $total = 0;
        foreach (PesvLibrary::all() as $template) {
            if ($template['kind'] !== 'form') continue;
            $form = PesvLibrary::form($template);
            $result = ExcelCalculator::calculate($form, ExcelCalculator::initialValues($form));
            expectTrue(isset($result['values'], $result['calculated'], $result['unsupported']));
            foreach ($form['sheets'] as $sheet) {
                foreach ($sheet['cells'] as $cell) if (isset($cell['f'])) $total++;
            }
        }
        expectSame(5820, $total);
        $maintenance = null;
        foreach (PesvLibrary::all() as $template) if ($template['title'] === 'PROGRAMA DE MANTENIMIENTO') $maintenance = $template;
        expectTrue($maintenance !== null);
        $form = PesvLibrary::form($maintenance);
        $input = [];
        $count = 0;
        foreach ($form['sheets'] as $sheet) {
            if (($sheet['state'] ?? 'visible') !== 'visible') continue;
            foreach ($sheet['rows'] as $row) foreach ($sheet['columns'] as $column) {
                $coordinate = SgsstLibrary::columnName($column) . $row;
                if (isset($sheet['merged'][$coordinate]) || isset($sheet['cells'][$coordinate]['f'])) continue;
                $input[$sheet['name']][$coordinate] = $sheet['cells'][$coordinate]['v'] ?? '';
                $count++;
            }
        }
        expectSame(34905, $count);
        expectTrue(strlen(json_encode($input, JSON_THROW_ON_ERROR)) < 12 * 1024 * 1024);
        $normalized = ExcelCalculator::normalizeInput($form, $input);
        $result = ExcelCalculator::calculate($form, $normalized);
        expectTrue(isset($result['values'], $result['calculated']));
    });

    $test('Calculador Excel: funciones, hojas anuales y fórmulas reales', static function (): void {
        $form = ['sheets' => [['name' => '2026', 'state' => 'visible', 'rows' => range(1, 9), 'columns' => [1, 2, 3], 'merged' => [], 'cells' => [
            'A1' => ['v' => 2], 'A2' => ['v' => 3], 'A3' => ['v' => 4], 'A4' => ['v' => 0], 'C1' => ['v' => 'x'], 'C2' => ['v' => 'y'], 'C3' => ['v' => 'x'],
            'B1' => ['f' => 'SUM(A1:A3)'], 'B2' => ['f' => 'IF(B1>=5,"Alto","Bajo")'], 'B3' => ['f' => 'IFERROR(1/A4,99)'],
            'B4' => ['f' => 'SUMIF(C1:C3,"x",A1:A3)'], 'B5' => ['f' => 'MAX(A1:A3)'], 'B6' => ['f' => 'AVERAGE(A1:A3)'],
            'B7' => ['f' => 'COUNT(A1:A3)'], 'B8' => ['f' => 'SUM(A1:A3)*2+B1'], 'B9' => ['f' => 'VLOOKUP(A1,A1:C3,2,0)', 'v' => 7],
        ]]]];
        $values = ExcelCalculator::initialValues($form);
        $result = ExcelCalculator::calculate($form, $values);
        $calculated = $result['calculated']['2026'];
        foreach (['B1' => 9, 'B3' => 99, 'B4' => 6, 'B5' => 4, 'B6' => 3, 'B7' => 3, 'B8' => 27, 'B9' => 7] as $cell => $expected) {
            expectTrue(abs((float) $calculated[$cell] - $expected) < 0.00001, 'Resultado de ' . $cell);
        }
        expectSame('Alto', $calculated['B2']);
        expectSame(1, count($result['unsupported']));
        $values = ExcelCalculator::normalizeInput($form, ['2026' => ['A1' => '5']]);
        expectSame(12.0, ExcelCalculator::calculate($form, $values)['calculated']['2026']['B1']);
        $blocked = false;
        try { ExcelCalculator::normalizeInput($form, ['2026' => ['Z99' => 'no permitido']]); } catch (InvalidArgumentException) { $blocked = true; }
        expectTrue($blocked);

        $total = $unsupported = $numeric = $mismatches = 0;
        foreach (SgsstLibrary::all() as $template) {
            if ($template['kind'] !== 'form') continue;
            $libraryForm = SgsstLibrary::form($template);
            $result = ExcelCalculator::calculate($libraryForm, ExcelCalculator::initialValues($libraryForm));
            $unsupported += count($result['unsupported']);
            foreach ($libraryForm['sheets'] as $sheet) {
                foreach ($sheet['cells'] as $coordinate => $cell) {
                    if (!isset($cell['f'])) continue;
                    $total++;
                    $cached = $cell['v'] ?? null;
                    $value = $result['calculated'][$sheet['name']][$coordinate] ?? null;
                    if (is_numeric($cached) && is_numeric($value)) {
                        $numeric++;
                        if (abs((float) $cached - (float) $value) / max(1, abs((float) $cached)) > 0.0001) $mismatches++;
                    }
                }
            }
        }
        expectSame(2337, $total);
        expectSame(4, $unsupported);
        expectSame(0, $mismatches);
        expectTrue($numeric >= 1850);
    });

    $test('Originales opcionales: ZIP externo, caché privada y hash verificado', static function (): void {
        $content = 'Original sintético para verificar extracción selectiva sin distribuir el ZIP documental.';
        $template = [
            'id' => 'BIB-AAAAAAAAAAA1', 'extension' => 'docx', 'sha256' => hash('sha256', $content),
            'bytes' => strlen($content), 'sources' => [['zip_index' => 0]],
        ];
        $zipPath = BASE_PATH . '/storage/testing-library-source.zip';
        $path = BASE_PATH . '/storage/sgsst-library/originals/' . $template['id'] . '.docx';
        $previousSource = getenv('SGSST_LIBRARY_ZIP');
        try {
            @unlink($zipPath);
            @unlink($path);
            $zip = new ZipArchive();
            expectTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
            $zip->addFromString('original.docx', $content);
            expectTrue($zip->close());
            putenv('SGSST_LIBRARY_ZIP=' . $zipPath);
            expectSame($path, LibraryOriginal::ensureAvailable($template));
            expectSame($template['sha256'], hash_file('sha256', $path));
            expectSame($template['bytes'], filesize($path));
            expectSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', LibraryOriginal::mime('docx'));
            unlink($zipPath);
            expectSame($path, LibraryOriginal::ensureAvailable($template), 'La copia privada verificada no debe depender del ZIP.');
            unlink($path);
            $rejected = false;
            try {
                LibraryOriginal::ensureAvailable($template);
            } catch (RuntimeException $exception) {
                $rejected = str_contains($exception->getMessage(), 'SGSST_LIBRARY_ZIP');
            }
            expectTrue($rejected, 'Sin ZIP ni copia privada debe informarse la configuración opcional.');
        } finally {
            if ($previousSource === false) putenv('SGSST_LIBRARY_ZIP');
            else putenv('SGSST_LIBRARY_ZIP=' . $previousSource);
            @unlink($zipPath);
            @unlink($path);
        }
    });

    $test('IVC y registros sanitarios: CRUD, evidencias y aislamiento', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $actaData = [
            'numero_acta' => 'IVC-CRUD-01', 'autoridad' => 'Autoridad de prueba', 'fecha_visita' => '2026-10-01',
            'fecha_notificacion' => '2026-10-02', 'fecha_limite' => '2026-11-01', 'concepto' => 'Favorable con requerimientos',
            'porcentaje' => 53.5, 'direccion_visita' => 'Dirección de prueba', 'fuente_nombre' => 'fuente.pdf',
            'fuente_sha256' => str_repeat('a', 64), 'observaciones' => 'Registro sintético para validar el CRUD.',
        ];
        $actaId = IvcActa::create($actaData + ['tenant_id' => $mainId]);
        $otherActaId = IvcActa::create($actaData + ['tenant_id' => $constructionId]);
        expectTrue($actaId > 0 && $otherActaId > 0, 'El mismo número de acta debe poder existir en tenants distintos.');
        expectSame(null, IvcActa::findForTenant($actaId, $constructionId));
        $changedActa = $actaData;
        $changedActa['concepto'] = 'Concepto actualizado por prueba';
        expectTrue(IvcActa::update($actaId, $mainId, $changedActa));
        expectSame('Concepto actualizado por prueba', IvcActa::findForTenant($actaId, $mainId)['concepto']);
        expectSame('Favorable con requerimientos', IvcActa::findForTenant($otherActaId, $constructionId)['concepto']);

        $requirementData = [
            'codigo' => 'INT-01', 'origen' => 'Seguimiento interno', 'tema' => 'quimicos',
            'hallazgo' => 'Hallazgo sintético', 'accion' => 'Acción de prueba', 'responsable' => 'Responsable de prueba',
            'fecha_compromiso' => '2026-11-15', 'estado' => 'Pendiente', 'evidencia_requerida' => 'Informe revisado', 'verificacion' => '',
        ];
        $requirementId = IvcRequerimiento::create($requirementData + ['tenant_id' => $mainId, 'acta_id' => $actaId]);
        expectSame(null, IvcRequerimiento::findForTenant($requirementId, $constructionId));
        $recordData = [
            'requerimiento_id' => $requirementId, 'tipo' => 'quimicos', 'titulo' => 'Inventario sintético',
            'fecha_registro' => '2026-10-27', 'responsable' => 'Responsable de prueba', 'estado' => 'Pendiente',
            'datos' => ['producto' => 'Producto de prueba', 'cantidad' => '2.5', 'unidad' => 'kg', 'sds_fecha' => '2026-10-01'],
            'observaciones' => 'No representa una ejecución real.', 'created_by' => (int) $admin['id'],
        ];
        $recordId = SanitarioRegistro::create($recordData + ['tenant_id' => $mainId]);
        expectSame(null, SanitarioRegistro::findForTenant($recordId, $constructionId));
        $recordData['titulo'] = 'Inventario sintético actualizado';
        $recordData['datos']['producto'] = 'Producto actualizado';
        expectTrue(SanitarioRegistro::update($recordId, $mainId, $recordData));
        $storedRecord = SanitarioRegistro::findForTenant($recordId, $mainId);
        expectSame('Producto actualizado', $storedRecord['datos']['producto']);
        expectSame('2.5', $storedRecord['datos']['cantidad']);

        $link = EvidenceLinker::resolve('ivc_requerimiento:' . $requirementId, $mainId, ModuleAccess::SGST);
        expectSame('ivc_requerimiento', $link['entidad_tipo']);
        $evidenceId = Evidencia::create(array_merge(evidenceTestData($mainId, 'ivc-crud-' . $requirementId, ModuleAccess::SGST, (int) $admin['id']), $link));
        Evidencia::review($evidenceId, $mainId, 'Revisado', 'Revisión sintética de prueba', (int) $admin['id']);
        expectSame(1, IvcRequerimiento::reviewedEvidenceCount($requirementId, $mainId));
        expectSame(1, (int) IvcRequerimiento::dependencies($requirementId, $mainId)['evidencias']);
        expectSame(1, (int) IvcActa::dependencies($actaId, $mainId)['registros']);
        expectSame('/ivc/' . $actaId . '#requerimiento-' . $requirementId, EvidenceLinker::url(['entidad_tipo' => 'ivc_requerimiento', 'entidad_id' => (string) $requirementId, 'tenant_id' => $mainId]));
        $crossBlocked = false;
        try { EvidenceLinker::resolve('ivc_requerimiento:' . $requirementId, $constructionId, ModuleAccess::SGST); } catch (DomainException) { $crossBlocked = true; }
        expectTrue($crossBlocked);
        $changedRequirement = $requirementData;
        $changedRequirement['estado'] = 'Cerrado';
        $changedRequirement['verificacion'] = 'Verificación sintética con evidencia revisada.';
        expectTrue(IvcRequerimiento::update($requirementId, $mainId, $changedRequirement));
        expectSame('Cerrado', IvcRequerimiento::findForTenant($requirementId, $mainId)['estado']);

        $secondRequirement = IvcRequerimiento::create(array_merge($requirementData, ['tenant_id' => $mainId, 'acta_id' => $actaId, 'codigo' => 'INT-02']));
        $secondRecord = SanitarioRegistro::create(array_merge($recordData, ['tenant_id' => $mainId, 'requerimiento_id' => $secondRequirement, 'titulo' => 'Registro eliminable']));
        expectTrue(SanitarioRegistro::delete($secondRecord, $mainId));
        expectTrue(IvcRequerimiento::delete($secondRequirement, $mainId));
        expectSame(null, IvcRequerimiento::findForTenant($secondRequirement, $mainId));
        expectSame(12, count(SanitaryCatalog::types()));
        expectTrue(!isset(SanitaryCatalog::types()['capacitacion_sanitaria']), 'La capacitación debe reutilizar el módulo existente.');
        foreach (SanitaryCatalog::types() as $type => $definition) {
            expectSame($type, SanitaryCatalog::definition($type) === $definition ? $type : '');
            $keys = array_column($definition['fields'], 'key');
            expectSame(count($keys), count(array_unique($keys)), 'Los campos JSON deben tener claves únicas.');
        }
    });

    $test('Brechas CRUD: indicadores, hallazgos, equipo, simulacros y CIIU', static function () use ($mainId, $constructionId): void {
        $admin = User::findForAuthentication('admin@andina.co');
        $indicatorData = ['tenant_id' => $mainId, 'tipo' => 'Personalizado', 'nombre' => 'Indicador CRUD', 'periodo' => '2026-10', 'numerador' => 2, 'denominador' => 20, 'factor' => 100, 'resultado' => 10, 'unidad' => '%', 'meta' => 80, 'fecha_corte' => '2026-10-31'];
        $indicatorId = Indicador::create($indicatorData);
        expectSame(null, Indicador::findForTenant($indicatorId, $constructionId));
        $indicatorData['numerador'] = 4;
        $indicatorData['resultado'] = 20;
        expectTrue(Indicador::update($indicatorId, $mainId, $indicatorData));
        expectSame(20.0, (float) Indicador::findForTenant($indicatorId, $mainId)['resultado']);
        expectTrue(Indicador::delete($indicatorId, $mainId));

        $findingData = ['tenant_id' => $mainId, 'origen' => 'Inspeccion', 'descripcion' => 'Hallazgo CRUD', 'accion_correctiva' => 'Acción inicial', 'responsable' => 'Responsable', 'fecha_limite' => '2026-11-30'];
        $findingId = Hallazgo::create($findingData);
        Hallazgo::setStatus($findingId, $mainId, 'Cerrado');
        $findingData['accion_correctiva'] = 'Acción actualizada';
        expectTrue(Hallazgo::update($findingId, $mainId, $findingData));
        expectSame('Cerrado', Hallazgo::findForTenant($findingId, $mainId)['estado']);
        expectSame(null, Hallazgo::findForTenant($findingId, $constructionId));
        expectTrue(Hallazgo::delete($findingId, $mainId));

        $employeeData = ['tenant_id' => $mainId, 'nombre' => 'Persona CRUD', 'documento' => 'TEST-CRUD-' . $mainId, 'email' => 'crud@example.test', 'cargo' => 'Cargo', 'area' => 'Área', 'tipo_vinculacion' => 'Contratista', 'fecha_ingreso' => '2026-01-01', 'telefono' => '123'];
        $employeeId = Empleado::create($employeeData);
        $employeeData['cargo'] = 'Cargo actualizado';
        expectTrue(Empleado::update($employeeId, $mainId, $employeeData));
        expectSame('Cargo actualizado', Empleado::findForTenant($employeeId, $mainId)['cargo']);
        expectSame(null, Empleado::findForTenant($employeeId, $constructionId));
        Empleado::setActive($employeeId, $mainId, false);
        expectSame(0, (int) Empleado::findForTenant($employeeId, $mainId)['activo']);

        $accountId = User::create(['tenant_id' => $mainId, 'nombre' => 'Cuenta CRUD', 'email' => 'crud-access-' . $mainId . '@example.test', 'password' => password_hash('ClaveInicial2026!', PASSWORD_DEFAULT), 'rol' => 'ResponsableSST', 'must_change_password' => true]);
        $accountData = ['nombre' => 'Cuenta actualizada', 'email' => 'crud-updated-' . $mainId . '@example.test', 'licencia_sst' => 'Licencia de prueba'];
        expectTrue(!User::updateForTenant($accountId, $constructionId, $accountData));
        expectTrue(User::updateForTenant($accountId, $mainId, $accountData));
        expectSame('Cuenta actualizada', User::findForTenant($accountId, $mainId)['nombre']);
        expectSame(1, (int) User::findForTenant($accountId, $mainId)['must_change_password']);
        expectTrue(!User::resetPasswordForTenant($accountId, $constructionId, password_hash('ClaveNueva2027!', PASSWORD_DEFAULT)));
        expectTrue(User::resetPasswordForTenant($accountId, $mainId, password_hash('ClaveNueva2027!', PASSWORD_DEFAULT)));
        expectTrue(password_verify('ClaveNueva2027!', User::find($accountId)['password']));
        User::setActiveForTenant($accountId, $mainId, false);

        $planId = PlanEmergencia::create(emergencyTestData($mainId));
        $drillId = SimulacroEmergencia::create(['tenant_id' => $mainId, 'plan_id' => $planId, 'fecha' => '2026-11-01', 'tipo' => 'Inicial', 'participantes' => 1, 'responsable' => 'Responsable', 'resultado' => '', 'acciones_mejora' => '', 'estado' => 'Programado']);
        $drillData = ['fecha' => '2026-11-02', 'tipo' => 'Evacuación actualizada', 'participantes' => 5, 'responsable' => 'Responsable actualizado', 'resultado' => 'Resultado completo', 'acciones_mejora' => 'Mejora', 'estado' => 'Realizado'];
        expectTrue(SimulacroEmergencia::update($drillId, $mainId, $drillData));
        $drill = SimulacroEmergencia::findForTenant($drillId, $mainId);
        expectSame('Evacuación actualizada', $drill['tipo']);
        expectSame(5, (int) $drill['participantes']);
        expectSame(null, SimulacroEmergencia::findForTenant($drillId, $constructionId));
        SimulacroEmergencia::delete($drillId, $mainId);
        $drawingId = PlanoEmergencia::create(['tenant_id' => $mainId, 'plan_id' => $planId, 'nombre' => 'Plano CRUD', 'escala' => 'Referencial', 'descripcion' => 'Prueba', 'datos_json' => FloorPlan::encode(FloorPlan::defaults()), 'created_by' => (int) $admin['id']]);
        expectSame(1, count(PlanoEmergencia::versions($drawingId, $mainId)));
        $drawingLink = EvidenceLinker::resolve('plano:' . $drawingId, $mainId, ModuleAccess::EMERGENCIAS);
        $drawingEvidence = Evidencia::create(array_merge(evidenceTestData($mainId, 'drawing-crud-' . $drawingId, ModuleAccess::EMERGENCIAS, (int) $admin['id']), $drawingLink));
        expectSame(1, PlanoEmergencia::dependencies($drawingId, $mainId)['evidencias']);
        expectTrue(Evidencia::delete($drawingEvidence, $mainId));
        PlanoEmergencia::delete($drawingId, $mainId);
        expectSame(null, PlanoEmergencia::findForTenant($drawingId, $mainId));
        expectSame([], PlanoEmergencia::versions($drawingId, $mainId));
        PlanEmergencia::delete($planId, $mainId);

        $reportId = Autoreporte::create(['tenant_id' => $mainId, 'user_id' => (int) $admin['id'], 'origen' => 'Portal', 'tipo' => 'Condicion insegura', 'descripcion' => 'Reporte CRUD sintético', 'ubicacion' => 'Prueba', 'fecha_evento' => '2026-10-27']);
        $reportLink = EvidenceLinker::resolve('autoreporte:' . $reportId, $mainId, ModuleAccess::SGST);
        $reportEvidence = Evidencia::create(array_merge(evidenceTestData($mainId, 'report-crud-' . $reportId, ModuleAccess::SGST, (int) $admin['id']), $reportLink));
        expectSame(1, Autoreporte::dependencies($reportId, $mainId)['evidencias']);
        expectTrue(Evidencia::delete($reportEvidence, $mainId));
        expectTrue(Autoreporte::delete($reportId, $mainId));
        expectSame(null, Autoreporte::findForTenant($reportId, $mainId));

        $saasId = PlanSaaS::create(['nombre' => 'Plan CRUD ' . $mainId, 'descripcion' => 'Plan sintético', 'precio_mensual' => 999999, 'max_usuarios' => 2, 'max_empleados' => 5, 'modulo' => 'sgsst']);
        PlanSaaS::update($saasId, ['nombre' => 'Plan CRUD actualizado ' . $mainId, 'descripcion' => 'Plan sintético actualizado', 'precio_mensual' => 999999, 'max_usuarios' => 3, 'max_empleados' => 6, 'modulo' => 'sgsst', 'activo' => false]);
        expectSame('Plan CRUD actualizado ' . $mainId, PlanSaaS::find($saasId)['nombre']);
        expectSame(0, (int) PlanSaaS::find($saasId)['activo']);

        $activity = Database::first('SELECT * FROM actividades_economicas WHERE codigo_ciuu = ?', ['4759']);
        expectTrue($activity !== null);
        $updatedData = ['codigo_ciuu' => '4759', 'descripcion' => $activity['descripcion'], 'sector' => $activity['sector'], 'nivel_riesgo_predeterminado' => 'II', 'perfil_sociodemografico' => $activity['perfil_sociodemografico'] . ' Validación temporal.', 'examenes_medicos' => json_decode($activity['examenes_medicos'], true)];
        ActividadEconomica::update((int) $activity['id'], $updatedData);
        $changedProfile = ActividadEconomica::find((int) $activity['id'])['perfil_sociodemografico'];
        $updatedData['perfil_sociodemografico'] = $activity['perfil_sociodemografico'];
        ActividadEconomica::update((int) $activity['id'], $updatedData);
        ActividadEconomica::setActive((int) $activity['id'], false);
        ActividadEconomica::setActive((int) $activity['id'], true);
        expectTrue(str_contains($changedProfile, 'Validación temporal.'));
    });

    $test('Extintores SCM: precarga real, pendientes, seguridad e idempotencia', static function () use ($mainId): void {
        $result = ExtintoresScmSeeder::seed();
        expectTrue($result['created']);
        expectSame(28, $result['requirements']);
        expectSame(7, $result['validations']);
        $tenant = Tenant::find((int) $result['tenant_id']);
        expectSame('Extintores SCM', $tenant['razonsocial']);
        expectSame('39626936-8', $tenant['nit']);
        expectSame('4759', $tenant['codigo_ciuu']);
        expectSame('II', $tenant['nivel_riesgo']);
        expectSame(7, (int) $tenant['grupo_estandares']);
        expectSame('3212944603', $tenant['telefono']);
        expectSame('julia_ines_buitrago@hotmail.com', $tenant['correo_contacto']);
        $account = User::findForAuthentication($result['access']['email']);
        expectTrue($account !== null && password_verify($result['access']['password'], $account['password']));
        expectTrue($account['password'] !== $result['access']['password']);
        expectSame(1, (int) $account['must_change_password']);
        expectSame(1, (int) Database::first('SELECT COUNT(*) AS n FROM users WHERE tenant_id = ?', [$tenant['id']])['n'], 'No se deben inventar accesos para Felipe u otras personas.');
        $documentStats = Database::first("SELECT COUNT(*) AS total, SUM(CASE WHEN estado = 'Aprobado' THEN 1 ELSE 0 END) AS aprobados FROM documentos_sgsst WHERE tenant_id = ?", [$tenant['id']]);
        expectSame($result['documents'], (int) $documentStats['total']);
        expectSame(0, (int) $documentStats['aprobados']);
        $acta = IvcActa::findForTenant((int) $result['acta_id'], (int) $tenant['id']);
        expectSame('028-050164-26', $acta['numero_acta']);
        expectSame('2026-10-09', $acta['fecha_limite']);
        expectSame(53.5, (float) $acta['porcentaje']);
        expectSame('18e87021e21d7b5aa245a9817e9f3882770641d744066bad32c8733a71e5f47f', $acta['fuente_sha256']);
        expectSame(null, IvcActa::findForTenant((int) $acta['id'], $mainId));
        $requirements = IvcRequerimiento::forActa((int) $acta['id'], (int) $tenant['id']);
        expectSame(28, count($requirements));
        expectSame(20, count(array_filter($requirements, static fn(array $row): bool => $row['origen'] === 'Requerimiento IVC')));
        expectSame(8, count(array_filter($requirements, static fn(array $row): bool => $row['origen'] === 'Brecha documental')));
        foreach ($requirements as $requirement) {
            expectSame('Pendiente', $requirement['estado']);
            expectSame('', $requirement['verificacion']);
            expectTrue($requirement['fecha_compromiso'] === null);
            expectTrue(str_starts_with($requirement['responsable'], 'Por asignar'));
            expectSame(0, (int) $requirement['evidencias']);
        }
        $topics = array_column($requirements, 'tema');
        foreach (['emergencias', 'sgsst', 'capacitaciones', 'quimicos', 'residuos', 'recursos'] as $topic) expectTrue(in_array($topic, $topics, true));
        $records = SanitarioRegistro::forTenant((int) $tenant['id']);
        expectSame(7, count($records));
        foreach ($records as $record) {
            expectSame('validacion', $record['tipo']);
            expectSame('Pendiente', $record['estado']);
        }
        $validationText = json_encode($records, JSON_UNESCAPED_UNICODE);
        foreach (['Cra. 4 No. 13-65', 'Felipe Cartaya', 'Felipe Martínez', '6 × 4 m', 'Luis Antonio Aguilar'] as $reference) {
            expectTrue(str_contains($validationText, $reference), 'Falta conservar la referencia ' . $reference);
        }
        expectTrue(!is_file(BASE_PATH . '/resources/extintores-scm/ivc-source.pdf'));
        $resource = file_get_contents(BASE_PATH . '/resources/extintores-scm/seed.json');
        expectTrue(!str_contains($resource, $result['access']['password']), 'La clave temporal no puede quedar en el recurso.');

        User::rehashPassword((int) $account['id'], password_hash($result['access']['password'], PASSWORD_DEFAULT));
        expectSame(1, (int) User::find((int) $account['id'])['must_change_password']);
        User::updatePassword((int) $account['id'], password_hash('ClaveNueva2027!', PASSWORD_DEFAULT));
        expectSame(0, (int) User::find((int) $account['id'])['must_change_password']);
        $before = (int) Database::first('SELECT COUNT(*) AS n FROM ivc_requerimientos WHERE tenant_id = ?', [$tenant['id']])['n'];
        $second = ExtintoresScmSeeder::seed();
        expectTrue(!$second['created']);
        expectSame($before, (int) Database::first('SELECT COUNT(*) AS n FROM ivc_requerimientos WHERE tenant_id = ?', [$tenant['id']])['n']);
    });

    $test('Migración idempotente e integridad del esquema ampliado', static function (): void {
        Database::initialize();
        expectSame([], Database::query('PRAGMA foreign_key_check'));
        expectSame(33, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
        expectSame(21, (int) Database::first('SELECT COUNT(*) AS n FROM actividades_economicas')['n']);
        $reportColumns = array_column(Database::query('PRAGMA table_info(autoreportes)'), 'name');
        foreach (['acceso_reporte_id', 'origen', 'nombre_externo', 'contacto_externo', 'anonimo'] as $column) {
            expectTrue(in_array($column, $reportColumns, true), 'Falta la columna migrada ' . $column);
        }
        $documentColumns = array_column(Database::query('PRAGMA table_info(documentos_sgsst)'), 'name');
        expectTrue(in_array('biblioteca_id', $documentColumns, true));
        $planColumns = array_column(Database::query('PRAGMA table_info(planes_saas)'), 'name');
        $tenantColumns = array_column(Database::query('PRAGMA table_info(tenants)'), 'name');
        expectTrue(in_array('modulo', $planColumns, true));
        expectTrue(in_array('plan_condicion', $tenantColumns, true));
        $evidenceColumns = array_column(Database::query('PRAGMA table_info(soportes_cumplimiento)'), 'name');
        foreach (['modulo', 'categoria', 'titulo', 'fecha_evidencia', 'entidad_tipo', 'entidad_id', 'entidad_referencia', 'estado_revision', 'observaciones_revision', 'revisado_por', 'revisado_at', 'updated_at'] as $column) {
            expectTrue(in_array($column, $evidenceColumns, true), 'Falta la columna de evidencia ' . $column);
        }
        expectSame(4, SchemaMigrator::VERSION);
        expectTrue(Database::first("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'planos_emergencia'") !== null);
        expectTrue(Database::first("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'planos_emergencia_versiones'") !== null);
    });

    $test('Instalación web segura y migración automática', static function (): void {
        expectSame(SchemaMigrator::VERSION, (int) Database::first('PRAGMA user_version')['user_version']);
        expectTrue(!Installer::needsInstallation(), 'Una instalación con SuperAdmin debe quedar bloqueada.');
        expectTrue(!SchemaMigrator::migrateIfNeeded(), 'Una migración vigente no debe repetirse.');
        $checks = Installer::requirements();
        expectTrue(count($checks) >= 6 && Installer::canInstall(), 'El entorno de pruebas debe cumplir los requisitos del instalador web.');
        foreach ($checks as $check) {
            expectTrue(isset($check['label'], $check['ok'], $check['detail']));
        }
    });

    $test('Firma de inducción única y acotada al tenant', static function () use ($mainId, $constructionId): void {
        $induction = Induccion::current($mainId);
        $worker = User::findForAuthentication('trabajador@andina.co');
        $signatureId = Induccion::sign([
            'tenant_id' => $mainId, 'induccion_id' => (int) $induction['id'], 'user_id' => (int) $worker['id'],
            'nombre_firmante' => $worker['nombre'], 'documento_firmante' => '1.032.455.890',
            'hash_evidencia' => hash('sha256', $induction['contenido_html']),
        ]);
        expectTrue($signatureId > 0);
        expectTrue(Induccion::hasSigned((int) $induction['id'], $mainId, (int) $worker['id']));
        expectTrue(!Induccion::hasSigned((int) $induction['id'], $constructionId, (int) $worker['id']));
        $duplicateBlocked = false;
        try {
            Induccion::sign([
                'tenant_id' => $mainId, 'induccion_id' => (int) $induction['id'], 'user_id' => (int) $worker['id'],
                'nombre_firmante' => $worker['nombre'], 'documento_firmante' => '1.032.455.890', 'hash_evidencia' => 'otro',
            ]);
        } catch (PDOException) { $duplicateBlocked = true; }
        expectTrue($duplicateBlocked);
        expectSame(1, Induccion::signatureCount($mainId, (int) $induction['id']));
    });

    $test('Contraseñas demo con password_hash y verificación', static function (): void {
        $user = User::findForAuthentication('admin@andina.co');
        expectTrue($user !== null && password_verify('Normia2026!', $user['password']));
        expectTrue($user['password'] !== 'Normia2026!' && password_get_info($user['password'])['algo'] !== null);
    });

    $passed = 0;
    $started = microtime(true);
    foreach ($tests as $name => $callback) {
        try {
            $callback();
            $passed++;
            echo "✓ {$name}\n";
        } catch (Throwable $exception) {
            echo "✗ {$name}\n  {$exception->getMessage()}\n";
            echo '  ' . $exception->getFile() . ':' . $exception->getLine() . "\n";
        }
    }
    $failed = count($tests) - $passed;
    printf("\n%d pruebas · %d exitosas · %d fallidas · %.2f s\n", count($tests), $passed, $failed, microtime(true) - $started);
    Database::disconnect();
    foreach ([$testDatabase, $testDatabase . '-shm', $testDatabase . '-wal'] as $file) {
        if (is_file($file)) unlink($file);
    }
    exit($failed === 0 ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, "Error al preparar las pruebas: {$exception->getMessage()}\n{$exception->getTraceAsString()}\n");
    exit(1);
}
