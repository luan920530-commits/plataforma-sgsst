<?php
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli', 'wasm'], true)) {
    http_response_code(404);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
$database = BASE_PATH . '/database/profile-testing.db';
$clean = static function () use ($database): void {
    foreach ([$database, $database . '-wal', $database . '-shm'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
};
$clean();
putenv('SGSST_DB_PATH=' . $database);
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Models\IvcActa;
use App\Models\IvcRequerimiento;
use App\Models\SanitarioRegistro;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ExtintoresScmSeeder;
use App\Services\Installer;
use App\Support\Auth;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $GLOBALS['config']['company_seed'] = ExtintoresScmSeeder::PROFILE;
    $checks = Installer::requirements();
    $profileChecks = array_values(array_filter($checks, static fn(array $check): bool => $check['label'] === 'Perfil Extintores SCM'));
    $assert(count($profileChecks) === 1 && $profileChecks[0]['ok'], 'El instalador no reconoce el recurso del perfil.');
    $result = Installer::installWithSuperAdmin('super@profile.test', 'SuperClave2026!', 'SuperAdmin de prueba');
    $assert($result['created'], 'No se creó el SuperAdmin.');
    $assert($result['company']['created'], 'No se creó la empresa del perfil.');
    $tenant = Tenant::find((int) $result['company']['tenant_id']);
    $assert($tenant !== null && $tenant['nit'] === '39626936-8', 'La empresa precargada no coincide.');
    $assert(IvcActa::findForTenant((int) $result['company']['acta_id'], (int) $tenant['id']) !== null, 'Falta el acta instalada.');
    $requirements = IvcRequerimiento::forActa((int) $result['company']['acta_id'], (int) $tenant['id']);
    $assert(count($requirements) === 28, 'La instalación no creó los 28 seguimientos.');
    foreach ($requirements as $requirement) {
        $assert($requirement['estado'] === 'Pendiente' && $requirement['verificacion'] === '', 'La instalación cerró o verificó un seguimiento.');
    }
    $assert(count(SanitarioRegistro::forTenant((int) $tenant['id'])) === 7, 'Las validaciones iniciales no coinciden.');
    $documentStats = Database::first("SELECT COUNT(*) AS total, SUM(CASE WHEN estado = 'Borrador' THEN 1 ELSE 0 END) AS borradores FROM documentos_sgsst WHERE tenant_id = ?", [(int) $tenant['id']]);
    $assert((int) $documentStats['total'] === 40 && (int) $documentStats['borradores'] === 40, 'La instalación debe crear 40 borradores, sin aprobaciones automáticas.');
    $cleaningDocuments = Database::first("SELECT COUNT(*) AS n FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar IN ('PR-LD-01', 'FT-LD-01', 'FT-LD-02')", [(int) $tenant['id']]);
    $assert((int) $cleaningDocuments['n'] === 3, 'Faltan el programa o los formatos de limpieza y desinfección.');
    $starterDocuments = Database::first("SELECT COUNT(*) AS n FROM documentos_sgsst WHERE tenant_id = ? AND codigo_estandar IN ('GUI-INI-01', 'MAN-SST-01', 'FT-COB-01', 'PR-CCL-01', 'PR-EME-01', 'PR-EPP-01', 'GUI-VIO-01')", [(int) $tenant['id']]);
    $assert((int) $starterDocuments['n'] === 7, 'Faltan documentos del paquete de inicio de Extintores SCM.');
    $access = $result['company']['access'];
    $assert(Auth::attempt($access['email'], $access['password']), 'El acceso temporal no autentica.');
    $assert(Auth::check() && !empty(Auth::user()['must_change_password']), 'El acceso temporal no exige renovación.');
    $userId = Auth::id();
    User::updatePassword($userId, password_hash('ClaveDefinitiva2027!', PASSWORD_DEFAULT));
    $assert((int) User::find($userId)['must_change_password'] === 0, 'El cambio explícito no retiró la renovación obligatoria.');
    $before = (int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n'];
    $second = Installer::installWithSuperAdmin('otro@profile.test', 'OtraClave2027!', 'Otro SuperAdmin');
    $assert(!$second['created'], 'La segunda instalación debería estar bloqueada.');
    $assert((int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n'] === $before, 'La segunda instalación duplicó la empresa.');
    $assert(Database::query('PRAGMA foreign_key_check') === [], 'Hay violaciones de llaves foráneas.');
    echo "Instalación Extintores SCM verificada: SuperAdmin, empresa, acta, 28 pendientes, 7 validaciones, 40 borradores (incluidos paquete de inicio y PR/FT-LD), acceso temporal e idempotencia.\n";
} catch (Throwable $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n");
    $clean();
    exit(1);
}
$clean();
