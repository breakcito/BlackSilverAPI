<?php

namespace App\Views\PrestamosAlmacenAtencion\Service;

use App\Data\AlmacenesData;
use App\Data\EmpleadosData;
use App\Data\LotesProductosData;
use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;

class AuxService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public static function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AlmacenesData::get_almacenes(id_responsable: $id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los empleados activos para seleccionar como entregador o receptor
     */
    public static function get_empleados()
    {
        $data = EmpleadosData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles de un producto en un almacén
     */
    public static function get_lotes_disponibles(int $id_almacen, int $id_producto)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen, [$id_producto]);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene las unidades de medida habilitadas.
     */
    public static function get_unidades_medida()
    {
        $data = UnidadesMedidaData::get_unidades();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles del almacén que recibe (destino) para reposición.
     */
    public static function get_lotes_destino(int $id_almacen_solicitante, array $id_productos)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen_solicitante, $id_productos);
        return ApiResponse::success($data);
    }
}
