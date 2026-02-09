<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Concesion;
use App\Shared\Responses\ApiResponse;

/**
 * Servicio para lógica de negocio del menú de navegación.
 */
class ConcesionService
{
    public function get_concesiones()
    {
        $concesiones = Concesion::get_concesiones();
        return ApiResponse::success($concesiones);
    }

    public function get_concesiones_by_empresa(int $id_empresa)
    {
        $concesiones = Concesion::get_concesiones_by_empresa($id_empresa);
        return ApiResponse::success($concesiones);
    }

    public function crear_concesion(int $id_empresa, string $nombre)
    {
        // verificar que no exista una concesion con el mismo nombre y empresa
        $existe = Concesion::verificar_concesion_existente($id_empresa, $nombre);
        if ($existe) {
            return ApiResponse::error('Ya existe una concesion con el mismo nombre');
        }

        $id_concesion = Concesion::crear_concesion($id_empresa, $nombre);
        return ApiResponse::success(["id_concesion" => $id_concesion]);
    }
}
