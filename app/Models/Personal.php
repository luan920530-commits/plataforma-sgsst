<?php
namespace App\Models;

use App\Database;

class Personal
{
    public static function porEmpresa(int $empresaId): array
    {
        return Database::query(
            'SELECT * FROM personal WHERE empresa_id = ? AND activo = 1 ORDER BY nombre_completo',
            [$empresaId]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::first('SELECT * FROM personal WHERE id = ?', [$id]);
    }

    public static function crear(int $empresaId, array $d): int
    {
        Database::execute(
            'INSERT INTO personal
             (empresa_id, nombre_completo, cedula, email, cargo, rh, celular,
              dependencia, entrenado_brigada, eps, afp,
              contacto_emergencia_nombre, contacto_emergencia_tel, alergias, firma_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $empresaId,
                $d['nombre_completo'],
                $d['cedula'] ?? null,
                $d['email'] ?? null,
                $d['cargo'] ?? null,
                $d['rh'] ?? null,
                $d['celular'] ?? null,
                $d['dependencia'] ?? null,
                $d['entrenado_brigada'] ?? null,
                $d['eps'] ?? null,
                $d['afp'] ?? null,
                $d['contacto_emergencia_nombre'] ?? null,
                $d['contacto_emergencia_tel'] ?? null,
                $d['alergias'] ?? null,
                $d['firma_url'] ?? null,
            ]
        );
        return Database::lastId();
    }

    public static function actualizar(int $id, array $d): void
    {
        Database::execute(
            'UPDATE personal SET
             nombre_completo = ?, cedula = ?, email = ?, cargo = ?, rh = ?,
             celular = ?, dependencia = ?, entrenado_brigada = ?, eps = ?, afp = ?,
             contacto_emergencia_nombre = ?, contacto_emergencia_tel = ?,
             alergias = ?, firma_url = ?
             WHERE id = ?',
            [
                $d['nombre_completo'],
                $d['cedula'] ?? null,
                $d['email'] ?? null,
                $d['cargo'] ?? null,
                $d['rh'] ?? null,
                $d['celular'] ?? null,
                $d['dependencia'] ?? null,
                $d['entrenado_brigada'] ?? null,
                $d['eps'] ?? null,
                $d['afp'] ?? null,
                $d['contacto_emergencia_nombre'] ?? null,
                $d['contacto_emergencia_tel'] ?? null,
                $d['alergias'] ?? null,
                $d['firma_url'] ?? null,
                $id,
            ]
        );
    }

    public static function eliminar(int $id): void
    {
        Database::execute('UPDATE personal SET activo = 0 WHERE id = ?', [$id]);
    }

    public static function contarBrigadistas(int $empresaId): int
    {
        return (int) Database::first(
            'SELECT COUNT(*) c FROM personal
             WHERE empresa_id = ? AND activo = 1
             AND entrenado_brigada IS NOT NULL AND entrenado_brigada != ""',
            [$empresaId]
        )['c'];
    }
}
