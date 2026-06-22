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
            'id_empleado_recibe' => 'required|integer',
            'fecha_hora_transferencia' => 'required|date',
            'observacion' => 'nullable|string',
            'evidencias' => 'nullable|array',

            // Detalles a transferir
            'detalles' => 'required|array|min:1',
            'detalles.*.id_orden_compra_recepcion_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.cantidad_transferida_base' => 'required|numeric|min:0',
            'detalles.*.comentario' => 'nullable|string',
        ]);

        try {
            $result = TransferenciaService::registrar_transferencia(
                id_empleado_transferencia: $request->input('id_empleado_transferencia'),
                id_orden_compra_recepcion: $request->input('id_orden_compra_recepcion'),
                id_almacen_destino: $request->input('id_almacen_destino'),
                id_empleado_recibe: $request->input('id_empleado_recibe'),
                fecha_hora_transferencia: $request->input('fecha_hora_transferencia'),
                observacion: $request->input('observacion'),
                evidencias: $request->input('evidencias', []),
                detalles: $request->input('detalles'),
                id_mina_destino: $request->input('id_mina_destino')
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error("Error al registrar transferencia: " . $e->getMessage()));
        }
    }
}
