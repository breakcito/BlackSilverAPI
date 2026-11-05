<?php

namespace App\Modules\VentaCarbon\Controller;

use App\Modules\VentaCarbon\Service\VentaCarbonReporteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VentaCarbonReporteController
{
    /**
     * Reporte de "Registro de Ventas" por anio.
     */
    public function get_reporte(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'anio' => 'required|integer|min:2000|max:2100',
        ], [
            'anio.required' => 'El año es obligatorio',
            'anio.integer' => 'El año debe ser un numero entero',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $result = VentaCarbonReporteService::get_control_ventas(
            (int) $request->input('anio')
        );

        return response()->json($result);
    }
}
