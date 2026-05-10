<?php

namespace App\Modules\OrdenesCompra\Controller;

use App\Modules\OrdenesCompra\Service\OCComprobanteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OCComprobanteController
{
    /**
     * Registrar un comprobante para una orden de compra.
     */
    public function registrar_comprobante(Request $request): JsonResponse
    {
        $id_orden_compra = (int) $request->input('id_orden_compra');
        $tipo_comprobante = $request->input('tipo_comprobante');
        $serie = $request->input('serie');
        $numero = $request->input('numero');
        $fecha_emision = $request->input('fecha_emision');
        $observacion = $request->input('observacion');
        $evidencias = $request->file('evidencias') ?? [];

        $moneda = $request->input('moneda');
        $tipo_cambio_venta_aplicado = (float) $request->input('tipo_cambio_venta_aplicado', 1);
        $es_auditable = (bool) $request->input('es_auditable');

        $total_antes_igv = (float) $request->input('total_antes_igv');
        $total_antes_igv_soles = (float) $request->input('total_antes_igv_soles');
        $incluye_igv = (bool) $request->input('incluye_igv');
        $porcentaje_igv = (float) $request->input('porcentaje_igv', 18);
        $monto_igv = (float) $request->input('monto_igv');
        $monto_igv_soles = (float) $request->input('monto_igv_soles');
        $total_despues_igv = (float) $request->input('total_despues_igv');
        $total_despues_igv_soles = (float) $request->input('total_despues_igv_soles');

        $ids_recepciones = json_decode($request->input('ids_recepciones', '[]'), true);

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        return response()->json(OCComprobanteService::registrar_comprobante(
            id_empleado_registro: (int) $authUser->id_empleado,
            id_orden_compra: $id_orden_compra,
            tipo_comprobante: $tipo_comprobante,
            serie: $serie,
            numero: $numero,
            fecha_emision: $fecha_emision,
            observacion: $observacion,
            evidencias: $evidencias,
            moneda: $moneda,
            tipo_cambio_venta_aplicado: $tipo_cambio_venta_aplicado,
            es_auditable: $es_auditable,
            total_antes_igv: $total_antes_igv,
            total_antes_igv_soles: $total_antes_igv_soles,
            incluye_igv: $incluye_igv,
            porcentaje_igv: $porcentaje_igv,
            monto_igv: $monto_igv,
            monto_igv_soles: $monto_igv_soles,
            total_despues_igv: $total_despues_igv,
            total_despues_igv_soles: $total_despues_igv_soles,
            ids_recepciones: $ids_recepciones
        ));
    }

    /**
     * Listar comprobantes de una orden de compra.
     */
    public function listar_comprobantes(int $id_orden_compra): JsonResponse
    {
        return response()->json(OCComprobanteService::listar_comprobantes($id_orden_compra));
    }
}
