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

    private static function kardex_tiene_carga_venta(): bool
    {
        if (self::$kardexTieneCargaVenta === null) {
            self::$kardexTieneCargaVenta = Schema::hasColumn('kardex_carbon', 'id_carga_venta_carbon');
        }

        return self::$kardexTieneCargaVenta;
    }

    /**
     * Lista las ventas de carbon con su cabecera y sus cargas.
     *
     * @param array{
     *   filtros?: string,
     *   id_venta_carbon?: int,
     *   id_cliente?: int,
     *   id_cliente_destino?: int,
     *   id_almacen_empresa_salida?: int,
     *   mes?: int,
     *   anio?: int
     * } $opts
     * @return array<object>
     */
    public static function get_ventas(array $opts = []): array
    {
        $sql = '
            SELECT
                vc.id AS id_venta_carbon,
                vc.correlativo,
                vc.numero_correlativo,
                vc.codigo,
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
                      AND cv.estado = :estado_confirmada
                ) AS cantidad_cargas_confirmadas,
                (
                    SELECT COALESCE(SUM(cv.cantidad_toneladas), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                      AND cv.estado = :estado_confirmada_2
                ) AS total_toneladas,
                (
                    SELECT COALESCE(SUM(cv.subtotal), 0)
                    FROM carga_venta_carbon cv
                    WHERE cv.id_venta_carbon = vc.id
                ) AS total_subtotal
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
            'estado_confirmada' => EstadoCargaVentaCarbon::LlegadaConfirmada->value,
            'estado_confirmada_2' => EstadoCargaVentaCarbon::LlegadaConfirmada->value,
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
            $sql .= ' AND (
                vc.correlativo LIKE :q
                OR vc.codigo LIKE :q
                OR c.razon_social LIKE :q
                OR cd.razon_social LIKE :q
                OR e.razon_social LIKE :q
            )';
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
        $cargasPorVenta = self::get_cargas_por_venta($ids);

        foreach ($rows as $row) {
            $cargas = $cargasPorVenta[(int) $row->id_venta_carbon] ?? [];
            $row->cargas = $cargas;
            $row->estado = self::resolver_estado_venta($cargas);
        }

        return $rows;
    }

    /**
     * Obtiene una venta por su id (misma estructura del listado).
     */
    public static function get_venta_por_id(int $id_venta_carbon): ?object
    {
        $rows = self::get_ventas(['id_venta_carbon' => $id_venta_carbon]);
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

        $sql = '
            SELECT
                cv.id AS id_carga_venta_carbon,
                cv.id_venta_carbon,
                cv.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                cv.id_tipo_carbon_estimado,
                tce.nombre AS tipo_carbon_estimado,
                tce.codigo AS tipo_carbon_estimado_codigo,
                cv.id_tipo_carbon_confirmado,
                tcc.nombre AS tipo_carbon_confirmado,
                tcc.codigo AS tipo_carbon_confirmado_codigo,
                cv.id_vehiculo_carbon,
                veh.placa AS vehiculo_placa,
                veh.tipo_vehiculo AS vehiculo_tipo,
                m.nombre AS vehiculo_marca,
                cv.calidad,
                cv.cantidad_toneladas,
                cv.cantidad_toneladas,
                cv.segunda_placa,
                cv.fecha_hora_salida,
                cv.fecha_hora_ingreso,
                cv.guia_remitente,
                cv.guia_transportista,
                cv.codigo_ticket_balanza,
                cv.codigo_reporte,
                cv.observacion,
                cv.porcentaje_ceniza,
                cv.porcentaje_humedad,
                cv.porcentaje_azufre,
                cv.porcentaje_pizarra,
                cv.analisis_granulometria,
                cv.precio_base,
                cv.subtotal_sin_descuento,
                cv.descuento,
                cv.bono,
                cv.subtotal,
                cv.evidencias,
                cv.estado,
                cv.created_at
            FROM carga_venta_carbon cv
            LEFT JOIN empleado e ON e.id = cv.id_empleado_registro
            LEFT JOIN tipo_carbon tce ON tce.id = cv.id_tipo_carbon_estimado
            LEFT JOIN tipo_carbon tcc ON tcc.id = cv.id_tipo_carbon_confirmado
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
            $resultado[(int) $row->id_venta_carbon][] = $row;
        }

        return $resultado;
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
            if (($carga->estado ?? null) !== EstadoCargaVentaCarbon::LlegadaConfirmada->value) {
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
                'codigo' => null,
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
            'codigo' => isset($data['codigo']) && $data['codigo'] !== '' ? (string) $data['codigo'] : null,
            'created_at' => (string) ($data['created_at'] ?? now()->toDateTimeString()),
        ]);
    }

    /**
     * Inserta las cargas de una venta. Cada carga descuenta el stock del
     * almacen de salida y genera su asiento de Kardex.
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

            $idAlmacenSalida = (int) $venta->id_almacen_empresa_salida;

            $idsCargas = [];
            foreach ($cargas as $c) {
                $idCarga = self::insert_carga($id_venta_carbon, $idAlmacenSalida, $c, $id_empleado);
                $idsCargas[] = $idCarga;
            }

            return $idsCargas;
        });
    }

    /**
     * Inserta una carga y ejecuta la salida de stock + Kardex.
     *
     * @param array<string, mixed> $c
     */
    private static function insert_carga(int $id_venta_carbon, int $id_almacen_salida, array $c, int $id_empleado): int
    {
        $idTipoCarbon = (int) $c['id_tipo_carbon'];
        $cantidad = round((float) $c['cantidad_toneladas'], 4);
        $calidad = (string) $c['calidad'];

        $evidenciasJson = isset($c['evidencias']) && $c['evidencias'] !== null
            ? (is_string($c['evidencias']) ? $c['evidencias'] : json_encode($c['evidencias'], JSON_UNESCAPED_UNICODE))
            : null;

        $idCarga = CargaVentaCarbon::insertGetId([
            'id_venta_carbon' => $id_venta_carbon,
            'id_empleado_registro' => $id_empleado,
            'id_tipo_carbon_estimado' => $idTipoCarbon,
            'id_tipo_carbon_confirmado' => null,
            'id_vehiculo_carbon' => isset($c['id_vehiculo_carbon']) && (int) $c['id_vehiculo_carbon'] > 0 ? (int) $c['id_vehiculo_carbon'] : null,
            'id_comprobante_venta_carbon' => null,
            'calidad' => $calidad,
            'cantidad_toneladas' => $cantidad,
            'cantidad_toneladas' => 0,
            'segunda_placa' => isset($c['segunda_placa']) && $c['segunda_placa'] !== '' ? strtoupper((string) $c['segunda_placa']) : null,
            'fecha_hora_salida' => (string) $c['fecha_hora_salida'],
            'fecha_hora_ingreso' => null,
            'guia_remitente' => null,
            'guia_transportista' => null,
            'codigo_ticket_balanza' => null,
            'codigo_reporte' => null,
            'observacion' => null,
            'porcentaje_ceniza' => 0,
            'porcentaje_humedad' => 0,
            'porcentaje_azufre' => 0,
            'porcentaje_pizarra' => 0,
            'analisis_granulometria' => null,
            'precio_base' => 0,
            'subtotal_sin_descuento' => 0,
            'descuento' => 0,
            'bono' => 0,
            'subtotal' => 0,
            'evidencias' => $evidenciasJson,
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoCargaVentaCarbon::EnCamino->value,
        ]);

        self::afectar_stock_salida(
            idAlmacen: $id_almacen_salida,
            idTipoCarbon: $idTipoCarbon,
            idCarga: $idCarga,
            cantidad: $cantidad,
            calidad: $calidad,
            fechaHoraSalida: (string) $c['fecha_hora_salida'],
        );

        return $idCarga;
    }

    /**
     * Descuenta el stock del almacen de salida y escribe el asiento de Kardex.
     *
     * El movimiento queda fechado en `fecha_hora_salida`: es el instante en el
     * que el carbon salio fisicamente del patio. Si no hay stock suficiente
     * se lanza excepcion y la transaccion completa se revierte.
     */
    private static function afectar_stock_salida(
        int $idAlmacen,
        int $idTipoCarbon,
        int $idCarga,
        float $cantidad,
        string $calidad,
        string $fechaHoraSalida
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
            'fecha_hora_movimiento' => $fechaHoraSalida,
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

        if (self::kardex_tiene_carga_venta()) {
            $movimiento['id_carga_venta_carbon'] = $idCarga;
        }

        KardexCarbon::insert($movimiento);
    }

    /**
     * Confirma la llegada de una carga al almacen del cliente.
     *
     * No mueve stock: el carbon ya salio del almacen de la empresa al ser
     * despachado. Lo que se registra aqui es la recepcion en destino, el
     * analisis de calidad, el peso confirmado y el precio.
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

            if ($carga->estado === EstadoCargaVentaCarbon::LlegadaConfirmada->value) {
                throw new \RuntimeException('La llegada de esta carga ya fue confirmada');
            }

            $cantidadConfirmada = round((float) $d['cantidad_toneladas'], 4);
            $precioBase = round((float) $d['precio_base'], 6);
            $descuento = round((float) ($d['descuento'] ?? 0), 2);
            $bono = round((float) ($d['bono'] ?? 0), 2);

            $subtotalSinDescuento = round($cantidadConfirmada * $precioBase, 2);
            $subtotal = round($subtotalSinDescuento - $descuento + $bono, 2);

            $idTipoConfirmado = (int) $d['id_tipo_carbon_confirmado'];
            $esTipoA = self::es_tipo_a($idTipoConfirmado);

            $analisis = null;
            if ($esTipoA && isset($d['analisis_granulometria']) && is_array($d['analisis_granulometria'])) {
                $analisis = [
                    'mayor_a_uno' => self::normalizar_porcentaje($d['analisis_granulometria']['mayor_a_uno'] ?? null),
                    'menor_a_uno' => self::normalizar_porcentaje($d['analisis_granulometria']['menor_a_uno'] ?? null),
                    'un_medio' => self::normalizar_porcentaje($d['analisis_granulometria']['un_medio'] ?? null),
                ];
            }

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

            DB::table('carga_venta_carbon')
                ->where('id', $id_carga_venta_carbon)
                ->update([
                    'id_tipo_carbon_confirmado' => $idTipoConfirmado,
                    'cantidad_toneladas' => $cantidadConfirmada,
                    'fecha_hora_ingreso' => (string) $d['fecha_hora_ingreso'],
                    'guia_remitente' => isset($d['guia_remitente']) && $d['guia_remitente'] !== '' ? (string) $d['guia_remitente'] : null,
                    'guia_transportista' => isset($d['guia_transportista']) && $d['guia_transportista'] !== '' ? (string) $d['guia_transportista'] : null,
                    'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) && $d['codigo_ticket_balanza'] !== '' ? (string) $d['codigo_ticket_balanza'] : null,
                    'codigo_reporte' => isset($d['codigo_reporte']) && $d['codigo_reporte'] !== '' ? (string) $d['codigo_reporte'] : null,
                    'observacion' => isset($d['observacion']) && $d['observacion'] !== '' ? (string) $d['observacion'] : null,
                    'porcentaje_ceniza' => round((float) ($d['porcentaje_ceniza'] ?? 0), 4),
                    'porcentaje_humedad' => round((float) ($d['porcentaje_humedad'] ?? 0), 4),
                    'porcentaje_azufre' => round((float) ($d['porcentaje_azufre'] ?? 0), 4),
                    'porcentaje_pizarra' => $esTipoA ? round((float) ($d['porcentaje_pizarra'] ?? 0), 4) : 0,
                    'analisis_granulometria' => $analisis !== null ? json_encode($analisis, JSON_UNESCAPED_UNICODE) : null,
                    'precio_base' => $precioBase,
                    'subtotal_sin_descuento' => $subtotalSinDescuento,
                    'descuento' => $descuento,
                    'bono' => $bono,
                    'subtotal' => $subtotal,
                    'evidencias' => empty($evidencias) ? null : json_encode($evidencias, JSON_UNESCAPED_UNICODE),
                    'estado' => EstadoCargaVentaCarbon::LlegadaConfirmada->value,
                ]);

            // El codigo de la venta lo entrega el cliente con su documento de
            // compra: se escribe en la cabecera de la venta.
            if (isset($d['codigo_venta']) && trim((string) $d['codigo_venta']) !== '') {
                DB::table('venta_carbon')
                    ->where('id', $id_venta_carbon)
                    ->update(['codigo' => trim((string) $d['codigo_venta'])]);
            }
        });
    }

    /**
     * El tipo de carbon "Tipo A" exige analisis granulometrico y % de pizarra.
     * Se resuelve por nombre porque el catalogo es editable desde el modulo
     * de tipos de carbon.
     */
    private static function es_tipo_a(int $id_tipo_carbon): bool
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
