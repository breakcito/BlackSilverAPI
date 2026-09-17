<?php

namespace App\Modules\ControlUso\Service;

use App\Models\ControlUsoActivo;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Models\TarifaUsoActivo;
use App\Models\TipoMaterial;
use App\Modules\ControlUso\Data\ControlUsoData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Servicio encargado de gestionar la lógica de negocio del módulo Control de Uso.
 */
class ControlUsoService
{
    /**
     * Obtener listado de logs de uso con filtros aplicados.
     */
    public static function get_logs(?string $tipo_control = 'horometro', ?int $mes = null, ?int $anio = null)
    {
        $logs = ControlUsoData::get_logs($tipo_control, $mes, $anio);
        return ApiResponse::success($logs);
    }

    /**
     * Obtener el último valor final del horómetro/odómetro para pre-cargar en el formulario.
     */
    public static function get_ultimo_horometro(int $id_activo_fijo)
    {
        $ultimo = ControlUsoData::get_ultimo_registro($id_activo_fijo);
        $valor = $ultimo ? (float) $ultimo->horometro_fin : 0.0;
        return ApiResponse::success(['ultimo_horometro' => $valor]);
    }

    public static function get_ultimo_odometro(int $id_activo_fijo)
    {
        $ultimo = ControlUsoData::get_ultimo_registro_odometro($id_activo_fijo);
        $valor = $ultimo ? (float) $ultimo->odometro_fin : 0.0;
        return ApiResponse::success(['ultimo_odometro' => $valor]);
    }

    /**
     * Registrar un nuevo log de uso, realizando los cálculos necesarios en backend.
     */
    public static function registrar_uso(
        int $id_activo_fijo,
        string $fecha_hora_inicio_control,
        ?string $fecha_hora_fin_control = null,
        ?float $horometro_inicio = null,
        ?float $horometro_fin = null,
        ?float $odometro_inicio = null,
        ?float $odometro_fin = null,
        ?int $cantidad_vueltas = null,
        ?int $cantidad_sacos = null,
        ?int $id_tarifa = null,
        ?float $precio_unitario = 0.0,
        ?bool $es_para_mina = null,
        ?int $id_mina = null,
        ?int $id_labor = null,
        ?int $id_lote_mineral = null,
        ?int $id_cliente = null,
        ?string $tipo_carga = null,
        ?string $observacion = null
    ) {
        return DB::transaction(function () use ($id_activo_fijo, $fecha_hora_inicio_control, $fecha_hora_fin_control, $horometro_inicio, $horometro_fin, $odometro_inicio, $odometro_fin, $cantidad_vueltas, $cantidad_sacos, $id_tarifa, $precio_unitario, $es_para_mina, $id_mina, $id_labor, $id_lote_mineral, $id_cliente, $tipo_carga, $observacion) {
            // Un solo registro (modo single / odometro): un único uuid_grupo
            $uuidGrupo = (string) Str::uuid();

            // Parses dates with Carbon
            $fecha_inicio = Carbon::parse($fecha_hora_inicio_control)->toDateTimeString();
            $fecha_fin = $fecha_hora_fin_control ? Carbon::parse($fecha_hora_fin_control)->toDateTimeString() : null;

            // Calculates difference and totals
            $total_horas = 0.0;
            $costo_total = 0.0;

            if ($cantidad_vueltas !== null) {
                $costo_total = $cantidad_vueltas * ($precio_unitario ?? 0.0);
            } elseif ($fecha_inicio && $fecha_fin) {
                $inicioCarbon = Carbon::parse($fecha_inicio);
                $finCarbon = Carbon::parse($fecha_fin);
                if ($finCarbon->greaterThan($inicioCarbon)) {
                    $diffInMinutes = $inicioCarbon->diffInMinutes($finCarbon);
                    $total_horas = round($diffInMinutes / 60.0, 2);
                    $costo_total = round($total_horas * ($precio_unitario ?? 0.0), 2);
                }
            } elseif ($horometro_fin !== null && $horometro_inicio !== null) {
                $total_horas = max(0.0, $horometro_fin - $horometro_inicio);
                $costo_total = $total_horas * ($precio_unitario ?? 0.0);
            } elseif ($odometro_fin !== null && $odometro_inicio !== null) {
                $total_km = max(0.0, $odometro_fin - $odometro_inicio);
                $costo_total = $total_km * ($precio_unitario ?? 0.0);
            }

            // Inserts standard usage log
            $log = ControlUsoActivo::create([
                'id_activo_fijo' => $id_activo_fijo,
                'fecha_hora_inicio_control' => $fecha_inicio,
                'fecha_hora_fin_control' => $fecha_fin,
                'horometro_inicio' => $horometro_inicio,
                'horometro_fin' => $horometro_fin,
                'odometro_inicio' => $odometro_inicio,
                'odometro_fin' => $odometro_fin,
                'cantidad_vueltas' => $cantidad_vueltas,
                'cantidad_sacos' => $cantidad_sacos,
                'total_horas' => $total_horas,
                'precio_unitario' => $precio_unitario ?? 0.0,
                'costo_total' => $costo_total,
                'es_para_mina' => $es_para_mina,
                'id_mina' => $id_mina,
                'id_labor' => $id_labor,
                'id_lote_mineral' => $id_lote_mineral,
                'id_cliente' => $id_cliente,
                'tipo_carga' => $tipo_carga,
                'id_tarifa' => $id_tarifa,
                'observacion' => $observacion,
                'uuid_grupo' => $uuidGrupo,
                'created_at' => now()->toDateTimeString()
            ]);



            // Update cumulative totals in the activo_fijo record
            $activoInfo = DB::table('activo_fijo')
                ->join('producto', 'producto.id', '=', 'activo_fijo.id_producto')
                ->join('categoria', 'categoria.id', '=', 'producto.id_categoria')
                ->select(
                    'categoria.control_por_horometro',
                    'categoria.control_por_odometro',
                    'categoria.control_por_vueltas'
                )
                ->where('activo_fijo.id', $id_activo_fijo)
                ->first();

            if ($activoInfo) {
                $updates = [];
                if ($activoInfo->control_por_horometro && $total_horas > 0) {
                    $currHoras = DB::table('activo_fijo')->where('id', $id_activo_fijo)->value('total_horas') ?? 0;
                    $updates['total_horas'] = $currHoras + $total_horas;
                }
                if ($activoInfo->control_por_odometro && $odometro_fin !== null) {
                    $updates['total_kilometros'] = $odometro_fin;
                }
                if ($activoInfo->control_por_vueltas && $cantidad_vueltas !== null) {
                    // Get current and add
                    $curr = DB::table('activo_fijo')->where('id', $id_activo_fijo)->value('total_vueltas') ?? 0;
                    $updates['total_vueltas'] = $curr + $cantidad_vueltas;
                }

                if (!empty($updates)) {
                    DB::table('activo_fijo')
                        ->where('id', $id_activo_fijo)
                        ->update($updates);
                }
            }

            return ApiResponse::success($log, 'Registro de uso guardado correctamente');
        });
    }

    /**
     * Registrar varios logs de uso en una sola transaccion (cabecera + items[]).
     * Cada item representa un tramo horario independiente con su propia observacion.
     * El acumulado del activo_fijo se actualiza una sola vez al final.
     *
     * @param array $items Cada item: ['hora_inicio'=>HH:MM, 'hora_fin'=>HH:MM, 'horometro_inicio'?=>float, 'horometro_fin'?=>float, 'observacion'?=>string]
     */
    public static function registrar_uso_bulk(
        int $id_activo_fijo,
        string $fecha_trabajo,
        ?int $id_tarifa,
        float $precio_unitario,
        bool $es_para_mina,
        ?int $id_mina,
        ?int $id_labor,
        ?int $id_cliente,
        ?int $id_lote_mineral,
        ?string $tipo_carga,
        array $items,
        int $id_empleado_registro = 0
    ) {
        if (count($items) === 0) {
            return ApiResponse::error('Debe incluir al menos un item de horario.');
        }

        return DB::transaction(function () use (
            $id_activo_fijo, $fecha_trabajo, $id_tarifa, $precio_unitario,
            $es_para_mina, $id_mina, $id_labor, $id_cliente, $id_lote_mineral, $tipo_carga, $items,
            $id_empleado_registro
        ) {
            // Toda la submission comparte un mismo uuid_grupo (cabecera + N items)
            $uuidGrupo = (string) Str::uuid();
            $created = [];
            $suma_total_horas = 0.0;

            foreach ($items as $idx => $it) {
                $horaInicio = isset($it['hora_inicio']) && $it['hora_inicio'] !== '' && $it['hora_inicio'] !== null
                    ? (string) $it['hora_inicio']
                    : null;
                $horaFin = isset($it['hora_fin']) && $it['hora_fin'] !== '' && $it['hora_fin'] !== null
                    ? (string) $it['hora_fin']
                    : null;

                $horometroInicio = (isset($it['horometro_inicio']) && $it['horometro_inicio'] !== '' && $it['horometro_inicio'] !== null)
                    ? (float) $it['horometro_inicio']
                    : null;
                $horometroFin = (isset($it['horometro_fin']) && $it['horometro_fin'] !== '' && $it['horometro_fin'] !== null)
                    ? (float) $it['horometro_fin']
                    : null;

                $tipoTurno = isset($it['tipo_turno']) && $it['tipo_turno'] !== '' && $it['tipo_turno'] !== null
                    ? (string) $it['tipo_turno']
                    : null;

                // Calculo de total_horas: prioridad horas, si no hay horas y si hay horometro, usar horometro.
                $totalHorasItem = 0.0;
                $dtInicioPersist = null;
                $dtFinPersist = null;

                if ($horaInicio !== null && $horaFin !== null) {
                    $dtInicio = Carbon::createFromFormat('Y-m-d H:i', "$fecha_trabajo $horaInicio");
                    $dtFin = Carbon::createFromFormat('Y-m-d H:i', "$fecha_trabajo $horaFin");

                    // Si la hora de fin es menor o igual a la de inicio, asumimos que cruza la medianoche
                    if (!$dtFin->greaterThan($dtInicio)) {
                        $dtFin = $dtFin->addDay();
                    }

                    $diffMinutes = $dtInicio->diffInMinutes($dtFin);
                    // NO redondear horas aqui: conservar el float crudo para que el
                    // costo se calcule exacto. La precision la trae la columna
                    // (DECIMAL(13,6)); el display en el front redondea a 2 decimales.
                    $totalHorasItem = $diffMinutes / 60.0;
                    $dtInicioPersist = $dtInicio->toDateTimeString();
                    $dtFinPersist = $dtFin->toDateTimeString();
                } elseif ($horometroInicio !== null && $horometroFin !== null) {
                    // Sin redondeo por la misma razón: calculo por horometro
                    // ya es exacto (diferencia de lecturas); no perder precision.
                    $totalHorasItem = max(0.0, $horometroFin - $horometroInicio);
                    // Sin horario, dejamos fecha_hora_fin_control en NULL y usamos la marca temporal de la fecha del dia + 00:00:00
                    $dtInicioPersist = Carbon::createFromFormat('Y-m-d', $fecha_trabajo)->toDateTimeString();
                } else {
                    throw new \RuntimeException("Item #$idx: debe registrar horas de inicio/fin o horometro inicial/final.");
                }

                if ($horometroInicio !== null && $horometroFin !== null && $horometroFin <= $horometroInicio) {
                    throw new \RuntimeException("Item #$idx: el horometro final no puede ser menor o igual al inicial.");
                }

                $costoItem = round($totalHorasItem * ($precio_unitario ?? 0.0), 2);

                $observacion = (isset($it['observacion']) && $it['observacion'] !== '')
                    ? trim((string) $it['observacion'])
                    : null;

                $log = ControlUsoActivo::create([
                    'id_activo_fijo' => $id_activo_fijo,
                    'fecha_hora_inicio_control' => $dtInicioPersist,
                    'fecha_hora_fin_control' => $dtFinPersist,
                    'horometro_inicio' => $horometroInicio,
                    'horometro_fin' => $horometroFin,
                    'odometro_inicio' => null,
                    'odometro_fin' => null,
                    'cantidad_vueltas' => null,
                    'cantidad_sacos' => null,
                    'total_horas' => $totalHorasItem,
                    'precio_unitario' => $precio_unitario ?? 0.0,
                    'costo_total' => $costoItem,
                    'es_para_mina' => $es_para_mina,
                    'id_mina' => $id_mina,
                    'id_labor' => $id_labor,
                    'id_lote_mineral' => $id_lote_mineral,
                    'id_cliente' => $id_cliente,
                    'tipo_carga' => $tipo_carga,
                    'id_tarifa' => $id_tarifa,
                    'observacion' => $observacion,
                    'tipo_turno' => $tipoTurno,
                    'uuid_grupo' => $uuidGrupo,
                    'created_at' => now()->toDateTimeString(),
                ]);

                $created[] = $log;
                $suma_total_horas += (float) $totalHorasItem;

                // Consumos directos (opcional por item). Si el item viene con
                // `consumos[]`, se inserta uno por uno en la tabla
                // `requerimiento_almacen_entrega_detalle_consumo` con
                // `es_consumo_directo=true` y se ejecuta `update_stock` con
                // origen=Consumo (SALIDA) para descontar del lote y registrar
                // el movimiento en kardex.
                if (!empty($it['consumos']) && is_array($it['consumos'])) {
                    $consumos = $it['consumos'];
                    foreach ($consumos as $csIdx => $cs) {
                        $cantidadConsumo = (float) $cs['cantidad_consumo'];
                        $cpp = (float) $cs['contenido_por_presentacion'];
                        $cantidadBase = round($cantidadConsumo * $cpp, 6);

                        // Estado (Consumo Parcial / Consumo Total)
                        $estadoRaw = $cs['estado'] ?? 'Consumo Total';
                        $estadoEnum = EstadoConsumoDetalleEntregaReq::tryFrom($estadoRaw)
                            ?? EstadoConsumoDetalleEntregaReq::ConsumoTotal;

                        // Nombre del producto y del activo para la descripcion del kardex
                        $productoNombre = DB::table('producto')
                            ->where('id', (int) $cs['id_producto'])
                            ->value('nombre');
                        $activoInfoConsumo = DB::table('activo_fijo')
                            ->where('id', $id_activo_fijo)
                            ->first();
                        $activoCorrelativo = $activoInfoConsumo->correlativo ?? 'S/C';

                        $descripcionConsumo = sprintf(
                            'Se consumio (%s) en %s - %s',
                            $productoNombre ?? 'producto',
                            $activoCorrelativo,
                            (string) $id_activo_fijo,
                        );

                        $idEmpleadoRegistro = $id_empleado_registro;

                        $consumoId = RequerimientoAlmacenEntregaDetalleConsumo::crear_consumo_directo(
                            id_empleado_registro: $idEmpleadoRegistro,
                            id_activo_fijo_consumidor: $id_activo_fijo,
                            id_lote_mineral: isset($cs['id_lote_mineral']) ? (int) $cs['id_lote_mineral'] : null,
                            id_labor_destino: isset($cs['id_labor_destino']) ? (int) $cs['id_labor_destino'] : null,
                            para_mantenimiento: (bool) ($cs['para_mantenimiento'] ?? false),
                            para_produccion: (bool) ($cs['para_produccion'] ?? false),
                            cantidad_base_consumida: $cantidadBase,
                            comentario_consumo: $cs['comentario'] ?? null,
                            uuid_control_uso_activo: $uuidGrupo,
                            id_producto: (int) $cs['id_producto'],
                            id_almacen: (int) $cs['id_almacen'],
                            id_lote_producto: (int) $cs['id_lote_producto'],
                            id_unidad_medida: (int) $cs['id_unidad_medida'],
                            contenido_por_presentacion: $cpp,
                            cantidad_consumo: $cantidadConsumo,
                            cantidad_base: $cantidadBase,
                            estado: $estadoEnum,
                        );

                        // Kardex SALIDA via update_stock (origen=Consumo).
                        LotesProductosService::update_stock(
                            id_lote: (int) $cs['id_lote_producto'],
                            id_origen: $consumoId,
                            tabla_origen: 'requerimiento_almacen_entrega_detalle_consumo',
                            tipo_origen: KardexOrigenMovimiento::Consumo,
                            tipo_movimiento: KardexTipoMovimiento::Salida,
                            cantidad_movimiento_base: $cantidadBase,
                            descripcion: $descripcionConsumo,
                        );
                    }
                }
            }

            // Update acumulado del activo_fijo UNA sola vez al final
            if ($suma_total_horas > 0) {
                $activoInfo = DB::table('activo_fijo')
                    ->join('producto', 'producto.id', '=', 'activo_fijo.id_producto')
                    ->join('categoria', 'categoria.id', '=', 'producto.id_categoria')
                    ->select('categoria.control_por_horometro')
                    ->where('activo_fijo.id', $id_activo_fijo)
                    ->first();

                if ($activoInfo && $activoInfo->control_por_horometro) {
                    $curr = DB::table('activo_fijo')->where('id', $id_activo_fijo)->value('total_horas') ?? 0;
                    DB::table('activo_fijo')
                        ->where('id', $id_activo_fijo)
                        ->update(['total_horas' => $curr + $suma_total_horas]);
                }
            }

            $cantidad = count($created);
            $msg = $cantidad === 1
                ? 'Registro de uso guardado correctamente'
                : "$cantidad registros de uso guardados correctamente";

            return ApiResponse::success($created, $msg);
        });
    }

    /**
     * Registrar varios logs de uso por vueltas en una sola transaccion (cabecera + items[]).
     * Cada item representa un viaje independiente con su propia cantidad de vueltas
     * y su propia tarifa (cada item puede tener una tarifa distinta).
     * El lote de mineral es cabecera-wide (compartido por todos los items) para asociar
     * los costos al lote de produccion elegido.
     * El acumulado del activo_fijo se actualiza una sola vez al final.
     *
     * @param array $items Cada item: ['id_tarifa'=>int, 'precio_unitario'=>float, 'cantidad_vueltas'=>int,
     *                            'cantidad_sacos'?=>int, 'horometro_inicio'?=>float, 'horometro_fin'?=>float,
     *                            'tipo_turno'?=>string, 'observacion'?=>string]
     */
    public static function registrar_uso_bulk_vueltas(
        int $id_activo_fijo,
        int $id_mina,
        int $id_labor,
        int $id_lote_mineral,
        array $items
    ) {
        if (count($items) === 0) {
            return ApiResponse::error('Debe incluir al menos un item de vueltas.');
        }

        return DB::transaction(function () use (
            $id_activo_fijo, $id_mina, $id_labor, $id_lote_mineral, $items
        ) {
            // Toda la submission (cabecera + N items) comparte un mismo uuid_grupo
            $uuidGrupo = (string) Str::uuid();
            $created = [];
            $suma_total_vueltas = 0;

            $dtInicio = now()->toDateTimeString();

            foreach ($items as $idx => $it) {
                $idTarifaItem = isset($it['id_tarifa']) && $it['id_tarifa'] !== '' && $it['id_tarifa'] !== null
                    ? (int) $it['id_tarifa']
                    : null;
                $precioItem = isset($it['precio_unitario']) && $it['precio_unitario'] !== '' && $it['precio_unitario'] !== null
                    ? (float) $it['precio_unitario']
                    : 0.0;
                $cantidadVueltas = (isset($it['cantidad_vueltas']) && $it['cantidad_vueltas'] !== '' && $it['cantidad_vueltas'] !== null)
                    ? (int) $it['cantidad_vueltas']
                    : 0;
                $cantidadSacos = (isset($it['cantidad_sacos']) && $it['cantidad_sacos'] !== '' && $it['cantidad_sacos'] !== null)
                    ? (int) $it['cantidad_sacos']
                    : null;
                $horometroInicio = (isset($it['horometro_inicio']) && $it['horometro_inicio'] !== '' && $it['horometro_inicio'] !== null)
                    ? (float) $it['horometro_inicio']
                    : null;
                $horometroFin = (isset($it['horometro_fin']) && $it['horometro_fin'] !== '' && $it['horometro_fin'] !== null)
                    ? (float) $it['horometro_fin']
                    : null;
                $observacion = (isset($it['observacion']) && $it['observacion'] !== '')
                    ? trim((string) $it['observacion'])
                    : null;

                $tipoTurno = isset($it['tipo_turno']) && $it['tipo_turno'] !== '' && $it['tipo_turno'] !== null
                    ? (string) $it['tipo_turno']
                    : null;

                if ($idTarifaItem === null) {
                    throw new \RuntimeException("Item #$idx: la tarifa es obligatoria.");
                }
                if ($cantidadVueltas <= 0) {
                    throw new \RuntimeException("Item #$idx: la cantidad de vueltas debe ser mayor a cero.");
                }

                if ($horometroInicio !== null && $horometroFin !== null && $horometroFin <= $horometroInicio) {
                    throw new \RuntimeException("Item #$idx: el horometro final no puede ser menor o igual al inicial.");
                }

                $costoItem = round($cantidadVueltas * $precioItem, 2);

                $log = ControlUsoActivo::create([
                    'id_activo_fijo' => $id_activo_fijo,
                    'fecha_hora_inicio_control' => $dtInicio,
                    'fecha_hora_fin_control' => null,
                    'horometro_inicio' => $horometroInicio,
                    'horometro_fin' => $horometroFin,
                    'odometro_inicio' => null,
                    'odometro_fin' => null,
                    'cantidad_vueltas' => $cantidadVueltas,
                    'cantidad_sacos' => $cantidadSacos,
                    'total_horas' => 0.0,
                    'precio_unitario' => $precioItem,
                    'costo_total' => $costoItem,
                    'es_para_mina' => true,
                    'id_mina' => $id_mina,
                    'id_labor' => $id_labor,
                    'id_lote_mineral' => $id_lote_mineral,
                    'id_cliente' => null,
                    'tipo_carga' => null,
                    'id_tarifa' => $idTarifaItem,
                    'observacion' => $observacion,
                    'tipo_turno' => $tipoTurno,
                    'uuid_grupo' => $uuidGrupo,
                    'created_at' => now()->toDateTimeString(),
                ]);

                $created[] = $log;
                $suma_total_vueltas += $cantidadVueltas;
            }

            if ($suma_total_vueltas > 0) {
                $activoInfo = DB::table('activo_fijo')
                    ->join('producto', 'producto.id', '=', 'activo_fijo.id_producto')
                    ->join('categoria', 'categoria.id', '=', 'producto.id_categoria')
                    ->select('categoria.control_por_vueltas')
                    ->where('activo_fijo.id', $id_activo_fijo)
                    ->first();

                if ($activoInfo && $activoInfo->control_por_vueltas) {
                    $curr = DB::table('activo_fijo')->where('id', $id_activo_fijo)->value('total_vueltas') ?? 0;
                    DB::table('activo_fijo')
                        ->where('id', $id_activo_fijo)
                        ->update(['total_vueltas' => $curr + $suma_total_vueltas]);
                }
            }

            $cantidad = count($created);
            $msg = $cantidad === 1
                ? 'Registro de uso guardado correctamente'
                : "$cantidad registros de uso guardados correctamente";

            return ApiResponse::success($created, $msg);
        });
    }

    public static function get_tarifas(int $id_activo_fijo)
    {
        $res = ControlUsoData::get_tarifas($id_activo_fijo);
        return ApiResponse::success($res);
    }

    public static function crear_tarifa(
        int $id_activo_fijo,
        string $tipo_control,
        float $precio_unitario,
        string $descripcion,
        ?int $id_tipo_material,
        ?int $distancia_metros = null
    ) {
        $tarifa = TarifaUsoActivo::create([
            'id_activo_fijo' => $id_activo_fijo,
            'tipo_control' => $tipo_control,
            'precio_unitario' => $precio_unitario,
            'descripcion' => $descripcion,
            'id_tipo_material' => $id_tipo_material,
            'distancia_metros' => $distancia_metros,
            'created_at' => now()->toDateTimeString()
        ]);
        return ApiResponse::success($tarifa, 'Tarifa registrada exitosamente');
    }

    public static function get_materiales()
    {
        $res = ControlUsoData::get_materiales();
        return ApiResponse::success($res);
    }

    public static function crear_material(string $nombre)
    {
        $material = TipoMaterial::create([
            'nombre' => $nombre,
            'created_at' => now()->toDateTimeString()
        ]);
        return ApiResponse::success($material, 'Material registrado exitosamente');
    }
}
