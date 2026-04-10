<?php

namespace App\Views\Cotizaciones;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Views\Cotizaciones\CotizacionesService;

class CotizacionesController
{
    /**
     * Registrar un nuevo comparativo con sus cotizaciones
     */
    public function registrar_comparativo(Request $request): JsonResponse
    {
        $productos    = $request->input('productos', []);
        $cotizaciones = $request->input('cotizaciones', []);

        $result = CotizacionesService::registrar_comparativo(
            productos:    $productos,
            cotizaciones: $cotizaciones
        );

        return response()->json($result);
    }

    /**
     * Listar cotizaciones agrupadas
     */
    public function get_listado(Request $request): JsonResponse
    {
        $result = CotizacionesService::listar();
        return response()->json($result);
    }
}
