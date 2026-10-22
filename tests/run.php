<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
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
use App\Models\Autoreporte;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\Induccion;
use App\Models\Peligro;
use App\Models\PermisoTrabajo;
use App\Models\PlanEmergencia;
use App\Models\ProgramaActividad;
use App\Models\ProgramaPrevencion;
use App\Models\RequisitoSeguimiento;
use App\Models\SimulacroEmergencia;
use App\Models\SoporteCumplimiento;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\ComplianceService;
use App\Services\DemoSeeder;
use App\Services\DocumentGenerator;
use App\Services\EvidenceStorage;
use App\Services\Installer;
use App\Services\OperationalDocument;
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

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void { $tests[$name] = $callback; };

try {
    Installer::run();
    DemoSeeder::seed();
    $main = tenantByNit('901.245.780-3');
    $construction = tenantByNit('900.812.447-1');
    $bakery = tenantByNit('901.552.019-8');
    $mainId = (int) $main['id'];
    $constructionId = (int) $construction['id'];

    $test('Esquema, claves foráneas y catálogos', static function (): void {
        expectSame(25, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
        expectSame(1, (int) Database::first('PRAGMA foreign_keys')['foreign_keys']);
        expectSame([], Database::query('PRAGMA foreign_key_check'));
        expectSame(20, (int) Database::first('SELECT COUNT(*) AS n FROM actividades_economicas')['n']);
        expectTrue((int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL")['n'] >= 20);
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

    $test('Detalle de autoreporte con aislamiento', static function () use ($mainId, $constructionId): void {
        $row = Database::first('SELECT id FROM autoreportes WHERE tenant_id = ? LIMIT 1', [$mainId]);
        $detail = Autoreporte::findDetailedForTenant((int) $row['id'], $mainId);
        expectTrue($detail !== null && !empty($detail['reportante']) && !empty($detail['email']));
        expectSame(null, Autoreporte::findDetailedForTenant((int) $row['id'], $constructionId));
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
        SoporteCumplimiento::delete($supportId, $mainId);
    });

    $test('Rutas privadas de evidencias y tipos permitidos', static function () use ($mainId): void {
        expectSame('application/pdf', EvidenceStorage::mime('pdf'));
        expectSame('image/png', EvidenceStorage::mime('png'));
        $blocked = false;
        try { EvidenceStorage::path($mainId, '../database/sgsst.db'); } catch (DomainException) { $blocked = true; }
        expectTrue($blocked, 'La ruta manipulada debía rechazarse.');
        expectSame(10485760, EvidenceStorage::MAX_BYTES);
    });

    $test('Migración idempotente e integridad del esquema ampliado', static function (): void {
        Database::initialize();
        expectSame([], Database::query('PRAGMA foreign_key_check'));
        expectSame(25, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
        expectSame(20, (int) Database::first('SELECT COUNT(*) AS n FROM actividades_economicas')['n']);
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
