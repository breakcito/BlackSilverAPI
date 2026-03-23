<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\AuxData;

class AuxService
{

    public static function get_catalogo(int $id_empleado)
    {
        $almacenes = AuxData::get_almacenes($id_empleado);
        $productos = AuxData::get_productos();
        $unidades_medida = AuxData::get_unidades_medida();

        return ApiResponse::success([
            'almacenes' => $almacenes,
            'productos' => $productos,
            'unidades_medida' => $unidades_medida,
        ]);
    }

    public static function get_lotes_disponibles(int $id_almacen_solicitante, array $id_productos)
    {
        $lotes = AuxData::get_lotes_disponibles($id_almacen_solicitante, $id_productos);
        return ApiResponse::success($lotes);
    }
}
