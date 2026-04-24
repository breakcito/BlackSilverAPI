<?php

namespace App\Modules\Cotizaciones\Controller;

use App\Modules\Cotizaciones\Service\AuxService;
use Illuminate\Http\JsonResponse;

class AuxController
{
    /**
     * Obtener unidades de medida
     */
    public function get_unidades_medida(): JsonResponse
    {
        return response()->json(AuxService::get_unidades_medida());
    }

    /**
     * Obtener catálogo de productos
     */
    public function get_productos(): JsonResponse
    {
        return response()->json(AuxService::get_productos());
    }

    /**
     * Obtener proveedores habilitados
     */
    public function get_proveedores(): JsonResponse
    {
        return response()->json(AuxService::get_proveedores());
    }

    /**
     * Obtener almacenes activos (para seleccionar el recepcionista)
     */
    public function get_almacenes(): JsonResponse
    {
        return response()->json(AuxService::get_almacenes());
    }

    /**
     * Obtener empresas
     */
    public function get_empresas(): JsonResponse
    {
        return response()->json(AuxService::get_empresas());
    }
}
