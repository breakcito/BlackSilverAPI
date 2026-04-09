<?php

namespace App\Views\Empleados;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Shared\Responses\ApiResponse;
use App\Views\Empleados\Data\EmpleadosData;

class AsignacionLaborEmpleadoController
{
    /**
     * Obtener labores disponibles para una mina (opcionalmente filtrando por empleado)
     */
    public function get_labores_disponibles(Request $request, int $id_mina): JsonResponse
    {
        $id_empleado = $request->query('id_empleado') ? (int) $request->query('id_empleado') : null;
        
        $labores = EmpleadosData::get_labores_disponibles_mina($id_mina, $id_empleado);
        
        // Error corregido: Envolver en ApiResponse::success
        return response()->json(ApiResponse::success($labores));
    }

    /**
     * Obtener labores asignadas a un empleado
     */
    public function get_labores_empleado(Request $request, int $id_empleado): JsonResponse
    {
        $labores = EmpleadosData::get_labores_empleado($id_empleado);
        
        // Error corregido: Envolver en ApiResponse::success
        return response()->json(ApiResponse::success($labores));
    }

    /**
     * Asignar nuevas labores a un empleado
     */
    public function asignar_labores(Request $request, int $id_empleado): JsonResponse
    {
        $ids_labor = $request->input('ids_labor');

        if (!is_array($ids_labor) || empty($ids_labor)) {
            return response()->json(ApiResponse::error('Debe seleccionar al menos una labor.'));
        }

        $result = AsignacionLaborEmpleadoService::asignar_labores(
            id_empleado: $id_empleado,
            ids_labor:   $ids_labor
        );

        return response()->json($result);
    }
}
