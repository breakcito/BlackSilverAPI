<?php

namespace App\Services;

use App\Models\ResponsableAlmacen;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class ResponsableAlmacenService
{

    /**
     * Asignar responsable
     */
    public function asignar_responsable_almacen(int $id_almacen, int $id_empleado, string $fecha_inicio, ?string $fecha_fin)
    {
        // Cerrar anteriores activos
        ResponsableAlmacen::where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_inicio,
                'estado' => EstadoBase::Inactivo->value,
            ]);

        // Crear nuevo usando el id de la tabla empleado
        $id = ResponsableAlmacen::insertGetId([
            'id_almacen' => $id_almacen,
            'id_empleado' => $id_empleado,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);

        $nuevoResponsableAlmacen = ResponsableAlmacen::get_responsables_historial(id_responsable_almacen: $id)[0];

        return ApiResponse::success($nuevoResponsableAlmacen, 'Responsable asignado correctamente');
    }

    /**
     * Obtener historial de responsables.
     */
    public function get_responsables_almacen(int $id_almacen)
    {
        $historial = ResponsableAlmacen::get_responsables_historial($id_almacen);
        return ApiResponse::success($historial);
    }
}
