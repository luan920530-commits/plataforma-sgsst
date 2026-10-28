<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class Indicador
{
    public static function forTenant(int $tenantId): array
    {
        $rows = Database::query('SELECT i.*,u.nombre verificado_por_nombre FROM indicadores i LEFT JOIN users u ON u.id=i.verificado_por WHERE i.tenant_id = ? ORDER BY i.fecha_corte DESC, i.id DESC', [$tenantId]);
        $rows = array_map(self::hydrate(...), $rows);
        $groups = [];
        foreach ($rows as $index => $row) $groups[$row['tipo'] . '|' . text_lower(trim((string) $row['nombre']))][] = $index;
        foreach ($groups as $indexes) {
            foreach ($indexes as $position => $index) {
                $olderIndex = $indexes[$position + 1] ?? null;
                $rows[$index]['anterior_resultado'] = $olderIndex !== null ? (float) $rows[$olderIndex]['resultado'] : null;
                $rows[$index]['variacion'] = $olderIndex !== null ? round((float) $rows[$index]['resultado'] - (float) $rows[$olderIndex]['resultado'], 2) : null;
                $rows[$index]['referencia_diferencia'] = $rows[$index]['referencia_valor'] !== null ? round((float) $rows[$index]['resultado'] - (float) $rows[$index]['referencia_valor'], 2) : null;
            }
        }
        return $rows;
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        $row = Database::first('SELECT i.*,u.nombre verificado_por_nombre FROM indicadores i LEFT JOIN users u ON u.id=i.verificado_por WHERE i.id = ? AND i.tenant_id = ?', [$id, $tenantId]);
        return $row ? self::hydrate($row) : null;
    }

    public static function latest(int $tenantId, int $limit = 6): array
    {
        return array_slice(self::forTenant($tenantId), 0, max(1, $limit));
    }

    public static function create(array $data): int
    {
        Database::execute('INSERT INTO indicadores (tenant_id,tipo,formula_tipo,nombre,periodo,numerador,denominador,factor,resultado,unidad,meta,fecha_corte,fuente_numerador,fuente_denominador,responsable,observaciones,referencia_valor,referencia_fuente,referencia_periodo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', self::values($data, true));
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): bool
    {
        $values = self::values($data, false);$values[] = $id;$values[] = $tenantId;
        return Database::execute('UPDATE indicadores SET tipo=?,formula_tipo=?,nombre=?,periodo=?,numerador=?,denominador=?,factor=?,resultado=?,unidad=?,meta=?,fecha_corte=?,fuente_numerador=?,fuente_denominador=?,responsable=?,observaciones=?,referencia_valor=?,referencia_fuente=?,referencia_periodo=?,verificado_por=NULL,verificado_at=NULL WHERE id=? AND tenant_id=?', $values) === 1;
    }

    private static function values(array $data, bool $creating): array
    {
        $presentism = $data['tipo'] === 'Presentismo';
        $values = [
            $presentism ? 'Personalizado' : $data['tipo'], $presentism ? 'Presentismo' : '', trim($data['nombre']), trim($data['periodo']), (float) $data['numerador'], (float) $data['denominador'],
            (float) $data['factor'], (float) $data['resultado'], $data['unidad'], $data['meta'] === null || $data['meta'] === '' ? null : (float) $data['meta'],
            $data['fecha_corte'], trim($data['fuente_numerador'] ?? ''), trim($data['fuente_denominador'] ?? ''), trim($data['responsable'] ?? ''),
            trim($data['observaciones'] ?? ''), $data['referencia_valor'] === null || $data['referencia_valor'] === '' ? null : (float) $data['referencia_valor'],
            trim($data['referencia_fuente'] ?? ''), trim($data['referencia_periodo'] ?? ''),
        ];
        if ($creating) array_unshift($values, (int) $data['tenant_id']);
        return $values;
    }

    private static function hydrate(array $row): array
    {
        $row['tipo_almacenado'] = $row['tipo'];
        if (($row['formula_tipo'] ?? '') === 'Presentismo' && $row['tipo'] === 'Personalizado') $row['tipo'] = 'Presentismo';
        return $row;
    }

    public static function verify(int $id, int $tenantId, int $userId): void
    {
        $indicator = self::findForTenant($id, $tenantId) ?: throw new DomainException('Indicador no encontrado.');
        if (trim((string) $indicator['fuente_numerador']) === '' || trim((string) $indicator['fuente_denominador']) === '' || trim((string) $indicator['responsable']) === '') throw new DomainException('Complete las dos fuentes y el responsable antes de verificar.');
        if ($indicator['referencia_valor'] !== null && (trim((string) $indicator['referencia_fuente']) === '' || trim((string) $indicator['referencia_periodo']) === '')) throw new DomainException('La referencia comparativa necesita fuente y periodo.');
        if (Evidencia::reviewedCountForEntity($tenantId, 'indicador', (string) $id) === 0) throw new DomainException('La verificación requiere un soporte revisado de los datos fuente.');
        Database::execute('UPDATE indicadores SET verificado_por=?,verificado_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?', [$userId, $id, $tenantId]);
    }

    public static function delete(int $id, int $tenantId): bool
    {
        return Database::execute('DELETE FROM indicadores WHERE id = ? AND tenant_id = ?', [$id, $tenantId]) === 1;
    }
}
