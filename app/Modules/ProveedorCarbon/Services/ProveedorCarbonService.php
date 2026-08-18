<?php

namespace App\Modules\ProveedorCarbon\Services;

use App\Modules\ProveedorCarbon\Data\ProveedorCarbonData;
use App\Shared\Responses\ApiResponse;

class ProveedorCarbonService
{
    /**
     * Lista los tipos de carbon asociados a un proveedor.
     */
    public static function get_tipos_por_proveedor(int $id_proveedor): array
    {
        $data = ProveedorCarbonData::get_tipos_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Tipos de carbon del proveedor');
    }

    /**
     * Reemplaza el set de tipos asociados al proveedor.
     * @param int[] $ids_tipo_carbon
     */
    public static function set_tipos_por_proveedor(int $id_proveedor, array $ids_tipo_carbon): array
    {
        ProveedorCarbonData::set_tipos_por_proveedor($id_proveedor, $ids_tipo_carbon);
        $data = ProveedorCarbonData::get_tipos_por_proveedor($id_proveedor);
        return ApiResponse::success($data, 'Tipos de carbon actualizados');
    }
}