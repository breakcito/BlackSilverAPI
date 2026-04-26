<?php

namespace App\Modules\OrdenCompra\Controller;

use App\Modules\OrdenCompra\Service\RecepcionesOCService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecepcionesOCController
{
    /**
     * Registrar la recepción de una orden de compra.
     */
    public function registrar_recepcion(Request $request): JsonResponse
    {
        $id_orden_compra = (int) $request->input('id_orden_compra');
        $id_almacen_recepcionista = (int) $request->input('id_almacen_recepcionista');
        $con_incidencia = (bool) $request->input('con_incidencia');
        $observacion = $request->input('observacion');
        $fecha_hora_recepcion = $request->input('fecha_hora_recepcion');
        $serie_guia = $request->input('serie_guia');
        $numero_guia = $request->input('numero_guia');
        $items = json_decode($request->input('items'), true);
        $evidencias = $request->file('evidencias') ?? [];

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        return response()->json(RecepcionesOCService::registrar_recepcion_oc(
            id_orden_compra: $id_orden_compra,
            id_almacen_recepcionista: $id_almacen_recepcionista,
            id_empleado_registro: (int) $authUser->id_empleado,
            con_incidencia: $con_incidencia,
            observacion: $observacion,
            fecha_hora_recepcion: $fecha_hora_recepcion,
            serie_guia: $serie_guia,
            numero_guia: $numero_guia,
            items: $items,
            evidencias: $evidencias
        ));
    }

    /**
     * Obtener el historial de recepciones de una orden de compra.
     */
    public function get_historial(int $id): JsonResponse
    {
        return response()->json(RecepcionesOCService::obtener_historial_recepciones($id));
    }
}
