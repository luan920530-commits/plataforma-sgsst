<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Support\Auth;

/** Alcance funcional del plan asignado; no depende de que sea pagado o sugerido. */
final class ModuleAccess
{
    public const SGST = 'sgsst';
    public const PESV = 'pesv';
    public const EMERGENCIAS = 'emergencias';
    public const SHARED = 'compartido';
    public const DOCUMENTS = 'documentos';

    public static function options(): array
    {
        return [
            self::SGST => 'SG-SST integral',
            self::PESV => 'PESVial · solo PESV',
            self::EMERGENCIAS => 'Emergencias y Vigilancia · solo módulo operativo',
        ];
    }

    public static function conditions(): array
    {
        return ['Pagado', 'Sugerido'];
    }

    public static function current(): string
    {
        if (!Auth::check() || Auth::role() === 'SuperAdmin') {
            return self::SGST;
        }
        return self::forTenant(Auth::tenantId());
    }

    public static function forTenant(?int $tenantId): string
    {
        if ($tenantId === null) {
            return self::SGST;
        }
        $row = Database::first(
            'SELECT p.modulo FROM tenants t LEFT JOIN planes_saas p ON p.id = t.plan_id WHERE t.id = ?',
            [$tenantId]
        );
        $module = (string) ($row['modulo'] ?? self::SGST);
        return isset(self::options()[$module]) ? $module : self::SGST;
    }

    public static function label(string $module): string
    {
        return self::options()[$module] ?? self::options()[self::SGST];
    }

    public static function shortLabel(string $module): string
    {
        return match ($module) {
            self::PESV => 'PESVial',
            self::EMERGENCIAS => 'Emergencias y Vigilancia',
            default => 'SG-SST',
        };
    }

    public static function allows(string $required, ?string $module = null): bool
    {
        $module ??= self::current();
        if ($module === self::SGST || in_array($required, [self::SHARED, self::DOCUMENTS], true)) {
            return true;
        }
        return $required === $module;
    }

    public static function documentMatches(array $document, string $module): bool
    {
        if ($module === self::SGST) {
            return true;
        }
        $code = (string) ($document['codigo_estandar'] ?? '');
        $library = (string) ($document['biblioteca_id'] ?? '');
        return match ($module) {
            self::PESV => $code === 'PESV-INDICE' || $library === 'PESV-INDICE' || str_starts_with($library, 'PESV-'),
            self::EMERGENCIAS => str_starts_with($code, 'EMG-PLAN-') || str_starts_with($code, 'PYP-DOC-'),
            default => false,
        };
    }
}
