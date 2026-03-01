<?php

namespace App\Services;

use App\Models\Cargo;
use App\Shared\Responses\ApiResponse;

class CargoService
{
    public function get_cargos()
    {
        $cargos = Cargo::select('id as id_cargo', 'nombre', 'estado')
            ->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)
            ->orderBy('nombre', 'asc')
            ->get();

        return ApiResponse::success($cargos);
    }
}
