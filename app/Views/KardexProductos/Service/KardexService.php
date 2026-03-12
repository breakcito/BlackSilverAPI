<?php

namespace App\Views\KardexProductos\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\KardexProductos\Data\KardexData;

class KardexService
{
    /**
     * obtener la lista de almacenes donde el empleado es responsable
     */
    public static function get_almacenes(int $id_empleado)
    {
        $movimientos = KardexData::get_almacenes($id_empleado);
        return ApiResponse::success($movimientos);
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
