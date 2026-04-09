<?php

namespace App\Views\Empleados;

use App\Shared\Responses\ApiResponse;
use App\Views\Empleados\Data\EmpleadosData;
use Illuminate\Support\Facades\DB;

class AsignacionLaborEmpleadoService
{
    /**
     * Asignar nuevas labores a un empleado
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
        $duplicadas = [];
        $invalidas  = [];

        DB::transaction(function () use ($ids_labor, $id_empleado, $id_mina, &$asignadas, &$duplicadas, &$invalidas) {
            foreach ($ids_labor as $id_labor) {
                // Verificar que la labor pertenece a la mina del empleado
                if (!EmpleadosData::labor_pertenece_a_mina($id_labor, $id_mina)) {
                    $invalidas[] = $id_labor;
                    continue;
                }

                // Verificar que no esté ya asignada
                if (EmpleadosData::existe_labor_empleado($id_empleado, $id_labor)) {
                    $duplicadas[] = $id_labor;
                    continue;
                }

                EmpleadosData::asignar_labor($id_empleado, $id_labor);
                $asignadas++;
            }
        });

        if ($asignadas === 0) {
            if (!empty($duplicadas)) {
                return ApiResponse::error('Las labores seleccionadas ya están asignadas a este empleado.');
            }
            if (!empty($invalidas)) {
                return ApiResponse::error('Las labores seleccionadas no pertenecen a la mina del empleado.');
            }
            return ApiResponse::error('No se seleccionó ninguna labor nueva.');
        }

        return ApiResponse::success(null, "Se asignaron {$asignadas} labor(es) correctamente.");
    }
}
