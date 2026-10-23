<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Autoreporte;
use App\Models\BibliotecaRespuesta;
use App\Models\Capacitacion;
use App\Models\Documento;
use App\Models\PlanEmergencia;
use App\Models\PlanoEmergencia;
use App\Models\ProgramaPrevencion;
use App\Models\SimulacroEmergencia;
use DomainException;

/** Resuelve vínculos reales y evita asociar archivos a registros de otro tenant o módulo. */
final class EvidenceLinker
{
    public static function moduleOptions(string $current): array
    {
        $options = ModuleAccess::options();
        if ($current !== ModuleAccess::SGST) {
            return isset($options[$current]) ? [$current => $options[$current]] : [];
        }
        return $options;
    }

    public static function options(int $tenantId, string $current): array
    {
        $groups = [];
        $documents = Documento::forTenant($tenantId, ['modulo' => $current]);
        self::addGroup($groups, 'Documentos generados', array_map(static fn(array $row): array => [
            'value' => 'documento:' . $row['id'],
            'label' => $row['codigo_estandar'] . ' · ' . $row['nombre_documento'],
        ], $documents));

        if ($current === ModuleAccess::SGST) {
            self::addGroup($groups, 'Autoreportes', array_map(static fn(array $row): array => [
                'value' => 'autoreporte:' . $row['id'],
                'label' => '#' . $row['id'] . ' · ' . $row['tipo'] . ' · ' . $row['fecha_evento'],
            ], Autoreporte::forTenant($tenantId)));
            self::addGroup($groups, 'Capacitaciones y asistencia', array_map(static fn(array $row): array => [
                'value' => 'capacitacion:' . $row['id'],
                'label' => $row['tema'] . ' · ' . $row['fecha_programada'],
            ], Capacitacion::forTenant($tenantId)));
        }

        if (in_array($current, [ModuleAccess::SGST, ModuleAccess::PESV], true)) {
            $responses = BibliotecaRespuesta::forTenant($tenantId);
            if ($current === ModuleAccess::PESV) {
                $responses = array_filter($responses, static fn(array $row): bool => str_starts_with($row['plantilla_id'], 'PESV-'));
            }
            self::addGroup($groups, 'Formularios diligenciados', array_map(static fn(array $row): array => [
                'value' => 'formulario:' . $row['id'],
                'label' => $row['titulo'] . ' · ' . $row['plantilla_id'],
            ], $responses));
        }

        if (in_array($current, [ModuleAccess::SGST, ModuleAccess::EMERGENCIAS], true)) {
            $plans = PlanEmergencia::forTenant($tenantId);
            self::addGroup($groups, 'Planes de emergencia', array_map(static fn(array $row): array => [
                'value' => 'emergencia:' . $row['id'],
                'label' => $row['codigo'] . ' · ' . $row['titulo'] . ' · ' . $row['centro_trabajo'],
            ], $plans));
            $drills = Database::query(
                'SELECT s.*, p.codigo AS plan_codigo FROM simulacros_emergencia s
                 JOIN planes_emergencia p ON p.id = s.plan_id WHERE s.tenant_id = ? ORDER BY s.fecha DESC, s.id DESC',
                [$tenantId]
            );
            self::addGroup($groups, 'Simulacros', array_map(static fn(array $row): array => [
                'value' => 'simulacro:' . $row['id'],
                'label' => $row['tipo'] . ' · ' . $row['fecha'] . ' · ' . $row['plan_codigo'],
            ], $drills));
            self::addGroup($groups, 'Programas y vigilancia', array_map(static fn(array $row): array => [
                'value' => 'programa:' . $row['id'],
                'label' => $row['nombre'] . ' · ' . $row['anio'],
            ], ProgramaPrevencion::forTenant($tenantId)));
            $plansById = array_column($plans, null, 'id');
            $drawings = [];
            foreach ($plans as $plan) {
                foreach (PlanoEmergencia::forPlan((int) $plan['id'], $tenantId) as $drawing) {
                    $drawing['plan_codigo'] = $plansById[$plan['id']]['codigo'] ?? 'Plan';
                    $drawings[] = $drawing;
                }
            }
            self::addGroup($groups, 'Planos de emergencia', array_map(static fn(array $row): array => [
                'value' => 'plano:' . $row['id'],
                'label' => $row['nombre'] . ' · v' . $row['version'] . ' · ' . $row['plan_codigo'],
            ], $drawings));
        }
        return $groups;
    }

    public static function resolve(string $value, int $tenantId, string $current, string $generalModule = ''): array
    {
        [$type, $rawId] = array_pad(explode(':', $value, 2), 2, '');
        if ($type === 'general' && $rawId === '') {
            $module = $generalModule !== '' ? $generalModule : ($current === ModuleAccess::SGST ? ModuleAccess::SGST : $current);
            self::authorize($module, $current);
            return ['entidad_tipo' => 'general', 'entidad_id' => '', 'entidad_referencia' => 'Archivo general · ' . ModuleAccess::shortLabel($module), 'modulo' => $module];
        }
        if (!ctype_digit($rawId) || (int) $rawId < 1) {
            throw new DomainException('Selecciona un registro válido para vincular la evidencia.');
        }
        $id = (int) $rawId;
        switch ($type) {
            case 'documento':
                $row = Documento::findForTenant($id, $tenantId);
                if ($row === null) break;
                $module = self::documentModule($row);
                self::authorize($module, $current);
                return self::linked($type, $id, $row['codigo_estandar'] . ' · ' . $row['nombre_documento'], $module);
            case 'autoreporte':
                $row = Autoreporte::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Autoreporte #' . $id . ' · ' . $row['tipo'], ModuleAccess::SGST);
            case 'capacitacion':
                $row = Capacitacion::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Capacitación · ' . $row['tema'] . ' · ' . $row['fecha_programada'], ModuleAccess::SGST);
            case 'formulario':
                $row = BibliotecaRespuesta::findForTenant($id, $tenantId);
                if ($row === null) break;
                $module = str_starts_with($row['plantilla_id'], 'PESV-') ? ModuleAccess::PESV : ModuleAccess::SGST;
                self::authorize($module, $current);
                return self::linked($type, $id, 'Formulario · ' . $row['titulo'] . ' · ' . $row['plantilla_id'], $module);
            case 'emergencia':
                $row = PlanEmergencia::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::EMERGENCIAS, $current);
                return self::linked($type, $id, $row['codigo'] . ' · ' . $row['titulo'], ModuleAccess::EMERGENCIAS);
            case 'simulacro':
                $row = SimulacroEmergencia::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::EMERGENCIAS, $current);
                return self::linked($type, $id, 'Simulacro · ' . $row['tipo'] . ' · ' . $row['fecha'], ModuleAccess::EMERGENCIAS);
            case 'programa':
                $row = ProgramaPrevencion::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::EMERGENCIAS, $current);
                return self::linked($type, $id, 'Programa · ' . $row['nombre'] . ' · ' . $row['anio'], ModuleAccess::EMERGENCIAS);
            case 'plano':
                $row = PlanoEmergencia::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::EMERGENCIAS, $current);
                return self::linked($type, $id, 'Plano · ' . $row['nombre'] . ' · v' . $row['version'], ModuleAccess::EMERGENCIAS);
        }
        throw new DomainException('El registro seleccionado no existe, no pertenece a tu empresa o cambió de módulo.');
    }

    public static function url(array $evidence): ?string
    {
        $type = (string) ($evidence['entidad_tipo'] ?? '');
        $id = (string) ($evidence['entidad_id'] ?? '');
        if ($id === '' && $type !== 'general') {
            return null;
        }
        return match ($type) {
            'documento' => '/documentos/' . rawurlencode($id),
            'autoreporte' => '/autoreportes/' . rawurlencode($id),
            'capacitacion' => '/capacitaciones/' . rawurlencode($id) . '/editar',
            'formulario' => (($evidence['modulo'] ?? '') === ModuleAccess::PESV ? '/pesv/respuestas/' : '/biblioteca-sgsst/respuestas/') . rawurlencode($id),
            'emergencia' => '/emergencias/' . rawurlencode($id),
            'simulacro' => self::drillUrl($evidence),
            'programa' => '/programas-prevencion/' . rawurlencode($id),
            'plano' => '/planos-emergencia/' . rawurlencode($id) . '/editar',
            'requisito' => '/cumplimiento-documental?requisito=' . rawurlencode($id) . '#soportes',
            default => null,
        };
    }

    private static function drillUrl(array $evidence): ?string
    {
        $row = Database::first(
            'SELECT plan_id FROM simulacros_emergencia WHERE id = ? AND tenant_id = ?',
            [(int) $evidence['entidad_id'], (int) $evidence['tenant_id']]
        );
        return $row === null ? null : '/emergencias/' . $row['plan_id'] . '#simulacros';
    }

    private static function linked(string $type, int $id, string $reference, string $module): array
    {
        return ['entidad_tipo' => $type, 'entidad_id' => (string) $id, 'entidad_referencia' => $reference, 'modulo' => $module];
    }

    private static function documentModule(array $document): string
    {
        if (ModuleAccess::documentMatches($document, ModuleAccess::PESV)) {
            return ModuleAccess::PESV;
        }
        if (ModuleAccess::documentMatches($document, ModuleAccess::EMERGENCIAS)) {
            return ModuleAccess::EMERGENCIAS;
        }
        return ModuleAccess::SGST;
    }

    private static function authorize(string $module, string $current): void
    {
        if (!in_array($module, [ModuleAccess::SGST, ModuleAccess::PESV, ModuleAccess::EMERGENCIAS], true)
            || ($current !== ModuleAccess::SGST && $current !== $module)) {
            throw new DomainException('La evidencia no pertenece al módulo habilitado para esta empresa.');
        }
    }

    private static function addGroup(array &$groups, string $label, array $options): void
    {
        if ($options !== []) {
            $groups[] = ['label' => $label, 'options' => array_values($options)];
        }
    }
}
