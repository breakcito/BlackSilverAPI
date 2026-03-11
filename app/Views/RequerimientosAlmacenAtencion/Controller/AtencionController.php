<?php

namespace App\Views\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Views\RequerimientosAlmacenAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    public function __construct(
        private AtencionService $atencionService
    ) {}

    /**
     * Obtener almacenes donde el usuario es responsable.
     */
    public function get_almacenes_autorizados(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = $this->atencionService->get_almacenes_autorizados($authUser->id_empleado);

        return response()->json($result);
    }

    /**
     * Listado de requerimientos para atención por almacén.
     */
    public function get_requerimientos(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        if (! $id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'), 400);
        }

        $result = $this->atencionService->get_requerimientos((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    /**
     * Obtener los detalles de un requerimiento.
     */
    public function get_detalles_requerimiento(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = $this->atencionService->get_detalles_requerimiento((int) $id);

        return response()->json($result);
    }

    /**
     * Aprobar o Rechazar un ítem del requerimiento.
     */
    public function update_estado_detalle_requerimiento(Request $request): JsonResponse
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

        $result = $this->atencionService->cambiar_estado_detalle(
            $authUser->id_empleado,
            (int) $request->id_requerimiento_almacen_detalle,
            $request->nuevo_estado,
            $request->comentario_decision
        );

        return response()->json($result);
    }

    /**
     * Obtener trazabilidad de un detalle de requerimiento.
     */
    public function get_trazabilidad(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_requerimiento_almacen_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = $this->atencionService->obtener_trazabilidad((int) $id_detalle);

        return response()->json($result);
    }
}
