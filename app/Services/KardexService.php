<?php

namespace App\Services;

use App\Models\KardexProducto;
use App\Shared\Responses\ApiResponse;

class KardexService
{
    /**
     * Listar movimientos de kardex por almacén.
     */
    public function get_movimientos(int $id_almacen)
    {
        $movimientos = KardexProducto::get_kardex_by_almacen($id_almacen);

        return ApiResponse::success($movimientos);
    }
}
