<?php

namespace App\Services;

use App\Data\OficinasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class OficinasService
{
    public static function get_oficinas(
        ?int $id_oficina = null,
        ?int $id_empresa = null,
        ?EstadoBase $estado = EstadoBase::Activo,
    ): array {
        $data = OficinasData::get_oficinas();
        return ApiResponse::success($data, "Oficinas obtenidas correctamente");
    }

    public static function crear_oficina(
        int $id_empresa,
        string $nombre,
        ?string $direccion,
        bool $es_principal = false
    ) {
        $ya_existe = OficinasData::ya_existe($id_empresa, $nombre);

        if ($ya_existe) {
            return ApiResponse::error("Ya existe esa oficina en la empresa.");
        }

        $id = OficinasData::crear_oficina($id_empresa, $nombre, $direccion, $es_principal);
        $nuevaOficina = OficinasData::get_oficinas(id_oficina: $id);
        return ApiResponse::success($nuevaOficina, "Oficina creada correctamente");
    }
}
