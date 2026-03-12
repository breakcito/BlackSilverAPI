<?php

namespace App\Views\Organigrama;

use App\Shared\Responses\ApiResponse;
use App\Views\Organigrama\Data\AreasData;
use App\Views\Organigrama\Data\CargosData;

class OrganigramaService
{
    // ÁREAS

    public static function get_areas()
    {
        return ApiResponse::success(AreasData::get_areas());
    }

    public static function crear_area(string $nombre)
    {
        if (AreasData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un área con este nombre.');
        }

        $id = AreasData::crear_area($nombre);
        $nueva = AreasData::get_area_by_id($id);

        return ApiResponse::success($nueva, 'Área creada correctamente');
    }

    // CARGOS

    public static function get_cargos(int $id_area)
    {
        return ApiResponse::success(CargosData::get_cargos(id_area: $id_area));
    }

    public static function crear_cargo(string $nombre, int $id_area)
    {
        if (CargosData::verificar_nombre_duplicado($nombre, $id_area)) {
            return ApiResponse::error('Ya existe este cargo en la misma área.');
        }

        $id = CargosData::crear_cargo($nombre, $id_area);
        $nuevo = CargosData::get_cargo_by_id($id);

        return ApiResponse::success($nuevo, 'Cargo creado correctamente');
    }

    public static function cambiar_estado_cargo(int $id_cargo)
    {
        $nuevo_estado = CargosData::cambiar_estado($id_cargo);

        return ApiResponse::success(null, "Cargo marcado como $nuevo_estado");
    }
}
