<?php

namespace App\Modules\Cotizaciones\Service;

use App\Modules\Cotizaciones\Data\AuxData;
use App\Shared\Responses\ApiResponse;
use App\Data\ProveedoresData;

class AuxService
{
    /**
     * Obtener proveedores
     */
    public static function get_proveedores(): array
    {
        $proveedores = ProveedoresData::get_proveedores();
        return ApiResponse::success($proveedores);
    }

    /**
     * Obtener empresas
     */
    public static function get_empresas(): array
    {
        $empresas = AuxData::get_empresas();
        return ApiResponse::success($empresas);
    }
}
