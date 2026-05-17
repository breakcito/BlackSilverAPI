<?php

namespace App\Modules\ControlConsumoActivos\Controller;

use App\Modules\ControlConsumoActivos\Service\ControlConsumoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


/**
 * Controlador de API para la gestión de registros de consumo de activos fijos.
 */
class ControlConsumoController extends Controller
{
    /**
     * Obtener el reporte de consumo de un activo fijo.
     */
    public function get_reporte(Request $request): JsonResponse
    {
        $id_activo_fijo = $request->input('id_activo_fijo') ? (int) $request->input('id_activo_fijo') : null;
        $mes = $request->input('mes') ? (int) $request->input('mes') : null;
        $yearcito = $request->input('yearcito') ? (int) $request->input('yearcito') : null;

        $res = ControlConsumoService::get_reporte($id_activo_fijo, $mes, $yearcito);
        return response()->json($res);
    }

}
