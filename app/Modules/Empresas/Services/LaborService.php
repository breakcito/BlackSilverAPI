<?php

namespace App\Modules\Empresas\Services;

use App\Modules\Empresas\Models\Labor;
use App\Shared\Responses\ApiResponse;

class LaborService
{
    public function get_labores(?int $id_concesion = null)
    {
        $labores = Labor::get_labores($id_concesion);
        return ApiResponse::success($labores);
    }

    public function get_labor_by_id(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }
        return ApiResponse::success($labor);
    }

    public function crear_labor(int $id_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        $id_labor = Labor::crear_labor(
            $id_concesion,
            $nombre,
            $descripcion,
            $tipo_labor,
            $tipo_sostenimiento
        );
        return ApiResponse::success(Labor::get_labor_by_id($id_labor));
    }

    public function update_labor(int $id, int $id_concesion, string $nombre, ?string $descripcion, string $tipo_labor, string $tipo_sostenimiento)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::update_labor(
            $id,
            $id_concesion,
            $nombre,
            $descripcion,
            $tipo_labor,
            $tipo_sostenimiento
        );

        return ApiResponse::success(['mensaje' => 'Labor actualizada correctamente']);
    }

    public function delete_labor(int $id)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::delete_labor($id);
        return ApiResponse::success(['mensaje' => 'Labor eliminada correctamente']);
    }
}
