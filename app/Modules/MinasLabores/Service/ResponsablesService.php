<?php

namespace App\Modules\MinasLabores\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\MinasLabores\Data\ResponsablesData;

class ResponsablesService
{

    public static function get_historial_responsables(int $id_mina): array|object
    {
        $historial = ResponsablesData::get_responsables($id_mina);

        return ApiResponse::success($historial);
    }

    public static function asignar_responsable(int $id_mina, int $id_empleado, string $fecha_inicio): array|object
    {
        $id_res = ResponsablesData::crear_responsable($id_mina, $id_empleado, $fecha_inicio);

        $asignado = ResponsablesData::get_responsables(id_responsable: $id_res);

        return ApiResponse::success($asignado, 'Responsable asignado correctamente');
    }

    public static function inactivar_responsable(int $id_responsable_mina, string $fecha_fin): array|object
    {
        ResponsablesData::inactivar_responsable($id_responsable_mina, $fecha_fin);

        return ApiResponse::success(null, 'Responsable inactivado correctamente');
    }
}
