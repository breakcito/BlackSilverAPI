<?php

namespace App\Modules\Empleados;

use App\Shared\Responses\ApiResponse;
use App\Modules\Empleados\Data\EmpleadosData;
use Illuminate\Support\Facades\DB;

class AsignacionLaborEmpleadoService
{
    /**
     * Sincronizar las labores de un empleado (Limpiar e Insertar)
     * 
     * @param int $id_empleado
     * @param int[] $ids_labor
     * @return array
     */
    public static function asignar_labores(int $id_empleado, array $ids_labor): array
    {
        // Obtener la mina del empleado
        $id_mina = EmpleadosData::get_mina_empleado($id_empleado);

        if (!$id_mina) {
            return ApiResponse::error('El empleado no tiene una mina asignada.');
        }

        $asignadas  = 0;
        $invalidas  = [];

        DB::transaction(function () use ($ids_labor, $id_empleado, $id_mina, &$asignadas, &$invalidas) {
            // 1. Limpiar labores actuales para este empleado para la sincronización
            EmpleadosData::eliminar_labores_empleado($id_empleado);

            // 2. Insertar las nuevas seleccionadas (si hay)
            foreach ($ids_labor as $id_labor) {
                // Validación de seguridad: que pertenezca a la mina
                if (!EmpleadosData::labor_pertenece_a_mina($id_labor, $id_mina)) {
                    $invalidas[] = $id_labor;
                    continue;
                }

                EmpleadosData::asignar_labor($id_empleado, $id_labor);
                $asignadas++;
            }
        });

        if (!empty($invalidas)) {
            return ApiResponse::success(null, "Cambios guardados, pero se omitieron labores de otras minas.");
        }

        return ApiResponse::success(null, "Labores actualizadas correctamente.");
    }
}
