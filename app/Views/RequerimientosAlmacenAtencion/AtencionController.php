
<?php

namespace App\Controllers;

use App\Services\RequerimientoAlmacenService;
use App\Services\RequerimientoAlmacenEntregaService;
use App\Services\LoteService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class RequerimientoAlmacenAtencionController extends Controller
{
    public function __construct(
        private RequerimientoAlmacenService $requerimientoService,
        private RequerimientoAlmacenEntregaService $entregaService,
        private LoteService $loteService
    ) {}

    /**
     * Listado de requerimientos para atención por almacén.
     */
    public function obtener_requerimientos_atencion(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        if (! $id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $estado = $request->input('estado');
        $result = $this->requerimientoService->obtener_requerimientos_atencion((int) $id_almacen, $estado);

        return response()->json($result);
    }

    /**
     * Aprobar o Rechazar un ítem del requerimiento.
     */
    public function cambiar_estado_detalle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento_almacen_detalle' => 'required|integer',
            'nuevo_estado' => 'required|string',
            'comentario_decision' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->requerimientoService->cambiar_estado_detalle(
            $authUser->id_empleado,
            (int) $request->id_requerimiento_almacen_detalle,
            $request->nuevo_estado,
            $request->comentario_decision
        );

        return response()->json($result);
    }

    /**
     * Obtener lotes disponibles (sugeridos) para un producto en un almacén.
     */
    public function obtener_lotes_disponibles(Request $request): JsonResponse
    {
        $id_producto = $request->input('id_producto');
        $id_almacen = $request->input('id_almacen');

        if (! $id_producto || ! $id_almacen) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'), 400);
        }

        $result = $this->loteService->obtener_lotes_disponibles((int) $id_producto, (int) $id_almacen);

        return response()->json($result);
    }

    /**
     * Registrar la entrega física de productos.
     */
    public function registrar_entrega(Request $request): JsonResponse
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
     * Obtiene los productos de un requerimiento listos para ser atendidos,
     * incluyendo los lotes disponibles en el almacén de destino.
     */
    public function obtener_detalles_atencion(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->requerimientoService->get_detalles_para_atencion((int) $id);

        return response()->json($result);
    }

    /**
     * Obtener el historial de entregas realizadas para un ítem específico.
     */
    public function obtener_historial_entregas_por_item(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_requerimiento_almacen_detalle');
        if (! $id_detalle) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = $this->entregaService->obtener_historial_entregas_por_item((int) $id_detalle);

        return response()->json($result);
    }
}
