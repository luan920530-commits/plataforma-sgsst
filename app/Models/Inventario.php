<?php
namespace App\Models;

use App\Database;

class Inventario
{
    // --- Extintores ---
    public static function extintores(int $analisisId): array
    {
        return Database::query(
            'SELECT * FROM inv_extintores WHERE analisis_id = ? ORDER BY id',
            [$analisisId]
        );
    }

    public static function crearExtintor(int $analisisId, array $d): int
    {
        Database::execute(
            'INSERT INTO inv_extintores
             (analisis_id, numero, ubicacion, tipo, peso, fecha_recarga, capacidad, cumple)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $analisisId,
                $d['numero'] ?? null,
                $d['ubicacion'] ?? null,
                $d['tipo'] ?? null,
                $d['peso'] ?? null,
                $d['fecha_recarga'] ?? null,
                $d['capacidad'] ?? null,
                (int) ($d['cumple'] ?? 1),
            ]
        );
        return Database::lastId();
    }

    // --- Botiquín ---
    public static function botiquin(int $analisisId): array
    {
        return Database::query(
            'SELECT * FROM inv_botiquin WHERE analisis_id = ? ORDER BY id',
            [$analisisId]
        );
    }

    public static function crearBotiquin(int $analisisId, array $d): int
    {
        Database::execute(
            'INSERT INTO inv_botiquin
             (analisis_id, elemento, cantidad, fecha_vencimiento, apto_para_uso)
             VALUES (?, ?, ?, ?, ?)',
            [
                $analisisId,
                $d['elemento'],
                (int) ($d['cantidad'] ?? 1),
                $d['fecha_vencimiento'] ?? null,
                (int) ($d['apto_para_uso'] ?? 1),
            ]
        );
        return Database::lastId();
    }

    // --- Otros ---
    public static function otros(int $analisisId): array
    {
        return Database::query(
            'SELECT * FROM inv_otros WHERE analisis_id = ? ORDER BY categoria',
            [$analisisId]
        );
    }

    public static function crearOtro(int $analisisId, array $d): int
    {
        Database::execute(
            'INSERT INTO inv_otros (analisis_id, categoria, cantidad, descripcion)
             VALUES (?, ?, ?, ?)',
            [
                $analisisId,
                $d['categoria'],
                (int) ($d['cantidad'] ?? 0),
                $d['descripcion'] ?? null,
            ]
        );
        return Database::lastId();
    }
}
