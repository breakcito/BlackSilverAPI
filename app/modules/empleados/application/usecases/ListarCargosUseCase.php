<?php

namespace App\Modules\Empleados\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empleados\Infraestructure\Models\Cargo;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar cargos.
 */
class ListarCargosUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return Collection<int, Cargo>
     */
    public function execute(): Collection
    {
        return Cargo::query()
            ->where('estado', EstadoBase::Activo)
            ->orderBy('nombre')
            ->get();
    }
}
