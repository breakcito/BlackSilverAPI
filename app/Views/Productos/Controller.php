
<?php

namespace App\Controllers;

use App\Services\ProductoService;
use App\Services\UnidadMedidaService;
use App\Shared\Enums\Periodo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class ProductoController extends Controller
{
    public function __construct(
        private ProductoService $productoService,
        private UnidadMedidaService $unidadMedidaService
    ) {}

    public function get_productos(Request $request): JsonResponse
    {
        $result = $this->productoService->get_productos();

        return response()->json($result);
    }

    public function get_unidades_medida_base(Request $request): JsonResponse
    {
        $result = $this->unidadMedidaService->get_unidades_medida(es_base: true);

        return response()->json($result);
    }

    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_categoria' => 'required|integer|exists:categoria,id',
            'id_unidad_medida_base' => 'required|integer|exists:unidad_medida,id',
            'nombre' => 'required|string|max:128',
            'es_fiscalizado' => 'required|boolean',
            'es_perecible' => 'required|boolean',
            'stock_minimo' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => ['nullable', new Enum(Periodo::class)],
            'dias_espera_vencimiento' => 'nullable|integer|min:0',
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_categoria.exists' => 'La categoría seleccionada no existe',
            'id_unidad_medida_base.required' => 'La unidad de medida base es requerida',
            'id_unidad_medida_base.exists' => 'La unidad de medida seleccionada no existe',
            'nombre.required' => 'El nombre es requerido',
            'es_fiscalizado.required' => 'Debe indicar si es fiscalizado',
            'es_perecible.required' => 'Debe indicar si es perecible',
            'periodo_espera_vencimiento.Illuminate\Validation\Rules\Enum' => 'El periodo de espera no es válido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = $this->productoService->crear_producto(
            id_categoria: $request->id_categoria,
            id_unidad_medida_base: $request->id_unidad_medida_base,
            nombre: $request->nombre,
            es_fiscalizado: $request->es_fiscalizado,
            es_perecible: $request->es_perecible,
            stock_minimo: (float) ($request->stock_minimo ?? 0),
            tiempo_espera_vencimiento: $request->tiempo_espera_vencimiento,
            periodo_espera_vencimiento: $request->periodo_espera_vencimiento,
            dias_espera_vencimiento: $request->dias_espera_vencimiento
        );

        return response()->json($result);
    }
}
