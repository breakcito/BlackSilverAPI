<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Service;

use App\Data\AlmacenesData;
use App\Data\EmpleadosData;
use App\Data\LotesProductosData;
use App\Data\PersonalExternoData;
use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Data\AuxData;

class AuxService
{
    /**
     * Obtiene los almacenes    
     */
    public static function get_almacenes(bool $es_principal = false)
    {
        $data = AlmacenesData::get_almacenes(es_principal: $es_principal ? 1 : 0);
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

    /**
     * Obtiene los almacenes que tienen stock de los productos solicitados
     */
    public static function get_almacenes_con_stock(int $id_almacen_excluido, array $ids_productos)
    {
        $data = AuxData::get_almacenes_con_stock($id_almacen_excluido, $ids_productos);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el stock total de uno o varios productos en un almacén específico.
     * Solo suma el stock de lotes activos y que no estén vencidos.
     */
    public static function get_stock_total_almacen_por_productos(int $id_almacen, array $ids_productos)
    {
        $data = AuxData::get_stock_total_almacen_por_productos($id_almacen, $ids_productos);
        return ApiResponse::success($data);
    }

    /**
     * Obtiene el personal externo
     */
    public static function get_personal_externo()
    {
        return ApiResponse::success(PersonalExternoData::get_personal());
    }

    /**
     * Registrar un nuevo personal externo
     */
    public static function crear_personal_externo(
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $dni = null
    ) {
        $id_personal = PersonalExternoData::crear_personal(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni
        );

        return ApiResponse::success(PersonalExternoData::get_personal(id_personal: $id_personal));
    }
}
