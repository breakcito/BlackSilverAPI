<?php

namespace App\Modules\KardexCarbon\Controller;

use App\Modules\KardexCarbon\Service\KardexCarbonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KardexCarbonController
{
    public function get_movimientos(Request $request): JsonResponse
    {
        $opts = [
            'id_almacen' => $request->query('id_almacen'),
            'id_tipo_carbon' => $request->query('id_tipo_carbon'),
            'mes' => $request->query('mes'),
            'anio' => $request->query('anio'),
            'filtros' => $request->query('filtros'),
        ];

        return response()->json(KardexCarbonService::get_movimientos($opts));
    }
}
