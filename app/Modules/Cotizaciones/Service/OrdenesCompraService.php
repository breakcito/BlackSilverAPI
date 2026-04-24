<?php

namespace App\Modules\Cotizaciones\Service;

use App\Modules\Cotizaciones\Data\OrdenesCompraData;
use App\Shared\Responses\ApiResponse;

class OrdenesCompraService
{
    public static function get_orden_compra(int $id_orden_compra): array
    {
        $orden_compra = OrdenesCompraData::get_orden_compra($id_orden_compra);
        $orden_compra['detalles'] = OrdenesCompraData::get_detalles_orden_compra($id_orden_compra);

        return ApiResponse::success($orden_compra);
    }
}
