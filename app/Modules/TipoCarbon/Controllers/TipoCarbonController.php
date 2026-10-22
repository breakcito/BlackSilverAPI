<?php

namespace App\Modules\TipoCarbon\Controllers;

use App\Modules\TipoCarbon\Service\CalidadCarbonService;
use App\Modules\TipoCarbon\Service\TipoCarbonService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoCarbonController
{
    public function get_tipos(Request $request): JsonResponse
    {
        $raw = $request->query('para_compra');
        $soloParaCompra = ($raw === null || $raw === '')
            ? null
            : filter_var($raw, FILTER_VALIDATE_BOOLEAN);

        $idProveedor = $request->has('id_proveedor') && is_numeric($request->query('id_proveedor'))
            ? (int) $request->query('id_proveedor')
            : null;

        return response()->json(TipoCarbonService::get_tipos(
            solo_para_compra: $soloParaCompra,
            id_proveedor: $idProveedor
        ));
    }

    public function get_tipo_by_id_route(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_tipo_by_id(id_tipo_carbon: $id_tipo_carbon));
    }

    public function crear_tipo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'codigo' => 'nullable|string|max:32',
            'para_compra' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(TipoCarbonService::crear_tipo(
            nombre: (string) $request->input('nombre'),
            codigo: $request->input('codigo'),
            para_compra: (bool) $request->boolean('para_compra')
        ));
    }

    public function actualizar_tipo(Request $request, int $id_tipo_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'codigo' => 'nullable|string|max:32',
            'para_compra' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(TipoCarbonService::actualizar_tipo(
            id_tipo_carbon: $id_tipo_carbon,
            nombre: (string) $request->input('nombre'),
            codigo: $request->input('codigo'),
            para_compra: (bool) $request->boolean('para_compra')
        ));
    }

    public function eliminar_tipo(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::eliminar_tipo(id_tipo_carbon: $id_tipo_carbon));
    }

    public function get_variantes(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_variantes(id_tipo_carbon: $id_tipo_carbon));
    }

    public function get_variantes_opciones(int $id_tipo_carbon): JsonResponse
    {
        return response()->json(TipoCarbonService::get_variantes_opciones(id_tipo_carbon: $id_tipo_carbon));
    }

    /**
     * @param Request $request 'variantes' es un array de
     *   { id_tipo_variante: int, porcentaje_extraccion: number }.
     */
    public function set_variantes(Request $request, int $id_tipo_carbon): JsonResponse
    {
        $variantesRaw = $request->input('variantes', []);

        // Se normaliza el shape: se acepta tanto un id suelto (compatibilidad)
        // como un objeto con el porcentaje de extraccion.
        $variantes = [];
        foreach ((array) $variantesRaw as $item) {
            if (is_int($item) || is_numeric($item)) {
                $variantes[] = [
                    'id_tipo_variante' => (int) $item,
                    'porcentaje_extraccion' => 0,
                ];
                continue;
            }
            if (!is_array($item) || !isset($item['id_tipo_variante'])) {
                continue;
            }
            $variantes[] = [
                'id_tipo_variante' => (int) $item['id_tipo_variante'],
                'porcentaje_extraccion' => (float) ($item['porcentaje_extraccion'] ?? 0),
            ];
        }

        $validator = Validator::make(['variantes' => $variantes], [
            'variantes' => 'present|array',
            'variantes.*.id_tipo_variante' => 'required|integer|min:1',
            'variantes.*.porcentaje_extraccion' => 'required|numeric|min:0|max:100',
        ], [
            'variantes.*.porcentaje_extraccion.max' => 'El porcentaje de extracción no puede superar el 100%',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        return response()->json(TipoCarbonService::set_variantes(
            id_tipo_carbon: $id_tipo_carbon,
            variantes: $variantes
        ));
    }

    /**
     * Lista las calidades de carbón con sus rangos de % de ceniza.
     */
    public function get_calidades(): JsonResponse
    {
        return response()->json(CalidadCarbonService::get_calidades());
    }

    /**
     * Actualiza el rango de % de ceniza de una calidad.
     */
    public function actualizar_calidad(Request $request, int $id_calidad_carbon): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'porcentaje_inicio_ceniza' => 'required|numeric|min:0|max:100',
            'porcentaje_fin_ceniza' => 'required|numeric|min:0|max:100',
        ], [
            'porcentaje_inicio_ceniza.required' => 'El porcentaje de ceniza inicial es obligatorio',
            'porcentaje_fin_ceniza.required' => 'El porcentaje de ceniza final es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 422);
        }

        $inicio = (float) $request->input('porcentaje_inicio_ceniza');
        $fin = (float) $request->input('porcentaje_fin_ceniza');

        if ($fin <= $inicio) {
            return response()->json(
                ApiResponse::error('El porcentaje final de ceniza debe ser mayor al inicial'),
                422
            );
        }

        return response()->json(CalidadCarbonService::actualizar_calidad(
            id_calidad_carbon: $id_calidad_carbon,
            porcentaje_inicio_ceniza: $inicio,
            porcentaje_fin_ceniza: $fin
        ));
    }
}