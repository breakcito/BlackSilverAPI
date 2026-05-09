<?php

namespace App\Modules\LotesProductos\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Service\LotesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class LotesController extends Controller
{

    public function get_resumen_lotes(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = LotesService::get_resumen_lotes((int) $id_almacen);

        return response()->json($result);
    }

    public function crear_lote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|integer',
            'id_unidad_medida' => 'required|integer',
            'id_almacen' => 'required|integer',
            'descripcion' => 'nullable|string',
            'stock_inicial' => 'required|numeric|min:0',
            'contenido_por_presentacion' => 'required|numeric|min:0',
            'fecha_hora_ingreso' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_hora_ingreso',
        ], [
            'id_producto.required' => 'El producto es requerido',
            'id_unidad_medida.required' => 'La unidad de medida es requerida',
            'id_almacen.required' => 'El almacén es requerido',
            'stock_inicial.min' => 'El stock inicial no puede ser negativo',
            'contenido_por_presentacion.required' => 'El contenido por presentación es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::crear_lote(
            $request->id_producto,
            $request->id_unidad_medida,
            $request->id_almacen,
            $request->descripcion ?? null,
            (float) $request->stock_inicial,
            (float) $request->contenido_por_presentacion,
            $request->fecha_hora_ingreso,
            $request->fecha_vencimiento
        );

        return response()->json($result);
    }

    public function ajustar_stock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_lote' => 'required|integer',
            'nuevo_stock_base' => 'required|numeric|min:0',
            'motivo' => 'nullable|string',
        ], [
            'id_lote.required' => 'El lote es requerido',
            'nuevo_stock_base.required' => 'El nuevo stock base es requerido',
            'nuevo_stock_base.min' => 'El stock base no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::ajustar_stock(
            (int) $request->id_lote,
            (float) $request->nuevo_stock_base,
            $request->motivo ?? null
        );

        return response()->json($result);
    }
    public function get_info_to_tickets(Request $request): JsonResponse
    {
        $ids_lotes = $request->query('ids');

        if (!$ids_lotes) {
            return response()->json(ApiResponse::error('Los IDs de lotes son requeridos'), 400);
        }

        // Convertir string "1,2,3" a array [1, 2, 3] si es necesario
        $ids_array = is_array($ids_lotes) ? $ids_lotes : explode(',', $ids_lotes);
        $ids_array = array_map('intval', $ids_array);

        $result = LotesService::get_info_to_tickets($ids_array);

        return response()->json($result);
    }
}
