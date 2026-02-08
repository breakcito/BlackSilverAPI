<?php

namespace App\Modules\Empresa\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empresa\Application\Dtos\CrearAreaRequest;
use App\Modules\Empresa\Infraestructure\Models\Area;

/**
 * Caso de uso para crear un área.
 */
class CrearAreaUseCase
{
    /**
     * Ejecutar el caso de uso.
     */
    public function execute(CrearAreaRequest $request): Area
    {
        return Area::query()->create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'estado' => EstadoBase::Activo,
        ]);
    }
}
