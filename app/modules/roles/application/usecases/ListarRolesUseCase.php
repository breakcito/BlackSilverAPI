<?php

namespace App\Modules\Roles\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Roles\Infraestructure\Models\Rol;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar roles con sus secciones y permisos.
 */
class ListarRolesUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @return Collection<int, Rol>
     */
    public function execute(): Collection
    {
        return Rol::query()
            ->with([
                'seccionesRol.seccion',
                'seccionesRol.permisos.accionSistemaSeccion.accionSistema',
            ])
            ->where('estado', EstadoBase::Activo)
            ->orderBy('nombre')
            ->get();
    }
}
