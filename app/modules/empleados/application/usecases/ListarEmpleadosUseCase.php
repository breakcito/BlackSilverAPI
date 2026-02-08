<?php

namespace App\Modules\Empleados\Application\Usecases;

use App\Enums\EstadoBase;
use App\Modules\Empleados\Infraestructure\Models\Empleado;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar empleados.
 */
class ListarEmpleadosUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @param  int|null  $idEmpresa  Filtrar por empresa
     * @return Collection<int, Empleado>
     */
    public function execute(?int $idEmpresa = null): Collection
    {
        $query = Empleado::query()
            ->with(['cargo', 'areaEmpresa.area', 'areaEmpresa.empresa'])
            ->where('estado', EstadoBase::Activo);

        if ($idEmpresa !== null) {
            $query->whereHas('areaEmpresa', function ($q) use ($idEmpresa) {
                $q->where('id_empresa', $idEmpresa);
            });
        }

        return $query->orderBy('apellido')->orderBy('nombre')->get();
    }
}
