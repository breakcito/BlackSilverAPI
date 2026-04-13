<?php

namespace App\Modules\KardexProductos\Service;

use App\Data\AlmacenesData;
use App\Shared\Responses\ApiResponse;
use App\Modules\KardexProductos\Data\AuxData;
use App\Modules\KardexProductos\Data\KardexData;

class KardexService
{
    /**
     * Listar almacenes.
     */
    public static function get_almacenes(int $id_usuario, int $id_empleado)
    {
        // Verificar si el usuario puede ver todos los almacenes
        $puede_ver_todos = AuxData::puede_ver_almacenes_all($id_usuario);

        // si puede ver todos, no filtramos nada
        // si NO puede ver todos, filtramos por los almacenes donde es responsable
        $almacenes = AlmacenesData::get_almacenes(id_responsable: $puede_ver_todos ? null : $id_empleado);

        return ApiResponse::success($almacenes);
    }


    /**
     * Listar movimientos de kardex por almacén.
     */
    public static function get_resumen_kardex(int $id_almacen, int $mes, int $yearcito)
    {
        $movimientos = KardexData::get_resumen_kardex($id_almacen, $mes, $yearcito);
        return ApiResponse::success($movimientos);
    }
}
