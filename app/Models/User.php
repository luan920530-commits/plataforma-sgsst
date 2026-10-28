<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    private const AUTH_SELECT = "SELECT u.*, t.razonsocial, t.estado AS tenant_estado, t.grupo_estandares,
                                        t.nivel_riesgo, t.numero_trabajadores, t.sector_economico
                                 FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id";

    public static function findActive(int $id): ?array
    {
        return Database::first(
            self::AUTH_SELECT . " WHERE u.id = ? AND u.activo = 1 AND (u.rol = 'SuperAdmin' OR t.estado = 'Activa')",
            [$id]
        );
    }

    public static function findForAuthentication(string $email): ?array
    {
        return Database::first(
            self::AUTH_SELECT . " WHERE lower(u.email) = ? AND u.activo = 1 AND (u.rol = 'SuperAdmin' OR t.estado = 'Activa')",
            [text_lower($email)]
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function allByTenant(int $tenantId): array
    {
        return Database::query('SELECT id, tenant_id, nombre, email, rol, licencia_sst, activo, must_change_password, last_login_at, created_at FROM users WHERE tenant_id = ? ORDER BY activo DESC, nombre', [$tenantId]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO users (tenant_id, nombre, email, password, rol, licencia_sst, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['rol'] === 'SuperAdmin' ? null : (int) $data['tenant_id'],
                trim($data['nombre']), text_lower(trim($data['email'])), $data['password'],
                $data['rol'], trim((string) ($data['licencia_sst'] ?? '')) ?: null,
                !empty($data['must_change_password']) ? 1 : 0,
            ]
        );
        return Database::lastId();
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE lower(email) = ?';
        $params = [text_lower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        return Database::first($sql, $params) !== null;
    }

    public static function updateForTenant(int $id, int $tenantId, array $data): bool
    {
        return Database::execute(
            'UPDATE users SET nombre = ?, email = ?, licencia_sst = ? WHERE id = ? AND tenant_id = ?',
            [trim($data['nombre']), text_lower(trim($data['email'])), trim((string) ($data['licencia_sst'] ?? '')) ?: null, $id, $tenantId]
        ) === 1;
    }

    public static function resetPasswordForTenant(int $id, int $tenantId, string $hash): bool
    {
        return Database::execute(
            'UPDATE users SET password = ?, must_change_password = 1 WHERE id = ? AND tenant_id = ?',
            [$hash, $id, $tenantId]
        ) === 1;
    }

    public static function updatePassword(int $id, string $hash): void
    {
        Database::execute('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?', [$hash, $id]);
    }

    public static function rehashPassword(int $id, string $hash): void
    {
        Database::execute('UPDATE users SET password = ? WHERE id = ?', [$hash, $id]);
    }

    public static function markLogin(int $id): void
    {
        Database::execute('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?', [$id]);
    }

    public static function setActiveForTenant(int $id, int $tenantId, bool $active): void
    {
        Database::execute('UPDATE users SET activo = ? WHERE id = ? AND tenant_id = ?', [$active ? 1 : 0, $id, $tenantId]);
    }

    public static function prepareTotp(int $id, string $encryptedSecret, array $recoveryHashes): void
    {
        Database::execute('UPDATE users SET totp_secret=?,totp_recovery_codes=?,totp_enabled=0,totp_confirmed_at=NULL WHERE id=?', [$encryptedSecret, json_encode(array_values($recoveryHashes), JSON_THROW_ON_ERROR), $id]);
    }

    public static function enableTotp(int $id): void
    {
        Database::execute('UPDATE users SET totp_enabled=1,totp_confirmed_at=CURRENT_TIMESTAMP WHERE id=? AND totp_secret<>\'\'', [$id]);
    }

    public static function disableTotp(int $id): void
    {
        Database::execute("UPDATE users SET totp_secret='',totp_recovery_codes='[]',totp_enabled=0,totp_confirmed_at=NULL WHERE id=?", [$id]);
    }

    public static function consumeRecoveryCode(int $id, string $code): bool
    {
        $user = self::find($id);if (!$user) return false;
        $hashes = json_decode((string) $user['totp_recovery_codes'], true);if (!is_array($hashes)) return false;
        $normalized = strtoupper(trim($code));
        foreach ($hashes as $index => $hash) {
            if (!is_string($hash) || !password_verify($normalized, $hash)) continue;
            unset($hashes[$index]);
            Database::execute('UPDATE users SET totp_recovery_codes=? WHERE id=?', [json_encode(array_values($hashes), JSON_THROW_ON_ERROR), $id]);
            return true;
        }
        return false;
    }

    public static function countByTenant(int $tenantId): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS total FROM users WHERE tenant_id = ? AND activo = 1', [$tenantId])['total'] ?? 0);
    }
}
