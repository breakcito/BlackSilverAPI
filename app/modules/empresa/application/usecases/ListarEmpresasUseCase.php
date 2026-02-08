<?php

namespace App\Modules\Empresa\Application\Usecases;

use App\Modules\Empresa\Infraestructure\Models\Empresa;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar empresas.
 */
class ListarEmpresasUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return Collection<int, Empresa>
     */
    public function execute(): Collection
    {
        return Empresa::query()
            ->orderBy('razon_social')
            ->get();
    }
}
