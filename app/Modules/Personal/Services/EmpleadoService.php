<?php

namespace App\Modules\Personal\Services;

use App\Modules\Personal\Models\Empleado;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class EmpleadoService
{
    /**
     * Obtener el listado.
     */
    public function get_empleados()
    {
        return ApiResponse::success(Empleado::get_empleados());
    }

    /**
     * Crear un nuevo empleado.
     */
    public function crear_empleado(
        int $id_cargo,
        int $id_empresa,
        string $nombre,
        string $apellido,
        ?string $dni,
        ?string $ruc,
        ?string $carnet_extranjeria,
        ?string $pasaporte,
        ?string $fecha_nacimiento,
        ?string $path_foto
    )
    {
        // Validar DNI único
        if ($dni && Empleado::verificar_documento_existente('dni', $dni)) {
            return ApiResponse::error('Ya existe un empleado con este DNI.');
        }

        // Validar RUC único
        if ($ruc && Empleado::verificar_documento_existente('ruc', $ruc)) {
            return ApiResponse::error('Ya existe un empleado con este RUC.');
        }

        $id = Empleado::crear_empleado(
            $id_cargo,
            $id_empresa,
            $nombre,
            $apellido,
            $dni,
            $ruc,
            $carnet_extranjeria,
            $pasaporte,
            $fecha_nacimiento,
            $path_foto
        );

        return ApiResponse::success(['id' => $id], 'Empleado registrado correctamente');
    }
}
