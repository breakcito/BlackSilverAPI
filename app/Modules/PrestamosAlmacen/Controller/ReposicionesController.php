<?php

namespace App\Modules\PrestamosAlmacen\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\PrestamosAlmacen\Service\ReposicionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class ReposicionesController extends Controller
{
    /**
     * Obtiene el historial de reposiciones de un préstamo.
     */
    public function get_historial(Request $request): JsonResponse
    {
        $id_prestamo_almacen = (int) $request->query('id_prestamo_almacen');
        if (!$id_prestamo_almacen) {
            return response()->json(ApiResponse::error('El id_prestamo_almacen es requerido'), 400);
        }

        $result = ReposicionesService::get_historial($id_prestamo_almacen);
        return response()->json($result);
    }

    /**
     * Registra una nueva reposición.
     */
    public function registrar_reposicion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_prestamo_almacen' => 'required|integer',
            'id_almacen_entrega' => 'required|integer',
            'fecha_hora_reposicion' => 'required|date',
            'observacion' => 'nullable|string',
            'items' => 'required', // Puede ser string JSON o array
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
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

        // Decodificamos el JSON de los items si vienen como string (común en Multipart)
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        // Validación adicional para los items después de decodificar
        if (!is_array($items) || count($items) === 0) {
            return response()->json(ApiResponse::error('Los items son requeridos y deben ser un array válido'), 400);
        }

        $result = ReposicionesService::registrar_reposicion(
            id_prestamo_almacen: (int) $request->input('id_prestamo_almacen'),
            id_almacen_entrega: (int) $request->input('id_almacen_entrega'),
            id_empleado_entrega: (int) $authUser->id_empleado,
            id_empleado_recibe: $request->input('id_empleado_recibe') ? (int) $request->input('id_empleado_recibe') : null,
            fecha_hora_reposicion: $request->input('fecha_hora_reposicion'),
            items: $items,
            observacion: $request->input('observacion'),
            evidencias: $request->file('evidencias'),
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
}
