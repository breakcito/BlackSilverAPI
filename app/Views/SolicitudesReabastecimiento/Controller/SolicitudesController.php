<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Service\SolicitudesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{
    // Obtener todas la lista de solicitudes en base a mes y año
    public function get_solicitudes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        $validator = Validator::make($request->all(), [
            'mes' => 'required|integer|between:1,12',
            'yearcito' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = SolicitudesService::get_solicitudes(
            (int) $authUser->id_empleado,
            (int) $request->mes,
            (int) $request->yearcito,
        );
        return response()->json($result);
    }

    // Registrar una solicitud y sus detalles
    public function crear_solicitud(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_almacen_solicitante' => 'required|integer',
            'premura' => 'required|string',
            'observacion' => 'nullable|string',
            'fecha_entrega_requerida' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = SolicitudesService::crear_solicitud(
            (int) $request->id_almacen_solicitante,
            (int) $authUser->id_empleado,
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
        $id_solicitud = $request->input('id_solicitud_reabastecimiento');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud_reabastecimiento es requerido'), 400);
        }

        $result = SolicitudesService::get_detalles_solicitud((int) $id_solicitud);
        return response()->json($result);
    }

    // Obtener la trazabilidad de un detalle
    public function get_trazabilidad_by_detalle(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_solicitud_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_solicitud_detalle es requerido'), 400);
        }

        $result = SolicitudesService::get_trazabilidad_by_detalle((int) $id_detalle);
        return response()->json($result);
    }
}
