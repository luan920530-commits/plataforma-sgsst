<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Induccion
{
    public static function current(int $tenantId): ?array
    {
        return Database::first('SELECT * FROM inducciones WHERE tenant_id = ? AND activo = 1 ORDER BY version DESC, id DESC LIMIT 1', [$tenantId]);
    }

    public static function create(array $data): int
    {
        return Database::transaction(static function () use ($data): int {
            Database::execute('UPDATE inducciones SET activo = 0 WHERE tenant_id = ?', [(int) $data['tenant_id']]);
            Database::execute('INSERT INTO inducciones (tenant_id, titulo, contenido_html, version) VALUES (?, ?, ?, ?)', [(int) $data['tenant_id'], trim($data['titulo']), $data['contenido_html'], (int) ($data['version'] ?? 1)]);
            return Database::lastId();
        });
    }

    public static function hasSigned(int $inductionId, int $tenantId, int $userId): bool
    {
        return Database::first('SELECT id FROM firmas_induccion WHERE induccion_id = ? AND tenant_id = ? AND user_id = ?', [$inductionId, $tenantId, $userId]) !== null;
    }

    public static function signature(int $inductionId, int $tenantId, int $userId): ?array
    {
        return Database::first('SELECT * FROM firmas_induccion WHERE induccion_id = ? AND tenant_id = ? AND user_id = ?', [$inductionId, $tenantId, $userId]);
    }

    public static function sign(array $data): int
    {
        Database::execute(
            'INSERT INTO firmas_induccion (tenant_id, induccion_id, user_id, nombre_firmante, documento_firmante, hash_evidencia, ip_address, user_agent, firmado_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                (int) $data['tenant_id'], (int) $data['induccion_id'], (int) $data['user_id'],
                trim($data['nombre_firmante']), trim($data['documento_firmante']), $data['hash_evidencia'],
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 500),
                $data['firmado_at'] ?? date('Y-m-d H:i:s'),
            ]
        );
        return Database::lastId();
    }

    public static function signatureCount(int $tenantId, int $inductionId): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS total FROM firmas_induccion WHERE tenant_id = ? AND induccion_id = ?', [$tenantId, $inductionId])['total'] ?? 0);
    }
}
