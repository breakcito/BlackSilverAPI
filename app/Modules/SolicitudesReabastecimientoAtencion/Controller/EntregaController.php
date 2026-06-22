<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Service\EntregaService;
use App\Modules\SolicitudesReabastecimientoAtencion\Service\RecepcionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{

    public function crear_entrega(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_solicitud' => 'required|integer',
            'id_almacen_entrega' => 'required|integer',
            'id_empleado_recibe' => 'required|integer',
            'fecha_hora_entrega' => 'required|date',
            'observacion' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_solicitud_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.id_activo_fijo' => 'nullable|integer',
            'detalles.*.cantidad_base' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_lote' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_solicitud' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');

        $result = EntregaService::registrar_entrega(
            (int) $request->id_almacen_entrega,
            $authUser->id_empleado,
            (int) $request->id_solicitud,
            (int) $request->id_empleado_recibe,
            $request->fecha_hora_entrega,
            $request->observacion,
            $request->file('evidencias'), // archivos
            $request->detalles
        );

        return response()->json($result);
    }

    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_solicitud = $request->input('id_solicitud');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('El id_solicitud es requerido'), 400);
        }

        $result = EntregaService::obtener_historial_entregas((int) $id_solicitud);

        return response()->json($result);
    }

    public function get_historial_recepciones(Request $request): JsonResponse
    {
        $id_entrega = $request->input('id_entrega');
        if (!$id_entrega) {
            return response()->json(ApiResponse::error('El id_entrega es requerido'), 400);
        }

        $result = RecepcionesService::get_historial_recepciones((int) $id_entrega);

        return response()->json($result);
    }
}
