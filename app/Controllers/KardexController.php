<?php

namespace App\Controllers;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KardexController extends Controller
{
    private $kardexService;

    public function obtener_kardex(
        Request $request,
        \App\Services\KardexService $kardexService
    ) {
        $this->kardexService = $kardexService;
    }

    public function get_movimientos(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->kardexService->get_movimientos((int) $id_almacen);

        return response()->json($result);
    }
}
