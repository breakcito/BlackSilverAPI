<?php

namespace App\Views\KardexProductos\Data;

use Illuminate\Support\Facades\DB;

class AuxData
{
    /**
     * Consulta para verificar si un usuario puede ver
     */
    public static function puede_ver_almacenes_all(int $id_usuario)
    {
        $sql = '
        SELECT
            1
        FROM
            acceso_usuario acu
        WHERE
            -- acceso para ver todos los almacenes para la vista de kardex
            acu.id_acceso = 2 AND 
            -- verificar si el usuario puede hacer eso
            acu.id_usuario = :id_usuario
        ';

        $result = DB::selectOne($sql, ['id_usuario' => $id_usuario]);

        return $result ? true : false;
    }
}
