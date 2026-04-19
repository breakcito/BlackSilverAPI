<?php

namespace App\Modules\OrdenCompra;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\OrdenCompra\OrdenCompraService;

class OrdenCompraController
{
    /**
     * Listar todas las órdenes de compra
     */
    public function get_listado(Request $request): JsonResponse
    {
        $mes = $request->query('mes') ? (int) $request->query('mes') : null;
        $year = $request->query('year') ? (int) $request->query('year') : null;

        $result = OrdenCompraService::listar($mes, $year);
        return response()->json($result);
    }

    /**
     * Obtener una orden de compra por ID
     */
    public function get_orden(Request $request): JsonResponse
    {
        $id = (int) $request->query('id');
        $result = OrdenCompraService::get_cabecera($id);
        return response()->json($result);
    }

    /**
     */
    public function get_detalles(Request $request): JsonResponse
    {
        $id_orden_compra = (int) $request->query('id_orden_compra');

        if (!$id_orden_compra) {
            return response()->json(\App\Shared\Responses\ApiResponse::error('Debe indicar el id de la Orden de Compra.'));
        }

        $result = OrdenCompraService::get_detalles($id_orden_compra);
        return response()->json($result);
    }
}
