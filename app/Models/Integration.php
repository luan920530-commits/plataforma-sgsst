<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ExternalIntegrationClient;
use App\Support\SecretBox;
use DomainException;

final class Integration
{
    public const TYPES = ['ARL', 'Nomina', 'Medicina ocupacional'];
    public const METHODS = ['POST', 'PUT', 'PATCH'];
    public const AUTH = ['Bearer', 'X-API-Key'];

    public static function forTenant(int $tenantId): array
    {
        return Database::query('SELECT i.*, (SELECT COUNT(*) FROM integracion_ejecuciones e WHERE e.integracion_id=i.id) ejecuciones,
            (SELECT MAX(ejecutado_at) FROM integracion_ejecuciones e WHERE e.integracion_id=i.id) ultima_ejecucion
            FROM integraciones i WHERE i.tenant_id=? ORDER BY i.tipo, i.nombre', [$tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $row = Database::first('SELECT * FROM integraciones WHERE id=? AND tenant_id=?', [$id, $tenantId]);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $data = self::validated($data, true);
        Database::execute('INSERT INTO integraciones (tenant_id,tipo,nombre,endpoint,metodo,autenticacion,credencial_cifrada,ruta_referencia,activo,created_by)
            VALUES (?,?,?,?,?,?,?,?,1,?)', [
            $data['tenant_id'], $data['tipo'], $data['nombre'], $data['endpoint'], $data['metodo'], $data['autenticacion'],
            SecretBox::encrypt($data['credencial']), $data['ruta_referencia'], $data['created_by'],
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, int $tenantId, array $data): void
    {
        $current = self::findForTenant($id, $tenantId);
        if ($current === null) throw new DomainException('La integración no existe o no pertenece a tu empresa.');
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = (int) $current['created_by'];
        $requiresCredential = trim((string) ($data['credencial'] ?? '')) !== '';
        $data = self::validated($data, $requiresCredential);
        $credential = $requiresCredential ? SecretBox::encrypt($data['credencial']) : $current['credencial_cifrada'];
        Database::execute('UPDATE integraciones SET tipo=?,nombre=?,endpoint=?,metodo=?,autenticacion=?,credencial_cifrada=?,ruta_referencia=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [
            $data['tipo'], $data['nombre'], $data['endpoint'], $data['metodo'], $data['autenticacion'], $credential, $data['ruta_referencia'], $id, $tenantId,
        ]);
    }

    public static function setActive(int $id, int $tenantId, bool $active): void
    {
        $integration = self::findForTenant($id, $tenantId);
        if ($integration === null) throw new DomainException('La integración no existe o no pertenece a tu empresa.');
        Database::execute('UPDATE integraciones SET activo=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [$active ? 1 : 0, $id, $tenantId]);
    }

    public static function credential(array $integration): string
    {
        $credential = SecretBox::decrypt((string) $integration['credencial_cifrada']);
        if ($credential === '') throw new DomainException('La credencial no está disponible. Actualízala antes de ejecutar.');
        return $credential;
    }

    private static function validated(array $data, bool $requireCredential): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        if ($tenantId < 1) throw new DomainException('La empresa no es válida.');
        $type = trim((string) ($data['tipo'] ?? ''));
        $method = strtoupper(trim((string) ($data['metodo'] ?? 'POST')));
        $auth = trim((string) ($data['autenticacion'] ?? 'Bearer'));
        if (!in_array($type, self::TYPES, true) || !in_array($method, self::METHODS, true) || !in_array($auth, self::AUTH, true)) {
            throw new DomainException('El tipo, método o mecanismo de autenticación no es compatible con el conector.');
        }
        $name = trim((string) ($data['nombre'] ?? ''));
        $credential = trim((string) ($data['credencial'] ?? ''));
        $path = trim((string) ($data['ruta_referencia'] ?? ''));
        if ($name === '' || text_length($name) > 150) throw new DomainException('El nombre del proveedor es obligatorio y no puede superar 150 caracteres.');
        if ($requireCredential && ($credential === '' || text_length($credential) > 4000)) throw new DomainException('La credencial es obligatoria y no puede superar 4000 caracteres.');
        if (text_length($credential) > 4000) throw new DomainException('La credencial no puede superar 4000 caracteres.');
        if (preg_match('/[\x00-\x1F\x7F]/', $credential)) throw new DomainException('La credencial contiene caracteres de control no permitidos.');
        if ($path !== '' && (!preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+){0,7}$/', $path) || text_length($path) > 180)) {
            throw new DomainException('La ruta de referencia debe usar campos JSON separados por puntos, por ejemplo data.radicado.');
        }
        $endpoint = ExternalIntegrationClient::validateEndpoint(trim((string) ($data['endpoint'] ?? '')));
        return [
            'tenant_id' => $tenantId, 'tipo' => $type, 'nombre' => $name, 'endpoint' => $endpoint,
            'metodo' => $method, 'autenticacion' => $auth, 'credencial' => $credential,
            'ruta_referencia' => $path, 'created_by' => (int) ($data['created_by'] ?? 0) ?: null,
        ];
    }
}
