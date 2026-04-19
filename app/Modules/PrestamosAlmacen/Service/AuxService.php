<?php

namespace App\Modules\PrestamosAlmacen\Service;

use App\Data\AlmacenesData;
use App\Data\LotesProductosData;
use App\Data\PersonalExternoData;
use App\Shared\Responses\ApiResponse;

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
     * Obtiene el personal externo
     */
    public static function get_personal_externo()
    {
        return PersonalExternoData::get_personal();
    }

    /**
     * Registrar un nuevo personal externo
     */
    public static function crear_personal_externo(
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $dni = null
    ) {
        return PersonalExternoData::crear_personal(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni
        );
    }
}
