<?php

namespace App\Views\LotesProductos\Data;

use Illuminate\Support\Facades\DB;

class AuxData
{
    // Util para determinar de que producto se creara el lote
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.id_unidad_medida_base,
            pr.nombre,
            uni.abreviatura as unidad_medida_base,
            pr.es_perecible,
            pr.es_fiscalizado,
            pr.stock_minimo,
            pr.tiempo_espera_vencimiento,
            pr.periodo_espera_vencimiento,
            pr.dias_espera_vencimiento
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE
            pr.estado = "Activo"
        ORDER BY
            pr.nombre;
        ';

        return DB::select($sql, []);
    }

    public static function get_abreviatura_unidad_medida(int $id_unidad_medida)
    {
        return DB::table('unidad_medida')->where('id', $id_unidad_medida)->value('abreviatura') ?? '';
    }

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
            -- acceso para ver todos los almacenes para la vista de lotes
            acu.id_acceso = 1 AND 
            -- verificar si el usuario puede hacer eso
            acu.id_usuario = :id_usuario
        ';

        $result = DB::selectOne($sql, ['id_usuario' => $id_usuario]);

        return $result ? true : false;
    }
}
