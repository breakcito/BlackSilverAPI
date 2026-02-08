<?php

namespace App\Modules\Empresa\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empresa\Infraestructure\Models\Area;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar áreas.
 */
class ListarAreasUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return Collection<int, Area>
     */
    public function execute(): Collection
    {
        return Area::query()
            ->where('estado', EstadoBase::Activo)
            ->orderBy('nombre')
            ->get();
    }
}
