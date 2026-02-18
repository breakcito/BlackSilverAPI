<?php

namespace App\Modules\Inventario\Controllers;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class KardexController extends Controller
{
    private $kardexService; // Definir service despues
    
    // Por simplicidad, usare DB directo temporal si no hay service o lo creo rapido
    // Ah, ya creé el service. Vamos a usarlo.
    
    public function __construct(
         \App\Modules\Inventario\Services\KardexService $kardexService
    ) {
        $this->kardexService = $kardexService;
    }

    public function get_movimientos(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->kardexService->get_movimientos((int)$id_almacen);
        return response()->json($result);
    }
}
