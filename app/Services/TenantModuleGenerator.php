<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Documento;
use App\Models\PlanEmergencia;
use App\Models\ProgramaPrevencion;
use App\Models\Tenant;
use DomainException;
use RuntimeException;

/** Genera únicamente el kit inicial correspondiente al plan de la empresa. */
final class TenantModuleGenerator
{
    public static function generate(int $tenantId): array
    {
        if (ModuleAccess::forTenant($tenantId) === ModuleAccess::SGST) {
            return DocumentGenerator::generate($tenantId) + ['modulo' => ModuleAccess::SGST];
        }

        return Database::transaction(static function () use ($tenantId): array {
            $tenant = Tenant::find($tenantId);
            if ($tenant === null) {
                throw new RuntimeException('La empresa no existe.');
            }
            if (!empty($tenant['configuracion_generada_at'])) {
                throw new DomainException('La configuración inicial ya fue generada para esta empresa.');
            }
            $module = ModuleAccess::forTenant($tenantId);
            $documents = $module === ModuleAccess::PESV
                ? self::generatePesv($tenant)
                : self::generateEmergencies($tenant);
            Tenant::markGenerated($tenantId);

            return [
                'documentos' => $documents,
                'estandares' => 0,
                'procedimientos_formatos' => 0,
                'pesv' => $module === ModuleAccess::PESV,
                'plan_trabajo' => 0,
                'capacitaciones' => 0,
                'peligros' => 0,
                'modulo' => $module,
            ];
        });
    }

    public static function successMessage(array $summary): string
    {
        return match ($summary['modulo'] ?? ModuleAccess::SGST) {
            ModuleAccess::PESV => 'Empresa configurada con el módulo PESVial: índice PESV en borrador y biblioteca de 24 pasos disponible.',
            ModuleAccess::EMERGENCIAS => 'Empresa configurada con Emergencias y Vigilancia: plan de emergencias y programa de vigilancia en borrador.',
            default => "Empresa configurada: {$summary['estandares']} estándares, {$summary['plan_trabajo']} actividades del PAT y {$summary['peligros']} peligros base.",
        };
    }

    private static function generatePesv(array $tenant): int
    {
        Documento::createOperational([
            'tenant_id' => (int) $tenant['id'],
            'codigo' => PesvLibrary::PLAN_CODE,
            'nombre' => 'Plan Estratégico de Seguridad Vial (PESV)',
            'fase' => 'Planear',
            'tipo' => 'Plan',
            'biblioteca_id' => PesvLibrary::PLAN_CODE,
            'contenido' => PesvLibrary::planDraft($tenant),
        ]);
        return 1;
    }

    private static function generateEmergencies(array $tenant): int
    {
        $tenantId = (int) $tenant['id'];
        $pending = 'Pendiente de diligenciamiento y validación por la empresa con las condiciones reales del centro de trabajo.';
        $emergencyId = PlanEmergencia::create([
            'tenant_id' => $tenantId,
            'titulo' => 'Plan de prevención, preparación y respuesta ante emergencias',
            'centro_trabajo' => 'Sede principal',
            'direccion' => trim((string) ($tenant['direccion'] ?? '')),
            'alcance' => $pending,
            // Las listas operativas inician vacías: un marcador visual no debe contarse como evidencia real.
            'amenazas_json' => '[]',
            'contactos_json' => '[]',
            'brigada_json' => '[]',
            'recursos_json' => '[]',
            'rutas_evacuacion' => $pending,
            'puntos_encuentro' => $pending,
            'procedimientos' => $pending,
            'plan_rescate' => $pending,
            'ayuda_mutua' => $pending,
            'responsable_plan' => (string) ($tenant['representante_legal'] ?: 'Por designar'),
            'telefono_alarma' => '',
            'proximo_simulacro' => null,
            'fecha_revision' => null,
        ]);
        $emergencyDocument = OperationalDocument::createEmergency($emergencyId, $tenantId);
        PlanEmergencia::linkDocument($emergencyId, $tenantId, $emergencyDocument);

        $year = (int) date('Y');
        $programId = ProgramaPrevencion::create([
            'tenant_id' => $tenantId,
            'categoria' => 'Vigilancia epidemiologica',
            'nombre' => 'Programa de vigilancia epidemiológica',
            'anio' => $year,
            'diagnostico' => $pending,
            'objetivo_general' => $pending,
            'objetivos_especificos' => $pending,
            'poblacion' => $pending,
            'peligros_relacionados' => $pending,
            'metas_indicadores' => $pending,
            'responsable' => (string) ($tenant['representante_legal'] ?: 'Por designar'),
            'recursos' => $pending,
            'presupuesto' => 0,
            'fecha_inicio' => date('Y-01-01'),
            'fecha_fin' => date('Y-12-31'),
        ]);
        $programDocument = OperationalDocument::createProgram($programId, $tenantId);
        ProgramaPrevencion::linkDocument($programId, $tenantId, $programDocument);
        return 2;
    }
}
