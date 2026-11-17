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
     * Reporte de "Registro de Ventas" por anio (opcionalmente por mes).
     */
    public function get_reporte(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
        ], [
            'anio.required' => 'El año es obligatorio',
            'anio.integer' => 'El año debe ser un numero entero',
            'mes.integer' => 'El mes debe ser un numero entero',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $mes = $request->input('mes');
        $mes = $mes === null || $mes === '' ? null : (int) $mes;

        $result = VentaCarbonReporteService::get_control_ventas(
            (int) $request->input('anio'),
            $mes
        );

        return response()->json($result);
    }
}
