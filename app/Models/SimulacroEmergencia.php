<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;

final class SimulacroEmergencia
{
    public static function forPlan(int $planId, int $tenantId): array
    {
        return Database::query('SELECT * FROM simulacros_emergencia WHERE plan_id = ? AND tenant_id = ? ORDER BY fecha DESC, id DESC', [$planId, $tenantId]);
    }

    public static function findForTenant(int $id, int $tenantId): ?array
    {
        return Database::first('SELECT * FROM simulacros_emergencia WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }

    public static function create(array $data): int
    {
        self::assertRealized($data);
        Database::execute(
            'INSERT INTO simulacros_emergencia (tenant_id, plan_id, fecha, tipo, participantes, responsable, resultado, acciones_mejora, estado, participantes_detalle, evaluacion, lecciones_aprendidas)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $data['tenant_id'], (int) $data['plan_id'], $data['fecha'], trim($data['tipo']), (int) $data['participantes'], trim($data['responsable']), trim($data['resultado'] ?? ''), trim($data['acciones_mejora'] ?? ''), $data['estado'], trim($data['participantes_detalle'] ?? ''), trim($data['evaluacion'] ?? ''), trim($data['lecciones_aprendidas'] ?? '')]
        );
        return Database::lastId();
    }

    public static function update(int $id, int $tenantId, array $data): bool
    {
        self::assertRealized($data);
        return Database::execute(
            'UPDATE simulacros_emergencia SET fecha = ?, tipo = ?, participantes = ?, responsable = ?, resultado = ?,
                                                acciones_mejora = ?, estado = ?, participantes_detalle = ?, evaluacion = ?, lecciones_aprendidas = ?, updated_at = CURRENT_TIMESTAMP
             WHERE id = ? AND tenant_id = ?',
            [$data['fecha'], trim($data['tipo']), (int) $data['participantes'], trim($data['responsable']), trim($data['resultado'] ?? ''), trim($data['acciones_mejora'] ?? ''), $data['estado'], trim($data['participantes_detalle'] ?? ''), trim($data['evaluacion'] ?? ''), trim($data['lecciones_aprendidas'] ?? ''), $id, $tenantId]
        ) === 1;
    }

    public static function setStatus(int $id, int $tenantId, string $status, string $result = '', string $improvements = ''): void
    {
        $current = self::findForTenant($id, $tenantId);
        if (!$current) throw new DomainException('El simulacro no existe o no pertenece a la empresa.');
        $data = array_merge($current, ['estado' => $status, 'resultado' => trim($result), 'acciones_mejora' => trim($improvements)]);
        self::update($id, $tenantId, $data);
    }

    private static function assertRealized(array $data): void
    {
        if (($data['estado'] ?? '') !== 'Realizado') return;
        if ((int) ($data['participantes'] ?? 0) < 1 || trim((string) ($data['participantes_detalle'] ?? '')) === '') throw new DomainException('Un simulacro realizado requiere participantes y detalle verificable de participación.');
        foreach (['resultado' => 'resultado', 'evaluacion' => 'evaluación', 'lecciones_aprendidas' => 'lecciones aprendidas'] as $field => $label) {
            if (trim((string) ($data[$field] ?? '')) === '') throw new DomainException('Un simulacro realizado requiere ' . $label . '.');
        }
    }

    public static function delete(int $id, int $tenantId): void
    {
        Database::execute('DELETE FROM simulacros_emergencia WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
    }
}
