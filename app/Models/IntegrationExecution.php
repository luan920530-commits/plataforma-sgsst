<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ExternalIntegrationClient;
use DomainException;
use JsonException;
use Throwable;

final class IntegrationExecution
{
    public static function forTenant(int $tenantId, int $integrationId = 0): array
    {
        $where = 'e.tenant_id=?';
        $params = [$tenantId];
        if ($integrationId > 0) { $where .= ' AND e.integracion_id=?'; $params[] = $integrationId; }
        return Database::query("SELECT e.*,i.nombre proveedor,i.tipo,u.nombre ejecutado_por_nombre,v.nombre verificado_por_nombre,
            (SELECT COUNT(*) FROM soportes_cumplimiento s WHERE s.tenant_id=e.tenant_id AND s.entidad_tipo='integracion_ejecucion' AND s.entidad_id=CAST(e.id AS TEXT) AND s.estado_revision='Revisado') soportes_revisados
            FROM integracion_ejecuciones e JOIN integraciones i ON i.id=e.integracion_id
            LEFT JOIN users u ON u.id=e.ejecutado_por LEFT JOIN users v ON v.id=e.verificado_por
            WHERE {$where} ORDER BY e.id DESC", $params);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $row = Database::first('SELECT * FROM integracion_ejecuciones WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        return $row ?: null;
    }

    public static function execute(int $integrationId, int $tenantId, string $operation, array $payload, int $userId): int
    {
        $integration = Integration::findForTenant($integrationId, $tenantId);
        if ($integration === null || !(bool) $integration['activo']) throw new DomainException('La integración no existe, no pertenece a tu empresa o está inactiva.');
        $operation = trim($operation);
        if ($operation === '' || text_length($operation) > 250) throw new DomainException('Describe la operación real en máximo 250 caracteres.');
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw new DomainException('El payload no puede convertirse en JSON válido.');
        }
        if (strlen($json) > 200000) throw new DomainException('El payload supera el máximo de 200.000 bytes del conector.');
        $correlation = bin2hex(random_bytes(16));
        Database::execute("INSERT INTO integracion_ejecuciones (tenant_id,integracion_id,correlacion,operacion,solicitud_sha256,estado_tecnico,detalle,ejecutado_por)
            VALUES (?,?,?,?,?,'En curso','Solicitud registrada antes de contactar al proveedor.',?)", [
            $tenantId, $integrationId, $correlation, $operation, hash('sha256', $json), $userId,
        ]);
        $id = (int) Database::connection()->lastInsertId();
        try {
            $result = ExternalIntegrationClient::send($integration, $json, Integration::credential($integration), $correlation);
            self::recordResponse($id, $tenantId, $integration, $result);
        } catch (Throwable $exception) {
            Database::execute("UPDATE integracion_ejecuciones SET estado_tecnico='Error de conexion',detalle=?,duracion_ms=0 WHERE id=? AND tenant_id=? AND estado_tecnico='En curso'", [
                excerpt($exception->getMessage(), 500), $id, $tenantId,
            ]);
        }
        return $id;
    }

    public static function verify(int $id, int $tenantId, int $userId): void
    {
        $execution = self::findForTenant($id, $tenantId);
        if ($execution === null) throw new DomainException('La ejecución no existe o no pertenece a tu empresa.');
        if ($execution['estado_tecnico'] === 'En curso') throw new DomainException('La solicitud todavía no tiene un resultado técnico.');
        if (!Permission::has($userId, $tenantId, Permission::INTEGRATIONS)) throw new DomainException('La validación requiere el permiso específico de integraciones.');
        if ($execution['verificado_at'] !== null) throw new DomainException('El soporte de esta ejecución ya fue verificado; no se puede repetir la validación.');
        if (Evidencia::reviewedCountForEntity($tenantId, 'integracion_ejecucion', (string) $id) < 1) {
            throw new DomainException('Adjunta y revisa el acuse o soporte oficial antes de validar esta ejecución. Un HTTP 2xx no es suficiente.');
        }
        Database::execute('UPDATE integracion_ejecuciones SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [$userId, $id, $tenantId]);
    }

    private static function recordResponse(int $id, int $tenantId, array $integration, array $result): void
    {
        $status = $result['status'];
        $body = (string) $result['body'];
        $technical = 'Error de conexion';
        $detail = (string) $result['error'];
        $reference = '';
        $decoded = null;
        $validJson = $body === '';
        if ($body !== '' && !$result['truncated']) {
            try { $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR); $validJson = true; }
            catch (JsonException) { $validJson = false; }
        }
        if (is_int($status) && $status >= 200 && $status < 300) {
            if ($result['truncated'] || !$validJson) {
                $technical = 'Respuesta invalida';
                $detail = $result['truncated'] ? 'La respuesta superó 1 MiB y no se aceptó como respuesta completa.' : 'El proveedor respondió HTTP 2xx, pero el cuerpo no es JSON válido. Revisa el contrato del servicio.';
            } else {
                $technical = 'Respuesta HTTPS';
                $detail = 'Respuesta técnica recibida. No implica radicación, aceptación, conciliación ni validez médica; requiere revisión del soporte oficial.';
                $reference = self::reference($decoded, (string) $integration['ruta_referencia']);
            }
        } elseif (is_int($status)) {
            $technical = 'Error HTTP';
            $detail = 'El proveedor respondió HTTP ' . $status . '. Conserva esta traza y revisa la operación; no se marcará como aceptada.';
        } elseif ($detail === '') {
            $detail = 'No se obtuvo un estado HTTP válido del proveedor.';
        }
        Database::execute('UPDATE integracion_ejecuciones SET respuesta_sha256=?,http_status=?,bytes_respuesta=?,duracion_ms=?,estado_tecnico=?,detalle=?,referencia_externa=? WHERE id=? AND tenant_id=?', [
            $body === '' ? '' : hash('sha256', $body), $status, strlen($body), (int) $result['duration_ms'], $technical, $detail, $reference, $id, $tenantId,
        ]);
    }

    private static function reference(mixed $decoded, string $path): string
    {
        if ($path === '' || !is_array($decoded)) return '';
        $value = $decoded;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) return '';
            $value = $value[$segment];
        }
        if (!is_scalar($value) || is_bool($value)) return '';
        return excerpt(trim((string) $value), 200);
    }
}
