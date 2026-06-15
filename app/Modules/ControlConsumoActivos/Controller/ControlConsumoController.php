<?php

namespace App\Modules\ControlConsumoActivos\Controller;

use App\Modules\ControlConsumoActivos\Service\ControlConsumoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


/**
 * Controlador de API para la gestión de registros de consumo de activos fijos.
 */
class ControlConsumoController extends Controller
{
    /**
     * Obtener el reporte de consumo de activos fijos e insumos.
     */
    public function get_reporte(Request $request): JsonResponse
    {
        $mes = $request->input('mes') ? (int) $request->input('mes') : null;
        $yearcito = $request->input('yearcito') ? (int) $request->input('yearcito') : null;

        $res = ControlConsumoService::get_reporte($mes, $yearcito);
        return response()->json($res);
    }

    /**
     * Registrar un nuevo consumo de un detalle de entrega de requerimiento.
     */
    public function registrar_consumo(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $request->validate([
            'id_requerimiento_almacen_entrega_detalle' => 'required|integer',
            'cantidad_base_consumida' => 'required|numeric|gt:0',
            'fecha_hora_consumo' => 'required|date',
            'comentario_consumo' => 'nullable|string',
            'id_activo_fijo_consumidor' => 'required_if:para_mantenimiento,true,1|nullable|integer',
            'id_labor_destino' => 'nullable|integer',
            'id_labores' => 'nullable|array',
            'id_labores.*' => 'integer',
            'id_lote_mineral' => 'required_if:para_produccion,true,1|nullable|integer',
            'para_mantenimiento' => 'nullable|boolean',
            'para_produccion' => 'nullable|boolean',
        ]);

        $res = ControlConsumoService::registrar_consumo(
            (int) $authUser->id_empleado,
            (int) $request->input('id_requerimiento_almacen_entrega_detalle'),
            (float) $request->input('cantidad_base_consumida'),
            (string) $request->input('fecha_hora_consumo'),
            $request->input('comentario_consumo') ? (string) $request->input('comentario_consumo') : null,
            $request->input('id_activo_fijo_consumidor') ? (int) $request->input('id_activo_fijo_consumidor') : null,
            $request->input('id_labor_destino') ? (int) $request->input('id_labor_destino') : null,
            $request->input('id_labores'),
            $request->input('id_lote_mineral') ? (int) $request->input('id_lote_mineral') : null,
            (bool) $request->input('para_mantenimiento', false),
            (bool) $request->input('para_produccion', false)
        );

        return response()->json($res);
    }
}
