<?php

namespace App\Modules\OrdenesCompra\Controller;

use App\Modules\OrdenesCompra\Service\TransferenciaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferenciaController
{
    /**
     * Registra una transferencia física de material recepcionado hacia su almacén destinado.
     */
    public function registrar_transferencia(Request $request): JsonResponse
    {
        $request->validate([
            'id_empleado_transferencia' => 'required|integer',
            'id_orden_compra_recepcion' => 'required|integer',
            'id_almacen_destino' => 'nullable|integer',
            'id_mina_destino' => 'nullable|integer',
            'fecha_hora_transferencia' => 'required|date',
            'observacion' => 'nullable|string',
            'evidencias' => 'nullable|array',

            // Detalles a transferir
            'detalles' => 'required|array|min:1',
            'detalles.*.id_orden_compra_recepcion_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.cantidad_transferida_base' => 'required|numeric|min:0',
            'detalles.*.comentario' => 'nullable|string',

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

        try {
            $result = TransferenciaService::registrar_transferencia(
                id_empleado_transferencia: $request->input('id_empleado_transferencia'),
                id_orden_compra_recepcion: $request->input('id_orden_compra_recepcion'),
                id_almacen_destino: $request->input('id_almacen_destino'),
                id_empleado_recibe: $request->input('id_empleado_recibe') ? (int) $request->input('id_empleado_recibe') : null,
                fecha_hora_transferencia: $request->input('fecha_hora_transferencia'),
                observacion: $request->input('observacion'),
                evidencias: $request->input('evidencias', []),
                detalles: $request->input('detalles'),
                id_mina_destino: $request->input('id_mina_destino'),
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
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error("Error al registrar transferencia: " . $e->getMessage()));
        }
    }
}
