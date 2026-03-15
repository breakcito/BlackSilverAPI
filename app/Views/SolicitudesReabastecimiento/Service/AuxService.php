<?php

namespace App\Views\SolicitudesReabastecimiento\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Data\AuxData;

class AuxService
{

    public function get_catalogo(int $id_empleado)
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
}
