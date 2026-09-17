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
            'items.*.hora_inicio'    => 'nullable|date_format:H:i|required_with:items.*.hora_fin',
            'items.*.hora_fin'       => 'nullable|date_format:H:i|required_with:items.*.hora_inicio',
            'items.*.horometro_inicio' => 'nullable|numeric|min:0|required_with:items.*.horometro_fin',
            'items.*.horometro_fin'    => 'nullable|numeric|min:0|required_with:items.*.horometro_inicio|gt:items.*.horometro_inicio',
            'items.*.tipo_turno'        => 'nullable|string|in:Dia,Noche',
            'items.*.observacion'    => 'nullable|string',

            // Consumos directos (opcional). COMPARTIDOS por todo el grupo
            // UUID (no van por item): un mismo consumo (p. ej. 50 galones
            // de combustible) cubre a todos los bloques horometrados. El
            // backend registra el descuento de stock y el kardex de
            // salida (origen=Consumo) una sola vez por consumo.
            'consumos'                              => 'nullable|array',
            'consumos.*.id_producto'                => 'required_with:consumos|integer',
            'consumos.*.id_almacen'                 => 'required_with:consumos|integer',
            'consumos.*.id_lote_producto'           => 'required_with:consumos|integer',
            'consumos.*.id_unidad_medida'           => 'required_with:consumos|integer',
            'consumos.*.cantidad_consumo'           => 'required_with:consumos|numeric|min:0.000001',
            'consumos.*.contenido_por_presentacion' => 'required_with:consumos|numeric|min:0.000001',
            'consumos.*.id_lote_mineral'            => 'nullable|integer',
            'consumos.*.id_labor_destino'           => 'nullable|integer',
            'consumos.*.para_produccion'            => 'nullable|boolean',
            'consumos.*.para_mantenimiento'         => 'nullable|boolean',
            'consumos.*.comentario'                 => 'nullable|string|max:512',
            'consumos.*.estado'                     => 'nullable|in:Consumo Parcial,Consumo Total',
        ], [
            'id_activo_fijo.required'  => 'El activo fijo es requerido',
            'fecha_trabajo.required'   => 'La fecha del trabajo es requerida',
            'fecha_trabajo.date_format' => 'La fecha debe tener formato YYYY-MM-DD',
            'precio_unitario.required' => 'El precio unitario es requerido',
            'es_para_mina.required'    => 'Debe indicar si el destino es en mina o para terceros',
            'items.required'           => 'Debe incluir al menos un item de horario',
            'items.min'                => 'Debe incluir al menos un item de horario',
            'items.*.hora_inicio.required_with' => 'La hora de inicio es obligatoria si indico hora de fin',
            'items.*.hora_fin.required_with'    => 'La hora de fin es obligatoria si indico hora de inicio',
            'items.*.hora_inicio.date_format'   => 'Hora de inicio debe tener formato HH:MM',
            'items.*.hora_fin.date_format'      => 'Hora de fin debe tener formato HH:MM',
            'items.*.horometro_inicio.required_with' => 'El horometro inicial es obligatorio si indico horometro final',
            'items.*.horometro_fin.required_with'    => 'El horometro final es obligatorio si indico horometro inicial',
            'items.*.horometro_fin.gt'              => 'El horometro final debe ser mayor al inicial',
            'items.*.tipo_turno.in' => 'El turno debe ser "Dia" o "Noche"',
            'consumos.*.id_producto.required_with'                => 'Si envias consumos, id_producto es obligatorio',
            'consumos.*.id_almacen.required_with'                 => 'Si envias consumos, id_almacen es obligatorio',
            'consumos.*.id_lote_producto.required_with'           => 'Si envias consumos, id_lote_producto es obligatorio',
            'consumos.*.id_unidad_medida.required_with'           => 'Si envias consumos, id_unidad_medida es obligatorio',
            'consumos.*.cantidad_consumo.required_with'           => 'Si envias consumos, cantidad_consumo es obligatorio',
            'consumos.*.contenido_por_presentacion.required_with' => 'Si envias consumos, contenido_por_presentacion es obligatorio',
            'consumos.*.estado.in'                                => 'El estado del consumo debe ser "Consumo Parcial" o "Consumo Total"',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $itemsNormalizados = array_map(function ($it) {
            return [
                'hora_inicio'      => $it['hora_inicio'] ?? null,
                'hora_fin'         => $it['hora_fin'] ?? null,
                'horometro_inicio' => isset($it['horometro_inicio']) ? (float) $it['horometro_inicio'] : null,
                'horometro_fin'    => isset($it['horometro_fin']) ? (float) $it['horometro_fin'] : null,
                'tipo_turno'       => $it['tipo_turno'] ?? null,
                'observacion'      => $it['observacion'] ?? null,
            ];
        }, $v['items']);

        // Consumos a nivel raiz (compartidos por todo el grupo UUID).
        $consumosNormalizados = [];
        if (!empty($v['consumos']) && is_array($v['consumos'])) {
            foreach ($v['consumos'] as $cs) {
                $consumosNormalizados[] = [
                    'id_producto'                => (int) $cs['id_producto'],
                    'id_almacen'                 => (int) $cs['id_almacen'],
                    'id_lote_producto'           => (int) $cs['id_lote_producto'],
                    'id_unidad_medida'           => (int) $cs['id_unidad_medida'],
                    'cantidad_consumo'           => (float) $cs['cantidad_consumo'],
                    'contenido_por_presentacion' => (float) $cs['contenido_por_presentacion'],
                    'id_lote_mineral'           => isset($cs['id_lote_mineral']) ? (int) $cs['id_lote_mineral'] : null,
                    'id_labor_destino'           => isset($cs['id_labor_destino']) ? (int) $cs['id_labor_destino'] : null,
                    'para_produccion'           => isset($cs['para_produccion']) ? (bool) $cs['para_produccion'] : false,
                    'para_mantenimiento'        => isset($cs['para_mantenimiento']) ? (bool) $cs['para_mantenimiento'] : false,
                    'comentario'                => $cs['comentario'] ?? null,
                    'estado'                     => $cs['estado'] ?? 'Consumo Total',
                ];
            }
        }

        $idEmpleadoRegistro = (int) (($request->attributes->get('auth_user')->id_empleado) ?? 0);

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
            items: $itemsNormalizados,
            consumos: $consumosNormalizados,
            id_empleado_registro: $idEmpleadoRegistro,
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
            'id_activo_fijo'    => 'required|integer',
            'id_mina'           => 'required|integer',
            'id_labor'          => 'required|integer',
            // `id_lote_mineral` pasa a ser OPCIONAL: muchas vueltas son
            // servicios o carguios iniciales donde aun no se asigna lote.
            'id_lote_mineral'   => 'nullable|integer',

            'items'                          => 'required|array|min:1',
            'items.*.id_tarifa'              => 'required|integer',
            'items.*.precio_unitario'        => 'required|numeric|min:0',
            'items.*.cantidad_vueltas'       => 'required|integer|min:1',
            'items.*.cantidad_sacos'         => 'nullable|integer|min:0',
            'items.*.horometro_inicio'       => 'nullable|numeric|min:0|required_with:items.*.horometro_fin',
            'items.*.horometro_fin'          => 'nullable|numeric|min:0|required_with:items.*.horometro_inicio|gt:items.*.horometro_inicio',
            'items.*.tipo_turno'             => 'nullable|string|in:Dia,Noche',
            // Fecha del Trabajo: ahora por bloque (cada item puede tener
            // la suya). Sigue siendo obligatoria porque el activo necesita
            // el momento del control.
            'items.*.fecha_trabajo'          => 'required|date_format:Y-m-d',
            'items.*.observacion'            => 'nullable|string',
        ], [
            'id_activo_fijo.required'        => 'El activo fijo es requerido',
            'id_mina.required'               => 'La mina es obligatoria para registrar un control por vueltas',
            'id_labor.required'              => 'La labor es obligatoria para registrar un control por vueltas',
            // sin mensaje de id_lote_mineral.required (es opcional ahora)
            'items.required'                 => 'Debe incluir al menos un item de vueltas',
            'items.min'                      => 'Debe incluir al menos un item de vueltas',
            'items.*.id_tarifa.required'     => 'La tarifa es obligatoria en todos los items',
            'items.*.precio_unitario.required' => 'El precio unitario es obligatorio en todos los items',
            'items.*.cantidad_vueltas.required' => 'La cantidad de vueltas es obligatoria en todos los items',
            'items.*.cantidad_vueltas.min'   => 'La cantidad de vueltas debe ser mayor o igual a 1',
            'items.*.horometro_fin.gt'         => 'El horometro final debe ser mayor al inicial',
            'items.*.horometro_inicio.required_with' => 'El horometro inicial es obligatorio si indico horometro final',
            'items.*.horometro_fin.required_with'    => 'El horometro final es obligatorio si indico horometro inicial',
            'items.*.tipo_turno.in'          => 'El turno debe ser "Dia" o "Noche"',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $itemsNormalizados = array_map(function ($it) {
            return [
                'id_tarifa'        => isset($it['id_tarifa']) ? (int) $it['id_tarifa'] : null,
                'precio_unitario'  => (float) $it['precio_unitario'],
                'cantidad_vueltas'  => (int) $it['cantidad_vueltas'],
                'cantidad_sacos'    => isset($it['cantidad_sacos']) ? (int) $it['cantidad_sacos'] : null,
                'horometro_inicio'  => isset($it['horometro_inicio']) ? (float) $it['horometro_inicio'] : null,
                'horometro_fin'     => isset($it['horometro_fin']) ? (float) $it['horometro_fin'] : null,
                'tipo_turno'        => $it['tipo_turno'] ?? null,
                // Fecha del trabajo: por bloque (cada viaje puede caer en
                // dia distinto). El backend la requiere y el service la
                // usa para `fecha_hora_inicio_control`.
                'fecha_trabajo'     => $it['fecha_trabajo'] ?? null,
                'observacion'       => $it['observacion'] ?? null,
            ];
        }, $v['items']);

        $res = \App\Modules\ControlUso\Service\ControlUsoService::registrar_uso_bulk_vueltas(
            id_activo_fijo: (int) $v['id_activo_fijo'],
            id_mina: (int) $v['id_mina'],
            id_labor: (int) $v['id_labor'],
            id_lote_mineral: (int) $v['id_lote_mineral'],
            items: $itemsNormalizados,
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

    /**
     * Anular un control de uso (soft-delete). Si tiene consumos directos
     * asociados, reingresa el stock al lote correspondiente, registra el
     * movimiento en Kardex y elimina fisicamente los consumos.
     */
    public function anular_control_uso(Request $request, int $id): JsonResponse
    {
        $res = ControlUsoService::anular_control_uso($id);
        return response()->json($res);
    }

    /**
     * Actualizar un control de uso individual (no masivo). Permite
     * corregir datos del registro (observacion, lecturas, tarifa, etc.).
     * Si esta 'Anulado', no se puede editar.
     */
    public function actualizar_control_uso(Request $request, int $id): JsonResponse
    {
        $res = ControlUsoService::actualizar_control_uso($request, $id);
        return response()->json($res);
    }
}
