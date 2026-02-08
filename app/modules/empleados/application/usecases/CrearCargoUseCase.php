<?php

namespace App\Modules\Empleados\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empleados\Application\Dtos\CrearCargoRequest;
use App\Modules\Empleados\Infraestructure\Models\Cargo;

/**
 * Caso de uso para crear un cargo.
 */
class CrearCargoUseCase
{
    /**
     * Ejecutar el caso de uso.
     */
    public function execute(CrearCargoRequest $request): Cargo
    {
        return Cargo::query()->create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => EstadoBase::Activo,
        ]);
    }
}
