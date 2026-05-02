<?php

namespace App\Modules\OrdenesCompraRecepcionTransferencias\Controller;

use App\Modules\OrdenesCompraRecepcionTransferencias\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuxController
{
    public function get_almacenes(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        return response()->json(AuxService::get_almacenes_autorizados((int) $authUser->id_empleado));
    }

    public function get_lotes(Request $request): JsonResponse
    {
        $id_almacen = (int) $request->query('id_almacen');
        $ids_productos = array_map('intval', (array) $request->query('ids_productos', []));
        return response()->json(AuxService::get_lotes_disponibles($id_almacen, $ids_productos));
    }
}
