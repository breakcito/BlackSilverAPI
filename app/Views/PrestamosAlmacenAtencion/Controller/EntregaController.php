<?php

namespace App\Views\PrestamosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\PrestamosAlmacenAtencion\Service\EntregaService;
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
            'id_prestamo'           => 'required|integer',
            'id_empleado_recibe'    => 'required|integer',
            'fecha_hora_entrega'    => 'required|date',
            'observacion'           => 'nullable|string|max:255',
            'detalles'              => 'required|array|min:1',
            'detalles.*.id_prestamo_detalle' => 'required|integer',
            'detalles.*.id_lote_salida'      => 'required|integer',
            'detalles.*.cantidad_lote'       => 'required|numeric|min:0.01',
            'detalles.*.cantidad_base'       => 'required|numeric|min:0.01',
            'detalles.*.cantidad_solicitud'  => 'required|numeric|min:0.01', // Cantidad en la UM de la solicitud
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
            (int) $request->input('id_empleado_recibe'),
            (string) $request->input('fecha_hora_entrega'),
            (string) $request->input('observacion'),
            (array) $request->input('detalles')
        );

        return response()->json($result);
    }
}
