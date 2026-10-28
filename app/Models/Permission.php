<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Permission
{
    public const MATRIX_VERIFY = 'matriz.verificar';
    public const DOCUMENT_SIGN = 'documentos.firmar';
    public const HEALTH = 'salud.reservada';
    public const COEXISTENCE = 'convivencia.reservada';

    public static function definitions(): array
    {
        return [
            self::MATRIX_VERIFY => ['Verificar requisitos y autoevaluación', 'Permite validar madurez, Cumple y No aplica; no calcula cumplimiento por documentos.'],
            self::DOCUMENT_SIGN => ['Firmar documentos', 'Permite firma electrónica reforzada con reautenticación y consentimiento; no emite certificados digitales.'],
            self::HEALTH => ['Expediente reservado de salud', 'Acceso a conceptos, restricciones, seguimiento y datos psicosociales agregados.'],
            self::COEXISTENCE => ['Expediente reservado de convivencia', 'Acceso a quejas, medidas, CCL y soportes reservados.'],
        ];
    }

    public static function forUser(int $userId, int $tenantId): array
    {
        return array_column(Database::query(
            'SELECT permiso FROM usuario_permisos WHERE user_id = ? AND tenant_id = ? ORDER BY permiso',
            [$userId, $tenantId]
        ), 'permiso');
    }

    public static function has(int $userId, int $tenantId, string $permission): bool
    {
        if (!array_key_exists($permission, self::definitions())) {
            return false;
        }
        return Database::first(
            'SELECT id FROM usuario_permisos WHERE user_id = ? AND tenant_id = ? AND permiso = ?',
            [$userId, $tenantId, $permission]
        ) !== null;
    }

    public static function grant(int $userId, int $tenantId, string $permission, int $grantedBy): void
    {
        if (!array_key_exists($permission, self::definitions())) {
            throw new \InvalidArgumentException('Permiso no reconocido.');
        }
        $target = Database::first('SELECT tenant_id FROM users WHERE id = ?', [$userId]);
        if ($target === null || (int) $target['tenant_id'] !== $tenantId) {
            throw new \DomainException('La cuenta no pertenece a la empresa para la que se otorga el permiso.');
        }
        Database::execute(
            'INSERT OR IGNORE INTO usuario_permisos (tenant_id, user_id, permiso, otorgado_por) VALUES (?, ?, ?, ?)',
            [$tenantId, $userId, $permission, $grantedBy]
        );
    }

    public static function sync(int $userId, int $tenantId, array $permissions, int $grantedBy): void
    {
        $permissions = array_values(array_unique(array_filter($permissions, static fn($item): bool => is_string($item) && array_key_exists($item, self::definitions()))));
        $target = Database::first('SELECT tenant_id FROM users WHERE id = ?', [$userId]);
        if ($target === null || (int) $target['tenant_id'] !== $tenantId) throw new \DomainException('La cuenta no pertenece a esta empresa.');
        Database::transaction(static function () use ($userId, $tenantId, $permissions, $grantedBy): void {
            Database::execute('DELETE FROM usuario_permisos WHERE user_id = ? AND tenant_id = ?', [$userId, $tenantId]);
            foreach ($permissions as $permission) {
                self::grant($userId, $tenantId, $permission, $grantedBy);
            }
        });
    }

    public static function grantDefaults(int $userId, int $tenantId, int $grantedBy, bool $administrator = false): void
    {
        $permissions = $administrator ? array_keys(self::definitions()) : [];
        foreach ($permissions as $permission) {
            self::grant($userId, $tenantId, $permission, $grantedBy);
        }
    }
}
