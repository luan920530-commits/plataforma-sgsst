<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\IvcActa;
use App\Models\IvcRequerimiento;
use App\Models\SanitarioRegistro;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit;
use RuntimeException;

/** Instala datos reales de trabajo sin incluir una base, el PDF privado ni credenciales en el código. */
final class ExtintoresScmSeeder
{
    public const PROFILE = 'extintores_scm';

    public static function seed(): array
    {
        $data = self::data();
        return Database::transaction(static function () use ($data): array {
            $existing = Database::first('SELECT id FROM tenants WHERE nit = ?', [$data['company']['nit']]);
            if ($existing !== null) {
                return ['created' => false, 'tenant_id' => (int) $existing['id']];
            }
            $activity = Database::first('SELECT id FROM actividades_economicas WHERE codigo_ciuu = ?', [$data['company']['ciiu']]);
            if ($activity === null) {
                throw new RuntimeException('El catálogo no contiene el CIIU requerido por el perfil Extintores SCM.');
            }
            $plan = Database::first("SELECT id FROM planes_saas WHERE nombre = 'Profesional' AND modulo = 'sgsst' AND activo = 1");
            if ($plan === null) {
                throw new RuntimeException('No se encontró el plan SG-SST requerido por el perfil Extintores SCM.');
            }
            $admin = $data['company']['admin'];
            if (User::emailExists($admin['email'])) {
                throw new RuntimeException('El correo empresarial del perfil ya está utilizado por otra cuenta.');
            }

            $tenantId = Tenant::create([
                'razonsocial' => $data['company']['razonsocial'], 'nit' => $data['company']['nit'],
                'sector_economico' => $data['company']['sector_economico'], 'actividad_economica_id' => (int) $activity['id'],
                'numero_trabajadores' => (int) $data['company']['numero_trabajadores'], 'nivel_riesgo' => $data['company']['nivel_riesgo'],
                'grupo_estandares' => (int) $data['company']['grupo_estandares'], 'metodo_configuracion' => $data['company']['metodo_configuracion'],
                'plan_id' => (int) $plan['id'], 'plan_condicion' => 'Pagado', 'representante_legal' => $data['company']['representante_legal'],
                'correo_contacto' => $data['company']['correo_contacto'], 'telefono' => $data['company']['telefono'],
                'ciudad' => $data['company']['ciudad'], 'direccion' => $data['company']['direccion'],
            ]);
            $password = self::temporaryPassword();
            $userId = User::create([
                'tenant_id' => $tenantId, 'nombre' => $admin['nombre'], 'email' => $admin['email'],
                'password' => password_hash($password, PASSWORD_DEFAULT), 'rol' => 'AdminEmpresa', 'must_change_password' => 1,
            ]);

            // Son borradores y actividades programadas: no se aprueban ni se marcan ejecutadas.
            $summary = DocumentGenerator::generate($tenantId);
            $actaId = IvcActa::create($data['acta'] + ['tenant_id' => $tenantId]);
            $requirementCount = 0;
            foreach (array_merge($data['requirements'], $data['documentary_gaps']) as $requirement) {
                IvcRequerimiento::create($requirement + ['tenant_id' => $tenantId, 'acta_id' => $actaId]);
                $requirementCount++;
            }
            foreach ($data['validations'] as $validation) {
                SanitarioRegistro::create([
                    'tenant_id' => $tenantId, 'requerimiento_id' => null, 'tipo' => 'validacion', 'titulo' => $validation['titulo'],
                    'fecha_registro' => date('Y-m-d'), 'responsable' => 'Por asignar', 'estado' => 'Pendiente',
                    'datos' => $validation['datos'], 'observaciones' => $validation['observaciones'], 'created_by' => $userId,
                ]);
            }
            Audit::record('Perfil real Extintores SCM instalado', 'Tenant', $tenantId, [
                'acta' => $data['acta']['numero_acta'], 'seguimientos_pendientes' => $requirementCount,
                'validaciones' => count($data['validations']), 'documentos_borrador' => $summary['documentos'],
                'fuente_pdf_incluida' => false,
            ], $tenantId, $userId);

            return [
                'created' => true, 'tenant_id' => $tenantId, 'acta_id' => $actaId,
                'requirements' => $requirementCount, 'validations' => count($data['validations']),
                'documents' => $summary['documentos'], 'access' => [
                    'email' => text_lower($admin['email']), 'password' => $password, 'must_change_password' => true,
                ],
            ];
        });
    }

    public static function data(): array
    {
        $path = BASE_PATH . '/resources/extintores-scm/seed.json';
        $json = @file_get_contents($path);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data) || ($data['profile'] ?? '') !== self::PROFILE) {
            throw new RuntimeException('No se pudo leer el perfil de instalación Extintores SCM.');
        }
        if (count($data['requirements'] ?? []) !== 20 || count($data['documentary_gaps'] ?? []) !== 8) {
            throw new RuntimeException('El perfil Extintores SCM debe contener 20 requerimientos y 8 brechas documentales.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) ($data['acta']['fuente_sha256'] ?? ''))) {
            throw new RuntimeException('La referencia de integridad del acta no es válida.');
        }
        return $data;
    }

    private static function temporaryPassword(): string
    {
        return 'N!' . bin2hex(random_bytes(12)) . 'a9';
    }
}
