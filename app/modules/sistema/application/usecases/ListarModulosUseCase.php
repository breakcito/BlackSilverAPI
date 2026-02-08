<?php

namespace App\Modules\Sistema\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Sistema\Infraestructure\Models\Modulo;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar módulos con submódulos y secciones.
 */
class ListarModulosUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return Collection<int, Modulo>
     */
    public function execute(): Collection
    {
        return Modulo::query()
            ->with([
                'submodulos' => function ($query) {
                    $query->where('estado', EstadoBase::Activo)
                        ->with([
                            'secciones' => function ($q) {
                                $q->where('estado', EstadoBase::Activo);
                            },
                        ]);
                },
            ])
            ->where('estado', EstadoBase::Activo)
            ->orderBy('nombre')
            ->get();
    }
}
