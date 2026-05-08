<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\AlmacenesData;
use App\Data\EmpleadosData;
use App\Data\LotesProductosData;
use App\Data\UnidadesMedidaData;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;

class AuxService
{
    /**
     * Obtiene los almacenes donde el empleado es responsable
     */
    public static function get_almacenes_autorizados(int $id_empleado)
    {
        $data = AlmacenesData::get_almacenes(id_responsable: $id_empleado, es_principal: 0);
        return ApiResponse::success($data);
    }

    /**
     * Datos iniciales para el registro de un requerimiento
     */
    public static function get_data_to_registro(int $id_empleado)
    {
        // $almacenes = AlmacenesData::get_almacenes(id_responsable: $id_empleado);
        $productos = RequerimientosDetalleData::get_productos();
        $unidades = UnidadesMedidaData::get_unidades();

        return ApiResponse::success([
            // 'almacenes' => $almacenes,
            'productos' => $productos,
            'unidades' => $unidades,
        ]);
    }

    /**
     * Obtiene la lista de minas que son abastecidas por un almacen
     */
    public static function get_minas_by_almacen(int $id_almacen)
    {
        $minas = RequerimientosData::get_minas_by_almacen($id_almacen);
        return ApiResponse::success($minas);
    }

    /**
     * Obtiene la lista de responsables y labores de una mina
     */
    public static function get_data_by_mina(int $id_mina)
    {
        $responsables = RequerimientosData::get_responsables_by_mina($id_mina);
        $labores = RequerimientosData::get_labores($id_mina);

        return ApiResponse::success([
            'responsables' => $responsables,
            'labores' => $labores,
        ]);
    }

    /**
     * Busca los empleados por nombre, codigo
     */
    public static function get_empleados()
    {
        $data = EmpleadosData::get_empleados();
        return ApiResponse::success($data);
    }

    /**
     * Obtiene los lotes disponibles para una lista de productos de un almacén.
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $data = LotesProductosData::get_lotes_disponibles($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }
}
