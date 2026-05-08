<?php

namespace App\Modules\LotesProductos\Data;

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
            pr.es_auditable,
            pr.stock_minimo_base,
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
}
