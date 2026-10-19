<?php

namespace App\Models;

class Cambista
{
    /**
     * Inserta un cambista. La tabla es minimal (`id`, `nombre`); la columna
     * `nombre` es VARCHAR(128) NOT NULL.
     */
    public static function insertar(string $nombre): int
    {
        return \Illuminate\Support\Facades\DB::table('cambista')->insertGetId([
            'nombre' => trim($nombre),
        ]);
    }

    /**
     * Lista ordenada por nombre ascendente para alimentar el select del
     * modal de Registrar Anticipo.
     *
     * @return array<int, object{id_cambista:int, nombre:string}>
     */
    public static function listar(): array
    {
        return \Illuminate\Support\Facades\DB::table('cambista')
            ->orderBy('nombre', 'asc')
            ->get(['id AS id_cambista', 'nombre'])
            ->map(fn ($r) => (object) ['id_cambista' => (int) $r->id_cambista, 'nombre' => (string) $r->nombre])
            ->all();
    }
}
