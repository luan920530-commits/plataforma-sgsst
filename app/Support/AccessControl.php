<?php
declare(strict_types=1);

namespace App\Support;

use App\Models\Permission;

final class AccessControl
{
    public static function can(string $permission, ?int $userId = null, ?int $tenantId = null): bool
    {
        $userId ??= Auth::id();
        $tenantId ??= Auth::tenantId();
        if ($userId === null || $tenantId === null || !Auth::canManageTenant()) {
            return false;
        }
        return Permission::has($userId, $tenantId, $permission);
    }

    public static function permissions(?int $userId = null, ?int $tenantId = null): array
    {
        $userId ??= Auth::id();
        $tenantId ??= Auth::tenantId();
        return $userId !== null && $tenantId !== null ? Permission::forUser($userId, $tenantId) : [];
    }
}
