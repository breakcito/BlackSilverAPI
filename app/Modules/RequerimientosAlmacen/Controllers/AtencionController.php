<?php

namespace App\Modules\RequerimientosAlmacen\Controllers;

use Illuminate\Routing\Controller;
use App\Modules\RequerimientosAlmacen\Services\AtencionService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    public function __construct(private AtencionService $atencionService)
    {
    }

    /**
     * Listado de requerimientos para atención por almacén.
     */
    public function obtener_requerimientos_atencion(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $estado = $request->input('estado');
        $result = $this->atencionService->obtener_requerimientos_atencion((int)$id_almacen, $estado);
        return response()->json($result);
    }

    /**
     * Aprobar o Rechazar un ítem del requerimiento.
     */
    public function cambiar_estado_detalle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento_almacen_detalle' => 'required|integer',
            'nuevo_estado'                     => 'required|string',
            'comentario_rechazo'               => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = auth()->user();
        $result = $this->atencionService->cambiar_estado_detalle(
            $authUser->id_usuario,
            (int)$request->id_requerimiento_almacen_detalle,
            $request->nuevo_estado,
            $request->comentario_rechazo
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

        if (!$id_producto || !$id_almacen) {
            return response()->json(ApiResponse::error('id_producto e id_almacen son requeridos'), 400);
        }

        $result = $this->atencionService->obtener_lotes_disponibles((int)$id_producto, (int)$id_almacen);
        return response()->json($result);
    }

    /**
     * Registrar la entrega física de productos.
     */
    public function registrar_entrega(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'fecha_entrega'    => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.id_requerimiento_almacen_detalle' => 'required|integer',
            'detalles.*.id_lote'                          => 'required|integer',
            'detalles.*.cantidad'                         => 'required|numeric|min:0.01'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = auth()->user();
        $result = $this->atencionService->registrar_entrega(
            $authUser->id_usuario,
            (int)$request->id_requerimiento,
            $request->fecha_entrega,
            $request->observacion,
            $request->detalles
        );

        return response()->json($result);
    }
}
