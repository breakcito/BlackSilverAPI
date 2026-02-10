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

    public function crear_labor(array $data)
    {
        $id_labor = Labor::crear_labor(
            $data['id_concesion'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo_labor'],
            $data['tipo_sostenimiento']
        );
        return ApiResponse::success(['id_labor' => $id_labor, 'mensaje' => 'Labor creada correctamente']);
    }

    public function update_labor(int $id, array $data)
    {
        $labor = Labor::get_labor_by_id($id);
        if (!$labor) {
            return ApiResponse::error('Labor no encontrada');
        }

        Labor::update_labor(
            $id,
            $data['id_concesion'],
            $data['nombre'],
            $data['descripcion'] ?? null,
            $data['tipo_labor'],
            $data['tipo_sostenimiento']
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
