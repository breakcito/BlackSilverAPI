<?php

namespace App\Modules\Empleados;

use App\Shared\Responses\ApiResponse;
use App\Modules\Empleados\Data\EmpleadosData;
use Illuminate\Support\Facades\DB;

class AsignacionLaborEmpleadoService
{
    /**
     * Sincronizar la gestión operativa del empleado (Mina y Labores)
     * 
     * @param int $id_empleado
     * @param int|null $id_mina
     * @param int[] $ids_labor
     * @return array
     */
    public static function asignar_labores(int $id_empleado, ?int $id_mina, array $ids_labor): array
    {
        $asignadas  = 0;
        $invalidas  = [];

        DB::transaction(function () use ($ids_labor, $id_empleado, $id_mina, &$asignadas, &$invalidas) {
            // 1. Actualizar la mina del empleado
            EmpleadosData::actualizar_mina($id_empleado, $id_mina);

            // 2. Limpiar labores actuales para este empleado para la sincronización
            EmpleadosData::eliminar_labores_empleado($id_empleado);

            // 3. Insertar las nuevas seleccionadas (solo si hay mina asignada)
            if ($id_mina) {
                foreach ($ids_labor as $id_labor) {
                    // Validación de seguridad: que pertenezca a la mina
                    if (!EmpleadosData::labor_pertenece_a_mina($id_labor, $id_mina)) {
                        $invalidas[] = $id_labor;
                        continue;
                    }

                    EmpleadosData::asignar_labor($id_empleado, $id_labor);
                    $asignadas++;
                }
            }
        });

        $mensaje = "Gestión operativa actualizada correctamente.";
        if (!empty($invalidas)) {
            $mensaje = "Cambios guardados, pero se omitieron labores que no pertenecen a la mina seleccionada.";
        }

        return ApiResponse::success(
            EmpleadosData::get_empleado_by_id($id_empleado),
            $mensaje
        );
    }
}
