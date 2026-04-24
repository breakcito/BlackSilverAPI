<?php

namespace App\Modules\Cotizaciones\Service;

use App\Modules\Cotizaciones\Data\AuxData;
use App\Shared\Responses\ApiResponse;
use App\Data\AlmacenesData;
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

    /**
     * Obtener almacenes activos
     */
    public static function get_almacenes(): array
    {
        $almacenes = AlmacenesData::get_almacenes();
        return ApiResponse::success($almacenes);
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
