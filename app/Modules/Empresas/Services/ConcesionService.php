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
    public function update_concesion(int $id, string $nombre)
    {
        $concesion = Concesion::get_concesion_by_id($id);
        if (!$concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        // verificar si el nombre ya existe en otra concesion de la misma empresa
        $existe = Concesion::verificar_concesion_existente($concesion->id_empresa, $nombre);
        if ($existe) {
            return ApiResponse::error('Ya existe una concesion con el mismo nombre');
        }

        Concesion::update_concesion($id, $nombre);
        return ApiResponse::success(['mensaje' => 'Concesion actualizada correctamente']);
    }

    public function delete_concesion(int $id)
    {
        $concesion = Concesion::get_concesion_by_id($id);
        if (!$concesion) {
            return ApiResponse::error('Concesion no encontrada');
        }

        Concesion::delete_concesion($id);
        return ApiResponse::success(['mensaje' => 'Concesion eliminada correctamente']);
    }
}
