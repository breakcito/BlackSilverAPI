<?php

namespace App\Modules\SolicitudesReabastecimiento\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimiento\Service\EntregasService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class EntregasController extends Controller
{
    // Obtener historial de entregas hechas por logistica y prestamos
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_solicitud = $request->input('id_solicitud_reabastecimiento');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud_reabastecimiento es requerido'), 400);
        }

        $result = EntregasService::get_historial_entregas((int) $id_solicitud);
        return response()->json($result);
    }
}
