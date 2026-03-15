<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Views\SolicitudesReabastecimiento\Service\SolicitudesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SolicitudesController extends Controller
{
    // Obtener todas la lista de solicitudes en base al almacen solicitante
    // dentro de un periodo de tiempo (mes y año)
    public function get_solicitudes(Request $request): JsonResponse
    {
        $result = SolicitudesService::get_solicitudes(
            $request->id_almacen_solicitante,
            $request->mes,
            $request->yearcito,
        );
        return response()->json($result);
    }

    // Registrar una solicitud y sus detalles
    public function crear_solicitud(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $result = SolicitudesService::crear_solicitud(
            $request->id_almacen_solicitante,
            $authUser->id_empleado,
            $request->premura,
            $request->observacion,
            $request->fecha_entrega_requerida,
            $request->detalles
        );

        return response()->json($result);
    }

    // Obtener los detalles de una solicitud
    public function get_detalles_solicitud(Request $request): JsonResponse
    {
        $result = SolicitudesService::get_detalles_solicitud(
            $request->id_solicitud_reabastecimiento,
        );
        return response()->json($result);
    }

    // Obtener la trazabilidad de un detalle
    public function get_trazabilidad_by_detalle(Request $request): JsonResponse
    {
        $result = SolicitudesService::get_trazabilidad_by_detalle(
            $request->id_solicitud_detalle,
        );
        return response()->json($result);
    }
}
