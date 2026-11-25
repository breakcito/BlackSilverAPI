<?php

namespace App\Modules\Dashboard\Controller;

use App\Modules\Dashboard\Service\DashboardService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DashboardController
{
    /**
     * Indicadores del dashboard.
     *
     * Devuelve los siete indicadores en una sola respuesta para que la
     * pantalla se arme de una vez y todos los bloques hablen del mismo
     * snapshot de datos.
     */
    public function get_indicadores(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d',
            'fecha_inicio_comparativo' => 'nullable|date_format:Y-m-d',
            'fecha_fin_comparativo' => 'nullable|date_format:Y-m-d',
            'id_almacen' => 'nullable|integer',
        ], [
            'fecha_inicio.required' => 'La fecha de inicio del periodo es obligatoria',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD',
            'fecha_fin.required' => 'La fecha de fin del periodo es obligatoria',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD',
            'id_almacen.integer' => 'El almacen debe ser un numero valido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $result = DashboardService::get_indicadores(
            fecha_inicio: (string) $request->input('fecha_inicio'),
            fecha_fin: (string) $request->input('fecha_fin'),
            id_almacen: $request->input('id_almacen') !== null
                ? (int) $request->input('id_almacen')
                : null,
            fecha_inicio_comparativo: $request->input('fecha_inicio_comparativo') !== null
                ? (string) $request->input('fecha_inicio_comparativo')
                : null,
            fecha_fin_comparativo: $request->input('fecha_fin_comparativo') !== null
                ? (string) $request->input('fecha_fin_comparativo')
                : null,
        );

        return response()->json($result);
    }
}
