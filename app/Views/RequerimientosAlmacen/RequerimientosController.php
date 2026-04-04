<?php

namespace App\Views\RequerimientosAlmacen;

use App\Shared\Enums\Premura;
use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacen\RequerimientosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class RequerimientosController extends Controller
{
    public function __construct(
        private RequerimientosService $requerimientoService
    ) {}

    public function get_requerimientos(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $mes = $request->query('mes');
        $yearcito = $request->query('yearcito');

        $result = $this->requerimientoService->get_requerimientos(
            $authUser->id_empleado,
            $mes,
            $yearcito
        );

        return response()->json($result);
    }

    public function crear_requerimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_mina' => 'required|integer',
            'id_almacen_destino' => 'required|integer',
            'premura' => ['required', new Enum(Premura::class)],
            'fecha_entrega_requerida' => 'nullable|date',
            'observacion' => 'nullable|string',
            'id_labores' => 'nullable|array',
            'id_labores.*' => 'integer',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
            'detalles.*.id_producto_destino' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->requerimientoService->crear_requerimiento(
            $authUser->id_empleado,
            (int) $request->id_mina,
            (int) $request->id_almacen_destino,
            $request->premura,
            $request->fecha_entrega_requerida,
            $request->observacion,
            $request->id_labores,
            $request->detalles
        );

        return response()->json($result);
    }

    public function get_detalle_by_requerimiento(Request $request): JsonResponse
    {
        $id = $request->query('id_requerimiento');
        if (! $id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->requerimientoService->get_detalle_by_requerimiento((int) $id);

        return response()->json($result);
    }

    public function get_trazabilidad_by_detalle(Request $request): JsonResponse
    {
        $id = $request->query('id_requerimiento_almacen_detalle');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = $this->requerimientoService->get_trazabilidad_by_detalle((int) $id);

        return response()->json($result);
    }

    public function get_labores_by_requerimiento(Request $request): JsonResponse
    {
        $id = $request->query('id_requerimiento');
        if (! $id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->requerimientoService->get_labores_by_requerimiento((int) $id);

        return response()->json($result);
    }

    public function get_data_to_registro(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $result = $this->requerimientoService->get_data_to_registro($authUser->id_empleado);

        return response()->json($result);
    }

    public function get_data_by_mina(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (!$id_mina) {
            return response()->json(ApiResponse::error('Mina requerida'), 400);
        }

        $result = $this->requerimientoService->get_data_by_mina((int) $id_mina);

        return response()->json($result);
    }
}
