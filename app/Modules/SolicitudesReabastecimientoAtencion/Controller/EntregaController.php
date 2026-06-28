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
            'fecha_hora_entrega' => 'required|date',
            'observacion' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_solicitud_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.id_activo_fijo' => 'nullable|integer',
            'detalles.*.cantidad_base' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_lote' => 'required|numeric|min:0.01',
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

        $result = EntregaService::registrar_entrega(
            id_almacen_entrega: (int) $request->id_almacen_entrega,
            id_empleado_entrega: $authUser->id_empleado,
            id_solicitud: (int) $request->id_solicitud,
            id_empleado_recibe: $request->id_empleado_recibe ? (int) $request->id_empleado_recibe : null,
            fecha_hora_entrega: $request->fecha_hora_entrega,
            observacion: $request->observacion,
            evidencias: $request->file('evidencias'), // archivos
            detalles: $request->detalles,
            medio_entrega: $request->medio_entrega,
            id_proveedor_transporte: $request->id_proveedor_transporte ? (int) $request->id_proveedor_transporte : null,
            id_agencia_transporte: $request->id_agencia_transporte ? (int) $request->id_agencia_transporte : null,
            numero_factura: $request->numero_factura,
            serie_factura: $request->serie_factura,
            serie_guia_transportista: $request->serie_guia_transportista,
            numero_guia_transportista: $request->numero_guia_transportista,
            serie_guia_remitente: $request->serie_guia_remitente,
            numero_guia_remitente: $request->numero_guia_remitente,
            costo_envio: $request->costo_envio ? (float) $request->costo_envio : null,
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
