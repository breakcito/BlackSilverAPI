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
            // Transport validations
            'medio_entrega' => 'required|string|in:Terceros,Agencia,Propio',
            'id_empleado_recibe' => 'required_if:medio_entrega,Propio|nullable|integer',
            'id_proveedor_transporte' => 'required_if:medio_entrega,Terceros|nullable|integer',
            'id_agencia_transporte' => 'required_if:medio_entrega,Agencia|nullable|integer',
            'numero_factura' => 'required_if:medio_entrega,Terceros,Agencia|nullable|string',
            'serie_factura' => 'required_if:medio_entrega,Terceros,Agencia|nullable|string',
            'serie_guia_transportista' => 'required_if:medio_entrega,Terceros,Agencia|nullable|string',
            'numero_guia_transportista' => 'required_if:medio_entrega,Terceros,Agencia|nullable|string',
            'serie_guia_remitente' => 'required_if:medio_entrega,Terceros,Propio|nullable|string',
            'numero_guia_remitente' => 'required_if:medio_entrega,Terceros,Propio|nullable|string',
            'costo_envio' => 'required_if:medio_entrega,Terceros|nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = EntregaService::registrar_despacho(
            id_prestamo: (int) $request->input('id_prestamo'),
            id_empleado_entrega: (int) $authUser->id_empleado,
            id_empleado_recibe: $request->input('id_empleado_recibe') ? (int) $request->input('id_empleado_recibe') : null,
            fecha_hora_entrega: (string) $request->input('fecha_hora_entrega'),
            observacion: (string) $request->input('observacion'),
            evidencias: $request->file('evidencias'),
            detalles: (array) $request->input('detalles'),
            medio_entrega: $request->input('medio_entrega'),
            id_proveedor_transporte: $request->input('id_proveedor_transporte') ? (int) $request->input('id_proveedor_transporte') : null,
            id_agencia_transporte: $request->input('id_agencia_transporte') ? (int) $request->input('id_agencia_transporte') : null,
            numero_factura: $request->input('numero_factura'),
            serie_factura: $request->input('serie_factura'),
            serie_guia_transportista: $request->input('serie_guia_transportista'),
            numero_guia_transportista: $request->input('numero_guia_transportista'),
            serie_guia_remitente: $request->input('serie_guia_remitente'),
            numero_guia_remitente: $request->input('numero_guia_remitente'),
            costo_envio: $request->input('costo_envio') ? (float) $request->input('costo_envio') : null,
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
