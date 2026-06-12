<?php
namespace App\Services;

use App\Data\PersonalExternoData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class PersonalExternoService
{
    /**
     * Obtiene el personal externo
     */
    public static function get_personal(
        ?int $id_personal = null,
        ?int $id_proveedor = null,
        ?EstadoBase $estado = null
    ) {
        return ApiResponse::success(PersonalExternoData::get_personal(
            id_personal: $id_personal,
            id_proveedor: $id_proveedor,
            estado: $estado
        ));
    }

    /**
     * Registrar un nuevo personal externo
     */
    public static function crear_personal(
        ?int $id_proveedor = null,
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $dni = null
    ) {
        $id_personal = PersonalExternoData::crear_personal(
            id_proveedor: $id_proveedor,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni
        );

        return ApiResponse::success(PersonalExternoData::get_personal(id_personal: $id_personal));
    }
}