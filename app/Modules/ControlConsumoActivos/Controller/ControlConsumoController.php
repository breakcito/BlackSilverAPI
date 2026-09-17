<?php

namespace App\Modules\ControlConsumoActivos\Controller;

use App\Modules\ControlConsumoActivos\Service\ControlConsumoService;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
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
            $request->input('id_lote_mineral') ? (int) $request->input('id_lote_mineral') : null,
            (bool) $request->input('para_mantenimiento', false),
            (bool) $request->input('para_produccion', false)
        );

        return response()->json($res);
    }

    /**
     * Registrar un consumo DIRECTO (sin requerimiento previo).
     * Se usa desde Control de Uso (horometro modal) y desde el boton
     * "Registrar Uso de Combustible" en la pagina de Consumo.
     */
    public function registrar_consumo_directo(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $request->validate([
            'id_activo_fijo_consumidor' => 'required|integer',
            'id_producto'                 => 'required|integer',
            'id_almacen'                  => 'required|integer',
            'id_lote_producto'            => 'required|integer',
            'id_unidad_medida'            => 'required|integer',
            'cantidad_consumo'            => 'required|numeric|gt:0',
            'contenido_por_presentacion'  => 'required|numeric|gt:0',
            'cantidad_base'               => 'required|numeric|gt:0',
            'comentario_consumo'          => 'nullable|string',
            'uuid_control_uso_activo'     => 'required|string|size:36',
            'id_lote_mineral'             => 'nullable|integer',
            'id_labor_destino'            => 'nullable|integer',
            'para_mantenimiento'          => 'nullable|boolean',
            'para_produccion'             => 'nullable|boolean',
            'estado'                       => 'nullable|in:Consumo Parcial,Consumo Total',
        ]);

        $estadoRaw = $request->input('estado') ?? 'Consumo Total';
        $estadoEnum = EstadoConsumoDetalleEntregaReq::tryFrom($estadoRaw)
            ?? EstadoConsumoDetalleEntregaReq::ConsumoTotal;

        $res = ControlConsumoService::registrar_consumo_directo(
            id_empleado_registro: (int) $authUser->id_empleado,
            id_activo_fijo_consumidor: (int) $request->input('id_activo_fijo_consumidor'),
            id_lote_mineral: $request->input('id_lote_mineral') ? (int) $request->input('id_lote_mineral') : null,
            id_labor_destino: $request->input('id_labor_destino') ? (int) $request->input('id_labor_destino') : null,
            para_mantenimiento: (bool) $request->input('para_mantenimiento', false),
            para_produccion: (bool) $request->input('para_produccion', false),
            cantidad_base_consumida: (float) $request->input('cantidad_base'),
            fecha_hora_consumo: now()->toDateTimeString(),
            comentario_consumo: $request->input('comentario_consumo') ? (string) $request->input('comentario_consumo') : null,
            uuid_control_uso_activo: (string) $request->input('uuid_control_uso_activo'),
            id_producto: (int) $request->input('id_producto'),
            id_almacen: (int) $request->input('id_almacen'),
            id_lote_producto: (int) $request->input('id_lote_producto'),
            id_unidad_medida: (int) $request->input('id_unidad_medida'),
            contenido_por_presentacion: (float) $request->input('contenido_por_presentacion'),
            cantidad_consumo: (float) $request->input('cantidad_consumo'),
            cantidad_base: (float) $request->input('cantidad_base'),
            estado: $estadoEnum,
        );

        return response()->json($res);
    }
}
