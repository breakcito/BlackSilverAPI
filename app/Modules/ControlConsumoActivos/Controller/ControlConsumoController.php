<?php

namespace App\Modules\ControlConsumoActivos\Controller;

use App\Modules\ControlConsumoActivos\Service\ControlConsumoService;
use App\Shared\Enums\_Generic\TipoTurno;
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
        $mes = $request->input('mes') ? (int) $request->input('mes') : (int) now()->month;
        $yearcito = $request->input('yearcito') ? (int) $request->input('yearcito') : (int) now()->year;

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
            'tipo_turno' => 'nullable|string|in:Dia,Noche',
        ]);

        $tipoTurno = $request->filled('tipo_turno')
            ? TipoTurno::tryFrom((string) $request->input('tipo_turno'))
            : null;

        $res = ControlConsumoService::registrar_consumo(
            id_empleado_registro: (int) $authUser->id_empleado,
            id_detalle: (int) $request->input('id_requerimiento_almacen_entrega_detalle'),
            cantidad_base_consumida: (float) $request->input('cantidad_base_consumida'),
            fecha_hora_consumo: (string) $request->input('fecha_hora_consumo'),
            comentario_consumo: $request->input('comentario_consumo') ? (string) $request->input('comentario_consumo') : null,
            id_activo_fijo_consumidor: $request->input('id_activo_fijo_consumidor') ? (int) $request->input('id_activo_fijo_consumidor') : null,
            id_labor_destino: $request->input('id_labor_destino') ? (int) $request->input('id_labor_destino') : null,
            id_lote_mineral: $request->input('id_lote_mineral') ? (int) $request->input('id_lote_mineral') : null,
            para_mantenimiento: (bool) $request->input('para_mantenimiento', false),
            para_produccion: (bool) $request->input('para_produccion', false),
            tipo_turno: $tipoTurno
        );

        return response()->json($res);
    }

    /**
     * Registrar un consumo DIRECTO (sin requerimiento previo).
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
            'tipo_turno'                  => 'nullable|string|in:Dia,Noche',
        ]);

        $estadoRaw = $request->input('estado') ?? 'Consumo Total';
        $estadoEnum = EstadoConsumoDetalleEntregaReq::tryFrom($estadoRaw)
            ?? EstadoConsumoDetalleEntregaReq::ConsumoTotal;

        $tipoTurno = $request->filled('tipo_turno')
            ? TipoTurno::tryFrom((string) $request->input('tipo_turno'))
            : null;

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
            tipo_turno: $tipoTurno
        );

        return response()->json($res);
    }

    /**
     * Actualizar el turno de un consumo.
     */
    public function actualizar_turno(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $request->validate([
            'tipo_turno' => 'required|string|in:Dia,Noche',
        ]);

        $tipoTurno = TipoTurno::from((string) $request->input('tipo_turno'));

        $res = ControlConsumoService::actualizar_turno($id, $tipoTurno);

        return response()->json($res);
    }

    /**
     * Obtener los gastos extra del periodo.
     */
    public function get_gastos_extra(Request $request): JsonResponse
    {
        $mes = $request->input('mes') ? (int) $request->input('mes') : (int) now()->month;
        $yearcito = $request->input('yearcito') ? (int) $request->input('yearcito') : (int) now()->year;

        $res = ControlConsumoService::get_gastos_extra($mes, $yearcito);
        return response()->json($res);
    }

    /**
     * Registrar un nuevo gasto extra.
     */
    public function registrar_gasto_extra(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $request->validate([
            'id_labor' => 'required|integer',
            'descripcion' => 'required|string|max:512',
            'monto' => 'required|numeric|gt:0',
        ]);

        $res = ControlConsumoService::registrar_gasto_extra(
            id_empleado_registro: (int) $authUser->id_empleado,
            id_labor: (int) $request->input('id_labor'),
            descripcion: trim((string) $request->input('descripcion')),
            monto: (float) $request->input('monto')
        );

        return response()->json($res);
    }
}
