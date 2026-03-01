<?php

namespace App\Services;

use App\Models\ResponsableAlmacen;
use App\Models\Usuario;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class ResponsableAlmacenService
{

    /**
     * Asignar responsable
     */
    public function asignar_responsable_almacen(int $id_almacen, int $id_empleado, string $fecha_inicio, ?string $fecha_fin)
    {
        // validar que el empleado exista
        $usuarioReal = Usuario::where('id_empleado', $id_empleado)->first();
        if (!$usuarioReal) {
            return ApiResponse::error('El empleado seleccionado no tiene cuenta de usuario en el sistema.');
        }
        $id_usuario_real = $usuarioReal->id;

        // Cerrar anteriores activos
        ResponsableAlmacen::where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value)
            ->update([
                'fecha_fin' => $fecha_inicio,
                'estado' => EstadoBase::Inactivo->value,
            ]);

        // Crear nuevo usando el id de la tabla usuario
        $id = ResponsableAlmacen::insertGetId([
            'id_almacen' => $id_almacen,
            'id_usuario' => $id_usuario_real,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(['id_asignacion' => $id], 'Responsable asignado correctamente');
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
