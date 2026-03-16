<?php

namespace App\Views\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimientoAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{

    public function get_solicitudes(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'));
        }

        $result = AtencionService::get_solicitudes((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    public function get_detalles_solicitud(Request $request): JsonResponse
    {
        $id = $request->input('id_solicitud');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_solicitud es requerido'));
        }

        $result = AtencionService::get_detalles_solicitud((int) $id);

        return response()->json($result);
    }

    public function update_detalle_estado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_solicitud_detalle' => 'required|integer',
            'nuevo_estado' => 'required|string',
            'comentario_decision' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'));
        }

        $result = AtencionService::update_detalle_estado(
            $authUser->id_empleado,
            (int) $request->id_solicitud_detalle,
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

        $result = AtencionService::get_trazabilidad((int) $id_detalle);

        return response()->json($result);
    }
}
