<?php

namespace App\Modules\MinasLabores\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\MinasLabores\Data\ResponsablesData;

class ResponsablesService
{

    public static function get_historial_responsables(int $id_mina): array|object
    {
        $historial = ResponsablesData::get_historial_responsables($id_mina);

        return ApiResponse::success($historial);
    }

    public static function get_contratistas_disponibles(int $id_mina): array|object
    {
        $contratistas = ResponsablesData::get_contratistas_disponibles($id_mina);

        return ApiResponse::success($contratistas);
    }

    public static function asignar_responsable(int $id_mina, int $id_contratista, string $fecha_inicio): array|object
    {
        $id_res = ResponsablesData::nuevo_responsable($id_mina, $id_contratista, $fecha_inicio);

        $asignado = ResponsablesData::get_responsable_by_id($id_res);

        return ApiResponse::success($asignado, 'Responsable asignado correctamente');
    }

    public static function inactivar_responsable(int $id_responsable_mina, string $fecha_fin): array|object
    {
        ResponsablesData::inactivar_responsable($id_responsable_mina, $fecha_fin);

        return ApiResponse::success(null, 'Responsable inactivado correctamente');
    }
}
