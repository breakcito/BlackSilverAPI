<?php

namespace App\Views\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Service\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{
    public function __construct(
        private EntregaService $entregaService
    ) {}

    /**
     * Obtener lotes disponibles para productos en un almacén.
     */
    public function get_lotes_disponibles(Request $request): JsonResponse
    {
        $id_producto = $request->input('id_producto');
        $id_almacen = $request->input('id_almacen');

        if (is_null($id_producto) || is_null($id_almacen)) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'), 400);
        }

        // Handle both single int and array of ints for backwards compatibility and new batch feature
        $ids_productos = is_array($id_producto) ? array_map('intval', $id_producto) : [(int) $id_producto];

        $result = $this->entregaService->obtener_lotes_disponibles($ids_productos, (int) $id_almacen);

        return response()->json($result);
    }

    /**
     * Registrar la entrega física de productos.
     */
    public function crear_entrega(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'id_empleado_recibe' => 'required|integer',
            'fecha_entrega' => 'required|date',
            'observacion' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_requerimiento_almacen_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'required|integer',
            'detalles.*.cantidad_base' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_lote' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_requerimiento' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->entregaService->registrar_entrega(
            $authUser->id_empleado,
            (int) $request->id_requerimiento,
            (int) $request->id_empleado_recibe,
            $request->fecha_entrega,
            $request->observacion,
            $request->detalles
        );

        return response()->json($result);
    }

    /**
     * Obtener el historial de entregas realizadas para un requerimiento específico.
     */
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_requerimiento = $request->input('id_requerimiento');
        if (! $id_requerimiento) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->entregaService->obtener_historial_entregas((int) $id_requerimiento);

        return response()->json($result);
    }

    /**
     * Obtener empleados facultados para recibir material.
     */
    public function get_empleados(): JsonResponse
    {
        $result = $this->entregaService->obtener_empleados();
        return response()->json($result);
    }
}
