<?php

namespace App\Modules\Balances\Controller;

use App\Modules\Balances\Service\BalancesService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class BalancesController extends Controller
{
    /**
     * Obtiene el balance de ingresos y egresos de un periodo, junto con el
     * periodo de comparacion.
     *
     * Periodo obligatorio: `fecha_inicio` y `fecha_fin` (Y-m-d).
     * Periodo de comparacion opcional: `fecha_inicio_comparativo` y
     * `fecha_fin_comparativo`. Si no se envia, el backend compara contra el
     * periodo inmediatamente anterior de la misma duracion.
     */
    public function get_balance(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_inicio' => 'required|date_format:Y-m-d',
            'fecha_fin' => 'required|date_format:Y-m-d',
            'fecha_inicio_comparativo' => 'nullable|date_format:Y-m-d',
            'fecha_fin_comparativo' => 'nullable|date_format:Y-m-d',
        ], [
            'fecha_inicio.required' => 'La fecha de inicio del periodo es obligatoria',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD',
            'fecha_fin.required' => 'La fecha de fin del periodo es obligatoria',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD',
            'fecha_inicio_comparativo.date_format' => 'La fecha de inicio de la comparacion debe tener el formato YYYY-MM-DD',
            'fecha_fin_comparativo.date_format' => 'La fecha de fin de la comparacion debe tener el formato YYYY-MM-DD',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $res = BalancesService::get_balance(
            (string) $request->input('fecha_inicio'),
            (string) $request->input('fecha_fin'),
            $request->input('fecha_inicio_comparativo'),
            $request->input('fecha_fin_comparativo')
        );

        return response()->json($res);
    }
}
