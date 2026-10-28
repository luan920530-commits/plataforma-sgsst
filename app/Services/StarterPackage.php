<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Documento;
use RuntimeException;
use Throwable;

/** Selecciona el paquete de inicio por régimen, actividad y peligros sin convertir sugerencias en cumplimiento. */
final class StarterPackage
{
    private static ?array $resource = null;

    public static function groups(array $tenant, array $activity, array $baseDocuments = []): array
    {
        $resource = self::resource();
        $tenant = isset($tenant['razonsocial_html']) ? $tenant : DocumentTemplate::prepareTenant($tenant);
        $regime = (int) ($tenant['grupo_estandares'] ?? 7);
        $activityCode = (string) ($activity['codigo_ciuu'] ?? $tenant['codigo_ciuu'] ?? 'GENERAL');
        $hazards = self::hazards($tenant, $activity);
        $selected = [];
        $expectedDocuments = $baseDocuments;

        foreach ($resource['kits'] as $kit) {
            [$matches, $reason] = self::matches($kit, $regime, $activityCode, $hazards);
            if (!$matches) {
                continue;
            }
            $documents = [];
            foreach ($kit['documents'] as $document) {
                if (!self::documentApplies($document, $regime, $activityCode)) {
                    continue;
                }
                $document['codigo'] = $document['code'];
                $document['nombre'] = $document['name'];
                $document['kit'] = $kit['key'];
                $document['origen'] = $kit['name'];
                $document['related_route'] = $kit['related_route'];
                $document['fase'] = $document['phase'];
                $document['tipo_documental'] = $document['type'];
                // El esquema histórico no tiene tipos Guia/Manual; se conservan como Programa sin reconstruir tablas con FK.
                $document['tipo'] = in_array($document['type'], ['Guia', 'Manual'], true) ? 'Programa' : $document['type'];
                $document['contenido'] = '';
                $documents[] = $document;
                $expectedDocuments[] = $document;
            }
            if ($documents === []) {
                continue;
            }
            $selected[$kit['key']] = [
                'nombre' => $kit['name'],
                'descripcion' => $kit['description'],
                'aplicabilidad' => $kit['applicability'] . ' Selección actual: ' . $reason . '.',
                'recomendado' => true,
                'related_route' => $kit['related_route'],
                'documentos' => $documents,
            ];
        }

        $existing = [];
        if (!empty($tenant['id'])) {
            $existing = Documento::forTenant((int) $tenant['id']);
        }
        $context = [
            'documents' => $expectedDocuments,
            'existing' => $existing,
            'groups' => $selected,
            'references' => $resource['references'],
            'notice' => $resource['legal_notice'],
            'version' => $resource['version'],
            'reviewed_at' => $resource['reviewed_at'],
        ];
        foreach ($selected as &$group) {
            foreach ($group['documentos'] as &$document) {
                $document['contenido'] = StarterPackageTemplate::render($tenant, $activity, $document, $context);
            }
            unset($document);
        }
        unset($group);
        return $selected;
    }

    public static function resource(): array
    {
        if (self::$resource !== null) {
            return self::$resource;
        }
        $json = @file_get_contents(BASE_PATH . '/resources/starter-package/library.json');
        if ($json === false) {
            throw new RuntimeException('No se encontró el manifiesto del paquete de inicio.');
        }
        $resource = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($resource['kits'], $resource['references']) || !is_array($resource['kits'])) {
            throw new RuntimeException('El manifiesto del paquete de inicio no es válido.');
        }
        self::$resource = $resource;
        return self::$resource;
    }

    private static function documentApplies(array $document, int $regime, string $activityCode): bool
    {
        if (isset($document['regimes']) && !in_array($regime, $document['regimes'], true)) {
            return false;
        }
        return !isset($document['activities']) || in_array($activityCode, $document['activities'], true);
    }

    private static function matches(array $kit, int $regime, string $activityCode, array $hazards): array
    {
        if (!in_array($regime, $kit['regimes'], true)) {
            return [false, 'régimen no aplicable'];
        }
        $hasSelectors = $kit['activities'] !== [] || $kit['hazard_classes'] !== [] || $kit['hazard_terms'] !== [];
        if (!$hasSelectors) {
            return [true, 'kit transversal del régimen ' . $regime];
        }
        if (in_array($activityCode, $kit['activities'], true)) {
            return [true, 'actividad CIIU ' . $activityCode];
        }
        foreach ($hazards as $hazard) {
            if (in_array((string) ($hazard['clasificacion'] ?? ''), $kit['hazard_classes'], true)) {
                return [true, 'peligro registrado de clase ' . $hazard['clasificacion']];
            }
        }
        $haystack = text_lower(json_encode($hazards, JSON_UNESCAPED_UNICODE) ?: '');
        foreach ($kit['hazard_terms'] as $term) {
            if (str_contains($haystack, text_lower($term))) {
                return [true, 'peligro o tarea relacionada con ' . $term];
            }
        }
        return [false, 'sin actividad o peligro asociado'];
    }

    private static function hazards(array $tenant, array $activity): array
    {
        $hazards = self::decodeList($activity['matriz_ipevr_base'] ?? []);
        if (!empty($tenant['id'])) {
            try {
                $rows = Database::query('SELECT proceso, cargo, peligro, clasificacion, efectos, controles_existentes, nivel_riesgo, medidas_control FROM peligros_ipevr WHERE tenant_id = ?', [(int) $tenant['id']]);
                $hazards = array_merge($hazards, $rows);
            } catch (Throwable) {
                // La fuente base sigue disponible durante instalaciones o renderizados aislados.
            }
        }
        return $hazards;
    }

    private static function decodeList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }
}
