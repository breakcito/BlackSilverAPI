<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Service\SolicitudesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{

    public function get_solicitudes(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'));
        }

        $result = SolicitudesService::get_solicitudes((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    public function get_detalles_solicitud(Request $request): JsonResponse
    {
        $id = $request->input('id_solicitud');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_solicitud es requerido'));
        }

        $result = SolicitudesService::get_detalles_solicitud((int) $id);

        return response()->json($result);
    }

    public function update_detalle_estado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_solicitud_detalle' => 'nullable|integer',
            'ids_detalles' => 'nullable|array',
            'ids_detalles.*' => 'integer',
            'nuevo_estado' => 'required|string',
            'comentario_decision' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        // Normalizar los IDs a un solo arreglo para el servicio
        $ids = [];
        if ($request->has('id_solicitud_detalle')) {
            $ids[] = (int) $request->id_solicitud_detalle;
        }
        if ($request->has('ids_detalles')) {
            $ids = array_merge($ids, $request->ids_detalles);
        }

        $ids = array_unique($ids);

        if (empty($ids)) {
            return response()->json(ApiResponse::error('Debe proporcionar al menos un ID de detalle'), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = SolicitudesService::update_detalle_estado(
            $authUser->id_empleado,
            $ids,
            $request->nuevo_estado,
            $request->comentario_decision
        );

        return response()->json($result);
    }

    public function get_trazabilidad(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_solicitud_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_solicitud_detalle es requerido'));
        }

        $result = SolicitudesService::get_trazabilidad((int) $id_detalle);

        return response()->json($result);
    }
}
