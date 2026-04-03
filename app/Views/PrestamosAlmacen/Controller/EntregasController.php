<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacen\Service\EntregasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EntregasController extends Controller
{
    /**
     * Obtiene el historial de entregas de un préstamo
     */
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_prestamo = (int) $request->query('id_prestamo');

        if (!$id_prestamo) {
            return response()->json(ApiResponse::error('id_prestamo es requerido'));
        }

        $result = EntregasService::get_historial_entregas($id_prestamo);
        return response()->json($result);
    }
}
