<?php

namespace App\Modules\ControlUso\Controller;

use App\Modules\ControlUso\Service\ControlUsoService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

/**
 * Controlador de API para la gestión de registros de uso de activos fijos.
 */
class ControlUsoController extends Controller
{
    /**
     * Obtener el listado de logs de uso.
     */
    public function get_logs(Request $request): JsonResponse
    {
        $tipo_control = $request->input('tipo_control', 'horometro');
        $mes = $request->input('mes') ? (int) $request->input('mes') : null;
        $anio = $request->input('anio') ? (int) $request->input('anio') : null;

        $res = \App\Modules\ControlUso\Service\ControlUsoService::get_logs($tipo_control, $mes, $anio);
        return response()->json($res);
    }

    /**
     * Obtener la última lectura para un activo fijo específico.
     */
    public function get_ultimo_horometro(Request $request, int $id_activo_fijo): JsonResponse
    {
        $res = \App\Modules\ControlUso\Service\ControlUsoService::get_ultimo_horometro($id_activo_fijo);
        return response()->json($res);
    }

    public function get_ultimo_odometro(Request $request, int $id_activo_fijo): JsonResponse
    {
        $res = \App\Modules\ControlUso\Service\ControlUsoService::get_ultimo_odometro($id_activo_fijo);
        return response()->json($res);
    }

    /**
     * Registrar un nuevo control de uso.
     */
    public function registrar_uso(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_activo_fijo' => 'required|integer',
            'fecha_hora_inicio_control' => 'required|date',
            'fecha_hora_fin_control' => 'nullable|date|after_or_equal:fecha_hora_inicio_control',
            
            // Horometro
            'horometro_inicio' => 'nullable|numeric|min:0',
            'horometro_fin' => 'nullable|numeric|min:0|gte:horometro_inicio',
            
            // Odometro
            'odometro_inicio' => 'nullable|numeric|min:0',
            'odometro_fin' => 'nullable|numeric|min:0|gte:odometro_inicio',
            
            // Vueltas
            'cantidad_vueltas' => 'nullable|integer|min:0',
            'cantidad_sacos'   => 'nullable|integer|min:0',

            // Tarifa
            'id_tarifa' => 'nullable|integer',
            'precio_unitario' => 'nullable|numeric|min:0',
            
            // Operativa
            'es_para_mina' => 'nullable|boolean',
            'id_mina' => 'nullable|integer',
            'id_labor' => 'nullable|integer',
            'id_lote_mineral' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
            'tipo_carga' => 'nullable|string',

            'observacion' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $res = \App\Modules\ControlUso\Service\ControlUsoService::registrar_uso(
            id_activo_fijo: (int) $v['id_activo_fijo'],
            fecha_hora_inicio_control: (string) $v['fecha_hora_inicio_control'],
            fecha_hora_fin_control: $v['fecha_hora_fin_control'] ?? null,
            horometro_inicio: isset($v['horometro_inicio']) ? (float) $v['horometro_inicio'] : null,
            horometro_fin: isset($v['horometro_fin']) ? (float) $v['horometro_fin'] : null,
            odometro_inicio: isset($v['odometro_inicio']) ? (float) $v['odometro_inicio'] : null,
            odometro_fin: isset($v['odometro_fin']) ? (float) $v['odometro_fin'] : null,
            cantidad_vueltas: isset($v['cantidad_vueltas']) ? (int) $v['cantidad_vueltas'] : null,
            cantidad_sacos: isset($v['cantidad_sacos']) ? (int) $v['cantidad_sacos'] : null,
            id_tarifa: isset($v['id_tarifa']) ? (int) $v['id_tarifa'] : null,
            precio_unitario: isset($v['precio_unitario']) ? (float) $v['precio_unitario'] : 0.0,
            es_para_mina: isset($v['es_para_mina']) ? (bool) $v['es_para_mina'] : null,
            id_mina: isset($v['id_mina']) ? (int) $v['id_mina'] : null,
            id_labor: isset($v['id_labor']) ? (int) $v['id_labor'] : null,
            id_lote_mineral: isset($v['id_lote_mineral']) ? (int) $v['id_lote_mineral'] : null,
            id_cliente: isset($v['id_cliente']) ? (int) $v['id_cliente'] : null,
            tipo_carga: isset($v['tipo_carga']) ? (string) $v['tipo_carga'] : null,
            observacion: isset($v['observacion']) ? (string) $v['observacion'] : null
        );

        return response()->json($res);
    }

    /**
     * Registrar multiples controles de uso en una sola transaccion (cabecera + items).
     */
    public function registrar_uso_bulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_activo_fijo'  => 'required|integer',
            'fecha_trabajo'   => 'required|date_format:Y-m-d',
            'id_tarifa'       => 'nullable|integer',
            'precio_unitario' => 'required|numeric|min:0',
            'es_para_mina'    => 'required|boolean',
            'id_mina'         => 'nullable|integer',
            'id_labor'        => 'nullable|integer',
            'id_cliente'      => 'nullable|integer',
            'id_lote_mineral' => 'nullable|integer',
            'tipo_carga'      => 'nullable|string|max:64',

            'items'                  => 'required|array|min:1',
            'items.*.hora_inicio'    => 'required|date_format:H:i',
            'items.*.hora_fin'       => 'required|date_format:H:i',
            'items.*.horometro_inicio' => 'nullable|numeric|min:0',
            'items.*.horometro_fin'    => 'nullable|numeric|min:0|gte:items.*.horometro_inicio',
            'items.*.observacion'    => 'nullable|string',
        ], [
            'id_activo_fijo.required'  => 'El activo fijo es requerido',
            'fecha_trabajo.required'   => 'La fecha del trabajo es requerida',
            'fecha_trabajo.date_format' => 'La fecha debe tener formato YYYY-MM-DD',
            'precio_unitario.required' => 'El precio unitario es requerido',
            'es_para_mina.required'    => 'Debe indicar si el destino es en mina o para terceros',
            'items.required'           => 'Debe incluir al menos un item de horario',
            'items.min'                => 'Debe incluir al menos un item de horario',
            'items.*.hora_inicio.required'  => 'La hora de inicio es obligatoria en todos los items',
            'items.*.hora_fin.required'     => 'La hora de fin es obligatoria en todos los items',
            'items.*.hora_inicio.date_format' => 'Hora de inicio debe tener formato HH:MM',
            'items.*.hora_fin.date_format'    => 'Hora de fin debe tener formato HH:MM',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $res = \App\Modules\ControlUso\Service\ControlUsoService::registrar_uso_bulk(
            id_activo_fijo: (int) $v['id_activo_fijo'],
            fecha_trabajo: (string) $v['fecha_trabajo'],
            id_tarifa: isset($v['id_tarifa']) ? (int) $v['id_tarifa'] : null,
            precio_unitario: (float) $v['precio_unitario'],
            es_para_mina: (bool) $v['es_para_mina'],
            id_mina: isset($v['id_mina']) ? (int) $v['id_mina'] : null,
            id_labor: isset($v['id_labor']) ? (int) $v['id_labor'] : null,
            id_cliente: isset($v['id_cliente']) ? (int) $v['id_cliente'] : null,
            id_lote_mineral: isset($v['id_lote_mineral']) ? (int) $v['id_lote_mineral'] : null,
            tipo_carga: isset($v['tipo_carga']) ? (string) $v['tipo_carga'] : null,
            items: $v['items'],
        );

        return response()->json($res);
    }

    /**
     * Registrar multiples controles por vueltas en una sola transaccion (cabecera + items[]).
     * Cada item representa un viaje independiente con su propia cantidad de vueltas y sacos.
     * El acumulado del activo_fijo se actualiza una sola vez al final.
     */
    public function registrar_uso_bulk_vueltas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_activo_fijo'  => 'required|integer',
            'id_tarifa'       => 'nullable|integer',
            'precio_unitario' => 'required|numeric|min:0',
            'id_mina'         => 'required|integer',
            'id_labor'        => 'required|integer',

            'items'                          => 'required|array|min:1',
            'items.*.cantidad_vueltas'       => 'required|integer|min:1',
            'items.*.cantidad_sacos'         => 'nullable|integer|min:0',
            'items.*.horometro_inicio'       => 'nullable|numeric|min:0',
            'items.*.horometro_fin'          => 'nullable|numeric|min:0|gte:items.*.horometro_inicio',
            'items.*.observacion'            => 'nullable|string',
        ], [
            'id_activo_fijo.required'        => 'El activo fijo es requerido',
            'precio_unitario.required'       => 'El precio unitario es requerido',
            'id_mina.required'               => 'La mina es obligatoria para registrar un control por vueltas',
            'id_labor.required'              => 'La labor es obligatoria para registrar un control por vueltas',
            'items.required'                 => 'Debe incluir al menos un item de vueltas',
            'items.min'                      => 'Debe incluir al menos un item de vueltas',
            'items.*.cantidad_vueltas.required' => 'La cantidad de vueltas es obligatoria en todos los items',
            'items.*.cantidad_vueltas.min'   => 'La cantidad de vueltas debe ser mayor o igual a 1',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $res = \App\Modules\ControlUso\Service\ControlUsoService::registrar_uso_bulk_vueltas(
            id_activo_fijo: (int) $v['id_activo_fijo'],
            id_tarifa: isset($v['id_tarifa']) ? (int) $v['id_tarifa'] : null,
            precio_unitario: (float) $v['precio_unitario'],
            id_mina: (int) $v['id_mina'],
            id_labor: (int) $v['id_labor'],
            items: $v['items'],
        );

        return response()->json($res);
    }

    /**
     * Obtener el listado de tarifas para un activo.
     */
    public function get_tarifas(Request $request, int $id_activo_fijo): JsonResponse
    {
        $res = \App\Modules\ControlUso\Service\ControlUsoService::get_tarifas($id_activo_fijo);
        return response()->json($res);
    }

    /**
     * Crear nueva tarifa.
     */
    public function crear_tarifa(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_activo_fijo' => 'required|integer',
            'tipo_control' => 'required|string',
            'precio_unitario'  => 'nullable|numeric|min:0',
            'descripcion'      => 'nullable|string',
            'id_tipo_material' => 'nullable|integer',
            'distancia_metros' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $res = \App\Modules\ControlUso\Service\ControlUsoService::crear_tarifa(
            id_activo_fijo:   (int) $v['id_activo_fijo'],
            tipo_control:     (string) $v['tipo_control'],
            precio_unitario:  isset($v['precio_unitario']) ? (float) $v['precio_unitario'] : 0.0,
            descripcion:      isset($v['descripcion']) ? (string) $v['descripcion'] : '',
            id_tipo_material: isset($v['id_tipo_material']) ? (int) $v['id_tipo_material'] : null,
            distancia_metros: isset($v['distancia_metros']) ? (int) $v['distancia_metros'] : null
        );

        return response()->json($res);
    }

    /**
     * Obtener el listado de tipos de material.
     */
    public function get_materiales(Request $request): JsonResponse
    {
        $res = \App\Modules\ControlUso\Service\ControlUsoService::get_materiales();
        return response()->json($res);
    }

    /**
     * Crear nuevo tipo de material.
     */
    public function crear_material(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $res = \App\Modules\ControlUso\Service\ControlUsoService::crear_material((string) $request->input('nombre'));
        return response()->json($res);
    }
}
