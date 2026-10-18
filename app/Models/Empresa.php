<?php
namespace App\Models;

use App\Database;

class Empresa
{
    public static function todasPorUsuario(int $usuarioId): array
    {
        return Database::query(
            'SELECT * FROM empresas WHERE usuario_id = ? ORDER BY razon_social',
            [$usuarioId]
        );
    }

    public static function buscar(int $id): ?array
    {
        return Database::first('SELECT * FROM empresas WHERE id = ?', [$id]);
    }

    public static function crear(array $d): int
    {
        Database::execute(
            'INSERT INTO empresas
             (usuario_id, razon_social, nit, ciiu, actividad_economica, direccion,
              ciudad, departamento, telefono, email, arl, nivel_riesgo,
              representante_legal, num_trabajadores, aforo_max, area_m2,
              num_pisos, horarios, areas_alto_riesgo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $d['usuario_id'],
                $d['razon_social'],
                $d['nit'],
                $d['ciiu'] ?? null,
                $d['actividad_economica'] ?? null,
                $d['direccion'] ?? null,
                $d['ciudad'] ?? null,
                $d['departamento'] ?? null,
                $d['telefono'] ?? null,
                $d['email'] ?? null,
                $d['arl'] ?? null,
                $d['nivel_riesgo'] ?? 'I',
                $d['representante_legal'] ?? null,
                (int) ($d['num_trabajadores'] ?? 0),
                (int) ($d['aforo_max'] ?? 0),
                (float) ($d['area_m2'] ?? 0),
                (int) ($d['num_pisos'] ?? 1),
                $d['horarios'] ?? null,
                $d['areas_alto_riesgo'] ?? null,
            ]
        );
        return Database::lastId();
    }

    public static function actualizar(int $id, array $d): void
    {
        Database::execute(
            'UPDATE empresas SET
             razon_social = ?, nit = ?, ciiu = ?, actividad_economica = ?,
             direccion = ?, ciudad = ?, departamento = ?, telefono = ?,
             email = ?, arl = ?, nivel_riesgo = ?, representante_legal = ?,
             num_trabajadores = ?, aforo_max = ?, area_m2 = ?, num_pisos = ?,
             horarios = ?, areas_alto_riesgo = ?
             WHERE id = ?',
            [
                $d['razon_social'],
                $d['nit'],
                $d['ciiu'] ?? null,
                $d['actividad_economica'] ?? null,
                $d['direccion'] ?? null,
                $d['ciudad'] ?? null,
                $d['departamento'] ?? null,
                $d['telefono'] ?? null,
                $d['email'] ?? null,
                $d['arl'] ?? null,
                $d['nivel_riesgo'] ?? 'I',
                $d['representante_legal'] ?? null,
                (int) ($d['num_trabajadores'] ?? 0),
                (int) ($d['aforo_max'] ?? 0),
                (float) ($d['area_m2'] ?? 0),
                (int) ($d['num_pisos'] ?? 1),
                $d['horarios'] ?? null,
                $d['areas_alto_riesgo'] ?? null,
                $id,
            ]
        );
    }

    public static function eliminar(int $id): void
    {
        Database::execute('DELETE FROM empresas WHERE id = ?', [$id]);
    }
}
