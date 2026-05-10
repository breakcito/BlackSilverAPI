<?php

namespace App\Modules\OrdenesCompra\Controller;

use App\Modules\OrdenesCompra\Service\RecepcionesOCService;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\TipoComprobante;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecepcionesOCController
{
    /**
     * Registrar la recepción de una orden de compra y opcionalmente su comprobante.
     * @param Request $request
     * @return JsonResponse
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

        $comprobanteInput = $request->input('comprobante');
        $comp = $comprobanteInput ? json_decode($comprobanteInput, true) : [];
        $compEvidencias = $request->file('comprobante_evidencias') ?? [];

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
            evidencias: $evidencias,
            // Comprobante
            tipo_comprobante: ($comp['tipo_comprobante'] ?? null) ? TipoComprobante::from($comp['tipo_comprobante']) : null,
            serie_comprobante: $comp['serie'] ?? null,
            numero_comprobante: $comp['numero'] ?? null,
            fecha_emision_comprobante: $comp['fecha_emision'] ?? null,
            observacion_comprobante: $comp['observacion'] ?? null,
            evidencias_comprobante: $compEvidencias,
            moneda_comprobante: ($comp['moneda'] ?? null) ? Moneda::from($comp['moneda']) : null,
            tipo_cambio_comprobante: (float) ($comp['tipo_cambio_venta_aplicado'] ?? 1),
            es_auditable_comprobante: (bool) ($comp['es_auditable'] ?? false),
            total_antes_igv_comprobante: (float) ($comp['total_antes_igv'] ?? 0),
            total_antes_igv_soles_comprobante: (float) ($comp['total_antes_igv_soles'] ?? 0),
            incluye_igv_comprobante: (bool) ($comp['incluye_igv'] ?? true),
            porcentaje_igv_comprobante: (float) ($comp['porcentaje_igv'] ?? 18),
            monto_igv_comprobante: (float) ($comp['monto_igv'] ?? 0),
            monto_igv_soles_comprobante: (float) ($comp['monto_igv_soles'] ?? 0),
            total_despues_igv_comprobante: (float) ($comp['total_despues_igv'] ?? 0),
            total_despues_igv_soles_comprobante: (float) ($comp['total_despues_igv_soles'] ?? 0)
        ));
    }

    /**
     * Obtener el historial de recepciones de una orden de compra.
     * @param int $id ID de la orden de compra
     * @return JsonResponse
     */
    public function get_historial(int $id): JsonResponse
    {
        return response()->json(RecepcionesOCService::obtener_historial_recepciones($id));
    }
}
