<?php

namespace App\Views\SolicitudesReabastecimiento\Data;

use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

class AuxData
{

    // Obtener toda la lista de productos junto a la abreviatura de su unidad de medida
    public static function get_productos()
    {
        $sql = '
        SELECT
            pr.id AS id_producto,
            pr.nombre,
            uni.id_unidad_medida,
            uni.nombre as unidad_medida_base,
            uni.abreviatura as unidad_medida_base_abv
        FROM
            producto pr
        INNER JOIN unidad_medida uni ON
            uni.id = pr.id_unidad_medida_base
        WHERE 
            pr.estado = "Activo"
        ';

        return DB::select($sql);
    }

    // Obtener la lista de almacenes en las que el empleado
    // solicitante es reesponsable
    public static function get_almacenes(int $id_empleado)
    {
        $sql = '
        SELECT DISTINCT
            alm.id AS id_almacen,
            alm.nombre
        FROM
            almacen alm
        INNER JOIN responsable_almacen res ON
            res.id_almacen = alm.id
        WHERE
            alm.es_principal != 1 AND -- que no sea un almacen principal
            res.id_empleado = :id_empleado AND -- donde el empleado sea responsable
            res.estado = "Activo" -- y su responsabilidad siga vigente
        ';

        return DB::select($sql, ["id_empleado" => $id_empleado]);
    }

    // Listar unidades de medida.
    public static function get_unidades_medida()
    {
        return UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura')
            ->orderBy('nombre', 'asc');
    }
}
