<?php

namespace App\Modules\Inventario\Services;

use App\Modules\Inventario\Models\KardexProducto;
use App\Shared\Responses\ApiResponse;

class KardexService
{
    /**
     * Listar movimientos de kardex.
     */
    public function get_movimientos(int $id_lote)
    {
        $movimientos = KardexProducto::get_kardex_by_lote($id_lote);
        return ApiResponse::success($movimientos);
    }
}
