<?php

namespace App\Services;

use App\Models\UnidadMedida;
use App\Shared\Responses\ApiResponse;

class UnidadMedidaService
{

    /**
     * Listar unidades de medida.
     */
    public function get_unidades_medida(?bool $es_base = null)
    {
        $query = UnidadMedida::select('id as id_unidad_medida', 'nombre', 'abreviatura', 'es_base')
            ->orderBy('nombre', 'asc');

        if ($es_base !== null) {
            $query->where('es_base', $es_base);
        }

        $unidades = $query->get();

        return ApiResponse::success($unidades);
    }
}