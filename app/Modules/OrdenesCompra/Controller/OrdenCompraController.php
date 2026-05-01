<?php

namespace App\Modules\OrdenesCompra\Controller;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\OrdenesCompra\Service\OrdenCompraService;

class OrdenCompraController
{
    /**
     * Listar todas las órdenes de compra
     */
    public function get_listado(Request $request): JsonResponse
    {
        $mes = $request->query('mes') ? (int) $request->query('mes') : null;
        $year = $request->query('year') ? (int) $request->query('year') : null;

        $result = OrdenCompraService::get_ordenes(mes: $mes, yearcito: $year);
        return response()->json($result);
    }

    /**
     * Obtener los detalles de una OC
     */
    public function get_detalles(Request $request): JsonResponse
    {
        $id_orden_compra = (int) $request->query('id_orden_compra');

        if (!$id_orden_compra) {
            return response()->json(ApiResponse::error('Debe indicar el id de la Orden de Compra.'));
        }

        $result = OrdenCompraService::get_detalles($id_orden_compra);
        return response()->json($result);
    }

    /**
     * Obtener el seguimiento de un detalle de OC
     */
    public function get_seguimiento(Request $request): JsonResponse
    {
        $id_detalle = (int) $request->query('id_orden_compra_detalle');

        if (!$id_detalle) {
            return response()->json(ApiResponse::error('Debe indicar el id de detalle.'));
        }

        $result = OrdenCompraService::get_seguimiento($id_detalle);
        return response()->json($result);
    }
}
