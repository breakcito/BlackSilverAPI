<?php

namespace App\Modules\MantenimientoActivos\Controller;

use App\Modules\MantenimientoActivos\Service\MantenimientoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class MantenimientoController extends Controller
{
    /**
     * Obtener listado de mantenimientos de activos por periodo (mes y año).
     */
    public function get_mantenimientos(Request $request): JsonResponse
    {
        $mes = $request->query('mes');
        $yearcito = $request->query('yearcito');
        $id_activo_fijo = $request->query('id_activo_fijo');

        if (!$mes || !$yearcito) {
            return response()->json(ApiResponse::error('mes y yearcito son requeridos'), 400);
        }

        $result = MantenimientoService::get_mantenimientos(
            (int) $mes,
            (int) $yearcito,
            $id_activo_fijo ? (int) $id_activo_fijo : null
        );

        return response()->json($result);
    }

    /**
     * Registrar un nuevo mantenimiento.
     */
    public function crear_mantenimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $validator = Validator::make($request->all(), [
            'id_activo_fijo' => 'required|integer',
            'id_mina' => 'nullable|integer',
            'id_almacen' => 'nullable|integer',
            'id_empleado_supervisor' => 'nullable|integer',
            'id_proveedor' => 'nullable|integer',
            'id_personal_externo' => 'nullable|integer',
            'id_empleado_ejecutor' => 'nullable|integer',
            'fecha_hora_mantenimiento' => 'required|date',
            'observacion' => 'nullable|string',
            'lugar_trabajo' => 'nullable|string',
            'serie_factura' => 'nullable|string',
            'numero_factura' => 'nullable|string',
            'costo_mano_obra' => 'nullable|numeric',
            'otros_gastos' => 'nullable|array',
            'productos_consumidos' => 'nullable|array',
            'productos_consumidos.*.id_entrega_detalle' => 'required|integer',
            'productos_consumidos.*.cantidad' => 'required|numeric|gt:0',
            'productos_consumidos.*.comentario' => 'nullable|string',
            'consumos_confirmados' => 'nullable|array',
            'consumos_confirmados.*' => 'integer',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $evidencias = $request->file('evidencias', []);

        $result = MantenimientoService::crear_mantenimiento(
            (int) $authUser->id_empleado,
            $request->all(),
            $evidencias
        );

        return response()->json($result);
    }

    /**
     * Obtener productos despachados pendientes de consumo para un activo específico.
     */
    public function get_productos_despachados(Request $request): JsonResponse
    {
        $id_activo_fijo = $request->query('id_activo_fijo');
        if (!$id_activo_fijo) {
            return response()->json(ApiResponse::error('id_activo_fijo es requerido'), 400);
        }

        $result = MantenimientoService::get_productos_despachados((int) $id_activo_fijo);
        return response()->json($result);
    }
}
