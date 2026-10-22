<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class EstandarMinimo
{
    public static function byGroup(int $group): array
    {
        return Database::query('SELECT * FROM estandares_minimos WHERE grupo = ? ORDER BY orden', [$group]);
    }

    public static function all(?int $group = null): array
    {
        if ($group !== null) {
            return self::byGroup($group);
        }
        return Database::query('SELECT * FROM estandares_minimos ORDER BY grupo DESC, orden');
    }

    public static function counts(): array
    {
        $rows = Database::query('SELECT grupo, COUNT(*) AS total FROM estandares_minimos GROUP BY grupo ORDER BY grupo');
        $counts = [7 => 0, 21 => 0, 60 => 0];
        foreach ($rows as $row) {
            $counts[(int) $row['grupo']] = (int) $row['total'];
        }
        return $counts;
    }
}
