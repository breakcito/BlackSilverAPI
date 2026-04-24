<?php

namespace App\Modules\Cotizaciones\Controller;

use App\Modules\Cotizaciones\Service\OrdenesCompraService;
use Illuminate\Http\JsonResponse;

class OrdenesCompraController
{
    /**
     * Obtener orden de compra
     */
    public function get_orden_compra(int $id_orden_compra): JsonResponse
    {
        return response()->json(OrdenesCompraService::get_orden_compra($id_orden_compra));
    }
}
