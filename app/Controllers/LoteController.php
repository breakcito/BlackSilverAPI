<?php

namespace App\Controllers;

use App\Services\LoteService;
use App\Services\UnidadMedidaService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class LoteController extends Controller
{
    public function __construct(
        private LoteService $loteService,
        private UnidadMedidaService $unidadMedidaService
    ) {}

    public function get_lotes_by_almacen(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = $this->loteService->get_lotes_by_almacen((int) $id_almacen);

        return response()->json($result);
    }

    public function get_productos_para_lote(Request $request): JsonResponse
    {
        $result = $this->loteService->get_productos_para_lote();

        return response()->json($result);
    }

    public function get_unidades_medida(Request $request): JsonResponse
    {
        $result = $this->unidadMedidaService->get_unidades_medida();

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
            'fecha_ingreso' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_ingreso',
        ], [
            'id_producto.required' => 'El producto es requerido',
            'id_unidad_medida.required' => 'La unidad de medida es requerida',
            'id_almacen.required' => 'El almacén es requerido',
            'stock_inicial.min' => 'El stock inicial no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->loteService->crear_lote(
            $request->id_producto,
            $request->id_unidad_medida,
            $request->id_almacen,
            $request->descripcion ?? null,
            (float) $request->stock_inicial,
            $request->fecha_ingreso,
            $request->fecha_vencimiento
        );

        return response()->json($result);
    }
}
