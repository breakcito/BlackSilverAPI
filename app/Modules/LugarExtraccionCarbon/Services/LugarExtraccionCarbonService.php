<?php

namespace App\Modules\LugarExtraccionCarbon\Services;

use App\Modules\LugarExtraccionCarbon\Data\LugarExtraccionCarbonData;
use App\Shared\Responses\ApiResponse;

class LugarExtraccionCarbonService
{
    /**
     * Lista los lugares de extraccion de un proveedor.
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $data = LugarExtraccionCarbonData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Lugares de extraccion del proveedor');
    }

    /**
     * Reemplaza el set de lugares asociados al proveedor.
     * @param int[] $lugares Lista de {id_departamento, id_provincia, id_distrito, direccion}
     */
    public static function set_para_proveedor(int $id_proveedor, array $lugares): array
    {
        LugarExtraccionCarbonData::set_para_proveedor($id_proveedor, $lugares);
        $data = LugarExtraccionCarbonData::get_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Lugares de extraccion actualizados');
    }
}