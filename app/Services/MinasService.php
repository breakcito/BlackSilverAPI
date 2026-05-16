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
        ?int $id_contratista_responsable = null
    ) {
        $minas = MinasData::get_minas(
            id_mina: $id_mina,
            id_concesion: $id_concesion,
            id_contratista_responsable: $id_contratista_responsable
        );

        return ApiResponse::success($minas);
    }
}