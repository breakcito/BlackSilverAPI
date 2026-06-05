<?php

namespace App\Modules\ControlUso\Controller;

use App\Modules\ControlUso\Service\ControlUsoReporteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ControlUsoReporteController extends Controller
{
    /**
     * Obtener el reporte general matricial mensual.
     */
    public function get_reporte_mensual(Request $request): JsonResponse
    {
        $mes = $request->input('mes');
        $anio = $request->input('anio');

        if (!$mes || !$anio) {
            return response()->json(ApiResponse::error('Mes y año son requeridos.'));
        }

        $res = ControlUsoReporteService::get_reporte_mensual((int)$mes, (int)$anio);
        return response()->json($res);
    }
}
