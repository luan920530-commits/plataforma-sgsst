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
use App\Models\Autoevaluacion;
use App\Models\BibliotecaRespuesta;
use App\Models\Capacitacion;
use App\Models\Comite;
use App\Models\Convivencia;
use App\Models\Documento;
use App\Models\Empleado;
use App\Models\EstandarMinimo;
use App\Models\Evento;
use App\Models\Evidencia;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\Induccion;
use App\Models\IvcActa;
use App\Models\IvcRequerimiento;
use App\Models\Operation;
use App\Models\Peligro;
use App\Models\Permission;
use App\Models\PermisoTrabajo;
use App\Models\PlanEmergencia;
use App\Models\PlanSaaS;
use App\Models\PlanTrabajo;
use App\Models\PlanoEmergencia;
use App\Models\Psicosocial;
use App\Models\ProgramaActividad;
use App\Models\ProgramaPrevencion;
use App\Models\RequisitoAccion;
use App\Models\RequisitoSeguimiento;
use App\Models\Salud;
use App\Models\SanitarioRegistro;
use App\Models\SoporteReservado;
use App\Models\ActividadEconomica;
use App\Models\SimulacroEmergencia;
use App\Models\SoporteCumplimiento;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\ComplianceService;
use App\Services\DocumentGenerator;
use App\Services\DocumentPackageExporter;
use App\Services\DocumentSignature;
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
use App\Services\StandardDocumentTemplate;
use App\Services\ProcedureLibrary;
use App\Services\RequirementCatalog;
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
    $mainAdminId = (int) Database::first("SELECT id FROM users WHERE tenant_id = ? AND rol = 'AdminEmpresa' ORDER BY id LIMIT 1", [$mainId])['id'];
    $mainHseId = (int) Database::first("SELECT id FROM users WHERE tenant_id = ? AND rol = 'ResponsableSST' ORDER BY id LIMIT 1", [$mainId])['id'];

    $test('Esquema, claves foráneas y catálogos', static function (): void {
        expectSame(51, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
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

    $test('CRUD y aislamiento de peligros IPEVR', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $data = [
            'tenant_id' => $mainId, 'proceso' => 'Prueba', 'cargo' => 'Equipo', 'peligro' => 'Peligro temporal',
            'clasificacion' => 'Seguridad', 'efectos' => 'Lesión', 'controles_existentes' => 'Señalización',
            'nivel_deficiencia' => 10, 'nivel_exposicion' => 4, 'nivel_probabilidad' => 40,
            'nivel_consecuencia' => 10, 'nivel_riesgo' => 'Alto', 'medidas_control' => 'Eliminación',
            'metodologia' => 'GTC 45', 'participantes' => 'Trabajadores y responsable SST de prueba', 'fecha_evaluacion' => '2026-10-20', 'proxima_revision' => '2027-01-20', 'jerarquia_controles' => 'Eliminación y control de ingeniería justificados para la prueba.',
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
        $blocked = false; try { Peligro::setStatus($id, $mainId, 'Controlado'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Controlado exige verificación humana.');
        $blocked = false; try { Peligro::verify($id, $mainId, $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La verificación IPEVR exige soporte.');
        Evidencia::create(['tenant_id' => $mainId, 'requisito_clave' => '', 'modulo' => 'sgsst', 'categoria' => 'Inspección', 'titulo' => 'Evaluación IPEVR de prueba', 'fecha_evidencia' => '2026-10-20', 'entidad_tipo' => 'peligro', 'entidad_id' => (string) $id, 'entidad_referencia' => 'IPEVR de prueba', 'nombre_original' => 'ipevr.pdf', 'nombre_almacenado' => 'ipevr-prueba.pdf', 'ruta_relativa' => 'uploads/tenant_'.$mainId.'/ipevr-prueba.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'tamano' => 68, 'hash_sha256' => hash('sha256', 'ipevr'), 'descripcion' => '', 'estado_revision' => 'Revisado', 'subido_por' => $mainAdminId]);
        Peligro::verify($id, $mainId, $mainAdminId);
        Peligro::setStatus($id, $mainId, 'Controlado');
        expectSame('Controlado', Peligro::findForTenant($id, $mainId)['estado']);
        Peligro::update($id, $mainId, $data);
        expectSame('Activo', Peligro::findForTenant($id, $mainId)['estado']);
        expectSame(null, Peligro::findForTenant($id, $mainId)['verificado_por']);
        Peligro::delete($id, $mainId);
        expectSame(null, Peligro::findForTenant($id, $mainId));
    });

    $test('CRUD y aislamiento de capacitaciones', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $data = ['tenant_id' => $mainId, 'tema' => 'Formación temporal', 'descripcion' => 'Prueba', 'responsable' => 'SST', 'fecha_programada' => '2026-12-15', 'duracion_horas' => 2, 'modalidad' => 'Virtual'];
        $id = Capacitacion::create($data);
        expectSame(null, Capacitacion::findForTenant($id, $constructionId));
        $data['tema'] = 'Formación editada'; $data['duracion_horas'] = 3;
        Capacitacion::update($id, $mainId, $data);
        expectSame('Formación editada', Capacitacion::findForTenant($id, $mainId)['tema']);
        expectSame(3.0, (float) Capacitacion::findForTenant($id, $mainId)['duracion_horas']);
        $blocked = false; try { Capacitacion::setStatus($id, $mainId, 'Realizada'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'No debe realizarse sin asistencia.');
        $employee = Empleado::forTenant($mainId)[0];
        $attendeeId = Capacitacion::addAttendee($id, $mainId, ['empleado_id' => $employee['id']]);
        expectSame(null, Capacitacion::attendee($attendeeId, $id, $constructionId));
        Capacitacion::updateAttendee($attendeeId, $id, $mainId, ['asistio' => '1', 'evaluacion' => '4.5', 'eficacia' => 'Aplica lo aprendido en la prueba.']);
        $blocked = false; try { Capacitacion::setStatus($id, $mainId, 'Realizada'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La asistencia no reemplaza la evidencia revisada.');
        Evidencia::create(['tenant_id' => $mainId, 'requisito_clave' => '', 'modulo' => 'sgsst', 'categoria' => 'Lista de asistencia', 'titulo' => 'Asistencia de prueba', 'fecha_evidencia' => '2026-12-15', 'entidad_tipo' => 'capacitacion', 'entidad_id' => (string) $id, 'entidad_referencia' => 'Capacitación de prueba', 'nombre_original' => 'asistencia.pdf', 'nombre_almacenado' => 'asistencia-prueba.pdf', 'ruta_relativa' => 'uploads/tenant_'.$mainId.'/asistencia-prueba.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'tamano' => 68, 'hash_sha256' => hash('sha256', 'asistencia'), 'descripcion' => '', 'estado_revision' => 'Revisado', 'subido_por' => $mainAdminId]);
        Capacitacion::setStatus($id, $mainId, 'Realizada');
        expectSame('Realizada', Capacitacion::findForTenant($id, $mainId)['estado']);
        Capacitacion::recordEfficacy($id, $mainId, 'Se verificó transferencia al puesto en la prueba.', '2026-12-30', $mainAdminId);
        expectSame('Se verificó transferencia al puesto en la prueba.', Capacitacion::findForTenant($id, $mainId)['eficacia']);
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

    $test('Resumen de validación disponible y escapado', static function (): void {
        $previous = $GLOBALS['errors'] ?? null;
        $GLOBALS['errors'] = ['general' => 'Dato inválido <script>alert(1)</script>'];
        try {
            $html = form_errors();
        } finally {
            if ($previous === null) unset($GLOBALS['errors']); else $GLOBALS['errors'] = $previous;
        }
        expectTrue(str_contains($html, 'role="alert"'));
        expectTrue(str_contains($html, 'Dato inválido &lt;script&gt;alert(1)&lt;/script&gt;'));
        expectTrue(!str_contains($html, '<script>'));
        expectSame('', form_errors());
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

    $test('Programa de limpieza y desinfección: kit completo, borradores e idempotencia', static function () use ($mainId, $constructionId): void {
        $catalog = ProcedureLibrary::catalog($mainId);
        expectTrue(isset($catalog['grupos']['limpieza']), 'El catálogo debe incluir el programa de limpieza.');
        expectSame(3, count($catalog['grupos']['limpieza']['documentos']));
        expectSame('/sanitario/registros/nuevo?tipo=limpieza', $catalog['grupos']['limpieza']['related_route']);
        expectTrue(in_array('limpieza', ProcedureLibrary::automaticKeys($constructionId), true));
        $codes = ['PR-LD-01', 'FT-LD-01', 'FT-LD-02'];
        $recordsBefore = count(SanitarioRegistro::forTenant($mainId));
        foreach ($codes as $code) {
            $existing = Documento::findByCode($code, $mainId);
            expectTrue($existing !== null, 'El onboarding debía generar ' . $code . ' como borrador.');
            Documento::deleteForTenant((int) $existing['id'], $mainId);
        }
        expectSame(3, ProcedureLibrary::generate($mainId, ['limpieza']));
        expectSame(0, ProcedureLibrary::generate($mainId, ['limpieza']), 'El kit no debe duplicar documentos.');
        expectSame($recordsBefore, count(SanitarioRegistro::forTenant($mainId)), 'Crear formatos no equivale a registrar ejecuciones sanitarias.');
        $program = Documento::findByCode('PR-LD-01', $mainId);
        expectSame('Programa', $program['tipo']);
        expectSame('Borrador', $program['estado']);
        foreach (['Frecuencia propuesta para validar', 'Dilución', 'tiempo de contacto', 'SDS', 'No mezclar productos', 'no con la sola creación o firma', 'FT-LD-01', 'FT-LD-02'] as $required) {
            expectTrue(str_contains($program['contenido_html'], $required), 'Falta contenido del programa: ' . $required);
        }
        foreach (['FT-LD-01', 'FT-LD-02'] as $code) {
            $format = Documento::findByCode($code, $mainId);
            expectSame('Registro', $format['tipo']);
            expectSame('Borrador', $format['estado']);
            expectTrue(str_contains($format['contenido_html'], '<table>'), 'El formato debe ser visual y tabular.');
            expectTrue(!str_contains($format['contenido_html'], 'checked'), 'El formato no puede precargar verificaciones.');
        }
        expectSame(null, Documento::findForTenant((int) $program['id'], $constructionId));
        $regenerated = ProcedureLibrary::catalog($mainId)['grupos']['limpieza']['documentos'];
        expectSame(3, count(array_filter($regenerated, static fn(array $document): bool => $document['registro'] !== null)));
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

    $test('Firma electrónica: consentimiento, versión, integridad y archivo', static function () use ($mainId): void {
        $signerId = User::create(['tenant_id' => $mainId, 'nombre' => 'Firmante de prueba', 'email' => 'firmante@andina.co', 'password' => password_hash('Clave-Firma-2026!', PASSWORD_DEFAULT), 'rol' => 'ResponsableSST', 'licencia_sst' => 'LIC-PRUEBA']);
        $documentId = Documento::createOperational(['tenant_id' => $mainId, 'codigo' => 'FIRMA-PRUEBA', 'nombre' => 'Documento de firma sintética', 'fase' => 'Hacer', 'contenido' => '<p>Contenido original para firma de prueba.</p>', 'tipo' => 'Procedimiento']);
        $blocked = false; try { DocumentSignature::sign($documentId, $mainId, $signerId, 'Clave-Firma-2026!', '1'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Un borrador no debe firmarse como versión aprobada.');
        Documento::approve($documentId, $mainId, $signerId);
        $blocked = false; try { DocumentSignature::sign($documentId, $mainId, $signerId, 'incorrecta', '1'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La reautenticación debe verificar la contraseña.');
        $blocked = false; try { DocumentSignature::sign($documentId, $mainId, $signerId, 'Clave-Firma-2026!', null); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'El consentimiento es obligatorio.');
        $signatureId = DocumentSignature::sign($documentId, $mainId, $signerId, 'Clave-Firma-2026!', '1');
        $signature = Database::first('SELECT * FROM documento_firmas WHERE id = ?', [$signatureId]);
        expectSame('electronica', $signature['tipo']); expectSame('', $signature['certificado_emisor']);
        expectTrue(DocumentSignature::verify($signatureId, $mainId)['valida']);
        expectSame(null, Database::first('SELECT id FROM documento_firmas WHERE id = ? AND tenant_id = ?', [$signatureId, 999999]));
        Documento::saveDraft($documentId, $mainId, '<p>Contenido editado después de firmar.</p>');
        $archived = Documento::versions($documentId, $mainId)[0];
        $signature = Database::first('SELECT * FROM documento_firmas WHERE id = ?', [$signatureId]);
        expectSame((int) $archived['id'], (int) $signature['documento_version_id'], 'La firma debe quedar enlazada a la versión archivada.');
        expectTrue(DocumentSignature::verify($signatureId, $mainId)['valida']);
        expectSame([], DocumentSignature::forDocument($documentId, $mainId, 2), 'La versión nueva no hereda firmas.');
        Database::execute('UPDATE documento_versiones SET contenido_html = ? WHERE id = ?', ['<p>Alteración de prueba</p>', $archived['id']]);
        expectSame(false, DocumentSignature::verify($signatureId, $mainId)['valida'], 'Debe detectar contenido alterado.');
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

    $test('Actualización normativa: paquete 1.1 y documentos generados', static function () use ($mainId): void {
        $library = json_decode(file_get_contents(BASE_PATH . '/resources/starter-package/library.json'), true, 512, JSON_THROW_ON_ERROR);
        $standards = json_decode(file_get_contents(BASE_PATH . '/resources/starter-package/standards.json'), true, 512, JSON_THROW_ON_ERROR);
        expectSame('1.1.0', $library['version']);
        expectSame('1.1.0', $standards['version']);
        expectTrue(!isset($library['references']['R2346']) && !isset($library['references']['R1918']));
        foreach (['R1843', 'D728', 'R2764', 'D1040', 'CIR027'] as $reference) {
            expectTrue(isset($library['references'][$reference]['url']), 'Falta la fuente oficial ' . $reference);
        }
        foreach ($library['kits'] as $kit) {
            foreach ($kit['documents'] as $document) {
                foreach ($document['references'] as $reference) {
                    expectTrue(isset($library['references'][$reference]), 'Referencia huérfana en ' . $document['code']);
                }
            }
        }
        $medical = Documento::findByCode('PR-SAL-01', $mainId)['contenido_html'];
        foreach (['Resolución 1843 de 2025', 'postincapacidad', 'retorno laboral', 'seguimiento', 'tres años'] as $text) {
            expectTrue(str_contains($medical, $text), 'Contenido médico actualizado ausente: ' . $text);
        }
        expectTrue(!str_contains($medical, '2346') && !str_contains($medical, '1918'));
        $psychosocial = Documento::findByCode('PR-PSI-01', $mainId)['contenido_html'];
        foreach (['Resolución 2764 de 2022', 'Decreto 728 de 2025', 'Decreto 1040 de 2026', 'sin alterar instrumentos', 'resultados agregados'] as $text) {
            expectTrue(str_contains($psychosocial, $text), 'Contenido psicosocial actualizado ausente: ' . $text);
        }
        $committee = Documento::findByCode('PR-CCL-01', $mainId)['contenido_html'];
        expectTrue(str_contains($committee, '65 días calendario') && str_contains($committee, 'Decreto 1040 de 2026'));
        expectTrue(str_contains($committee, 'exactamente 5 o 20 trabajadores'));
        $guide = Documento::findByCode('GUI-INI-01', $mainId)['contenido_html'];
        expectTrue(str_contains($guide, 'Circular 027 de 2026') && str_contains($guide, 'autoevaluación anual'));
        expectSame('Resolución 1843 de 2025 y Decreto 1072 de 2015.', $standards['topics']['medical_eval']['reference']);
        expectTrue(str_contains(implode(' ', $standards['topics']['induction']['checks']), 'firma electrónica'));
    });

    $test('Fuentes históricas señaladas y referencia PESV actualizada', static function (): void {
        foreach (['BIB-FD13ED44C01C', 'BIB-B9CF24140944', 'BIB-4A772E8F400E', 'BIB-1364C3547054', 'BIB-5C1EBA0A3EAF'] as $id) {
            $item = SgsstLibrary::findById($id);
            expectTrue($item !== null && !empty($item['legal_review']), 'Falta advertencia normativa en ' . $id);
            expectTrue(str_contains($item['legal_review'], '1843 de 2025'));
        }
        $item = PesvLibrary::findById('PESV-7721AA56D741');
        $form = PesvLibrary::form($item);
        $cell = null;
        foreach ($form['sheets'] as $sheet) {
            if (isset($sheet['cells']['A28'])) $cell = $sheet['cells']['A28'];
        }
        expectTrue($cell !== null && str_contains($cell['v'], 'Resolución 1843 de 2025'));
        expectTrue(!str_contains($cell['v'], '2346') && !str_contains($cell['v'], '1918'));
        expectTrue(str_contains(implode(' ', $item['warnings']), 'no acredita aptitud'));
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
        $indicatorData['fuente_numerador'] = 'Eventos registrados en la prueba'; $indicatorData['fuente_denominador'] = 'Directorio de trabajadores de la prueba'; $indicatorData['responsable'] = 'Responsable de datos';
        Indicador::update($indicatorId, $mainId, $indicatorData);
        $blocked = false; try { Indicador::verify($indicatorId, $mainId, (int) $admin['id']); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La verificación requiere soporte revisado.');
        Evidencia::create(['tenant_id' => $mainId, 'requisito_clave' => '', 'modulo' => 'sgsst', 'categoria' => 'Reporte o informe', 'titulo' => 'Datos fuente de indicador de prueba', 'fecha_evidencia' => '2026-10-31', 'entidad_tipo' => 'indicador', 'entidad_id' => (string) $indicatorId, 'entidad_referencia' => 'Indicador de prueba', 'nombre_original' => 'fuente.pdf', 'nombre_almacenado' => 'fuente-indicador-prueba.pdf', 'ruta_relativa' => 'uploads/tenant_'.$mainId.'/fuente-indicador-prueba.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'tamano' => 68, 'hash_sha256' => hash('sha256', 'indicador'), 'descripcion' => '', 'estado_revision' => 'Revisado', 'subido_por' => $admin['id']]);
        Indicador::verify($indicatorId, $mainId, (int) $admin['id']);
        expectSame((int) $admin['id'], (int) Indicador::findForTenant($indicatorId, $mainId)['verificado_por']);
        Indicador::update($indicatorId, $mainId, $indicatorData);
        expectSame(null, Indicador::findForTenant($indicatorId, $mainId)['verificado_por'], 'Editar debe reiniciar la verificación.');
        expectTrue(Indicador::delete($indicatorId, $mainId));

        $findingData = ['tenant_id' => $mainId, 'origen' => 'Inspeccion', 'descripcion' => 'Hallazgo CRUD', 'accion_correctiva' => 'Acción inicial', 'responsable' => 'Responsable', 'fecha_limite' => '2026-11-30', 'tipo_accion' => 'Correctiva', 'causa_raiz' => 'Causa raíz de prueba', 'recursos' => 'Recursos de prueba'];
        $findingId = Hallazgo::create($findingData);
        $blocked = false; try { Hallazgo::setStatus($findingId, $mainId, 'Cerrado'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'ACPA no debe cerrarse sin eficacia.');
        $findingData['accion_correctiva'] = 'Acción actualizada';
        expectTrue(Hallazgo::update($findingId, $mainId, $findingData));
        expectSame('Abierto', Hallazgo::findForTenant($findingId, $mainId)['estado']);
        expectSame(null, Hallazgo::findForTenant($findingId, $constructionId));
        Evidencia::create(['tenant_id' => $mainId, 'requisito_clave' => '', 'modulo' => 'sgsst', 'categoria' => 'Inspección', 'titulo' => 'Eficacia ACPA de prueba', 'fecha_evidencia' => '2026-11-20', 'entidad_tipo' => 'hallazgo', 'entidad_id' => (string) $findingId, 'entidad_referencia' => 'ACPA de prueba', 'nombre_original' => 'eficacia.pdf', 'nombre_almacenado' => 'eficacia-acpa-prueba.pdf', 'ruta_relativa' => 'uploads/tenant_'.$mainId.'/eficacia-acpa-prueba.pdf', 'mime' => 'application/pdf', 'extension' => 'pdf', 'tamano' => 68, 'hash_sha256' => hash('sha256', 'eficacia-acpa'), 'descripcion' => '', 'estado_revision' => 'Revisado', 'subido_por' => $admin['id']]);
        Hallazgo::verifyEfficacy($findingId, $mainId, ['eficacia_resultado' => 'No eficaz', 'eficacia_fecha' => '2026-11-20'], (int) $admin['id']);
        $blocked = false; try { Hallazgo::setStatus($findingId, $mainId, 'Cerrado'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Una acción no eficaz permanece abierta.');
        Hallazgo::verifyEfficacy($findingId, $mainId, ['eficacia_resultado' => 'Eficaz', 'eficacia_fecha' => '2026-11-21'], (int) $admin['id']);
        Hallazgo::setStatus($findingId, $mainId, 'Cerrado');
        expectSame('Cerrado', Hallazgo::findForTenant($findingId, $mainId)['estado']);
        $blocked = false; try { Hallazgo::update($findingId, $mainId, $findingData); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Un hallazgo cerrado no debe editarse sin reapertura.');
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
        expectSame(40, (int) $documentStats['total'], 'El perfil debe incluir el paquete de inicio ampliado y los tres documentos de limpieza.');
        expectSame(0, (int) $documentStats['aprobados']);
        foreach (['PR-LD-01', 'FT-LD-01', 'FT-LD-02'] as $code) {
            $cleaningDocument = Documento::findByCode($code, (int) $tenant['id']);
            expectTrue($cleaningDocument !== null && $cleaningDocument['estado'] === 'Borrador', 'Falta el borrador ' . $code . ' en Extintores SCM.');
        }
        foreach (['GUI-INI-01', 'MAN-SST-01', 'FT-COB-01', 'PR-SAL-01', 'PR-PSI-01', 'PR-ERG-01', 'PR-CCL-01', 'PR-EME-01', 'PR-EPP-01', 'GUI-VIO-01'] as $code) {
            $starterDocument = Documento::findByCode($code, (int) $tenant['id']);
            expectTrue($starterDocument !== null && $starterDocument['estado'] === 'Borrador', 'Falta el documento de inicio ' . $code . ' en Extintores SCM.');
        }
        expectSame(null, Documento::findByCode('PR-MAQ-01', (int) $tenant['id']), 'La caída de objetos en estanterías no debe activar un programa de maquinaria.');
        expectTrue(str_contains(Documento::findByCode('PR-CCL-01', (int) $tenant['id'])['contenido_html'], '65 días calendario'));
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

    $test('Catálogo de matriz: 60 ítems, 100 puntos y referencias separadas', static function (): void {
        $items = RequirementCatalog::autoevaluation();
        expectSame(60, count($items));
        expectSame(60, count(array_unique(array_column($items, 'item_codigo'))));
        expectSame(100.0, array_sum(array_column($items, 'puntaje_maximo')));
        expectSame(16, count(RequirementCatalog::legal()));
        expectSame(22, count(RequirementCatalog::iso()));
        foreach (['Planear' => 25.0, 'Hacer' => 60.0, 'Verificar' => 5.0, 'Actuar' => 10.0] as $cycle => $weight) {
            $actual = array_sum(array_map(static fn(array $row): float => $row['ciclo'] === $cycle ? (float) $row['puntaje_maximo'] : 0.0, $items));
            expectSame($weight, $actual, 'Peso del ciclo ' . $cycle);
        }
        expectSame(21, count(RequirementCatalog::applicableCodes(21)));
        expectSame(7, count(RequirementCatalog::applicableCodes(7)));
        foreach (['3.3.1','3.3.6','4.2.1','4.2.6'] as $code) expectTrue(in_array($code, array_column($items, 'item_codigo'), true));
        foreach (RequirementCatalog::iso() as $iso) expectSame(0.0, (float) $iso['puntaje_maximo']);
    });

    $test('Autoevaluación: aplicabilidad, evidencia y validación humana', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $year = 2026;
        $evaluation = Autoevaluacion::initialize($mainId, $year, $mainAdminId);
        $evaluationId = (int) $evaluation['id'];
        expectSame(21, (int) $evaluation['grupo']);
        expectSame(98, (int) Database::first('SELECT COUNT(*) AS n FROM requisitos_matriz WHERE autoevaluacion_id = ?', [$evaluationId])['n']);
        expectSame(0.0, (float) $evaluation['puntaje']);
        expectSame(60, (int) Database::first("SELECT COUNT(*) AS n FROM requisitos_matriz WHERE autoevaluacion_id = ? AND tipo = 'Autoevaluacion' AND evaluacion = 'Sin evaluar'", [$evaluationId])['n']);
        Autoevaluacion::confirmApplicability($evaluationId, $mainId, $mainAdminId);
        expectSame(39, (int) Database::first("SELECT COUNT(*) AS n FROM requisitos_matriz WHERE autoevaluacion_id = ? AND tipo = 'Autoevaluacion' AND evaluacion = 'No aplica' AND na_aprobado_por IS NOT NULL", [$evaluationId])['n']);
        expectSame(21, (int) Database::first("SELECT COUNT(*) AS n FROM requisitos_matriz WHERE autoevaluacion_id = ? AND tipo = 'Autoevaluacion' AND evaluacion = 'Sin evaluar'", [$evaluationId])['n']);
        $requirement = Database::first("SELECT * FROM requisitos_matriz WHERE autoevaluacion_id = ? AND item_codigo = '1.1.1'", [$evaluationId]);
        $id = (int) $requirement['id'];
        expectSame(null, Autoevaluacion::requirement($id, $constructionId), 'La matriz debe quedar aislada por tenant.');
        $input = array_intersect_key($requirement, array_flip(['responsable','periodicidad','fecha_vencimiento','nivel_madurez','evaluacion','justificacion_na','nota_implementacion','fecha_implementacion','nota_verificacion','eficacia_resultado','eficacia_fecha','accion_mejora','recursos']));
        $input['nivel_madurez'] = 'Documentado';
        $errors = Autoevaluacion::updateRequirement($id, $mainId, $input, $mainAdminId, true);
        expectTrue(isset($errors['nivel_madurez']), 'Documentado no puede declararse sin evidencia.');
        $evidence = evidenceTestData($mainId, 'matriz-1.1.1', 'sgsst', $mainAdminId);
        $evidence['entidad_tipo'] = 'matriz_requisito'; $evidence['entidad_id'] = (string) $id; $evidence['entidad_referencia'] = 'Matriz 1.1.1';
        $evidenceId = Evidencia::create($evidence);
        $input['nivel_madurez'] = 'Implementado'; $input['responsable'] = 'Responsable designado';
        $input['nota_implementacion'] = 'Designación comunicada y verificada en el centro.'; $input['fecha_implementacion'] = '2026-10-20';
        $errors = Autoevaluacion::updateRequirement($id, $mainId, $input, $mainAdminId, true);
        expectTrue(isset($errors['nivel_madurez']), 'Implementado exige revisión humana de la evidencia.');
        Evidencia::review($evidenceId, $mainId, 'Revisado', 'Evidencia pertinente revisada.', $mainAdminId);
        $input['evaluacion'] = 'Cumple';
        $errors = Autoevaluacion::updateRequirement($id, $mainId, $input, $mainAdminId, true);
        expectTrue(isset($errors['evaluacion']), 'Cumple no puede derivarse directamente de Implementado.');
        $input['nivel_madurez'] = 'Verificado'; $input['nota_verificacion'] = 'Se contrastó designación, curso y comunicación con la evidencia.';
        expectSame([], Autoevaluacion::updateRequirement($id, $mainId, $input, $mainAdminId, true));
        $updated = Autoevaluacion::requirement($id, $mainId);
        expectSame(0.5, (float) $updated['puntaje_obtenido']);
        expectSame($mainAdminId, (int) $updated['verificado_por']);
        Autoevaluacion::updateHeader($evaluationId, $mainId, ['fecha_evaluacion' => '2026-12-01', 'responsable' => 'Equipo SG-SST', 'remitida_arl_at' => '', 'registrada_sgrl_at' => '', 'radicado' => '']);
        expectTrue(Autoevaluacion::close($evaluationId, $mainId, $mainAdminId) !== [], 'El cierre debe bloquearse mientras existan ítems sin evaluar.');
        expectSame('Borrador', Autoevaluacion::findForTenant($mainId, $year)['estado']);
    });

    $test('Permisos explícitos: concesión, revocación y tenant', static function () use ($mainId, $constructionId, $mainAdminId, $mainHseId): void {
        expectTrue(Permission::has($mainAdminId, $mainId, Permission::MATRIX_VERIFY));
        expectTrue(!Permission::has($mainHseId, $mainId, Permission::MATRIX_VERIFY));
        Permission::grant($mainHseId, $mainId, Permission::MATRIX_VERIFY, $mainAdminId);
        expectTrue(Permission::has($mainHseId, $mainId, Permission::MATRIX_VERIFY));
        expectTrue(!Permission::has($mainHseId, $constructionId, Permission::MATRIX_VERIFY));
        Permission::sync($mainHseId, $mainId, [], $mainAdminId);
        expectTrue(!Permission::has($mainHseId, $mainId, Permission::MATRIX_VERIFY));
    });

    $test('Gestión operativa: ejecución, evidencia y verificación real', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $input = ['modulo' => 'responsables', 'tipo' => 'Designación', 'titulo' => 'Designación SG-SST de prueba', 'descripcion' => 'Registro controlado de prueba', 'responsable' => 'Responsable SST', 'recursos' => 'Tiempo y acceso', 'fecha_programada' => '2026-10-20', 'fecha_ejecucion' => '', 'fecha_vencimiento' => '2027-10-20', 'prioridad' => 'Alta', 'resultado' => '', 'requisito_clave' => '1.1.1', 'formacion' => 'Profesional con curso de 50 horas', 'licencia' => 'Licencia de prueba', 'curso_50h_fecha' => '2026-01-10', 'actualizacion_20h_fecha' => '', 'dedicacion' => 'Tiempo parcial con autoridad', 'centro' => 'Sede principal', 'evaluacion_competencia' => 'Pendiente de evaluación'];
        $id = Operation::create($mainId, $input, $mainAdminId);
        expectSame('Pendiente', Operation::findForTenant($id, $mainId)['estado']);
        expectSame(null, Operation::findForTenant($id, $constructionId));
        $blocked = false; try { Operation::setStatus($id, $mainId, 'Completado', $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'No debe completarse sin fecha y resultado.');
        $input['fecha_ejecucion'] = '2026-10-21'; $input['resultado'] = 'Designación realizada y comunicada.';
        Operation::update($id, $mainId, $input, $mainAdminId);
        Operation::setStatus($id, $mainId, 'Completado', $mainAdminId);
        $blocked = false; try { Operation::setStatus($id, $mainId, 'Verificado', $mainAdminId, 'Revisión de prueba'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Verificado exige evidencia revisada.');
        $evidence = evidenceTestData($mainId, 'operacion-responsable', 'sgsst', $mainAdminId);
        $evidence['entidad_tipo'] = 'operacion'; $evidence['entidad_id'] = (string) $id; $evidence['entidad_referencia'] = 'Designación SG-SST';
        $evidenceId = Evidencia::create($evidence); Evidencia::review($evidenceId, $mainId, 'Revisado', 'Soporte revisado.', $mainAdminId);
        Operation::setStatus($id, $mainId, 'Verificado', $mainAdminId, 'Se verificó identidad, contenido y fecha del soporte.');
        $record = Operation::findForTenant($id, $mainId);
        expectSame('Verificado', $record['estado']); expectSame($mainAdminId, (int) $record['verificado_por']);
        $blocked = false; try { Operation::update($id, $mainId, $input, $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Un registro verificado debe quedar inmutable.');
        expectSame('/operacion-registros/' . $id . '/editar', EvidenceLinker::url(['entidad_tipo' => 'operacion', 'entidad_id' => (string) $id]));
    });

    $test('Salud reservada: conceptos, seguimiento y aislamiento', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $employee = Empleado::forTenant($mainId)[0];
        $id = Salud::create($mainId, ['empleado_id' => $employee['id'], 'tipo' => 'Concepto medico-laboral', 'fecha' => '2026-10-20', 'proxima_fecha' => '2027-01-20', 'entidad' => 'Entidad de prueba', 'profesional' => 'Profesional de prueba', 'concepto' => 'Concepto sintético con adaptación laboral.', 'restricciones' => 'Restricción de prueba', 'recomendaciones' => 'Adaptación de puesto', 'seguimiento' => '', 'soporte_referencia' => 'SOP-RES-1'], $mainAdminId);
        expectSame(null, Salud::findForTenant($id, $constructionId));
        expectSame('En seguimiento', Salud::findForTenant($id, $mainId)['estado']);
        $blocked = false; try { Salud::setStatus($id, $mainId, 'Cerrado', '', $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'No debe cerrarse sin seguimiento.');
        Salud::setStatus($id, $mainId, 'Cerrado', 'Se verificó la adaptación y se cerró el seguimiento de prueba.', $mainAdminId);
        expectSame('Cerrado', Salud::findForTenant($id, $mainId)['estado']);
        $aggregate = Salud::aggregate($mainId); expectTrue($aggregate !== []);
        expectSame(['tipo','estado','cantidad'], array_keys($aggregate[0]), 'La estadística no debe incluir personas ni conceptos.');
    });

    $test('Psicosocial: resultados agregados y eficacia con soporte reservado', static function () use ($mainId, $mainAdminId): void {
        $id = Psicosocial::create($mainId, ['vigencia' => 2026, 'poblacion' => 'Grupo sintético de prueba', 'instrumento' => 'Bateria Resolucion 2764 de 2022 · estructura sin alteraciones', 'fecha_aplicacion' => '2026-10-15', 'profesional' => 'Profesional de prueba', 'plan_intervencion' => '', 'proxima_fecha' => ''], $mainAdminId);
        expectSame('Planificada', Psicosocial::findForTenant($id, $mainId)['estado']); expectSame(0, (int) Psicosocial::findForTenant($id, $mainId)['participantes']);
        Psicosocial::recordApplication($id, $mainId, ['participantes' => 12, 'resultado_intralaboral' => 'Resultado colectivo de prueba', 'resultado_extralaboral' => '', 'resultado_estres' => '', 'interpretacion_agregada' => 'Interpretación profesional agregada de prueba.', 'plan_intervencion' => 'Intervención organizacional de prueba.'], $mainAdminId);
        Psicosocial::saveIntervention($id, $mainId, 'Ajustes organizacionales, sensibilización y canalización de prueba.', $mainAdminId);
        $blocked = false; try { Psicosocial::evaluate($id, $mainId, 'Mejora agregada de prueba.', '2027-01-15', $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La eficacia requiere soporte reservado revisado.');
        $beforeGeneral = (int) Database::first('SELECT COUNT(*) AS n FROM soportes_cumplimiento')['n'];
        Database::execute("INSERT INTO soportes_reservados (tenant_id,area,entidad_tipo,entidad_id,categoria,titulo,nombre_original,nombre_almacenado,ruta_relativa,mime,extension,tamano,hash_sha256,subido_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)", [$mainId,'salud','psicosocial',$id,'Informe agregado','Soporte sintético','agregado.pdf',str_repeat('a',32).'.pdf','reserved/tenant_'.$mainId.'/'.str_repeat('a',32).'.pdf','application/pdf','pdf',68,hash('sha256','psicosocial'),$mainAdminId]);
        $supportId = Database::lastId();
        expectSame($beforeGeneral, (int) Database::first('SELECT COUNT(*) AS n FROM soportes_cumplimiento')['n'], 'El soporte reservado no debe aparecer en la biblioteca general.');
        expectSame(null, SoporteReservado::findForTenant($supportId, 999999));
        SoporteReservado::review($supportId, $mainId, 'salud', $mainAdminId);
        Psicosocial::evaluate($id, $mainId, 'Mejora agregada verificada en el grupo de prueba.', '2027-01-15', $mainAdminId);
        Psicosocial::close($id, $mainId, $mainAdminId);
        expectSame('Cerrada', Psicosocial::findForTenant($id, $mainId)['estado']);
    });

    $test('Convivencia reservada: medidas, CCL y estadística anónima', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $id = Convivencia::createCase($mainId, ['tipo' => 'Otra', 'fecha_recepcion' => '2026-10-20', 'canal' => 'Canal reservado de prueba', 'descripcion' => 'Caso sintético para validar confidencialidad.', 'personas_involucradas' => 'Persona A y Persona B', 'medidas_proteccion' => '', 'encaminamiento' => '', 'responsable_id' => $mainAdminId, 'fecha_compromiso' => '2026-10-25'], $mainAdminId);
        expectSame(null, Convivencia::case($id, $constructionId));
        $blocked = false; try { Convivencia::updateCase($id, $mainId, ['estado' => 'Medidas activas', 'medidas_proteccion' => '', 'encaminamiento' => '', 'responsable_id' => $mainAdminId, 'fecha_compromiso' => '2026-10-25', 'cierre_motivo' => ''], $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Medidas activas exige medidas documentadas.');
        Convivencia::updateCase($id, $mainId, ['estado' => 'Medidas activas', 'medidas_proteccion' => 'Medidas de protección y no revictimización de prueba.', 'encaminamiento' => '', 'responsable_id' => $mainAdminId, 'fecha_compromiso' => '2026-10-25', 'cierre_motivo' => ''], $mainAdminId);
        $meeting = Convivencia::scheduleMeeting($mainId, ['tipo' => 'Ordinaria mensual', 'fecha' => '2026-10-25', 'participantes' => '', 'agenda' => 'Seguimiento general sin divulgar casos', 'proxima_fecha' => '2026-11-25'], $mainAdminId);
        $outcome = ['estado' => 'Realizada', 'participantes' => 'Miembros CCL de prueba', 'compromisos' => 'Seguimiento trimestral de prueba.', 'acta_referencia' => 'CCL-ACTA-PRUEBA', 'proxima_fecha' => '2026-11-25'];
        $blocked = false; try { Convivencia::recordMeeting($meeting, $mainId, $outcome, $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'El quórum CCL debe confirmarse.');
        Convivencia::recordMeeting($meeting, $mainId, $outcome + ['quorum_verificado' => '1'], $mainAdminId);
        expectSame('Realizada', Convivencia::meeting($meeting, $mainId)['estado']);
        $aggregate = Convivencia::aggregate($mainId); expectTrue($aggregate !== []); expectSame(['tipo','estado','cantidad'], array_keys($aggregate[0]));
    });

    $test('COPASST: miembros, reunión y quórum confirmado', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $id = Comite::create($mainId, ['tipo' => 'COPASST', 'centro_trabajo' => 'Centro de prueba', 'fecha_constitucion' => '2026-01-15', 'periodo' => '2026-2028', 'quorum_minimo' => 51], $mainAdminId);
        expectSame(null, Comite::findForTenant($id, $constructionId));
        $employee = Empleado::forTenant($mainId)[0];
        $member1 = Comite::addMember($id, $mainId, ['empleado_id' => $employee['id'], 'nombre' => '', 'documento' => '', 'representacion' => 'Trabajadores', 'condicion' => 'Principal', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '', 'capacitacion_horas' => 2], $mainAdminId);
        $member2 = Comite::addMember($id, $mainId, ['empleado_id' => 0, 'nombre' => 'Representante empleador de prueba', 'documento' => 'E-1', 'representacion' => 'Empleador', 'condicion' => 'Principal', 'fecha_inicio' => '2026-01-15', 'fecha_fin' => '', 'capacitacion_horas' => 2], $mainAdminId);
        expectSame(['empleador' => 1, 'trabajadores' => 1, 'activos' => 2], Comite::composition($id, $mainId));
        $meeting = Comite::scheduleMeeting($id, $mainId, ['tipo' => 'Ordinaria', 'fecha' => '2026-10-25', 'lugar' => 'Sala de prueba', 'orden_dia' => 'Revisión de indicadores y compromisos', 'proxima_reunion' => '2026-11-25'], $mainAdminId);
        expectSame('Programada', Comite::meeting($meeting, $id, $mainId)['estado']);
        $outcome = ['estado' => 'Realizada', 'asistentes' => [$member1, $member2], 'desarrollo' => 'Se revisaron los temas del orden del día.', 'compromisos' => 'Control de prueba con responsable.', 'acta_referencia' => 'ACTA-PRUEBA-001', 'proxima_reunion' => '2026-11-25'];
        $blocked = false; try { Comite::recordMeeting($meeting, $id, $mainId, $outcome, $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La asistencia debe confirmarse humanamente.');
        Comite::recordMeeting($meeting, $id, $mainId, $outcome + ['confirmar_asistencia' => '1'], $mainAdminId);
        $recorded = Comite::meeting($meeting, $id, $mainId);
        expectSame('Realizada', $recorded['estado']); expectSame(100.0, (float) $recorded['quorum_porcentaje']); expectSame(1, (int) $recorded['quorum_cumple']);
        expectSame('/comites/' . $id . '#reunion-' . $meeting, EvidenceLinker::url(['entidad_tipo' => 'comite_reunion', 'entidad_id' => (string) $meeting, 'tenant_id' => $mainId]));
    });

    $test('Eventos SST: investigación, causas, testigos y eficacia separada', static function () use ($mainId, $constructionId, $mainAdminId): void {
        $employee = Empleado::forTenant($mainId)[0];
        $input = ['tipo' => 'Accidente de trabajo', 'fecha_evento' => '2026-10-20', 'fecha_reporte' => '2026-10-21', 'lugar' => 'Taller de prueba', 'proceso' => 'Mantenimiento', 'descripcion' => 'Evento sintético para validar el flujo de investigación.', 'lesion' => 'Lesión leve registrada para prueba', 'gravedad' => 'Leve', 'dias_perdidos' => 3, 'dias_cargados' => 0, 'reportado_arl_at' => '', 'reportado_eps_at' => '', 'reportado_mintrabajo_at' => '', 'radicados' => '', 'fecha_limite_investigacion' => '2026-11-05', 'responsable' => 'Responsable SST', 'afectado_empleado_id' => $employee['id'], 'afectado_nombre' => '', 'afectado_documento' => '', 'afectado_vinculacion' => ''];
        $beforeFindings = (int) Database::first('SELECT COUNT(*) AS n FROM hallazgos_acpa WHERE tenant_id = ?', [$mainId])['n'];
        $id = Evento::create($mainId, $input, $mainAdminId);
        expectSame('Reportado', Evento::findForTenant($id, $mainId)['estado']);
        expectSame(null, Evento::findForTenant($id, $constructionId));
        expectSame($beforeFindings, (int) Database::first('SELECT COUNT(*) AS n FROM hallazgos_acpa WHERE tenant_id = ?', [$mainId])['n'], 'El evento no debe mezclarse con hallazgos ACPA.');
        expectSame(1, count(Evento::people($id, $mainId)));
        Evento::addPerson($id, $mainId, ['empleado_id' => 0, 'nombre' => 'Testigo de prueba', 'documento' => 'T-1', 'vinculacion' => 'Contratista', 'rol' => 'Testigo', 'declaracion' => 'Declaración sintética.'], $mainAdminId);
        Evento::saveInvestigation($id, $mainId, ['metodologia' => 'Resolucion 1401 de 2007', 'equipo' => 'Responsable SST y COPASST', 'fecha_inicio' => '2026-10-22', 'causas_inmediatas' => 'Condición y acto identificados', 'causas_basicas' => 'Factores personales y de trabajo', 'causas_administrativas' => '', 'analisis' => 'Análisis de los hechos y controles.', 'conclusiones' => 'Se requieren controles del sistema.'], $mainAdminId);
        $blocked = false; try { Evento::closeInvestigation($id, $mainId, $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La investigación no debe cerrarse sin causas administrativas.');
        Evento::saveInvestigation($id, $mainId, ['metodologia' => 'Resolucion 1401 de 2007', 'equipo' => 'Responsable SST y COPASST', 'fecha_inicio' => '2026-10-22', 'causas_inmediatas' => 'Condición y acto identificados', 'causas_basicas' => 'Factores personales y de trabajo', 'causas_administrativas' => 'Falta de verificación del control', 'analisis' => 'Análisis de los hechos y controles.', 'conclusiones' => 'Se requieren controles del sistema.'], $mainAdminId);
        Evento::closeInvestigation($id, $mainId, $mainAdminId);
        expectSame('Investigado', Evento::findForTenant($id, $mainId)['estado']);
        $actionId = Evento::addAction($id, $mainId, ['tipo' => 'Correctiva', 'descripcion' => 'Implantar y verificar el control definido.', 'responsable' => 'Jefe de mantenimiento', 'recursos' => 'Horas y elementos técnicos', 'fecha_compromiso' => '2026-11-10'], $mainAdminId);
        $blocked = false; try { Evento::setActionStatus($actionId, $id, $mainId, 'Implementada', [], $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'Implementada requiere fecha real.');
        Evento::setActionStatus($actionId, $id, $mainId, 'Implementada', ['fecha_ejecucion' => '2026-11-08'], $mainAdminId);
        $blocked = false; try { Evento::setActionStatus($actionId, $id, $mainId, 'Eficaz', ['eficacia_fecha' => '2026-11-20', 'eficacia_resultado' => 'Control verificado sin recurrencia.'], $mainAdminId); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La eficacia requiere evidencia revisada.');
        $evidence = evidenceTestData($mainId, 'evento-accion', 'sgsst', $mainAdminId); $evidence['entidad_tipo'] = 'evento_accion'; $evidence['entidad_id'] = (string) $actionId; $evidence['entidad_referencia'] = 'Acción correctiva AT';
        $evidenceId = Evidencia::create($evidence); Evidencia::review($evidenceId, $mainId, 'Revisado', 'Soporte de eficacia revisado.', $mainAdminId);
        Evento::setActionStatus($actionId, $id, $mainId, 'Eficaz', ['eficacia_fecha' => '2026-11-20', 'eficacia_resultado' => 'Control verificado sin recurrencia.'], $mainAdminId);
        Evento::close($id, $mainId, $mainAdminId);
        expectSame('Cerrado', Evento::findForTenant($id, $mainId)['estado']);
        expectSame(3, Evento::stats($mainId, 2026)['dias_perdidos']);
        expectSame('/eventos/' . $id . '#acciones', EvidenceLinker::url(['entidad_tipo' => 'evento_accion', 'entidad_id' => (string) $actionId, 'tenant_id' => $mainId]));
    });

    $test('Migración idempotente e integridad del esquema ampliado', static function (): void {
        Database::initialize();
        expectSame([], Database::query('PRAGMA foreign_key_check'));
        expectSame(51, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
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
        expectSame(5, SchemaMigrator::VERSION);
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

    $test('Complementación de empresa existente: actualiza sólo lo automático y es idempotente', static function (): void {
        $tenant = tenantByNit('39626936-8');
        $tenantId = (int) $tenant['id'];
        $codes = array_column(EstandarMinimo::byGroup(7), 'codigo');
        [$legacyCode, $editedCode, $approvedCode] = [$codes[1], $codes[2], $codes[3]];
        $notice = 'documento base generado a partir de la clasificación registrada';
        Database::execute("UPDATE documentos_sgsst SET contenido_html = ?, estado = 'Borrador', version = 1 WHERE tenant_id = ? AND codigo_estandar = ?", ['<article class="sst-document"><p>Contenido genérico histórico.</p><p>' . $notice . '</p></article>', $tenantId, $legacyCode]);
        Database::execute("UPDATE documentos_sgsst SET contenido_html = ?, estado = 'Borrador', version = 2 WHERE tenant_id = ? AND codigo_estandar = ?", ['<article class="sst-document"><p>Edición protegida del usuario.</p><p>' . $notice . '</p></article>', $tenantId, $editedCode]);
        Database::execute("UPDATE documentos_sgsst SET contenido_html = ?, estado = 'Aprobado', version = 1 WHERE tenant_id = ? AND codigo_estandar = ?", ['<article class="sst-document"><p>Aprobación protegida del usuario.</p><p>' . $notice . '</p></article>', $tenantId, $approvedCode]);
        $guide = Documento::findByCode('GUI-INI-01', $tenantId);
        Documento::deleteForTenant((int) $guide['id'], $tenantId);
        $otherCounts = [];
        foreach (['plan_trabajo', 'capacitaciones', 'peligros_ipevr', 'perfiles_riesgo'] as $table) {
            $otherCounts[$table] = (int) Database::first("SELECT COUNT(*) AS n FROM {$table} WHERE tenant_id = ?", [$tenantId])['n'];
        }
        $result = DocumentGenerator::complement($tenantId);
        expectSame(1, $result['created'], 'Debe restaurar únicamente la guía faltante.');
        expectSame(1, $result['updated'], 'Sólo el borrador automático histórico debe actualizarse.');
        expectSame(40, count(Documento::forTenant($tenantId)));
        expectTrue(str_contains(Documento::findByCode($legacyCode, $tenantId)['contenido_html'], 'Procedimiento de implementación'));
        expectSame(2, (int) Documento::findByCode($legacyCode, $tenantId)['version']);
        expectTrue(str_contains(Documento::findByCode($editedCode, $tenantId)['contenido_html'], 'Edición protegida del usuario.'));
        expectTrue(str_contains(Documento::findByCode($approvedCode, $tenantId)['contenido_html'], 'Aprobación protegida del usuario.'));
        expectSame('Aprobado', Documento::findByCode($approvedCode, $tenantId)['estado']);
        foreach ($otherCounts as $table => $count) {
            expectSame($count, (int) Database::first("SELECT COUNT(*) AS n FROM {$table} WHERE tenant_id = ?", [$tenantId])['n'], 'La complementación no debe repetir registros de ' . $table);
        }
        $versions = (int) Database::first('SELECT COUNT(*) AS n FROM documento_versiones WHERE tenant_id = ?', [$tenantId])['n'];
        expectSame(['created' => 0, 'updated' => 0], array_intersect_key(DocumentGenerator::complement($tenantId), ['created' => 1, 'updated' => 1]));
        expectSame($versions, (int) Database::first('SELECT COUNT(*) AS n FROM documento_versiones WHERE tenant_id = ?', [$tenantId])['n']);
    });

    $test('Exportación editable: DOCX y paquete ZIP sin ZipArchive', static function () use ($mainId): void {
        $document = Documento::findByCode('FT-IPEVR-01', $mainId);
        $download = DocumentPackageExporter::document($document);
        if ($download['format'] === 'docx') {
            expectTrue(str_starts_with($download['content'], "PK\x03\x04"), 'El DOCX debe ser un contenedor OOXML válido.');
            expectSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $download['mime']);
        } else {
            expectTrue(str_contains($download['content'], '<table>'), 'La alternativa HTML debe conservar las tablas.');
        }
        $package = DocumentPackageExporter::package(Tenant::find($mainId), [$document]);
        expectTrue(str_starts_with($package['content'], "PK\x03\x04"));
        expectTrue(strlen($package['content']) > 2000);
        expectSame('zip', $package['format']);
    });

    $test('Paquete de inicio: auditoría de 21 actividades por 3 regímenes', static function (): void {
        $required = ['GUI-INI-01', 'MAN-SST-01', 'FT-COB-01', 'FT-RES-01', 'FT-AFI-01', 'FT-CAP-01', 'FT-CAP-02', 'FT-PAT-01', 'FT-IPEVR-01', 'PR-SAL-01', 'FT-SAL-01', 'PR-PSI-01', 'FT-PSI-01', 'PR-ERG-01', 'FT-ERG-01', 'PR-CCL-01', 'FT-CCL-01', 'PR-EME-01', 'FT-EME-01', 'FT-EME-02', 'PR-EPP-01', 'FT-EPP-01', 'FT-EPP-02', 'FT-AST-01', 'PR-LD-01', 'FT-LD-01', 'FT-LD-02'];
        $conditional = [
            '0113' => [['PR-QUI-01', 'PR-AGR-01', 'PR-AGF-01'], ['PR-BIO-01', 'PR-ZOO-01']],
            '0141' => [['PR-ZOO-01', 'PR-AGR-01', 'PR-MAQ-01'], ['PR-BIO-01']],
            '1071' => [['PR-QUI-01', 'PR-ALM-01', 'PR-MAQ-01'], ['PR-BIO-01']],
            '1410' => [['PR-AGF-01', 'PR-MAQ-01'], ['PR-QUI-01']],
            '2220' => [['PR-QUI-01', 'PR-AGF-01', 'PR-MAQ-01'], ['PR-BIO-01']],
            '2511' => [['PR-QUI-01', 'PR-AGF-01', 'PR-MAQ-01', 'PR-IZA-01', 'PR-SOL-01'], ['PR-BIO-01']],
            '4100' => [['PR-ALT-01', 'PR-AGF-01', 'PR-MAQ-01', 'PR-IZA-01'], ['PR-PMT-01', 'PR-QUI-01']],
            '4210' => [['PR-PMT-01', 'PR-MAQ-01', 'PR-IZA-01'], ['PR-VIA-01']],
            '4321' => [['PR-ELE-01', 'PR-ALT-01'], ['PR-MAQ-01']],
            '4711' => [['GUI-VIO-01'], ['PR-QUI-01', 'PR-MAQ-01']],
            '4759' => [['GUI-VIO-01'], ['PR-MAQ-01', 'PR-TEC-01']],
            '4923' => [[PesvLibrary::PLAN_CODE], ['PR-MAQ-01', 'PR-QUI-01']],
            '5210' => [['PR-MAQ-01', 'PR-IZA-01'], ['PR-ALT-01']],
            '5511' => [['PR-QUI-01', 'GUI-VIO-01'], ['PR-ALM-01']],
            '5610' => [['PR-ALM-01', 'GUI-VIO-01'], ['PR-QUI-01']],
            '6201' => [['PR-TEC-01'], ['PR-QUI-01', 'GUI-VIO-01']],
            '7020' => [['PR-TEC-01', 'PR-VIA-01'], ['PR-PMT-01']],
            '8530' => [['PR-TEC-01', 'PR-EDU-01', 'GUI-VIO-01'], ['PR-QUI-01']],
            '8621' => [['PR-BIO-01', 'GUI-VIO-01'], ['PR-ZOO-01']],
            '8610' => [['PR-BIO-01', 'GUI-VIO-01'], ['PR-ZOO-01']],
            '8121' => [['PR-QUI-01'], ['PR-MAQ-01', 'GUI-VIO-01']],
        ];
        $activities = ActividadEconomica::all(true);
        expectSame(21, count($activities));
        expectSame(array_keys($conditional), array_values(array_intersect(array_keys($conditional), array_column($activities, 'codigo_ciuu'))));
        $generatedTotal = 0;
        $profiles = 0;
        $standardResource = StandardDocumentTemplate::resource();
        foreach ($activities as $activity) {
            $ciiu = $activity['codigo_ciuu'];
            foreach ([7, 21, 60] as $group) {
                $tenantId = Tenant::create([
                    'razonsocial' => 'Auditoría ' . $ciiu . ' / ' . $group,
                    'nit' => '8' . str_pad((string) $activity['id'], 3, '0', STR_PAD_LEFT) . $group,
                    'sector_economico' => $activity['sector'], 'actividad_economica_id' => (int) $activity['id'],
                    'numero_trabajadores' => match ($group) { 7 => 10, 21 => 30, default => 60 },
                    'nivel_riesgo' => match ($group) { 7 => 'II', 21 => 'III', default => 'V' },
                    'grupo_estandares' => $group, 'metodo_configuracion' => 'Combinado', 'plan_id' => null,
                    'representante_legal' => 'Responsable de prueba', 'correo_contacto' => 'auditoria@example.com',
                    'telefono' => '', 'ciudad' => 'Bogotá', 'direccion' => '',
                ]);
                $result = DocumentGenerator::generate($tenantId);
                $documents = Documento::forTenant($tenantId);
                $codes = array_column($documents, 'codigo_estandar');
                expectSame(count($codes), count(array_unique($codes)), 'Códigos duplicados en CIIU ' . $ciiu . ' / ' . $group);
                expectSame($result['documentos'], count($documents));
                expectSame($group, (int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst d WHERE d.tenant_id = ? AND EXISTS (SELECT 1 FROM estandares_minimos e WHERE e.codigo = d.codigo_estandar AND e.grupo = ?)', [$tenantId, $group])['n']);
                if ($profiles < 3) {
                    foreach (EstandarMinimo::byGroup($group) as $standard) {
                        $code = $standard['codigo'];
                        if (in_array($code, ['M21-07', '2.1.1'], true)) continue;
                        $topic = $standardResource['topics'][$standardResource['standard_map'][$code]];
                        $standardDocument = Documento::findByCode($code, $tenantId);
                        expectTrue(str_contains($standardDocument['contenido_html'], e($topic['focus'])), 'Estándar sin tema específico: ' . $code);
                        expectTrue(!str_contains($standardDocument['contenido_html'], 'Desarrollo y control del proceso'), 'Estándar genérico persistente: ' . $code);
                    }
                }
                foreach (array_merge($required, ['PR-ACT-' . $ciiu, 'FT-ACT-' . $ciiu]) as $code) {
                    expectTrue(in_array($code, $codes, true), 'Falta ' . $code . ' en CIIU ' . $ciiu . ' / ' . $group);
                }
                expectSame($group >= 21, in_array('FT-INC-02', $codes, true), 'Alcance incorrecto del formato de investigación.');
                expectSame($group === 60, in_array('FT-AUD-01', $codes, true), 'Alcance incorrecto del formato de auditoría.');
                foreach ($conditional[$ciiu][0] as $code) expectTrue(in_array($code, $codes, true), 'Falta anexo ' . $code . ' en CIIU ' . $ciiu);
                foreach ($conditional[$ciiu][1] as $code) expectTrue(!in_array($code, $codes, true), 'Anexo inaplicable ' . $code . ' en CIIU ' . $ciiu);
                foreach ($documents as $document) {
                    expectSame('Borrador', $document['estado']);
                    expectTrue(str_contains($document['contenido_html'], '<article class="sst-document">'), 'Documento no visual: ' . $document['codigo_estandar']);
                    expectTrue(!str_contains($document['contenido_html'], 'checked='), 'No se deben precargar verificaciones.');
                }
                $coverage = Documento::findByCode('FT-COB-01', $tenantId);
                expectTrue(str_contains($coverage['contenido_html'], '☐ Ejecutado') && str_contains($coverage['contenido_html'], '☐ Adaptado'));
                expectTrue(str_contains(Documento::findByCode('PR-CCL-01', $tenantId)['contenido_html'], '65 días calendario'));
                if ($group >= 21) expectTrue(str_contains(Documento::findByCode('FT-INC-02', $tenantId)['contenido_html'], '15 días'));
                $generatedTotal += count($documents);
                $profiles++;
            }
        }
        expectSame(63, $profiles);
        expectTrue($generatedTotal > 4000, 'La cobertura total debe superar 4.000 documentos contextualizados.');
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
    file_put_contents('php://stderr', "Error al preparar las pruebas: {$exception->getMessage()}\n{$exception->getTraceAsString()}\n");
    exit(1);
}
