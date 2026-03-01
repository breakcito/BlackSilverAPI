<?php

namespace App\Services;

use App\Models\Cargo;
use App\Shared\Enums\EstadoBase;
use App\Shared\Responses\ApiResponse;

class CargoService
{
    public function get_cargos()
    {
        $cargos = Cargo::select('id as id_cargo', 'nombre', 'estado')
            ->where('estado', EstadoBase::Activo->value)
            ->orderBy('nombre', 'asc')
            ->get();

        return ApiResponse::success($cargos);
    }
}
