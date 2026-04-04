<?php

namespace App\Views\SolicitudesReabastecimiento\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\SolicitudesReabastecimiento\Service\AuxService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;

class AuxController extends Controller
{
    // Obtener la lista de almacenes, productos y unidades de medida
    public function get_catalogos(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = AuxService::get_catalogo(
            $authUser->id_empleado,
        );
        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles en el almacen solicitante para recepcionar 
     * (registrar nuevos lotes o ajustar stock de los existentes) los 
     * productos entregados
     */
    public function get_lotes_destino(Request $request): JsonResponse
    {
        $id_almacen_solicitante = $request->input('id_almacen_solicitante');
        $id_productos = $request->input('id_productos');

        if (!$id_almacen_solicitante || empty($id_productos) || !is_array($id_productos)) {
            return response()->json(ApiResponse::error('El id_almacen_solicitante y un arreglo de id_productos son requeridos'), 400);
        }

        $result = AuxService::get_lotes_disponibles((int) $id_almacen_solicitante, $id_productos);
        return response()->json($result);
    }
}
