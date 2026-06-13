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
            // Nuevos
            'serie_factura_compra' => 'nullable|string',
            'numero_factura_compra' => 'nullable|string',
            'costo_por_unidad' => 'nullable|numeric|min:0',
        ], [
            'id_producto.required' => 'El producto es requerido',
            'id_unidad_medida.required' => 'La unidad de medida es requerida',
            'id_almacen.required' => 'El almacén es requerido',
            'stock_inicial.min' => 'El stock inicial no puede ser negativo',
            'contenido_por_presentacion.required' => 'El contenido por presentación es requerido',
            'costo_por_unidad.min' => 'El costo unitario no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::crear_lote(
            id_producto: (int) $request->id_producto,
            id_unidad_medida: (int) $request->id_unidad_medida,
            id_almacen: (int) $request->id_almacen,
            descripcion: $request->descripcion ?? null,
            stock_inicial: (float) $request->stock_inicial,
            contenido_por_presentacion: (float) $request->contenido_por_presentacion,
            fecha_hora_ingreso: $request->fecha_hora_ingreso,
            fecha_vencimiento: $request->fecha_vencimiento,
            // Nuevos
            serie_factura_compra: $request->serie_factura_compra ?? null,
            numero_factura_compra: $request->numero_factura_compra ?? null,
            costo_por_unidad: $request->has('costo_por_unidad') && $request->costo_por_unidad !== null ? (float) $request->costo_por_unidad : null
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
