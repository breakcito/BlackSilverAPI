<?php

namespace App\Modules\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Service\RecepcionesService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class RecepcionesController extends Controller
{
    /**
     * Obtener el historial de recepciones de una entrega
     */
    public function get_historial_recepciones_entrega(Request $request): JsonResponse
    {
        $id_entrega = $request->input('id_prestamo_entrega');

        if (!$id_entrega) {
            return response()->json(ApiResponse::error('El id_prestamo_entrega es requerido'), 400);
        }

        $result = RecepcionesService::obtener_historial_recepciones((int) $id_entrega);

        return response()->json($result);
    }
}
