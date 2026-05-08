<?php

namespace App\Modules\Productos\Controller;

use App\Modules\Productos\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuxController
{

    /**
     * Obtener unidades de medida base
     */
    public function get_unidades_medida(Request $request): JsonResponse
    {
        $result = AuxService::get_unidades_medida();

        return response()->json($result);
    }

    /**
     * Obtener categorías internas del módulo de productos
     */
    public function get_categorias(Request $request): JsonResponse
    {
        $result = AuxService::get_categorias();

        return response()->json($result);
    }
}
