<?php

namespace App\Views\PrestamosAlmacen\Controller;

use App\Views\PrestamosAlmacen\Service\AuxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuxController extends Controller
{
    /**
     * Obtiene los almacenes secundarios
     */
    public function get_almacenes_secundarios(Request $request): JsonResponse
    {
        $result = AuxService::get_almacenes_secundarios();
        return response()->json($result);
    }
}
