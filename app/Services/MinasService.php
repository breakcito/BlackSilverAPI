<?php
namespace App\Services;

use App\Data\MinasData;
use App\Shared\Responses\ApiResponse;
class MinasService
{
    /**
     * Listar minas.
     */
    public static function get_minas(
        ?int $id_mina = null,
        ?int $id_concesion = null,
        ?int $id_empleado_responsable = null,
        ?int $id_almacen_abastece = null
    ) {
        $minas = MinasData::get_minas(
            id_mina: $id_mina,
            id_concesion: $id_concesion,
            id_empleado_responsable: $id_empleado_responsable,
            id_almacen_abastece: $id_almacen_abastece
        );

        return ApiResponse::success($minas);
    }
}