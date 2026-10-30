<?php

namespace App\Modules\VentaCarbon\Data;

use App\Models\CargaVentaCarbon;
use App\Models\KardexCarbon;
use App\Models\VentaCarbon;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\VentaCarbon\EstadoCargaVentaCarbon;
use App\Shared\Enums\VentaCarbon\EstadoVentaCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VentaCarbonData
{
    /**
     * Cache del chequeo de esquema: `kardex_carbon.id_carga_venta_carbon`
     * se agrega con SQL plano y se detecta en runtime.
     */
    private static ?bool $kardexTieneCargaVenta = null;

    /**
     * Cache de las columnas nuevas de `carga_venta_carbon`. Las migran por
     * SQL plano, asi que se detectan en runtime: si todavia no existen, el
     * listado devuelve valores neutros en vez de romperse.
     *
     * @var array<string, string>|null
     */
    private static ?array $columnasCarga = null;

    /**
     * Cache de las columnas nuevas de `carga_venta_carbon` que ya existen.
     *
     * @var string[]|null
     */
    private static ?array $columnasCargaExistentes = null;

    /**
     * Expresiones SQL de las columnas nuevas de `carga_venta_carbon`.
     *
     * @return array<string, string>
     */
    private static function columnas_carga(): array
    {
        if (self::$columnasCarga !== null) {
            return self::$columnasCarga;
        }

        $nuevas = [
            'id_liquidacion_venta_carbon' => 'NULL AS id_liquidacion_venta_carbon',
            'fue_aceptado' => '0 AS fue_aceptado',
            'precio_materia_prima' => '0 AS precio_materia_prima',
            'monto_igv' => '0 AS monto_igv',
            'total' => '0 AS total',
            'monto_detraccion' => '0 AS monto_detraccion',
            'neto_a_pagar' => '0 AS neto_a_pagar',
        ];

        $resueltas = [];
        foreach ($nuevas as $columna => $alternativa) {
            $resueltas[$columna] = Schema::hasColumn('carga_venta_carbon', $columna)
                ? 'cv.' . $columna
                : $alternativa;
        }

        self::$columnasCarga = $resueltas;

        return $resueltas;
    }

    /**
     * Columnas nuevas de `carga_venta_carbon` que ya existen en el esquema.
     *
     * @return string[]
     */
    private static function columnas_carga_existentes(): array
    {
        if (self::$columnasCargaExistentes !== null) {
            return self::$columnasCargaExistentes;
        }

        $existentes = [];
        foreach (array_keys(self::columnas_carga()) as $columna) {
            if (Schema::hasColumn('carga_venta_carbon', $columna)) {
                $existentes[] = $columna;
            }
        }

        self::$columnasCargaExistentes = $existentes;

        return $existentes;
    }

    /**
     * Filtra un payload de escritura dejando solo las columnas que existen.
     *
     * @param array<string, mixed> $datos
     * @param string[] $columnas
     * @return array<string, mixed>
     */
    private static function solo_columnas_existentes(array $datos, array $columnas): array
    {
        $resultado = [];
        foreach ($columnas as $columna) {
            if (array_key_exists($columna, $datos)) {
                $resultado[$columna] = $datos[$columna];
            }
        }

        return $resultado;
    }

    private static function kardex_tiene_carga_venta(): bool
    {
        if (self::$kardexTieneCargaVenta === null) {
            self::$kardexTieneCargaVenta = Schema::hasColumn(
                'kardex_carbon',
                'id_carga_venta_carbon'
            );
        }

        return self::$kardexTieneCargaVenta;
    }

    /**
     * Lista las ventas de carbon con su cabecera y, si se pide el detalle,
     * el arbol completo (cargas, liquidaciones, comprobantes y pagos).
     *
     * El listado se pide SIN detalle: la cabecera y los contadores vienen en
     * una sola consulta y el arbol se carga recien cuando el usuario despliega
     * una venta. Asi el listado no arrastra 10 arboles completos.
     *
     * El arbol se arma con un numero fijo de consultas (independiente de
     * cuantas ventas se listan) para no caer en N+1.
     *
     * @param array{
     *   filtros?: string,
     *   id_venta_carbon?: int,
     *   id_cliente?: int,
     *   id_cliente_destino?: int,
     *   id_almacen_empresa_salida?: int,
     *   mes?: int,
     *   anio?: int,
     *   detalle?: bool
     * } $opts
     * @return array<object>
     */
    public static function get_ventas(array $opts = []): array
    {
        $conDetalle = !empty($opts['detalle']);

        // El contador de liquidaciones solo existe si la tabla ya fue creada.
        $contadorLiquidaciones = Schema::hasTable('liquidacion_venta_carbon')
            ? '(SELECT COUNT(*) FROM liquidacion_venta_carbon lv WHERE lv.id_venta_carbon = vc.id) AS cantidad_liquidaciones'
            : '0 AS cantidad_liquidaciones';

        $sql = '
            SELECT
                vc.id AS id_venta_carbon,
                vc.correlativo,
                vc.numero_correlativo,
                vc.id_empresa,
                e.razon_social AS empresa,
                e.ruc AS empresa_ruc,
                vc.id_cliente,
                c.razon_social AS cliente,
                c.ruc AS cliente_ruc,
                vc.id_cliente_destino,
                cd.razon_social AS cliente_destino,
                cd.ruc AS cliente_destino_ruc,
                vc.id_almacen_empresa_salida,
                ae.nombre AS almacen_empresa_salida,
                vc.id_almacen_cliente_llegada,
                acc.direccion AS almacen_cliente_llegada,
                vc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                vc.porcentaje_igv,
                vc.porcentaje_detraccion,
                vc.created_at,
                (
                    SELECT COUNT(*)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                ) AS cantidad_cargas,
                (
                    SELECT COUNT(*)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                      AND cv.estado <> :estado_en_camino
                ) AS cantidad_cargas_confirmadas,
                (
                    SELECT COALESCE(SUM(cv.cantidad_toneladas), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                ) AS total_toneladas,
                (
                    SELECT COALESCE(SUM(cv.cantidad_toneladas), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                      AND cv.estado <> :estado_en_camino_2
                ) AS total_toneladas_confirmadas,
                (
                    SELECT COALESCE(SUM(cv.subtotal), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                ) AS total_subtotal,
                (
                    SELECT COALESCE(SUM(cv.total), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                ) AS total_total,
                ' . $contadorLiquidaciones . '
            FROM venta_carbon vc
            INNER JOIN empresa e ON e.id = vc.id_empresa
            LEFT JOIN cliente c ON c.id = vc.id_cliente
            LEFT JOIN cliente cd ON cd.id = vc.id_cliente_destino
            LEFT JOIN almacen ae ON ae.id = vc.id_almacen_empresa_salida
            LEFT JOIN almacen_carbon_cliente acc ON acc.id = vc.id_almacen_cliente_llegada
            LEFT JOIN empleado er ON er.id = vc.id_empleado_registro
            WHERE 1 = 1
        ';

        $params = [
            'estado_en_camino' => EstadoCargaVentaCarbon::EnCamino->value,
            'estado_en_camino_2' => EstadoCargaVentaCarbon::EnCamino->value,
        ];

        if (!empty($opts['id_venta_carbon'])) {
            $sql .= ' AND vc.id = :id_venta_carbon';
            $params['id_venta_carbon'] = (int) $opts['id_venta_carbon'];
        }
        if (!empty($opts['id_cliente'])) {
            $sql .= ' AND vc.id_cliente = :id_cliente';
            $params['id_cliente'] = (int) $opts['id_cliente'];
        }
        if (!empty($opts['id_cliente_destino'])) {
            $sql .= ' AND vc.id_cliente_destino = :id_cliente_destino';
            $params['id_cliente_destino'] = (int) $opts['id_cliente_destino'];
        }
        if (!empty($opts['id_almacen_empresa_salida'])) {
            $sql .= ' AND vc.id_almacen_empresa_salida = :id_almacen_empresa_salida';
            $params['id_almacen_empresa_salida'] = (int) $opts['id_almacen_empresa_salida'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $condiciones = [
                'vc.correlativo LIKE :q',
                'c.razon_social LIKE :q',
                'cd.razon_social LIKE :q',
                'e.razon_social LIKE :q',
            ];

            // El codigo de la orden de compra vive en la liquidacion: se
            // busca ahi solo si la tabla ya existe.
            if (Schema::hasTable('liquidacion_venta_carbon')) {
                $condiciones[] = 'EXISTS (
                    SELECT 1
                    FROM liquidacion_venta_carbon lv
                    WHERE lv.id_venta_carbon = vc.id
                      AND (lv.codigo_orden_compra LIKE :q OR lv.numero_reporte LIKE :q)
                )';
            }

            $sql .= ' AND (' . implode(' OR ', $condiciones) . ')';
            $params['q'] = '%' . $filtros . '%';
        }

        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(vc.created_at) = :mes AND YEAR(vc.created_at) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(vc.created_at) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY vc.id DESC';

        $rows = DB::select($sql, $params);
        if (empty($rows)) {
            return [];
        }

        $ids = array_map(fn ($r) => (int) $r->id_venta_carbon, $rows);

        // Listado liviano: solo cabecera y contadores. El arbol se carga
        // bajo demanda con `get_venta_por_id`.
        if (!$conDetalle) {
            foreach ($rows as $row) {
                $row->cargas = [];
                $row->liquidaciones = [];
                $row->estado = self::resolver_estado_venta_resumen(
                    (int) $row->cantidad_cargas,
                    (int) $row->cantidad_cargas_confirmadas
                );
            }

            return $rows;
        }

        $cargasPorVenta = self::get_cargas_por_venta($ids);
        $liquidacionesPorVenta = VentaCarbonPagosData::get_liquidaciones_por_venta($ids);

        foreach ($rows as $row) {
            $idVenta = (int) $row->id_venta_carbon;
            $cargas = $cargasPorVenta[$idVenta] ?? [];
            $liquidaciones = $liquidacionesPorVenta[$idVenta] ?? [];

            // Cada liquidacion expone las cargas que la componen. Las cargas
            // ya vienen cargadas desde `get_cargas_por_venta`, asi que solo
            // se agrupan por id de liquidacion.
            $cargasPorLiquidacion = [];
            foreach ($cargas as $carga) {
                $idLiquidacion = (int) ($carga->id_liquidacion_venta_carbon ?? 0);
                if ($idLiquidacion > 0) {
                    $cargasPorLiquidacion[$idLiquidacion][] = $carga;
                }
            }

            foreach ($liquidaciones as &$liquidacion) {
                $idLiquidacion = (int) $liquidacion->id_liquidacion_venta_carbon;
                $liquidacion->cargas = $cargasPorLiquidacion[$idLiquidacion] ?? [];
            }
            unset($liquidacion);

            $row->cargas = $cargas;
            $row->liquidaciones = $liquidaciones;
            $row->estado = self::resolver_estado_venta($cargas);
        }

        return $rows;
    }

    /**
     * Obtiene una venta por su id con el arbol completo de cargas,
     * liquidaciones, comprobantes y pagos.
     */
    public static function get_venta_por_id(int $id_venta_carbon): ?object
    {
        $rows = self::get_ventas([
            'id_venta_carbon' => $id_venta_carbon,
            'detalle' => true,
        ]);

        return $rows[0] ?? null;
    }

    /**
     * Trae las cargas de varias ventas en una sola consulta (evita N+1).
     * @param int[] $ids_venta
     * @return array<int, array<object>> Indexado por id de venta.
     */
    public static function get_cargas_por_venta(array $ids_venta): array
    {
        if (empty($ids_venta)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids_venta as $i => $id) {
            $key = 'id_venta_' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }
        $inClause = implode(',', $placeholders);
        $cols = self::columnas_carga();

        $sql = '
            SELECT
                cv.id AS id_carga_venta_carbon,
                cv.id_venta_carbon,
                cv.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                cv.id_tipo_carbon,
                tce.nombre AS tipo_carbon,
                tce.codigo AS tipo_carbon_codigo,
                cv.id_vehiculo_carbon,
                veh.placa AS vehiculo_placa,
                veh.tipo_vehiculo AS vehiculo_tipo,
                m.nombre AS vehiculo_marca,
                cv.calidad,
                cv.cantidad_toneladas,
                cv.segunda_placa,
                cv.fecha_hora_salida,
                cv.fecha_hora_ingreso,
                cv.guia_remitente,
                cv.guia_transportista,
                cv.codigo_ticket_balanza,
                cv.observacion,
                cv.porcentaje_ceniza,
                cv.porcentaje_humedad,
                cv.porcentaje_azufre,
                cv.porcentaje_pizarra,
                cv.analisis_granulometria,
                ' . $cols['fue_aceptado'] . ',
                ' . $cols['id_liquidacion_venta_carbon'] . ',
                cv.precio_base,
                cv.descuento,
                cv.bono,
                ' . $cols['precio_materia_prima'] . ',
                cv.subtotal,
                ' . $cols['monto_igv'] . ',
                ' . $cols['total'] . ',
                ' . $cols['monto_detraccion'] . ',
                ' . $cols['neto_a_pagar'] . ',
                cv.evidencias,
                cv.estado,
                cv.created_at
            FROM carga_venta_carbon cv
            LEFT JOIN empleado e ON e.id = cv.id_empleado_registro
            LEFT JOIN tipo_carbon tce ON tce.id = cv.id_tipo_carbon
            LEFT JOIN vehiculo_carbon veh ON veh.id = cv.id_vehiculo_carbon
            LEFT JOIN marca m ON m.id = veh.id_marca
            WHERE cv.id_venta_carbon IN (' . $inClause . ')
            ORDER BY cv.id ASC
        ';

        $rows = DB::select($sql, $params);

        $resultado = [];
        foreach ($rows as $row) {
            $row->evidencias = self::decode_json($row->evidencias ?? null);
            $row->analisis_granulometria = self::decode_json($row->analisis_granulometria ?? null);
            $row->fue_aceptado = (int) ($row->fue_aceptado ?? 0) === 1;
            $resultado[(int) $row->id_venta_carbon][] = $row;
        }

        return $resultado;
    }

    /**
     * Estado derivado de la venta a partir de los contadores.
     *
     * Es el que usa el listado liviano: sin las cargas a mano, solo se sabe
     * cuantas hay y cuantas ya confirmaron su llegada.
     */
    private static function resolver_estado_venta_resumen(
        int $cantidadCargas,
        int $cantidadConfirmadas
    ): string {
        if ($cantidadCargas === 0) {
            return EstadoVentaCarbon::SinCargas->value;
        }

        return $cantidadConfirmadas >= $cantidadCargas
            ? EstadoVentaCarbon::Completada->value
            : EstadoVentaCarbon::EnCurso->value;
    }

    /**
     * Estado derivado de la venta a partir del estado de sus cargas.
     */
    private static function resolver_estado_venta(array $cargas): string
    {
        if (empty($cargas)) {
            return EstadoVentaCarbon::SinCargas->value;
        }

        $todasConfirmadas = true;
        foreach ($cargas as $carga) {
            if (($carga->estado ?? null) !== EstadoCargaVentaCarbon::LlegadaConfirmada->value
                && ($carga->estado ?? null) !== EstadoCargaVentaCarbon::EnLiquidacion->value
                && ($carga->estado ?? null) !== EstadoCargaVentaCarbon::Pagado->value
            ) {
                $todasConfirmadas = false;
                break;
            }
        }

        return $todasConfirmadas
            ? EstadoVentaCarbon::Completada->value
            : EstadoVentaCarbon::EnCurso->value;
    }

    /**
     * Genera el siguiente correlativo anual de venta de carbon (VC-26-00001).
     *
     * Mismo formato que `CorrelativoHelper`, pero con la fila bloqueada: el
     * correlativo debe ser unico y dos ventas simultaneas no pueden tomar el
     * mismo numero. Se llama siempre dentro de la transaccion del alta.
     *
     * @return array{correlativo: string, numero_correlativo: int}
     */
    public static function generar_correlativo(): array
    {
        $anio = (int) now()->year;

        $max = DB::table('venta_carbon')
            ->whereYear('created_at', $anio)
            ->lockForUpdate()
            ->max('numero_correlativo');

        $siguiente = ((int) $max) + 1;

        return [
            'correlativo' => sprintf('VC-%s-%05d', now()->format('y'), $siguiente),
            'numero_correlativo' => $siguiente,
        ];
    }

    /**
     * Registra la venta completa: cabecera + cargas, en una sola transaccion.
     *
     * El correlativo se genera dentro de la transaccion y con la fila bloqueada
     * para que dos ventas simultaneas no obtengan el mismo numero.
     *
     * @param array<string, mixed> $cabecera
     * @param array<int, array<string, mixed>> $cargas
     */
    public static function registrar_venta(array $cabecera, array $cargas, int $id_empleado): int
    {
        return DB::transaction(function () use ($cabecera, $cargas, $id_empleado) {
            $correlativo = self::generar_correlativo();

            $idVenta = self::insert_venta([
                'id_empleado_registro' => $id_empleado,
                'id_empresa' => (int) $cabecera['id_empresa'],
                'id_cliente' => (int) $cabecera['id_cliente'],
                'id_cliente_destino' => (int) $cabecera['id_cliente_destino'],
                'id_almacen_empresa_salida' => (int) $cabecera['id_almacen_empresa_salida'],
                'id_almacen_cliente_llegada' => (int) $cabecera['id_almacen_cliente_llegada'],
                'correlativo' => $correlativo['correlativo'],
                'numero_correlativo' => $correlativo['numero_correlativo'],
                'porcentaje_igv' => (float) ($cabecera['porcentaje_igv'] ?? 0),
                'porcentaje_detraccion' => (float) ($cabecera['porcentaje_detraccion'] ?? 0),
            ]);

            if (!empty($cargas)) {
                self::insert_cargas($idVenta, $cargas, $id_empleado);
            }

            return $idVenta;
        });
    }

    /**
     * Inserta la cabecera de la venta.
     * @param array<string, mixed> $data
     */
    public static function insert_venta(array $data): int
    {
        return VentaCarbon::insertGetId([
            'id_empleado_registro' => (int) $data['id_empleado_registro'],
            'id_empresa' => (int) $data['id_empresa'],
            'id_cliente' => (int) $data['id_cliente'],
            'id_cliente_destino' => (int) $data['id_cliente_destino'],
            'id_almacen_empresa_salida' => (int) $data['id_almacen_empresa_salida'],
            'id_almacen_cliente_llegada' => (int) $data['id_almacen_cliente_llegada'],
            'correlativo' => (string) $data['correlativo'],
            'numero_correlativo' => (int) $data['numero_correlativo'],
            'porcentaje_igv' => (float) ($data['porcentaje_igv'] ?? 0),
            'porcentaje_detraccion' => (float) ($data['porcentaje_detraccion'] ?? 0),
            'created_at' => (string) ($data['created_at'] ?? now()->toDateTimeString()),
        ]);
    }

    /**
     * Inserta las cargas de una venta.
     *
     * El despacho NO mueve stock ni escribe Kardex: el asiento se genera al
     * confirmar la llegada (el carbon sigue en el patio hasta ese momento).
     *
     * @param array<int, array<string, mixed>> $cargas
     * @return array<int> IDs de las cargas insertadas
     */
    public static function insert_cargas(int $id_venta_carbon, array $cargas, int $id_empleado): array
    {
        return DB::transaction(function () use ($id_venta_carbon, $cargas, $id_empleado) {
            $venta = DB::table('venta_carbon')->where('id', $id_venta_carbon)->first();
            if ($venta === null) {
                throw new \RuntimeException('La venta no existe');
            }

            $idsCargas = [];
            foreach ($cargas as $c) {
                $idsCargas[] = self::insert_carga($id_venta_carbon, $c, $id_empleado);
            }

            return $idsCargas;
        });
    }

    /**
     * Inserta una carga en estado "En Camino".
     *
     * @param array<string, mixed> $c
     */
    private static function insert_carga(int $id_venta_carbon, array $c, int $id_empleado): int
    {
        $cantidad = round((float) $c['cantidad_toneladas'], 4);

        $evidenciasJson = isset($c['evidencias']) && $c['evidencias'] !== null
            ? (is_string($c['evidencias']) ? $c['evidencias'] : json_encode($c['evidencias'], JSON_UNESCAPED_UNICODE))
            : null;

        $datos = [
            'id_venta_carbon' => $id_venta_carbon,
            'id_empleado_registro' => $id_empleado,
            'id_tipo_carbon' => (int) $c['id_tipo_carbon'],
            'id_vehiculo_carbon' => null,
            'calidad' => (string) $c['calidad'],
            'cantidad_toneladas' => $cantidad,
            'segunda_placa' => null,
            'fecha_hora_salida' => (string) $c['fecha_hora_salida'],
            'fecha_hora_ingreso' => null,
            'guia_remitente' => null,
            'guia_transportista' => null,
            'codigo_ticket_balanza' => null,
            'observacion' => null,
            'porcentaje_ceniza' => 0,
            'porcentaje_humedad' => 0,
            'porcentaje_azufre' => 0,
            'porcentaje_pizarra' => 0,
            'analisis_granulometria' => null,
            'precio_base' => 0,
            'descuento' => 0,
            'bono' => 0,
            'subtotal' => 0,
            'evidencias' => $evidenciasJson,
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoCargaVentaCarbon::EnCamino->value,
        ];

        // Columnas de la liquidacion: solo se escriben si ya existen.
        $datos = array_merge($datos, self::solo_columnas_existentes([
            'id_liquidacion_venta_carbon' => null,
            'fue_aceptado' => 0,
            'precio_materia_prima' => 0,
            'monto_igv' => 0,
            'total' => 0,
            'monto_detraccion' => 0,
            'neto_a_pagar' => 0,
        ], self::columnas_carga_existentes()));

        return CargaVentaCarbon::insertGetId($datos);
    }

    /**
     * Stock realmente disponible de un tipo/calidad en un almacen de salida.
     *
     * Descuenta del stock fisico las cargas que ya salieron del patio pero
     * todavia no confirmaron su llegada: esas toneladas estan comprometidas
     * y no se pueden volver a despachar.
     *
     * @param int $idCargaExcluir Carga que se esta evaluando (para no contarse a si misma).
     */
    public static function calcular_stock_disponible(
        int $id_almacen,
        int $id_tipo_carbon,
        string $calidad,
        int $idCargaExcluir = 0
    ): float {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $id_almacen)
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where('calidad', $calidad)
            ->first();

        $stockActual = $stock !== null ? (float) $stock->stock_actual : 0.0;

        $comprometido = (float) DB::table('carga_venta_carbon')
            ->selectRaw('COALESCE(SUM(carga_venta_carbon.cantidad_toneladas), 0) AS total')
            ->join('venta_carbon', 'venta_carbon.id', '=', 'carga_venta_carbon.id_venta_carbon')
            ->where('venta_carbon.id_almacen_empresa_salida', $id_almacen)
            ->where('carga_venta_carbon.id_tipo_carbon', $id_tipo_carbon)
            ->where('carga_venta_carbon.calidad', $calidad)
            ->where('carga_venta_carbon.estado', EstadoCargaVentaCarbon::EnCamino->value)
            ->when($idCargaExcluir > 0, fn ($q) => $q->where('carga_venta_carbon.id', '!=', $idCargaExcluir))
            ->value('total');

        return max(0.0, round($stockActual - (float) $comprometido, 4));
    }

    /**
     * Descuenta el stock del almacen de salida y escribe el asiento de Kardex.
     *
     * Se ejecuta al CONFIRMAR LA LLEGADA: el asiento queda fechado en
     * `fecha_hora_ingreso` y enlazado a la carga via
     * `kardex_carbon.id_carga_venta_carbon`.
     */
    private static function afectar_stock_salida(
        int $idAlmacen,
        int $idTipoCarbon,
        int $idCarga,
        float $cantidad,
        string $calidad,
        string $fechaHoraIngreso
    ): void {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $idAlmacen)
            ->where('id_tipo_carbon', $idTipoCarbon)
            ->where('calidad', $calidad)
            ->lockForUpdate()
            ->first();

        if ($stock === null) {
            throw new \RuntimeException(
                'No hay stock registrado de la calidad ' . $calidad
                . ' para ese tipo de carbon en el almacen de salida'
            );
        }

        $stockAnterior = (float) $stock->stock_actual;

        if ($stockAnterior + 0.0001 < $cantidad) {
            throw new \RuntimeException(
                'No hay stock suficiente de la calidad ' . $calidad
                . ' en el almacen de salida (disponible: '
                . rtrim(rtrim(number_format($stockAnterior, 2), '0'), '.') . ' TN)'
            );
        }

        $stockResultante = round($stockAnterior - $cantidad, 4);

        DB::table('stock_carbon')
            ->where('id', $stock->id)
            ->update(['stock_actual' => max(0.0, $stockResultante)]);

        $movimiento = [
            'id_almacen' => $idAlmacen,
            'id_tipo_carbon' => $idTipoCarbon,
            'fecha_hora_movimiento' => $fechaHoraIngreso,
            'tipo_movimiento' => KardexTipoMovimiento::Salida->value,
            'calidad' => $calidad,
            'stock_anterior' => $stockAnterior,
            'cantidad_movimiento' => $cantidad,
            'stock_resultante' => max(0.0, $stockResultante),
            // El carbon no tiene metodo de costeo implementado: la salida se
            // registra con costo 0 hasta que exista valorizacion de inventario.
            'costo_total' => 0,
            'created_at' => now()->toDateTimeString(),
        ];

        // Invariante: una salida de carbon siempre viene de una carga de
        // venta. Sin el enlace el asiento no se puede rastrear.
        if (!self::kardex_tiene_carga_venta()) {
            throw new \RuntimeException(
                'Falta la columna kardex_carbon.id_carga_venta_carbon: '
                . 'aplica el script SQL del modulo de venta de carbon'
            );
        }

        $movimiento['id_carga_venta_carbon'] = $idCarga;

        KardexCarbon::insert($movimiento);
    }

    /**
     * Confirma la llegada de una carga al almacen del cliente.
     *
     * Aqui se cierra el ciclo del carbon: se registra la recepcion en destino,
     * el analisis de calidad, el peso confirmado, el vehiculo y se descuenta
     * el stock del almacen de salida con su asiento de Kardex.
     *
     * @param array<string, mixed> $d
     */
    public static function confirmar_llegada(int $id_venta_carbon, int $id_carga_venta_carbon, array $d): void
    {
        DB::transaction(function () use ($id_venta_carbon, $id_carga_venta_carbon, $d) {
            $carga = DB::table('carga_venta_carbon')
                ->where('id', $id_carga_venta_carbon)
                ->lockForUpdate()
                ->first();

            if ($carga === null) {
                throw new \RuntimeException('La carga no existe');
            }

            if ((int) $carga->id_venta_carbon !== $id_venta_carbon) {
                throw new \RuntimeException('La carga no pertenece a la venta indicada');
            }

            if ((int) ($carga->id_liquidacion_venta_carbon ?? 0) > 0) {
                throw new \RuntimeException(
                    'La carga ya pertenece a una liquidacion; no se puede confirmar la llegada'
                );
            }

            $venta = DB::table('venta_carbon')->where('id', $id_venta_carbon)->first();
            if ($venta === null) {
                throw new \RuntimeException('La venta no existe');
            }

            $cantidadConfirmada = round((float) $d['cantidad_toneladas'], 4);
            $calidad = (string) $d['calidad'];
            $idTipoConfirmado = (int) $d['id_tipo_carbon'];
            $idAlmacenSalida = (int) $venta->id_almacen_empresa_salida;

            // El tipo o la calidad pueden corregirse al confirmar: en ese caso
            // el stock a descontar es el del tipo/calidad final.
            $disponible = self::calcular_stock_disponible(
                $idAlmacenSalida,
                $idTipoConfirmado,
                $calidad,
                $id_carga_venta_carbon
            );

            if ($disponible + 0.0001 < $cantidadConfirmada) {
                throw new \RuntimeException(
                    'No hay stock suficiente de la calidad ' . $calidad
                    . ' en el almacen de salida (disponible: '
                    . rtrim(rtrim(number_format($disponible, 2), '0'), '.') . ' TN)'
                );
            }

            $esTipoA = self::es_tipo_a($idTipoConfirmado);

            $analisis = null;
            if ($esTipoA && isset($d['analisis_granulometria']) && is_array($d['analisis_granulometria'])) {
                $analisis = [
                    'mayor_a_uno' => self::normalizar_porcentaje($d['analisis_granulometria']['mayor_a_uno'] ?? null),
                    'menor_a_uno' => self::normalizar_porcentaje($d['analisis_granulometria']['menor_a_uno'] ?? null),
                    'un_medio' => self::normalizar_porcentaje($d['analisis_granulometria']['un_medio'] ?? null),
                ];
            }

            $fueAceptado = !empty($d['fue_aceptado']);

            // Si la carga fue aceptada, la observacion se arma sola.
            $observacion = $fueAceptado
                ? 'ACEPTADO COMO ' . (string) $d['nombre_tipo_carbon']
                : (isset($d['observacion']) && $d['observacion'] !== '' ? (string) $d['observacion'] : null);

            // Las evidencias se acumulan: se conservan las del despacho y se
            // agregan las de la llegada.
            $evidencias = self::decode_json($carga->evidencias ?? null);
            if (!is_array($evidencias)) {
                $evidencias = [];
            }
            if (isset($d['evidencias']) && $d['evidencias'] !== null) {
                $nuevas = is_string($d['evidencias'])
                    ? self::decode_json($d['evidencias'])
                    : $d['evidencias'];
                if (is_array($nuevas)) {
                    $evidencias = array_merge($evidencias, $nuevas);
                }
            }

            $actualizacion = [
                'id_tipo_carbon' => $idTipoConfirmado,
                'calidad' => $calidad,
                'cantidad_toneladas' => $cantidadConfirmada,
                'fecha_hora_ingreso' => (string) $d['fecha_hora_ingreso'],
                'id_vehiculo_carbon' => isset($d['id_vehiculo_carbon']) && (int) $d['id_vehiculo_carbon'] > 0
                    ? (int) $d['id_vehiculo_carbon']
                    : null,
                'segunda_placa' => isset($d['segunda_placa']) && $d['segunda_placa'] !== ''
                    ? strtoupper((string) $d['segunda_placa'])
                    : null,
                'guia_remitente' => isset($d['guia_remitente']) && $d['guia_remitente'] !== '' ? (string) $d['guia_remitente'] : null,
                'guia_transportista' => isset($d['guia_transportista']) && $d['guia_transportista'] !== '' ? (string) $d['guia_transportista'] : null,
                'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) && $d['codigo_ticket_balanza'] !== '' ? (string) $d['codigo_ticket_balanza'] : null,
                'observacion' => $observacion,
                'porcentaje_ceniza' => round((float) ($d['porcentaje_ceniza'] ?? 0), 4),
                'porcentaje_humedad' => round((float) ($d['porcentaje_humedad'] ?? 0), 4),
                'porcentaje_azufre' => round((float) ($d['porcentaje_azufre'] ?? 0), 4),
                'porcentaje_pizarra' => $esTipoA ? round((float) ($d['porcentaje_pizarra'] ?? 0), 4) : 0,
                'analisis_granulometria' => $analisis !== null ? json_encode($analisis, JSON_UNESCAPED_UNICODE) : null,
                'evidencias' => empty($evidencias) ? null : json_encode($evidencias, JSON_UNESCAPED_UNICODE),
                'estado' => EstadoCargaVentaCarbon::LlegadaConfirmada->value,
            ];

            $actualizacion = array_merge(
                $actualizacion,
                self::solo_columnas_existentes(
                    ['fue_aceptado' => $fueAceptado ? 1 : 0],
                    self::columnas_carga_existentes()
                )
            );

            DB::table('carga_venta_carbon')
                ->where('id', $id_carga_venta_carbon)
                ->update($actualizacion);

            // El asiento de Kardex se escribe al confirmar la llegada.
            self::afectar_stock_salida(
                idAlmacen: $idAlmacenSalida,
                idTipoCarbon: $idTipoConfirmado,
                idCarga: $id_carga_venta_carbon,
                cantidad: $cantidadConfirmada,
                calidad: $calidad,
                fechaHoraIngreso: (string) $d['fecha_hora_ingreso'],
            );
        });
    }

    /**
     * El tipo de carbon "Tipo A" exige analisis granulometrico y % de pizarra.
     * Se resuelve por nombre porque el catalogo es editable desde el modulo
     * de tipos de carbon.
     */
    public static function es_tipo_a(int $id_tipo_carbon): bool
    {
        if ($id_tipo_carbon <= 0) {
            return false;
        }

        $nombre = DB::table('tipo_carbon')
            ->where('id', $id_tipo_carbon)
            ->value('nombre');

        return is_string($nombre) && mb_strtolower(trim($nombre)) === 'tipo a';
    }

    /**
     * Normaliza un valor de porcentaje del analisis granulometrico.
     */
    private static function normalizar_porcentaje(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $numero = (float) $valor;
        if (!is_finite($numero)) {
            return null;
        }

        return round($numero, 4);
    }

    /**
     * Empresa vendedora por id.
     */
    public static function get_empresa(int $id_empresa): ?object
    {
        return DB::table('empresa')->where('id', $id_empresa)->first();
    }

    /**
     * Cliente por id (facturado o de destino).
     */
    public static function get_cliente(int $id_cliente): ?object
    {
        return DB::table('cliente')->where('id', $id_cliente)->first();
    }

    /**
     * Almacen de la empresa por id.
     */
    public static function get_almacen(int $id_almacen): ?object
    {
        return DB::table('almacen')->where('id', $id_almacen)->first();
    }

    /**
     * Almacen de carbon de un cliente por id.
     */
    public static function get_almacen_carbon_cliente(int $id_almacen): ?object
    {
        return DB::table('almacen_carbon_cliente')->where('id', $id_almacen)->first();
    }

    /**
     * Vehiculo de carbon por id.
     */
    public static function get_vehiculo_carbon(int $id_vehiculo_carbon): ?object
    {
        return DB::table('vehiculo_carbon')->where('id', $id_vehiculo_carbon)->first();
    }

    /**
     * Tipo de carbon por id.
     */
    public static function get_tipo_carbon(int $id_tipo_carbon): ?object
    {
        return DB::table('tipo_carbon')->where('id', $id_tipo_carbon)->first();
    }

    /**
     * Catalogo de calidades de carbon.
     *
     * La tabla `calidad_carbon` es el fuente de verdad: puede tener mas
     * calidades que el enum `TipoCalidadCarbon` (por ejemplo `Calidad`, para
     * el carbon con mas de 30% de ceniza).
     *
     * @return array<int, string>
     */
    public static function get_calidades(): array
    {
        return DB::table('calidad_carbon')
            ->pluck('nombre')
            ->map(fn ($n) => (string) $n)
            ->all();
    }

    /**
     * Devuelve la cantidad de stock disponible de un tipo/calidad en un almacen.
     */
    public static function get_stock_disponible(int $id_almacen, int $id_tipo_carbon, string $calidad): float
    {
        $stock = DB::table('stock_carbon')
            ->where('id_almacen', $id_almacen)
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where('calidad', $calidad)
            ->first();

        return $stock !== null ? (float) $stock->stock_actual : 0.0;
    }

    /**
     * Helper para decodificar JSON sin errores.
     */
    public static function decode_json(mixed $val): mixed
    {
        if (is_array($val) || is_object($val)) {
            return $val;
        }
        if (is_string($val) && $val !== '') {
            $dec = json_decode($val, true);
            return json_last_error() === JSON_ERROR_NONE ? $dec : [];
        }
        return [];
    }
}
