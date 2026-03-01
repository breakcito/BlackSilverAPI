<?php

namespace App\Services;

use App\Models\UnidadMedida;
use App\Shared\Responses\ApiResponse;

class UnidadMedidaService
{

    /**
     * Listar unidades de medida.
     */
    public function get_unidades_medida()
    {
        $unidades = UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura')
            ->orderBy('nombre', 'asc')
            ->get();

        return ApiResponse::success($unidades);
    }
}
