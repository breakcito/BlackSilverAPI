<?php

namespace App\Modules\SolicitudesReabastecimientoAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\SolicitudesReabastecimientoAtencion\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    public function get_almacenes_con_stock(Request $request): JsonResponse
    {
        $id_almacen_excluido = (int) $request->query('id_almacen_excluido');
        $ids_productos = (array) $request->query('ids_productos', []);

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('No se han especificado productos'), 400);
        }

        $result = AuxService::get_almacenes_con_stock($id_almacen_excluido, $ids_productos);
        return response()->json($result);
    }

    public function get_stock_total_almacen_por_productos(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $ids_productos = (array) $request->query('ids_productos', []);

        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        if (empty($ids_productos)) {
            return response()->json(ApiResponse::error('No se han especificado productos'), 400);
        }

        $result = AuxService::get_stock_total_almacen_por_productos($id_almacen, $ids_productos);
        return response()->json($result);
    }
}
