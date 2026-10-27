<?php

namespace App\Modules\CompraCarbon\Controller;

use App\Modules\CompraCarbon\Service\CompraCarbonReporteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompraCarbonReporteController
{
    /**
     * Reporte de "Registro de Compras" (Control de Compras) por año.
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

        $result = CompraCarbonReporteService::get_control_compras(
            (int) $request->input('anio')
        );

        return response()->json($result);
    }
}
