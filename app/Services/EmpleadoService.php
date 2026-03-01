<?php

namespace App\Services;

use App\Models\Empleado;
use App\Shared\Responses\ApiResponse;

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
    ) {
        // Validar DNI único
        if ($dni && Empleado::where('dni', $dni)->exists()) {
            return ApiResponse::error('Ya existe un empleado con este DNI.');
        }

        // Validar RUC único
        if ($ruc && Empleado::where('ruc', $ruc)->exists()) {
            return ApiResponse::error('Ya existe un empleado con este RUC.');
        }

        $empleado = Empleado::create([
            'id_cargo' => $id_cargo,
            'id_empresa' => $id_empresa,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'ruc' => $ruc,
            'carnet_extranjeria' => $carnet_extranjeria,
            'pasaporte' => $pasaporte,
            'fecha_nacimiento' => $fecha_nacimiento,
            'path_foto' => $path_foto,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);

        return ApiResponse::success(Empleado::get_empleado_by_id($empleado->id), 'Empleado registrado correctamente');
    }
}
