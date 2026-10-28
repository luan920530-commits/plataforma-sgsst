<?php
declare(strict_types=1);

if (!in_array(PHP_SAPI, ['cli','wasm'], true)) { http_response_code(404); exit; }
define('BASE_PATH', dirname(__DIR__));
$db = BASE_PATH . '/database/migration-v4-testing.db';
foreach ([$db,$db.'-wal',$db.'-shm'] as $file) if (is_file($file)) unlink($file);
putenv('SGSST_DB_PATH=' . $db);
require BASE_PATH . '/app/bootstrap.php';

use App\Core\Database;
use App\Services\Installer;
use App\Services\SchemaMigrator;

function checkMigration(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

Installer::installDemo();
$beforeTenants = (int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n'];
$beforeDocuments = (int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst')['n'];
$beforeUsers = (int) Database::first('SELECT COUNT(*) AS n FROM users')['n'];

// Reconstruye el estado estructural de una instalación v4 con datos existentes.
Database::connection()->exec('PRAGMA foreign_keys = OFF');
$tables = ['capacitacion_asistentes','documento_firmas','soportes_reservados','convivencia_reuniones','convivencia_casos','psicosocial_evaluaciones','salud_registros','comite_reuniones','comite_miembros','comites','evento_acciones','evento_investigaciones','evento_personas','eventos_sst','operacion_registros','requisitos_matriz','autoevaluaciones','usuario_permisos'];
foreach ($tables as $table) Database::connection()->exec('DROP TABLE IF EXISTS ' . $table);
$columns = [
    'plan_trabajo' => ['objetivo','meta','indicador','recursos','eficacia_resultado','verificado_por','verificado_at'],
    'hallazgos_acpa' => ['tipo_accion','causa_raiz','recursos','fecha_ejecucion','eficacia_resultado','eficacia_fecha','verificado_por','verificado_at'],
    'capacitaciones' => ['publico_objetivo','competencia','contenido','idoneidad_instructor','evaluacion_aprendizaje','eficacia','fecha_eficacia','verificado_por','verificado_at'],
    'indicadores' => ['fuente_numerador','fuente_denominador','responsable','observaciones','verificado_por','verificado_at'],
    'peligros_ipevr' => ['metodologia','participantes','fecha_evaluacion','proxima_revision','jerarquia_controles','verificado_por','verificado_at'],
];
foreach ($columns as $table => $names) foreach ($names as $name) Database::connection()->exec('ALTER TABLE ' . $table . ' DROP COLUMN ' . $name);
Database::connection()->exec('PRAGMA foreign_keys = ON');

for ($i = 1; $i <= 6; $i++) Database::execute('UPDATE estandares_minimos SET codigo = ? WHERE grupo = 60 AND codigo = ?', ['3.2.' . ($i + 3), '3.3.' . $i]);
for ($i = 1; $i <= 6; $i++) Database::execute('UPDATE estandares_minimos SET codigo = ? WHERE grupo = 60 AND codigo = ?', [$i === 1 ? '4.1.5' : '4.2.' . ($i - 1), '4.2.' . $i]);
Database::connection()->exec('PRAGMA user_version = 4');
checkMigration((int) Database::first('PRAGMA user_version')['user_version'] === 4, 'No fue posible preparar la versión 4.');

SchemaMigrator::migrate();

checkMigration((int) Database::first('PRAGMA user_version')['user_version'] === 5, 'La migración no marcó la versión 5.');
checkMigration((int) Database::first("SELECT COUNT(*) AS n FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")['n'] === 51, 'La migración no creó las 51 tablas esperadas.');
checkMigration((int) Database::first('SELECT COUNT(*) AS n FROM tenants')['n'] === $beforeTenants, 'Se alteró el número de empresas.');
checkMigration((int) Database::first('SELECT COUNT(*) AS n FROM documentos_sgsst')['n'] === $beforeDocuments, 'Se alteró el número de documentos.');
checkMigration((int) Database::first('SELECT COUNT(*) AS n FROM users')['n'] === $beforeUsers, 'Se alteró el número de usuarios.');
checkMigration(Database::query('PRAGMA foreign_key_check') === [], 'Hay violaciones de claves foráneas después de migrar.');
foreach ($columns as $table => $names) {
    $actual = array_column(Database::query('PRAGMA table_info(' . $table . ')'), 'name');
    foreach ($names as $name) checkMigration(in_array($name, $actual, true), 'Falta ' . $table . '.' . $name . ' después de migrar.');
}
foreach (['3.3.1','3.3.2','3.3.3','3.3.4','3.3.5','3.3.6','4.2.1','4.2.2','4.2.3','4.2.4','4.2.5','4.2.6'] as $code) {
    checkMigration(Database::first('SELECT id FROM estandares_minimos WHERE grupo = 60 AND codigo = ?', [$code]) !== null, 'No se corrigió el estándar ' . $code);
}
checkMigration((int) Database::first('SELECT COUNT(DISTINCT codigo) AS n FROM estandares_minimos WHERE grupo = 60')['n'] === 60, 'La corrección produjo códigos duplicados.');
$admins = Database::query("SELECT id, tenant_id FROM users WHERE rol = 'AdminEmpresa'");
foreach ($admins as $admin) checkMigration((int) Database::first('SELECT COUNT(*) AS n FROM usuario_permisos WHERE user_id = ? AND tenant_id = ?', [$admin['id'],$admin['tenant_id']])['n'] === 4, 'Faltan permisos explícitos del administrador.');

echo "Migración v4 → v5 verificada: datos preservados, 51 tablas, columnas aditivas, códigos corregidos, permisos y claves foráneas válidas.\n";
