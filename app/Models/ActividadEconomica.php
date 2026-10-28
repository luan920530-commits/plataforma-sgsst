<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ActividadEconomica
{
    public static function all(bool $onlyActive = false, string $search = ''): array
    {
        $sql = 'SELECT * FROM actividades_economicas WHERE 1 = 1';
        $params = [];
        if ($onlyActive) {
            $sql .= ' AND activo = 1';
        }
        if ($search !== '') {
            $sql .= ' AND (descripcion LIKE ? OR codigo_ciuu LIKE ? OR sector LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term);
        }
        return Database::query($sql . ' ORDER BY sector, descripcion', $params);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM actividades_economicas WHERE id = ?', [$id]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            'INSERT INTO actividades_economicas (codigo_ciuu, descripcion, sector, nivel_riesgo_predeterminado,
                                                 perfil_sociodemografico, examenes_medicos, matriz_ipevr_base)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                trim($data['codigo_ciuu']), trim($data['descripcion']), trim($data['sector']),
                $data['nivel_riesgo_predeterminado'], trim($data['perfil_sociodemografico']),
                json_encode($data['examenes_medicos'], JSON_UNESCAPED_UNICODE),
                json_encode($data['matriz_ipevr_base'], JSON_UNESCAPED_UNICODE),
            ]
        );
        return Database::lastId();
    }

    public static function update(int $id, array $data): bool
    {
        return Database::execute(
            'UPDATE actividades_economicas SET codigo_ciuu = ?, descripcion = ?, sector = ?, nivel_riesgo_predeterminado = ?,
                                                 perfil_sociodemografico = ?, examenes_medicos = ?
             WHERE id = ?',
            [
                trim($data['codigo_ciuu']), trim($data['descripcion']), trim($data['sector']), $data['nivel_riesgo_predeterminado'],
                trim($data['perfil_sociodemografico']), json_encode($data['examenes_medicos'], JSON_UNESCAPED_UNICODE), $id,
            ]
        ) === 1;
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::execute('UPDATE actividades_economicas SET activo = ? WHERE id = ?', [$active ? 1 : 0, $id]);
    }

    public static function count(): int
    {
        return (int) (Database::first('SELECT COUNT(*) AS total FROM actividades_economicas')['total'] ?? 0);
    }
}
