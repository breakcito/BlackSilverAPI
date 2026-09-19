<?php

namespace App\Modules\ControlUso\Service;

use App\Models\ControlUsoActivo;
use App\Models\RequerimientoAlmacenEntregaDetalleConsumo;
use App\Models\TarifaUsoActivo;
use App\Models\TipoMaterial;
use App\Modules\ControlUso\Data\ControlUsoData;
use App\Services\LotesProductosService;
use App\Shared\Enums\ControlUso\EstadoControlUso;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoConsumoDetalleEntregaReq;
use App\Shared\Responses\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
     * Los consumos son COMPARTIDOS por todo el grupo (mismo `uuid_grupo`):
     * un mismo consumo (p. ej. 50 galones de combustible) cubre a todos los
     * bloques horometrados del grupo, no a uno puntual.
     *
     * @param array $items     Cada item: ['hora_inicio'=>HH:MM, 'hora_fin'=>HH:MM, 'horometro_inicio'?=>float, 'horometro_fin'?=>float, 'observacion'?=>string]
     * @param array $consumos  Lista compartida: cada elemento con los campos id_producto, id_almacen, id_lote_producto, id_unidad_medida, cantidad_consumo, contenido_por_presentacion, ...
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
        array $consumos = [],
        int $id_empleado_registro = 0
    ) {
        if (count($items) === 0) {
            return ApiResponse::error('Debe incluir al menos un item de horario.');
        }

        return DB::transaction(function () use (
            $id_activo_fijo, $fecha_trabajo, $id_tarifa, $precio_unitario,
            $es_para_mina, $id_mina, $id_labor, $id_cliente, $id_lote_mineral, $tipo_carga, $items, $consumos,
            $id_empleado_registro
        ) {
            // Toda la submission comparte un mismo uuid_grupo (cabecera + N items + consumos compartidos)
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
                    'estado' => EstadoControlUso::Activo->value,
                    'created_at' => now()->toDateTimeString(),
                ]);

                $created[] = $log;
                $suma_total_horas += (float) $totalHorasItem;
            }

            // Consumos COMPARTIDOS por todo el grupo UUID. Se procesan UNA
            // sola vez al final (no por bloque): un consumo (p. ej. 50
            // galones de combustible) cubre a todos los bloques horometrados
            // que comparten este `uuid_grupo`.
            if (!empty($consumos) && is_array($consumos)) {
                // Nombre del activo (solo una vez) para la descripcion del kardex.
                $activoInfoConsumo = DB::table('activo_fijo')
                    ->where('id', $id_activo_fijo)
                    ->first();
                $activoCorrelativo = $activoInfoConsumo->correlativo ?? 'S/C';

                foreach ($consumos as $csIdx => $cs) {
                    $cantidadConsumo = (float) $cs['cantidad_consumo'];
                    $cpp = (float) $cs['contenido_por_presentacion'];
                    $cantidadBase = round($cantidadConsumo * $cpp, 6);

                    // Estado (Consumo Parcial / Consumo Total)
                    $estadoRaw = $cs['estado'] ?? 'Consumo Total';
                    $estadoEnum = EstadoConsumoDetalleEntregaReq::tryFrom($estadoRaw)
                        ?? EstadoConsumoDetalleEntregaReq::ConsumoTotal;

                    // Nombre del producto y del almacen para la descripcion del kardex.
                    $productoNombre = DB::table('producto')
                        ->where('id', (int) $cs['id_producto'])
                        ->value('nombre');
                    $almacenNombre = DB::table('almacen')
                        ->where('id', (int) $cs['id_almacen'])
                        ->value('nombre');

                    $descripcionConsumo = sprintf(
                        'Se consumio %s - %s en %s',
                        $productoNombre ?? 'producto',
                        $almacenNombre ?? 'S/A',
                        $activoCorrelativo,
                    );

                    $consumoId = RequerimientoAlmacenEntregaDetalleConsumo::crear_consumo_directo(
                        id_empleado_registro: $id_empleado_registro,
                        id_activo_fijo_consumidor: $id_activo_fijo,
                        id_lote_mineral: isset($cs['id_lote_mineral']) ? (int) $cs['id_lote_mineral'] : null,
                        id_labor_destino: isset($cs['id_labor_destino']) ? (int) $cs['id_labor_destino'] : null,
                        para_mantenimiento: (bool) ($cs['para_mantenimiento'] ?? false),
                        para_produccion: (bool) ($cs['para_produccion'] ?? false),
                        cantidad_base_consumida: $cantidadBase,
                        comentario_consumo: $cs['comentario'] ?? null,
                        // El consumo se asocia al GRUPO uuid_control_uso_activo
                        // (== control_uso_activo.uuid_grupo), no a un item
                        // puntual. Por eso NO pasamos `id_control_uso_activo`.
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
     * El lote de mineral es cabecera-wide pero ahora OPCIONAL: hay vueltas
     * (servicios, carguios iniciales) donde aun no se asigna lote. La fecha
     * del trabajo pasa a ser POR BLOQUE (`fecha_trabajo` en cada item), no
     * cabecera-wide, porque cada viaje puede ocurrir en un dia distinto.
     * El acumulado del activo_fijo se actualiza una sola vez al final.
     *
     * @param array $items Cada item: ['id_tarifa'=>int, 'precio_unitario'=>float, 'cantidad_vueltas'=>int,
     *                            'cantidad_sacos'?=>int, 'horometro_inicio'?=>float, 'horometro_fin'?=>float,
     *                            'tipo_turno'?=>string, 'fecha_trabajo'=>string 'Y-m-d', 'observacion'?=>string]
     */
    public static function registrar_uso_bulk_vueltas(
        int $id_activo_fijo,
        int $id_mina,
        int $id_labor,
        ?int $id_lote_mineral,
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

                // Fecha del trabajo por bloque. Si no viene, usamos la
                // fecha del bloque anterior o el dia actual como
                // fallback para no romper el payload.
                $fechaTrabajoItem = (isset($it['fecha_trabajo']) && $it['fecha_trabajo'] !== '' && $it['fecha_trabajo'] !== null)
                    ? (string) $it['fecha_trabajo']
                    : null;

                if ($idTarifaItem === null) {
                    throw new \RuntimeException("Item #$idx: la tarifa es obligatoria.");
                }
                if ($cantidadVueltas <= 0) {
                    throw new \RuntimeException("Item #$idx: la cantidad de vueltas debe ser mayor a cero.");
                }
                if ($fechaTrabajoItem === null) {
                    throw new \RuntimeException("Item #$idx: la fecha del trabajo es obligatoria.");
                }

                if ($horometroInicio !== null && $horometroFin !== null && $horometroFin <= $horometroInicio) {
                    throw new \RuntimeException("Item #$idx: el horometro final no puede ser menor o igual al inicial.");
                }

                $costoItem = round($cantidadVueltas * $precioItem, 2);

                // fecha_hora_inicio_control = la fecha del item (sin hora,
                // 00:00:00). fecha_hora_fin_control = null porque las
                // vueltas son discretas (un viaje), no un tramo continuo.
                $dtInicio = Carbon::createFromFormat('Y-m-d', $fechaTrabajoItem)
                    ->startOfDay()
                    ->toDateTimeString();

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
                    'estado' => EstadoControlUso::Activo->value,
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

    /**
     * Anula un control de uso (soft-delete). El reingreso de stock al lote
     * y la eliminacion fisica del consumo SOLO se hacen cuando este es el
     * ULTIMO item activo del grupo (mismo `uuid_grupo`). Si quedan otros
     * bloques activos del mismo "Registrar Control por Horometro",
     * unicamente se marca este bloque como Anulado: el consumo del grupo
     * sigue siendo valido para los bloques restantes.
     *
     * Reglas:
     *  - Buscar el consumo asociado por `uuid_control_uso_activo` (==
     *    `control_uso_activo.uuid_grupo`). Ya NO usamos `id_control_uso_activo`
     *    porque el consumo representa al grupo entero, no a un item.
     *  - Contar cuantos items del `uuid_grupo` siguen 'Activo'. Si despues
     *    de marcar el actual como Anulado queda >= 1 activo, solo marcar y
     *    salir (no tocar stock ni kardex).
     *  - Si ya no queda ninguno activo del grupo: reingresar stock, registrar
     *    Kardex (Ingreso / Reingreso) y eliminar fisicamente el/los consumos.
     */
    public static function anular_control_uso(int $id_control_uso)
    {
        $log = ControlUsoActivo::find($id_control_uso);
        if (!$log) {
            return ApiResponse::error("Control de uso no encontrado.", 404);
        }
        if ($log->estado === EstadoControlUso::Anulado) {
            return ApiResponse::error("Este control de uso ya fue anulado.", 409);
        }

        try {
            return DB::transaction(function () use ($id_control_uso, $log) {
                // 1) Recopilar info del activo fijo una sola vez (para la
                //    descripcion del Kardex).
                $activoInfo = DB::table('activo_fijo')
                    ->leftJoin('producto', 'producto.id', '=', 'activo_fijo.id_producto')
                    ->where('activo_fijo.id', $log->id_activo_fijo)
                    ->select(
                        'activo_fijo.correlativo',
                        'activo_fijo.id',
                        'producto.nombre as producto_nombre',
                    )
                    ->first();

                $activoCorrelativo = $activoInfo->correlativo ?? 'S/C';
                $idActivoFijo = $log->id_activo_fijo;

                // 2) Marcar el control_uso_activo actual como Anulado.
                $log->estado = EstadoControlUso::Anulado;
                $log->save();

                // 3) Determinar si queda ALGUN otro item del mismo grupo
                //    todavia en estado 'Activo'.
                $uuidGrupo = $log->uuid_grupo;
                $restantesActivos = 0;
                if ($uuidGrupo !== null && $uuidGrupo !== '') {
                    $restantesActivos = (int) DB::table('control_uso_activo')
                        ->where('uuid_grupo', $uuidGrupo)
                        ->where('estado', EstadoControlUso::Activo->value)
                        ->count();
                }

                // 4) Si quedan mas bloques activos en el grupo, NO tocamos
                //    stock ni consumos: el consumo del grupo sigue siendo
                //    valido para esos bloques restantes.
                if ($restantesActivos > 0) {
                    return ApiResponse::success(
                        $log,
                        "Control de uso anulado. Quedan {$restantesActivos} registro(s) activo(s) en este grupo, no se reingresa stock.",
                    );
                }

                // 5) Grupo completamente anulado -> reingresar stock,
                //    registrar Kardex y eliminar consumo(s) del grupo.
                $consumos = RequerimientoAlmacenEntregaDetalleConsumo::where(
                    'uuid_control_uso_activo',
                    $uuidGrupo,
                )->get();

                foreach ($consumos as $cs) {
                    $cantidadBase = (float) $cs->cantidad_base_consumida;
                    if ($cantidadBase <= 0) {
                        // Sin cantidad para reingresar, solo borrar.
                        $cs->delete();
                        continue;
                    }

                    $descripcionReingreso = sprintf(
                        'Reingreso por anulacion en control de uso en %s',
                        $activoCorrelativo,
                    );

                    LotesProductosService::update_stock(
                        id_lote: (int) $cs->id_lote_producto,
                        id_origen: (int) $cs->id,
                        tabla_origen: 'requerimiento_almacen_entrega_detalle_consumo',
                        tipo_origen: KardexOrigenMovimiento::Reingreso,
                        tipo_movimiento: KardexTipoMovimiento::Ingreso,
                        cantidad_movimiento_base: $cantidadBase,
                        descripcion: $descripcionReingreso,
                    );

                    // Eliminar fisicamente el consumo.
                    $cs->delete();
                }

                return ApiResponse::success(
                    $log,
                    "Control de uso anulado. Grupo completo anulado: stock reingresado y consumos eliminados.",
                );
            });
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'Error al anular el control de uso: ' . $e->getMessage(),
                500,
            );
        }
    }

    /**
     * Actualiza un control de uso individual (no masivo). Los campos que se
     * pueden modificar son los "datos operativos" del registro:
     * fechas, lecturas de horometro/odometro/vueltas, observaciones,
     * tarifa, mina/labor/lote/cliente, tipo_turno, tipo_carga, precio.
     *
     * Si el registro ya esta 'Anulado' no se permite editar.
     */
    public static function actualizar_control_uso(Request $request, int $id)
    {
        $log = ControlUsoActivo::find($id);
        if (!$log) {
            return ApiResponse::error("Control de uso no encontrado.", 404);
        }
        if ($log->estado === EstadoControlUso::Anulado) {
            return ApiResponse::error(
                "No se puede editar un control de uso anulado. Si necesitas corregirlo, primero anulalo y registra uno nuevo.",
                409,
            );
        }

        try {
            return DB::transaction(function () use ($log, $request) {
                // Campos directos (strings / numericos simples)
                if ($request->has('fecha_hora_inicio_control')) {
                    $log->fecha_hora_inicio_control = $request->input('fecha_hora_inicio_control');
                }
                if ($request->has('fecha_hora_fin_control')) {
                    $fechaFin = $request->input('fecha_hora_fin_control');
                    $log->fecha_hora_fin_control = $fechaFin !== null && $fechaFin !== '' ? $fechaFin : null;
                }
                if ($request->has('horometro_inicio')) {
                    $log->horometro_inicio = $request->input('horometro_inicio');
                }
                if ($request->has('horometro_fin')) {
                    $log->horometro_fin = $request->input('horometro_fin');
                }
                if ($request->has('odometro_inicio')) {
                    $log->odometro_inicio = $request->input('odometro_inicio');
                }
                if ($request->has('odometro_fin')) {
                    $log->odometro_fin = $request->input('odometro_fin');
                }
                if ($request->has('cantidad_vueltas')) {
                    $cv = $request->input('cantidad_vueltas');
                    $log->cantidad_vueltas = ($cv === null || $cv === '') ? null : (int) $cv;
                }
                if ($request->has('cantidad_sacos')) {
                    $cs = $request->input('cantidad_sacos');
                    $log->cantidad_sacos = ($cs === null || $cs === '') ? null : (int) $cs;
                }
                if ($request->has('precio_unitario')) {
                    $log->precio_unitario = $request->input('precio_unitario');
                }
                if ($request->has('observacion')) {
                    $log->observacion = $request->input('observacion');
                }
                if ($request->has('tipo_turno')) {
                    $log->tipo_turno = $request->input('tipo_turno');
                }
                if ($request->has('es_para_mina')) {
                    $log->es_para_mina = (bool) $request->input('es_para_mina');
                }
                if ($request->has('id_mina')) {
                    $log->id_mina = $request->input('id_mina');
                }
                if ($request->has('id_labor')) {
                    $log->id_labor = $request->input('id_labor');
                }
                if ($request->has('id_lote_mineral')) {
                    $log->id_lote_mineral = $request->input('id_lote_mineral');
                }
                if ($request->has('id_cliente')) {
                    $log->id_cliente = $request->input('id_cliente');
                }
                if ($request->has('tipo_carga')) {
                    $log->tipo_carga = $request->input('tipo_carga');
                }
                if ($request->has('id_tarifa')) {
                    $log->id_tarifa = $request->input('id_tarifa');
                }

                // Recalcular total_horas si tenemos fechas o lecturas de
                // horometro/odometro (mismo criterio que el bulk).
                if ($log->horometro_inicio !== null && $log->horometro_fin !== null) {
                    $log->total_horas = max(0.0, (float) $log->horometro_fin - (float) $log->horometro_inicio);
                } elseif ($log->odometro_inicio !== null && $log->odometro_fin !== null) {
                    $log->total_horas = max(0.0, (float) $log->odometro_fin - (float) $log->odometro_inicio);
                } elseif ($log->fecha_hora_inicio_control && $log->fecha_hora_fin_control) {
                    // Recalcular a partir de las fechas (mismo calculo del bulk).
                    try {
                        $inicio = Carbon::parse($log->fecha_hora_inicio_control);
                        $fin = Carbon::parse($log->fecha_hora_fin_control);
                        $diff = $fin->diffInMinutes($inicio, true);
                        $log->total_horas = round($diff / 60.0, 6);
                    } catch (\Throwable $e) {
                        // Si las fechas no son validas, dejar el total previo.
                    }
                }

                // Recalcular costo_total (mismo criterio del bulk).
                if ($log->precio_unitario !== null) {
                    $log->costo_total = round(((float) $log->total_horas) * ((float) $log->precio_unitario), 2);
                }

                $log->save();

                return ApiResponse::success(
                    $log,
                    "Control de uso actualizado correctamente.",
                );
            });
        } catch (\Throwable $e) {
            return ApiResponse::error(
                'Error al actualizar el control de uso: ' . $e->getMessage(),
                500,
            );
        }
    }
}
