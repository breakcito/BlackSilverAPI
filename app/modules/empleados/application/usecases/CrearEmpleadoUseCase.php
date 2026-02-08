<?php

namespace App\Modules\Empleados\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empleados\Application\Dtos\CrearEmpleadoRequest;
use App\Modules\Empleados\Infraestructure\Models\Empleado;

/**
 * Caso de uso para crear un empleado.
 */
class CrearEmpleadoUseCase
{
    /**
     * Ejecutar el caso de uso.
     */
    public function execute(CrearEmpleadoRequest $request): Empleado
    {
        return Empleado::query()->create([
            'id_area_empresa' => $request->idAreaEmpresa,
            'id_cargo' => $request->idCargo,
            'nombre' => $request->nombre,
            'apellido' => $request->apellido,
            'dni' => $request->dni,
            'ruc' => $request->ruc,
            'carnet_extranjeria' => $request->carnetExtranjeria,
            'pasaporte' => $request->pasaporte,
            'fecha_nacimiento' => $request->fechaNacimiento,
            'estado' => EstadoBase::Activo,
        ]);
    }
}
