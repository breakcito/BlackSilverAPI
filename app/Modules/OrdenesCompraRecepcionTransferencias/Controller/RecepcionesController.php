<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Controller;

use App\Modules\OrdenesCompraRecepcionTransferencias\Service\RecepcionesService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecepcionesController
{
    /**
     * Obtener el historial de recepciones de una transferencia.
     */
    public function get_historial(int $id_transferencia): JsonResponse
    {
        return response()->json(
            RecepcionesService::get_recepciones($id_transferencia)
        );
    }

    /**
     * Registrar la recepción de una transferencia OC.
     * Payload (multipart/form-data):
     *   - id_transferencia: int
     *   - con_incidencia: bool
     *   - observacion: string|null
     *   - fecha_hora_recepcion: string|null
     *   - id_almacen_recepcionista: int (almacén destino que recepciona)
     *   - items: JSON string [{
     *       id_detalle_transferencia, cantidad_base, es_nuevo_lote,
     *       id_lote_existente, descripcion, fecha_ingreso, fecha_vencimiento
     *     }]
     *   - evidencias[]: files (optional)
     */
    public function registrar(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $id_transferencia = (int) $request->input('id_transferencia');
        $id_almacen_recepcionista = (int) $request->input('id_almacen_recepcionista');
        $con_incidencia = filter_var($request->input('con_incidencia'), FILTER_VALIDATE_BOOLEAN);
        $observacion = $request->input('observacion');
        $fecha_hora_recepcion = $request->input('fecha_hora_recepcion');
        $items = json_decode($request->input('items'), true);
        $evidencias = $request->file('evidencias') ?? [];

        return response()->json(RecepcionesService::registrar_recepcion(
            id_transferencia: $id_transferencia,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            id_empleado: (int) $authUser->id_empleado,
            con_incidencia: $con_incidencia,
            observacion: $observacion,
            fecha_hora_recepcion: $fecha_hora_recepcion,
            items: $items,
            evidencias: $evidencias
        ));
    }
}
