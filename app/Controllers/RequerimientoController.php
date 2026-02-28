<?php

namespace App\Modules\RequerimientosAlmacen\Controllers;

use App\Modules\RequerimientosAlmacen\Services\RequerimientoService;
use App\Shared\Enums\EstadoRequerimiento;
use App\Shared\Enums\Premura;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

class RequerimientoController extends Controller
{
    public function __construct(
        private RequerimientoService $requerimientoService
    ) {}

    public function get_requerimientos(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        $id_almacen_destino = $request->query('id_almacen_destino');
        $estado = $request->query('estado');
        $fecha_inicio = $request->query('fecha_inicio');
        $fecha_fin = $request->query('fecha_fin');

        $result = $this->requerimientoService->get_requerimientos(
            $id_mina ? (int)$id_mina : null,
            $id_almacen_destino ? (int)$id_almacen_destino : null,
            $estado,
            $fecha_inicio,
            $fecha_fin
        );

        return response()->json($result);
    }

    public function crear_requerimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_mina'                 => 'required|integer',
            'id_almacen_destino'      => 'required|integer',
            'premura'                 => ['required', new Enum(Premura::class)],
            'fecha_entrega_requerida' => 'nullable|date',
            'id_labores'              => 'nullable|array',
            'id_labores.*'            => 'integer',
            'detalles'                => 'required|array|min:1',
            'detalles.*.id_producto'         => 'required|integer',
            'detalles.*.id_unidad_medida'    => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.comentario'          => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $result = $this->requerimientoService->crear_requerimiento(
            $authUser->id_usuario,
            (int)$request->id_mina,
            $request->id_labores,
            (int)$request->id_almacen_destino,
            $request->premura,
            $request->fecha_entrega_requerida,
            $request->detalles
        );

        return response()->json($result);
    }

    public function get_almacenes_por_mina(Request $request): JsonResponse
    {
        $id_mina = $request->query('id_mina');
        if (!$id_mina) {
            return response()->json(ApiResponse::error('Mina requerida'), 400);
        }

        $result = $this->requerimientoService->get_almacenes_por_mina((int)$id_mina);
        return response()->json($result);
    }

    public function obtener_requerimiento_por_id(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->requerimientoService->get_requerimiento_por_id((int)$id);
        return response()->json($result);
    }

    public function obtener_trazabilidad_detalle(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento_almacen_detalle');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = $this->requerimientoService->get_trazabilidad_detalle((int)$id);
        return response()->json($result);
    }
}
