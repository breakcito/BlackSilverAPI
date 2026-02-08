<?php

namespace App\Modules\Usuarios\Application\Usecases;

use App\Modules\Usuarios\Infraestructure\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Caso de uso para listar usuarios.
 */
class ListarUsuariosUseCase
{
    /**
     * Ejecutar el caso de uso.
     *
     * @param  int|null  $idEmpresa  Filtrar por empresa
     * @return Collection<int, Usuario>
     */
    public function execute(?int $idEmpresa = null): Collection
    {
        $query = Usuario::query()
            ->with(['rol', 'empleado.cargo', 'empleado.areaEmpresa.area']);

        if ($idEmpresa !== null) {
            $query->whereHas('empresas', function ($q) use ($idEmpresa) {
                $q->where('empresa.id', $idEmpresa);
            });
        }

        return $query->orderBy('username')->get();
    }
}
