<?php

namespace App\Modules\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacenAtencion\Service\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{
    /**
     * Registrar despacho físico por préstamo
     */
    public function registrar_despacho(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo' => 'required|integer',
            'id_personal_recibe' => 'required|integer',
            'fecha_hora_entrega' => 'nullable|date',
            'observacion' => 'nullable|string|max:255',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_prestamo_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.id_activo_fijo' => 'nullable|integer',
            'detalles.*.cantidad_lote' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_base' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_solicitud' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = EntregaService::registrar_despacho(
            (int) $request->input('id_prestamo'),
            (int) $authUser->id_empleado,
            (int) $request->input('id_personal_recibe'),
            (string) $request->input('fecha_hora_entrega'),
            (string) $request->input('observacion'),
            $request->file('evidencias'),
            (array) $request->input('detalles')
        );

        return response()->json($result);
    }

    /**
     * Obtener historial de entregas por préstamo filtrado por solicitud_reabastecimiento.
     */
    public function get_entregas_por_solicitud(Request $request): JsonResponse
    {
        $id_solicitud = $request->query('id_solicitud');
        if (!$id_solicitud) {
            return response()->json(ApiResponse::error('Falta el parámetro id_solicitud'), 400);
        }

        $result = EntregaService::get_historial_por_solicitud((int) $id_solicitud);
        return response()->json($result);
    }
}
