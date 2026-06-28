<?php

namespace App\Services;

use App\Data\AgenciasData;
use App\Shared\Responses\ApiResponse;

class AgenciasService
{
    /**
     * Listar agencias.
     */
    public static function get_agencias(?int $id_agencia = null): array
    {
        $agencias = AgenciasData::get_agencias($id_agencia);
        return ApiResponse::success($agencias, "Agencias de transporte obtenidas correctamente");
    }

    /**
     * Registrar agencia.
     */
    public static function crear_agencia(string $razon_social, bool $return_object = false): array
    {
        if (AgenciasData::ya_existe($razon_social)) {
            return ApiResponse::error("La agencia con esa razón social ya existe");
        }

        $id = AgenciasData::crear_agencia($razon_social);

        if ($return_object) {
            $new_agencia = AgenciasData::get_agencias($id);
            return ApiResponse::success($new_agencia, "Agencia creada correctamente");
        }

        return ApiResponse::success($id, "Agencia creada correctamente");
    }
}
