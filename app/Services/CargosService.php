<?php
namespace App\Services;

use App\Data\CargosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;

class CargosService
{
    /**
     * Listar cargos, con soporte para filtrar sin área.
     */
    public static function get_cargos(
        ?int $id_cargo = null,
        $id_area = null,
        ?EstadoBase $estado = null,
        ?bool $con_area = null,
    ) {
        $data = CargosData::get_cargos(
            id_cargo: $id_cargo,
            id_area: $id_area,
            estado: $estado,
            con_area: $con_area,
        );

        return ApiResponse::success($data);
    }

    /**
     * Crear cargo con id_area opcional (null = sin área).
     */
    public static function crear_cargo(string $nombre, ?int $id_area): array|object
    {
        if (CargosData::verificar_nombre_duplicado(nombre: $nombre, id_area: $id_area)) {
            $contexto = $id_area ? 'en la misma área' : 'en el sistema.';
            return ApiResponse::error("Ya existe este cargo $contexto.");
        }

        $id = CargosData::crear_cargo($nombre, $id_area);
        $nuevo = CargosData::get_cargos(id_cargo: $id);

        return ApiResponse::success($nuevo, 'Cargo creado correctamente');
    }

    /**
     * Actualizar el área de un cargo (drag & drop). id_area null quita el área.
     */
    public static function actualizar_area_cargo(int $id_cargo, ?int $id_area): array|object
    {
        CargosData::actualizar_area($id_cargo, $id_area);

        return ApiResponse::success(null, 'Área del cargo actualizada');
    }
}
