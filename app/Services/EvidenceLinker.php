<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Autoevaluacion;
use App\Models\Autoreporte;
use App\Models\BibliotecaRespuesta;
use App\Models\Capacitacion;
use App\Models\Comite;
use App\Models\Documento;
use App\Models\Evento;
use App\Models\Hallazgo;
use App\Models\Indicador;
use App\Models\IvcRequerimiento;
use App\Models\Operation;
use App\Models\Peligro;
use App\Models\PlanEmergencia;
use App\Models\PlanoEmergencia;
use App\Models\ProgramaPrevencion;
use App\Models\SanitarioRegistro;
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
            self::addGroup($groups, 'Hallazgos y acciones ACPA', array_map(static fn(array $row): array => [
                'value' => 'hallazgo:' . $row['id'], 'label' => 'ACPA #' . $row['id'] . ' · ' . $row['origen'] . ' · ' . excerpt($row['descripcion'], 70),
            ], Hallazgo::forTenant($tenantId)));
            self::addGroup($groups, 'Indicadores y datos fuente', array_map(static fn(array $row): array => [
                'value' => 'indicador:' . $row['id'], 'label' => $row['nombre'] . ' · ' . $row['periodo'],
            ], Indicador::forTenant($tenantId)));
            self::addGroup($groups, 'Evaluaciones IPEVR', array_map(static fn(array $row): array => [
                'value' => 'peligro:' . $row['id'], 'label' => 'IPEVR #' . $row['id'] . ' · ' . $row['peligro'] . ' · ' . $row['cargo'],
            ], Peligro::forTenant($tenantId)));
            self::addGroup($groups, 'Requerimientos y brechas IVC', array_map(static fn(array $row): array => [
                'value' => 'ivc_requerimiento:' . $row['id'],
                'label' => $row['codigo'] . ' · Acta ' . $row['numero_acta'] . ' · ' . excerpt($row['hallazgo'], 70),
            ], IvcRequerimiento::forTenant($tenantId)));
            self::addGroup($groups, 'Registros sanitarios', array_map(static fn(array $row): array => [
                'value' => 'sanitario:' . $row['id'],
                'label' => $row['titulo'] . ' · ' . $row['fecha_registro'],
            ], SanitarioRegistro::forTenant($tenantId)));
            $operations = Database::query('SELECT id, modulo, titulo, tipo FROM operacion_registros WHERE tenant_id = ? ORDER BY id DESC', [$tenantId]);
            self::addGroup($groups, 'Registros operativos', array_map(static fn(array $row): array => [
                'value' => 'operacion:' . $row['id'], 'label' => $row['modulo'] . ' · ' . $row['tipo'] . ' · ' . $row['titulo'],
            ], $operations));
            $events = Evento::forTenant($tenantId, [], AccessControl::can(\App\Models\Permission::HEALTH));
            self::addGroup($groups, 'Incidentes, AT y EL', array_map(static fn(array $row): array => [
                'value' => 'evento:' . $row['id'], 'label' => $row['codigo'] . ' · ' . $row['tipo'] . ' · ' . $row['fecha_evento'],
            ], $events));
            $healthSql = AccessControl::can(\App\Models\Permission::HEALTH) ? '' : " AND e.tipo <> 'Enfermedad laboral'";
            $eventActions = Database::query('SELECT a.id, a.tipo, e.id evento_id, e.codigo FROM evento_acciones a JOIN eventos_sst e ON e.id=a.evento_id WHERE a.tenant_id = ?' . $healthSql . ' ORDER BY a.id DESC', [$tenantId]);
            self::addGroup($groups, 'Acciones derivadas de eventos', array_map(static fn(array $row): array => [
                'value' => 'evento_accion:' . $row['id'], 'label' => $row['codigo'] . ' · Acción ' . $row['tipo'],
            ], $eventActions));
            self::addGroup($groups, 'COPASST y Vigía', array_map(static fn(array $row): array => [
                'value' => 'comite:' . $row['id'], 'label' => $row['tipo'] . ' · ' . $row['centro_trabajo'] . ' · ' . $row['periodo'],
            ], Comite::forTenant($tenantId)));
            $committeeMeetings = Database::query('SELECT r.id, r.fecha, r.tipo, c.tipo comite_tipo FROM comite_reuniones r JOIN comites c ON c.id=r.comite_id WHERE r.tenant_id = ? ORDER BY r.fecha DESC', [$tenantId]);
            self::addGroup($groups, 'Reuniones COPASST / Vigía', array_map(static fn(array $row): array => [
                'value' => 'comite_reunion:' . $row['id'], 'label' => $row['comite_tipo'] . ' · ' . $row['tipo'] . ' · ' . $row['fecha'],
            ], $committeeMeetings));
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
            case 'matriz_requisito':
                $row = Autoevaluacion::requirement($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Matriz ' . $row['vigencia'] . ' · ' . $row['item_codigo'] . ' · ' . $row['estandar'], ModuleAccess::SGST);
            case 'comite':
                $row = Comite::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, $row['tipo'] . ' · ' . $row['centro_trabajo'], ModuleAccess::SGST);
            case 'comite_reunion':
                $row = Database::first('SELECT r.id, r.fecha, c.tipo FROM comite_reuniones r JOIN comites c ON c.id=r.comite_id WHERE r.id = ? AND r.tenant_id = ?', [$id, $tenantId]);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, $row['tipo'] . ' · Reunión ' . $row['fecha'], ModuleAccess::SGST);
            case 'evento':
                $row = Evento::findForTenant($id, $tenantId);
                if ($row === null) break;
                if ($row['tipo'] === 'Enfermedad laboral' && !AccessControl::can(\App\Models\Permission::HEALTH)) throw new DomainException('Este evento requiere acceso reservado de salud.');
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, $row['codigo'] . ' · ' . $row['tipo'], ModuleAccess::SGST);
            case 'evento_accion':
                $row = Database::first('SELECT a.id, a.tipo, e.tipo evento_tipo, e.codigo FROM evento_acciones a JOIN eventos_sst e ON e.id = a.evento_id WHERE a.id = ? AND a.tenant_id = ?', [$id, $tenantId]);
                if ($row === null) break;
                if ($row['evento_tipo'] === 'Enfermedad laboral' && !AccessControl::can(\App\Models\Permission::HEALTH)) throw new DomainException('Esta acción requiere acceso reservado de salud.');
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, $row['codigo'] . ' · Acción ' . $row['tipo'], ModuleAccess::SGST);
            case 'operacion':
                $row = Operation::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Operación · ' . $row['modulo'] . ' · ' . $row['titulo'], ModuleAccess::SGST);
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
            case 'hallazgo':
                $row = Hallazgo::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'ACPA #' . $id . ' · ' . $row['origen'], ModuleAccess::SGST);
            case 'indicador':
                $row = Indicador::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Indicador · ' . $row['nombre'] . ' · ' . $row['periodo'], ModuleAccess::SGST);
            case 'peligro':
                $row = Peligro::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'IPEVR #' . $id . ' · ' . $row['peligro'], ModuleAccess::SGST);
            case 'ivc_requerimiento':
                $row = IvcRequerimiento::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'IVC ' . $row['codigo'] . ' · ' . excerpt($row['hallazgo'], 100), ModuleAccess::SGST);
            case 'sanitario':
                $row = SanitarioRegistro::findForTenant($id, $tenantId);
                if ($row === null) break;
                self::authorize(ModuleAccess::SGST, $current);
                return self::linked($type, $id, 'Registro sanitario · ' . $row['titulo'] . ' · ' . $row['fecha_registro'], ModuleAccess::SGST);
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
            'matriz_requisito' => '/autoevaluacion/requisitos/' . rawurlencode($id) . '/editar',
            'comite' => '/comites/' . rawurlencode($id),
            'comite_reunion' => self::committeeMeetingUrl($evidence),
            'evento' => '/eventos/' . rawurlencode($id),
            'evento_accion' => self::eventActionUrl($evidence),
            'operacion' => '/operacion-registros/' . rawurlencode($id) . '/editar',
            'documento' => '/documentos/' . rawurlencode($id),
            'autoreporte' => '/autoreportes/' . rawurlencode($id),
            'capacitacion' => '/capacitaciones/' . rawurlencode($id),
            'hallazgo' => '/hallazgos#hallazgo-' . rawurlencode($id),
            'indicador' => '/indicadores#indicador-' . rawurlencode($id),
            'peligro' => '/matriz-ipevr#peligro-' . rawurlencode($id),
            'ivc_requerimiento' => self::ivcRequirementUrl($evidence),
            'sanitario' => '/sanitario/registros/' . rawurlencode($id),
            'formulario' => (($evidence['modulo'] ?? '') === ModuleAccess::PESV ? '/pesv/respuestas/' : '/biblioteca-sgsst/respuestas/') . rawurlencode($id),
            'emergencia' => '/emergencias/' . rawurlencode($id),
            'simulacro' => self::drillUrl($evidence),
            'programa' => '/programas-prevencion/' . rawurlencode($id),
            'plano' => '/planos-emergencia/' . rawurlencode($id) . '/editar',
            'requisito' => '/cumplimiento-documental?requisito=' . rawurlencode($id) . '#soportes',
            default => null,
        };
    }

    private static function committeeMeetingUrl(array $evidence): ?string
    {
        $row = Database::first('SELECT comite_id FROM comite_reuniones WHERE id = ? AND tenant_id = ?', [(int) $evidence['entidad_id'], (int) $evidence['tenant_id']]);
        return $row === null ? null : '/comites/' . $row['comite_id'] . '#reunion-' . $evidence['entidad_id'];
    }

    private static function eventActionUrl(array $evidence): ?string
    {
        $row = Database::first('SELECT evento_id FROM evento_acciones WHERE id = ? AND tenant_id = ?', [(int) $evidence['entidad_id'], (int) $evidence['tenant_id']]);
        return $row === null ? null : '/eventos/' . $row['evento_id'] . '#acciones';
    }

    private static function ivcRequirementUrl(array $evidence): ?string
    {
        $row = IvcRequerimiento::findForTenant((int) $evidence['entidad_id'], (int) $evidence['tenant_id']);
        return $row === null ? null : '/ivc/' . $row['acta_id'] . '#requerimiento-' . $row['id'];
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
