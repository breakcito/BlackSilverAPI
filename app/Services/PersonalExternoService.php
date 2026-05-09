<?php
namespace App\Services;

use App\Data\PersonalExternoData;
use App\Shared\Responses\ApiResponse;

class PersonalExternoService
{
    /**
     * Obtiene el personal externo
     */
    public static function get_personal()
    {
        return ApiResponse::success(PersonalExternoData::get_personal());
    }

    /**
     * Registrar un nuevo personal externo
     */
    public static function crear_personal(
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