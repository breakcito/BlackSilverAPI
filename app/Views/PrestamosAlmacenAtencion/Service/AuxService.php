<?php

namespace App\Views\PrestamosAlmacenAtencion\Service;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Data\AuxData;

class AuxService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public static function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AuxData::get_almacenes_autorizados($id_empleado);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los empleados activos para seleccionar como entregador o receptor
     */
    public static function get_empleados()
    {
        $data = AuxData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles de un producto en un almacén
     */
    public static function get_lotes_disponibles(int $id_producto, int $id_almacen)
    {
        $data = AuxData::get_lotes_disponibles($id_producto, $id_almacen);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene las unidades de medida habilitadas.
     */
    public static function get_unidades_medida()
    {
        $data = AuxData::get_unidades_medida();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles del almacén que recibe (destino) para reposición.
     */
    public static function get_lotes_destino(int $id_almacen_solicitante, array $id_productos)
    {
        $data = AuxData::get_lotes_disponibles_destino($id_almacen_solicitante, $id_productos);
        return ApiResponse::success($data);
    }
}
