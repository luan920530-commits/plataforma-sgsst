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
use App\Models\Documento;
use App\Models\Induccion;
use App\Models\User;
use App\Services\ClassificationService;
use App\Services\DemoSeeder;
use App\Services\DocumentGenerator;
use App\Services\Installer;
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
        expectSame(17, (int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")['n']);
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
