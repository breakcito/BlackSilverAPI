<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Service\PrestamosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PrestamosAlmacenController extends Controller
{
    /**
     * Obtener resumen de préstamos
     */
    public function get_prestamos_resumen(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $mes = (int) $request->query('mes');
        $yearcito = (int) $request->query('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'));
        }

        $result = PrestamosService::get_prestamos_por_almacen($id_almacen, $mes, $yearcito);
        return response()->json($result);
    }
    /**
     * Obtener detalles de un préstamo
     */
    public function get_detalles_prestamo(Request $request): JsonResponse
    {
        $id_prestamo = (int) $request->query('id_prestamo');
        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('id_prestamo es requerido'));
        }

        $result = PrestamosService::get_detalles_prestamo($id_prestamo);
        return response()->json($result);
    }

    /**
     * Obtener trazabilidad de un detalle
     */
    public function get_trazabilidad(Request $request): JsonResponse
    {
        $id_detalle = (int) $request->query('id_prestamo_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('id_prestamo_detalle es requerido'));
        }

        $result = PrestamosService::get_trazabilidad($id_detalle);
        return response()->json($result);
    }
}
