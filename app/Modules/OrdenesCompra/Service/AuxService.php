<?php

namespace App\Modules\OrdenesCompra\Service;

use App\Data\AlmacenesData;
use App\Data\LotesProductosData;
use App\Data\PersonalExternoData;
use App\Shared\Responses\ApiResponse;


class AuxService
{
    public static function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    public static function get_lotes_disponibles(int $id_almacen_recepcionista, array $id_productos)
    {
        $lotes = LotesProductosData::get_lotes_disponibles($id_almacen_recepcionista, $id_productos);
        return ApiResponse::success($lotes);
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
