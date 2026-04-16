<?php

namespace App\Modules\Cotizaciones;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\Cotizaciones\CotizacionesService;

class CotizacionesController
{
    /**
     * Registrar un nuevo comparativo con sus cotizaciones
     */
    public function registrar_comparativo(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'productos'    => 'required|array|min:1',
            'cotizaciones' => 'required|array|min:1',
            'cotizaciones.*.empresas_ids' => 'required|array|min:1',
            'cotizaciones.*.empresas_ids.*' => 'integer',
        ], [
            'productos.required'    => 'Debe incluir al menos un producto para el comparativo.',
            'cotizaciones.required' => 'Debe incluir al menos una cotización de proveedor.',
            'cotizaciones.*.empresas_ids.required' => 'Debe seleccionar al menos una empresa para cada cotización.',
        ]);

        if ($validator->fails()) {
            return response()->json(\App\Shared\Responses\ApiResponse::error($validator->errors()->first()));
        }

        $result = CotizacionesService::registrar_comparativo(
            productos: $request->input('productos'),
            cotizaciones: $request->input('cotizaciones')
        );

        return response()->json($result);
    }

    /**
     * Listar cotizaciones agrupadas
     */
    public function get_listado(Request $request): JsonResponse
    {
        $result = CotizacionesService::listar();
        return response()->json($result);
    }

    /**
     * Obtener unidades de medida (Independencia de vista)
     */
    public function get_unidades_medida(): JsonResponse
    {
        $result = CotizacionesService::get_unidades_medida();
        return response()->json($result);
    }

    /**
     * Obtener productos (Independencia de vista)
     */
    public function get_productos(): JsonResponse
    {
        $result = CotizacionesService::get_productos();
        return response()->json($result);
    }

    /**
     * Obtener proveedores (Independencia de vista)
     */
    public function get_proveedores(): JsonResponse
    {
        $result = CotizacionesService::get_proveedores();
        return response()->json($result);
    }

    /**
     * Aprobar una cotización específica
     */
    public function aprobar_cotizacion(int $id): JsonResponse
    {
        $result = CotizacionesService::aprobar_cotizacion($id);
        return response()->json($result);
    }
}
