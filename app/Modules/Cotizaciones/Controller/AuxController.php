<?php

namespace App\Modules\Cotizaciones\Controller;

use App\Modules\Cotizaciones\Service\AuxService;
use Illuminate\Http\JsonResponse;

class AuxController
{

    /**
     * Obtener proveedores habilitados
     */
    public function get_proveedores(): JsonResponse
    {
        return response()->json(AuxService::get_proveedores());
    }

    /**
     * Obtener empresas
     */
    public function get_empresas(): JsonResponse
    {
        return response()->json(AuxService::get_empresas());
    }
}
