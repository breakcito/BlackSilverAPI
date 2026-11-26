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
     *
     * Cada indicador de FLUJO tiene su propio periodo, asi que el usuario
     * puede mirar el balance de un mes y los cobros de otro:
     *
     *   ?periodo[balance][inicio]=2026-11-01&periodo[balance][fin]=2026-11-30
     *   &periodo[cobros][inicio]=...&periodo[cobros][fin]=...
     *   &periodo[costo][inicio]=...&periodo[costo][fin]=...
     *   &periodo[compras][inicio]=...&periodo[compras][fin]=...
     *
     * Los indicadores de ESTADO (stock critico, mantenimiento y pendientes
     * de atencion) son una foto del ahora y no llevan periodo.
     */
    public function get_indicadores(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'periodo.balance.inicio' => 'nullable|date_format:Y-m-d',
            'periodo.balance.fin' => 'nullable|date_format:Y-m-d',
            'periodo.cobros.inicio' => 'nullable|date_format:Y-m-d',
            'periodo.cobros.fin' => 'nullable|date_format:Y-m-d',
            'periodo.costo.inicio' => 'nullable|date_format:Y-m-d',
            'periodo.costo.fin' => 'nullable|date_format:Y-m-d',
            'periodo.compras.inicio' => 'nullable|date_format:Y-m-d',
            'periodo.compras.fin' => 'nullable|date_format:Y-m-d',
        ], [
            'periodo.balance.inicio.date_format' => 'El inicio del periodo del balance debe tener el formato YYYY-MM-DD',
            'periodo.balance.fin.date_format' => 'El fin del periodo del balance debe tener el formato YYYY-MM-DD',
            'periodo.cobros.inicio.date_format' => 'El inicio del periodo de cobros debe tener el formato YYYY-MM-DD',
            'periodo.cobros.fin.date_format' => 'El fin del periodo de cobros debe tener el formato YYYY-MM-DD',
            'periodo.costo.inicio.date_format' => 'El inicio del periodo de costo debe tener el formato YYYY-MM-DD',
            'periodo.costo.fin.date_format' => 'El fin del periodo de costo debe tener el formato YYYY-MM-DD',
            'periodo.compras.inicio.date_format' => 'El inicio del periodo de compras debe tener el formato YYYY-MM-DD',
            'periodo.compras.fin.date_format' => 'El fin del periodo de compras debe tener el formato YYYY-MM-DD',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $periodos = $request->input('periodo', []);

        if (!is_array($periodos)) {
            $periodos = [];
        }

        foreach (['balance', 'cobros', 'costo', 'compras'] as $clave) {
            $inicio = $periodos[$clave]['inicio'] ?? null;
            $fin = $periodos[$clave]['fin'] ?? null;

            if ($inicio !== null && $fin !== null && $fin < $inicio) {
                return response()->json(ApiResponse::error(
                    'La fecha final del periodo no puede ser anterior a la fecha inicial.'
                ), 422);
            }
        }

        $result = DashboardService::get_indicadores($periodos);

        return response()->json($result);
    }
}
