<?php

namespace App\Views\Proveedores\Data;

use Illuminate\Support\Facades\DB;

class BancosData
{
    public function get_bancos(): array
    {
        return DB::select('
            SELECT
                bc.id AS id_banco,
                bc.abreviatura,
                bc.nombre
            FROM
                banco bc
            WHERE
                bc.estado = "Activo"
            ORDER BY bc.nombre ASC;
        ');
    }

    public function crear_banco(string $nombre, string $abreviatura): int
    {
        return DB::table('banco')->insertGetId([
            'nombre' => $nombre,
            'abreviatura' => $abreviatura,
            'estado' => 'Activo'
        ]);
    }
}
