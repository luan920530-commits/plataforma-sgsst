<?php
namespace App;

/** Trazabilidad: quién hizo qué, sobre qué empresa y cuándo (Decreto 1072/2015, conservación documental). */
class Auditoria
{
    public static function registrar(string $accion, ?int $empresaId = null, ?string $entidad = null, ?int $entidadId = null, ?string $detalle = null): void
    {
        try {
            Database::execute(
                'INSERT INTO auditoria_log (usuario_id, empresa_id, accion, entidad, entidad_id, detalle, ip) VALUES (?,?,?,?,?,?,?)',
                [Auth::id(), $empresaId, $accion, $entidad, $entidadId, $detalle,
                 $_SERVER['REMOTE_ADDR'] ?? null]
            );
        } catch (\Throwable) { /* la auditoría nunca debe romper la operación */ }
    }

    public static function porEmpresa(int $empresaId, int $limite = 100): array
    {
        return Database::query(
            'SELECT a.*, u.nombre AS usuario FROM auditoria_log a LEFT JOIN usuarios u ON u.id = a.usuario_id
             WHERE a.empresa_id = ? ORDER BY a.id DESC LIMIT ' . (int) $limite,
            [$empresaId]
        );
    }
}
