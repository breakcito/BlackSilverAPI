<?php

namespace App\Modules\Cotizaciones\Service;

use App\Shared\Responses\ApiResponse;
use App\Data\UnidadesMedidaData;
use App\Data\ProductosData;
use App\Data\ProveedoresData;

class AuxService
{

    /**
     * Obtener unidades de medida
     */
    public static function get_unidades_medida(): array
    {
        $unidades = UnidadesMedidaData::get_unidades();
        return ApiResponse::success($unidades);
    }

    /**
     * Obtener productos
     */
    public static function get_productos(): array
    {
        $productos = ProductosData::get_productos();
        return ApiResponse::success($productos);
    }

    /**
     * Obtener proveedores
     */
    public static function get_proveedores(): array
    {
        $proveedores = ProveedoresData::get_proveedores();
        return ApiResponse::success($proveedores);
    }
}
