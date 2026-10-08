<?php

namespace App\Modules\CompraCarbon\Data;

use Illuminate\Support\Facades\DB;

/**
 * Único lugar con SQL del proceso de comprobantes y pagos de Compra de Carbon.
 *
 * Cubre 4 objetos fisicos que comparten la misma logica de acumulados
 * (comprobante del proveedor, comprobantes de flete, pagos al proveedor y
 * pagos al transportista): comprobante_compra_carbon,
 * comprobante_transporte_carbon, pago_compra_carbon y
 * pago_transporte_carbon.
 */
class CompraCarbonPagosData
{
    // ------------------------------------------------------------------
    // COMPROBANTE DEL PROVEEDOR
    // ------------------------------------------------------------------

    /**
     * Trae el comprobante del proveedor de una compra con su avance real.
     * @return object|null
     */
    public static function get_comprobante_proveedor(int $id_compra_carbon): ?object
    {
        $row = DB::selectOne(
            self::SELECT_COMPROBANTE_PROVEEDOR . ' WHERE cc.id_compra_carbon = :id LIMIT 1',
            ['id' => $id_compra_carbon]
        );

        return $row === null ? null : self::hidratar_comprobante($row);
    }

    /**
     * Trae un comprobante del proveedor por su id.
     * @return object|null
     */
    public static function get_comprobante_proveedor_por_id(int $id_comprobante): ?object
    {
        $row = DB::selectOne(
            self::SELECT_COMPROBANTE_PROVEEDOR . ' WHERE cc.id = :id LIMIT 1',
            ['id' => $id_comprobante]
        );

        return $row === null ? null : self::hidratar_comprobante($row);
    }

    /**
     * Pagos registrados contra un comprobante del proveedor.
     * @return array<object>
     */
    public static function get_pagos_comprobante_proveedor(int $id_comprobante): array
    {
        $rows = DB::select(self::SELECT_PAGOS_COMPRA . ' AND p.id_comprobante_compra_carbon = :id ORDER BY p.id DESC', [
            'id' => $id_comprobante,
        ]);

        return array_map([self::class, 'hidratar_pago'], $rows);
    }

    /**
     * Todos los pagos al proveedor de una compra, con o sin comprobante.
     * @return array<object>
     */
    public static function get_pagos_proveedor(int $id_compra_carbon): array
    {
        $rows = DB::select(
            self::SELECT_PAGOS_COMPRA . ' AND p.id_compra_carbon = :id ORDER BY p.id DESC',
            ['id' => $id_compra_carbon]
        );

        return array_map([self::class, 'hidratar_pago'], $rows);
    }

    /**
     * Suma pagada por bucket dentro de un comprobante del proveedor.
     * @return array{neto: float, detraccion: float}
     */
    public static function get_avances_comprobante_proveedor(int $id_comprobante): array
    {
        $row = DB::selectOne(
            'SELECT
                COALESCE(SUM(CASE WHEN p.es_para_detraccion = 0 THEN p.monto_pagado ELSE 0 END), 0) AS neto,
                COALESCE(SUM(CASE WHEN p.es_para_detraccion = 1 THEN p.monto_pagado ELSE 0 END), 0) AS detraccion
             FROM pago_compra_carbon p
             WHERE p.id_comprobante_compra_carbon = :id',
            ['id' => $id_comprobante]
        );

        return [
            'neto' => (float) ($row->neto ?? 0),
            'detraccion' => (float) ($row->detraccion ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $d
     */
    public static function insert_comprobante_proveedor(array $d): int
    {
        return DB::table('comprobante_compra_carbon')->insertGetId([
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'id_compra_carbon' => (int) $d['id_compra_carbon'],
            'codigo_comprobante' => (string) $d['codigo_comprobante'],
            'fecha_emision' => (string) $d['fecha_emision'],
            'observacion' => $d['observacion'] ?? null,
            'evidencias' => $d['evidencias'] ?? null,
            'total' => (float) $d['total'],
            'con_detraccion' => !empty($d['con_detraccion']) ? 1 : 0,
            'porcentaje_detraccion' => (float) ($d['porcentaje_detraccion'] ?? 0),
            'monto_detraccion' => (float) ($d['monto_detraccion'] ?? 0),
            'total_neto' => (float) $d['total_neto'],
            'avance_pago_detraccion' => 0.0,
            'created_at' => (string) $d['created_at'],
            'estado' => (string) $d['estado'],
        ]);
    }

    /**
     * @param array<string, mixed> $d
     */
    public static function insert_pago_proveedor(array $d): int
    {
        return DB::table('pago_compra_carbon')->insertGetId([
            'id_compra_carbon' => (int) $d['id_compra_carbon'],
            'id_comprobante_compra_carbon' => $d['id_comprobante_compra_carbon'] ?? null,
            'id_cuenta_bancaria_empresa' => (int) $d['id_cuenta_bancaria_empresa'],
            'id_cuenta_bancaria_proveedor' => (int) $d['id_cuenta_bancaria_proveedor'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'medio_pago' => (string) $d['medio_pago'],
            'numero_operacion' => $d['numero_operacion'] ?? null,
            'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
            'es_para_detraccion' => !empty($d['es_para_detraccion']) ? 1 : 0,
            'observacion' => $d['observacion'] ?? null,
            'evidencias' => $d['evidencias'] ?? null,
            'monto_pagado' => (float) $d['monto_pagado'],
            'created_at' => (string) $d['created_at'],
        ]);
    }

    // ------------------------------------------------------------------
    // COMPROBANTES DE FLETE
    // ------------------------------------------------------------------

    /**
     * Agrupa las cargas con pagar_flete de una compra por transportista.
     * Cada grupo es un comprobante distinto. Trae ademas el comprobante ya
     * registrado (si existe) para no duplicar.
     * @return array<int, object>
     */
    public static function get_grupos_flete(int $id_compra_carbon): array
    {
        $grupos = DB::select(
            'SELECT
                d.id_transportista,
                tr.razon_social AS transportista_razon_social,
                tr.tipo_entidad AS transportista_tipo_entidad,
                COUNT(*) AS cantidad_cargas,
                COALESCE(SUM(d.descuento_flete), 0) AS total,
                COALESCE(SUM(d.cantidad), 0) AS cantidad_tm,
                GROUP_CONCAT(d.id ORDER BY d.id ASC) AS ids_detalles
             FROM carga_compra_carbon d
             INNER JOIN transportista tr ON tr.id = d.id_transportista
             WHERE d.id_compra_carbon = :id AND d.pagar_flete = 1
             GROUP BY d.id_transportista, tr.razon_social, tr.tipo_entidad
             ORDER BY tr.razon_social ASC',
            ['id' => $id_compra_carbon]
        );

        $existentes = [];
        foreach (DB::select(
            'SELECT id, id_transportista, estado FROM comprobante_transporte_carbon WHERE id_compra_carbon = :id',
            ['id' => $id_compra_carbon]
        ) as $c) {
            $existentes[(int) $c->id_transportista] = $c;
        }

        foreach ($grupos as $g) {
            $g->ids_detalles = array_values(array_filter(array_map('intval', explode(',', (string) $g->ids_detalles))));
            $g->total = (float) $g->total;
            $g->cantidad_cargas = (int) $g->cantidad_cargas;
            $g->cantidad_tm = (float) $g->cantidad_tm;
            $existente = $existentes[(int) $g->id_transportista] ?? null;
            $g->id_comprobante_transporte_carbon = $existente ? (int) $existente->id : null;
            $g->estado = $existente ? (string) $existente->estado : null;
        }

        return $grupos;
    }

    /**
     * Todos los comprobantes de flete de una compra con su avance real.
     * @return array<object>
     */
    public static function get_comprobantes_transporte(int $id_compra_carbon): array
    {
        $rows = DB::select(
            self::SELECT_COMPROBANTE_TRANSPORTE . ' WHERE ctc.id_compra_carbon = :id ORDER BY ctc.id ASC',
            ['id' => $id_compra_carbon]
        );

        return array_map([self::class, 'hidratar_comprobante'], $rows);
    }

    /**
     * @return object|null
     */
    public static function get_comprobante_transporte_por_id(int $id_comprobante): ?object
    {
        $row = DB::selectOne(
            self::SELECT_COMPROBANTE_TRANSPORTE . ' WHERE ctc.id = :id LIMIT 1',
            ['id' => $id_comprobante]
        );

        return $row === null ? null : self::hidratar_comprobante($row);
    }

    /**
     * @return object|null
     */
    public static function get_comprobante_transporte_de_transportista(int $id_compra_carbon, int $id_transportista): ?object
    {
        $row = DB::selectOne(
            self::SELECT_COMPROBANTE_TRANSPORTE . ' WHERE ctc.id_compra_carbon = :id AND ctc.id_transportista = :id_tr LIMIT 1',
            ['id' => $id_compra_carbon, 'id_tr' => $id_transportista]
        );

        return $row === null ? null : self::hidratar_comprobante($row);
    }

    /**
     * Cargas (detalles de la compra) que componen un comprobante de flete.
     * @return array<object>
     */
    public static function get_cargas_comprobante_transporte(int $id_comprobante): array
    {
        return DB::select(
            'SELECT
                d.id AS id_carga_compra_carbon,
                d.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                d.placa,
                d.guia_remitente,
                d.guia_transportista,
                d.codigo_ticket_balanza,
                d.cantidad,
                d.costo_flete_por_tonelada,
                d.descuento_flete
             FROM detalle_comprobante_transporte_carbon dc
             INNER JOIN carga_compra_carbon d ON d.id = dc.id_carga_compra_carbon
             INNER JOIN tipo_carbon t ON t.id = d.id_tipo_carbon
             WHERE dc.id_comprobante_transporte_carbon = :id
             ORDER BY d.id ASC',
            ['id' => $id_comprobante]
        );
    }

    /**
     * @return array<object>
     */
    public static function get_pagos_comprobante_transporte(int $id_comprobante): array
    {
        $rows = DB::select(
            self::SELECT_PAGOS_TRANSPORTE . ' AND p.id_comprobante_transporte_carbon = :id ORDER BY p.id DESC',
            ['id' => $id_comprobante]
        );

        return array_map([self::class, 'hidratar_pago'], $rows);
    }

    /**
     * @return array<object>
     */
    public static function get_pagos_transporte(int $id_compra_carbon): array
    {
        $rows = DB::select(
            self::SELECT_PAGOS_TRANSPORTE . ' AND p.id_compra_carbon = :id ORDER BY p.id DESC',
            ['id' => $id_compra_carbon]
        );

        return array_map([self::class, 'hidratar_pago'], $rows);
    }

    /**
     * @return array{neto: float, detraccion: float}
     */
    public static function get_avances_comprobante_transporte(int $id_comprobante): array
    {
        $row = DB::selectOne(
            'SELECT
                COALESCE(SUM(CASE WHEN p.es_para_detraccion = 0 THEN p.monto_pagado ELSE 0 END), 0) AS neto,
                COALESCE(SUM(CASE WHEN p.es_para_detraccion = 1 THEN p.monto_pagado ELSE 0 END), 0) AS detraccion
             FROM pago_transporte_carbon p
             WHERE p.id_comprobante_transporte_carbon = :id',
            ['id' => $id_comprobante]
        );

        return [
            'neto' => (float) ($row->neto ?? 0),
            'detraccion' => (float) ($row->detraccion ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $d
     * @param array<int, int> $ids_detalle_carga
     */
    public static function insert_comprobante_transporte(array $d, array $ids_detalle_carga): int
    {
        $id = DB::table('comprobante_transporte_carbon')->insertGetId([
            'id_compra_carbon' => (int) $d['id_compra_carbon'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'id_transportista' => (int) $d['id_transportista'],
            'codigo_comprobante' => (string) $d['codigo_comprobante'],
            'fecha_emision' => (string) $d['fecha_emision'],
            'observacion' => $d['observacion'] ?? null,
            'evidencias' => $d['evidencias'] ?? null,
            'total' => (float) $d['total'],
            'con_detraccion' => !empty($d['con_detraccion']) ? 1 : 0,
            'porcentaje_detraccion' => (float) ($d['porcentaje_detraccion'] ?? 0),
            'monto_detraccion' => (float) ($d['monto_detraccion'] ?? 0),
            'total_neto' => (float) $d['total_neto'],
            'avance_pago_detraccion' => 0.0,
            'created_at' => (string) $d['created_at'],
            'estado' => (string) $d['estado'],
        ]);

        DB::table('detalle_comprobante_transporte_carbon')->insert(array_map(
            fn(int $idDetalle): array => [
                'id_comprobante_transporte_carbon' => $id,
                'id_carga_compra_carbon' => $idDetalle,
            ],
            $ids_detalle_carga
        ));

        return $id;
    }

    /**
     * @param array<string, mixed> $d
     */
    public static function insert_pago_transporte(array $d): int
    {
        return DB::table('pago_transporte_carbon')->insertGetId([
            'id_compra_carbon' => (int) $d['id_compra_carbon'],
            'id_comprobante_transporte_carbon' => (int) $d['id_comprobante_transporte_carbon'],
            'id_cuenta_bancaria_empresa' => (int) $d['id_cuenta_bancaria_empresa'],
            'id_cuenta_bancaria_transportista' => (int) $d['id_cuenta_bancaria_transportista'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'medio_pago' => (string) $d['medio_pago'],
            'numero_operacion' => $d['numero_operacion'] ?? null,
            'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
            'es_para_detraccion' => !empty($d['es_para_detraccion']) ? 1 : 0,
            'observacion' => $d['observacion'] ?? null,
            'evidencias' => $d['evidencias'] ?? null,
            'monto_pagado' => (float) $d['monto_pagado'],
            'created_at' => (string) $d['created_at'],
        ]);
    }

    /**
     * Cargas de una compra que se van a facturar en el comprobante de flete de
     * un transportista concreto.
     *
     * @param array<int, int> $idsDetalle
     * @return array<object>
     */
    public static function get_cargas_a_facturar(int $id_compra_carbon, int $id_transportista, array $idsDetalle): array
    {
        if (empty($idsDetalle)) {
            return [];
        }

        $ph = [];
        $params = ['id_compra' => $id_compra_carbon, 'id_tr' => $id_transportista];
        foreach ($idsDetalle as $i => $idDet) {
            $key = "d_$i";
            $ph[] = ":$key";
            $params[$key] = $idDet;
        }

        return DB::select(
            'SELECT
                d.id AS id_carga_compra_carbon,
                d.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                d.placa,
                d.guia_remitente,
                d.guia_transportista,
                d.codigo_ticket_balanza,
                d.cantidad,
                d.costo_flete_por_tonelada,
                d.descuento_flete
             FROM carga_compra_carbon d
             INNER JOIN tipo_carbon t ON t.id = d.id_tipo_carbon
             WHERE d.id_compra_carbon = :id_compra
               AND d.id_transportista = :id_tr
               AND d.id IN (' . implode(',', $ph) . ')
             ORDER BY d.id ASC',
            $params
        );
    }

    // ------------------------------------------------------------------
    // ACUMULADOS DE LA COMPRA
    // ------------------------------------------------------------------

    /**
     * Suma un pago al avance acumulado de la cabecera de la compra.
     * `$deltaNeto` alimenta avance_pago_neto, `$deltaFlete` avance_pago_flete.
     */
    public static function sumar_avance_compra(int $id_compra_carbon, float $deltaNeto, float $deltaFlete): void
    {
        DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'avance_pago_neto' => DB::raw('COALESCE(avance_pago_neto, 0) + ' . round($deltaNeto, 2)),
                'avance_pago_flete' => DB::raw('COALESCE(avance_pago_flete, 0) + ' . round($deltaFlete, 2)),
            ]);
    }

    /**
     * Escribe el estado de la compra. El calculo del estado vive en el Service;
     * aqui solo se persiste.
     */
    public static function set_estado_compra(int $id_compra_carbon, string $estado): void
    {
        DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update(['estado' => $estado]);
    }

    /**
     * Saldos de la cabecera mas lo ya cobrado por cada canal.
     * @return array{
     *   total_con_descuento: float,
     *   monto_pagado_anticipos: float,
     *   avance_pago_neto: float,
     *   descuento_flete: float,
     *   avance_pago_flete: float,
     *   saldos_pagos_proveedor: array<object>,
     *   saldos_pagos_transporte: array<object>
     * }
     */
    public static function get_saldos_compra(int $id_compra_carbon): array
    {
        $cab = DB::selectOne(
            'SELECT
                COALESCE(total_con_descuento, 0) AS total_con_descuento,
                COALESCE(monto_pagado_anticipos, 0) AS monto_pagado_anticipos,
                COALESCE(avance_pago_neto, 0) AS avance_pago_neto,
                COALESCE(descuento_flete, 0) AS descuento_flete,
                COALESCE(avance_pago_flete, 0) AS avance_pago_flete
             FROM compra_carbon WHERE id = :id LIMIT 1',
            ['id' => $id_compra_carbon]
        );

        $saldosProveedor = DB::select(
            'SELECT
                COALESCE(id_comprobante_compra_carbon, 0) AS id_comprobante,
                COALESCE(SUM(CASE WHEN es_para_detraccion = 0 THEN monto_pagado ELSE 0 END), 0) AS neto,
                COALESCE(SUM(CASE WHEN es_para_detraccion = 1 THEN monto_pagado ELSE 0 END), 0) AS detraccion
             FROM pago_compra_carbon
             WHERE id_compra_carbon = :id
             GROUP BY id_comprobante_compra_carbon',
            ['id' => $id_compra_carbon]
        );

        $saldosTransporte = DB::select(
            'SELECT
                id_comprobante_transporte_carbon AS id_comprobante,
                COALESCE(SUM(CASE WHEN es_para_detraccion = 0 THEN monto_pagado ELSE 0 END), 0) AS neto,
                COALESCE(SUM(CASE WHEN es_para_detraccion = 1 THEN monto_pagado ELSE 0 END), 0) AS detraccion
             FROM pago_transporte_carbon
             WHERE id_compra_carbon = :id
             GROUP BY id_comprobante_transporte_carbon',
            ['id' => $id_compra_carbon]
        );

        return [
            'total_con_descuento' => (float) ($cab->total_con_descuento ?? 0),
            'monto_pagado_anticipos' => (float) ($cab->monto_pagado_anticipos ?? 0),
            'avance_pago_neto' => (float) ($cab->avance_pago_neto ?? 0),
            'descuento_flete' => (float) ($cab->descuento_flete ?? 0),
            'avance_pago_flete' => (float) ($cab->avance_pago_flete ?? 0),
            'saldos_pagos_proveedor' => $saldosProveedor,
            'saldos_pagos_transporte' => $saldosTransporte,
        ];
    }

    /**
     * Guarda el estado y el avance de detraccion de un comprobante del proveedor.
     */
    public static function set_estado_comprobante_proveedor(int $id_comprobante, string $estado, float $avanceDetraccion): void
    {
        DB::table('comprobante_compra_carbon')
            ->where('id', $id_comprobante)
            ->update([
                'estado' => $estado,
                'avance_pago_detraccion' => round($avanceDetraccion, 2),
            ]);
    }

    /**
     * Guarda el estado y el avance de detraccion de un comprobante de flete.
     */
    public static function set_estado_comprobante_transporte(int $id_comprobante, string $estado, float $avanceDetraccion): void
    {
        DB::table('comprobante_transporte_carbon')
            ->where('id', $id_comprobante)
            ->update([
                'estado' => $estado,
                'avance_pago_detraccion' => round($avanceDetraccion, 2),
            ]);
    }

    /**
     * Saldo de cada comprobante (y del tramo sin comprobante) de la compra.
     *
     * El estado de la compra NO puede derivarse de `avance_pago_neto` /
     * `avance_pago_flete` a secas: esos acumulados solo crecen con los pagos
     * no-detraccion, asi que el monto retenido quedaria como saldo fantasma y
     * la compra jamas cerraria aunque este pagada de verdad. Este metodo
     * devuelve los dos buckets por separado para que la decision se tome sobre
     * los documentos reales.
     *
     * @return array<int, array{
     *   tipo: string,
     *   id_comprobante: int|null,
     *   total_neto: float,
     *   monto_detraccion: float,
     *   neto_pagado: float,
     *   detraccion_pagada: float,
     *   saldo_total: float
     * }>
     */
    public static function get_saldos_por_comprobante(int $id_compra_carbon): array
    {
        $saldos = [];

        $comprobanteProveedor = DB::selectOne(
            'SELECT cc.id, cc.total_neto, cc.monto_detraccion
             FROM comprobante_compra_carbon cc WHERE cc.id_compra_carbon = :id LIMIT 1',
            ['id' => $id_compra_carbon]
        );

        if ($comprobanteProveedor !== null) {
            $pagos = DB::selectOne(
                'SELECT
                    COALESCE(SUM(CASE WHEN es_para_detraccion = 0 THEN monto_pagado ELSE 0 END), 0) AS neto,
                    COALESCE(SUM(CASE WHEN es_para_detraccion = 1 THEN monto_pagado ELSE 0 END), 0) AS detraccion
                 FROM pago_compra_carbon
                 WHERE id_comprobante_compra_carbon = :id',
                ['id' => (int) $comprobanteProveedor->id]
            );

            $saldos[] = self::armar_saldo(
                tipo: 'proveedor',
                idComprobante: (int) $comprobanteProveedor->id,
                totalNeto: (float) $comprobanteProveedor->total_neto,
                montoDetraccion: (float) $comprobanteProveedor->monto_detraccion,
                netoPagado: (float) $pagos->neto,
                detraccionPagada: (float) $pagos->detraccion
            );
        } else {
            // Compra sin comprobante: el pago compite contra el total menos
            // los anticipos ya consumidos.
            $cab = DB::selectOne(
                'SELECT COALESCE(total_con_descuento, 0) AS total, COALESCE(monto_pagado_anticipos, 0) AS anticipos
                 FROM compra_carbon WHERE id = :id LIMIT 1',
                ['id' => $id_compra_carbon]
            );
            $pagos = DB::selectOne(
                'SELECT COALESCE(SUM(CASE WHEN es_para_detraccion = 0 THEN monto_pagado ELSE 0 END), 0) AS neto
                 FROM pago_compra_carbon
                 WHERE id_compra_carbon = :id AND id_comprobante_compra_carbon IS NULL',
                ['id' => $id_compra_carbon]
            );

            $saldos[] = self::armar_saldo(
                tipo: 'proveedor-sin-comprobante',
                idComprobante: null,
                totalNeto: round((float) $cab->total - (float) $cab->anticipos, 2),
                montoDetraccion: 0.0,
                netoPagado: (float) $pagos->neto,
                detraccionPagada: 0.0
            );
        }

        $comprobantesFlete = DB::select(
            'SELECT ctc.id, ctc.total_neto, ctc.monto_detraccion
             FROM comprobante_transporte_carbon ctc WHERE ctc.id_compra_carbon = :id',
            ['id' => $id_compra_carbon]
        );

        foreach ($comprobantesFlete as $ctc) {
            $pagos = DB::selectOne(
                'SELECT
                    COALESCE(SUM(CASE WHEN es_para_detraccion = 0 THEN monto_pagado ELSE 0 END), 0) AS neto,
                    COALESCE(SUM(CASE WHEN es_para_detraccion = 1 THEN monto_pagado ELSE 0 END), 0) AS detraccion
                 FROM pago_transporte_carbon
                 WHERE id_comprobante_transporte_carbon = :id',
                ['id' => (int) $ctc->id]
            );

            $saldos[] = self::armar_saldo(
                tipo: 'flete',
                idComprobante: (int) $ctc->id,
                totalNeto: (float) $ctc->total_neto,
                montoDetraccion: (float) $ctc->monto_detraccion,
                netoPagado: (float) $pagos->neto,
                detraccionPagada: (float) $pagos->detraccion
            );
        }

        return $saldos;
    }

    /**
     * @return array{
     *   tipo: string,
     *   id_comprobante: int|null,
     *   total_neto: float,
     *   monto_detraccion: float,
     *   neto_pagado: float,
     *   detraccion_pagada: float,
     *   saldo_total: float
     * }
     */
    private static function armar_saldo(
        string $tipo,
        ?int $idComprobante,
        float $totalNeto,
        float $montoDetraccion,
        float $netoPagado,
        float $detraccionPagada
    ): array {
        $saldo = round(
            ($totalNeto - $netoPagado) + ($montoDetraccion - $detraccionPagada),
            2
        );

        return [
            'tipo' => $tipo,
            'id_comprobante' => $idComprobante,
            'total_neto' => round($totalNeto, 2),
            'monto_detraccion' => round($montoDetraccion, 2),
            'neto_pagado' => round($netoPagado, 2),
            'detraccion_pagada' => round($detraccionPagada, 2),
            'saldo_total' => $saldo,
        ];
    }

    // ------------------------------------------------------------------
    // CONSULTAS AUXILIARES DE SOPORTE
    // ------------------------------------------------------------------

    /**
     * Verifica que la cuenta bancaria exista, sea de la entidad esperada y
     * este en soles.
     *
     * `$paraDetraccion` fija el criterio del flag `es_para_detraccion`:
     * - `true`  la cuenta debe estar marcada para detraccion.
     * - `false` la cuenta NO debe estar marcada para detraccion.
     * - `null`  el flag no importa (cuentas de salida de la empresa).
     *
     * @return array{ok: bool, mensaje: string|null}
     */
    public static function validar_cuenta(string $tabla, string $colEntidad, int $id_cuenta, int $id_entidad, ?bool $paraDetraccion = null): array
    {
        $row = DB::selectOne(
            "SELECT cn.id, cn.{$colEntidad} AS id_entidad, cn.moneda, cn.es_para_detraccion
             FROM {$tabla} cn
             WHERE cn.id = :id LIMIT 1",
            ['id' => $id_cuenta]
        );

        if ($row === null) {
            return ['ok' => false, 'mensaje' => 'La cuenta bancaria seleccionada no existe'];
        }
        if ((int) $row->id_entidad !== $id_entidad) {
            return ['ok' => false, 'mensaje' => 'La cuenta bancaria seleccionada no pertenece a la entidad esperada'];
        }
        if ((string) $row->moneda !== 'Soles') {
            return ['ok' => false, 'mensaje' => 'Solo se admiten cuentas en soles para registrar pagos'];
        }

        if ($paraDetraccion !== null) {
            $esDetraccion = (int) $row->es_para_detraccion === 1;
            if ($paraDetraccion && !$esDetraccion) {
                return ['ok' => false, 'mensaje' => 'El pago de detracción debe depositarse en la cuenta de detracción designada por SUNAT'];
            }
            if (!$paraDetraccion && $esDetraccion) {
                return ['ok' => false, 'mensaje' => 'La cuenta de detracción solo puede usarse para pagos de detracción'];
            }
        }

        return ['ok' => true, 'mensaje' => null];
    }

    /**
     * Confirma que los detalles indicados pertenezcan a la compra y esten
     * marcados con pagar_flete.
     * @param array<int, int> $ids
     * @return array<int, int> Ids validos, en el orden recibido.
     */
    public static function filtrar_detalles_con_flete(int $id_compra_carbon, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ph = [];
        $params = ['id_compra' => $id_compra_carbon];
        foreach ($ids as $i => $idDet) {
            $key = "d_$i";
            $ph[] = ":$key";
            $params[$key] = (int) $idDet;
        }

        $rows = DB::select(
            'SELECT id FROM carga_compra_carbon
             WHERE id_compra_carbon = :id_compra AND pagar_flete = 1 AND id IN (' . implode(',', $ph) . ')',
            $params
        );

        $validos = array_map(fn($r): int => (int) $r->id, $rows);

        return array_values(array_intersect(array_map('intval', $ids), $validos));
    }

    // ------------------------------------------------------------------
    // SQL Y HELPERS PRIVADOS
    // ------------------------------------------------------------------

    private const SELECT_COMPROBANTE_PROVEEDOR = '
        SELECT
            cc.id AS id_comprobante_compra_carbon,
            cc.id_compra_carbon,
            cc.id_empleado_registro,
            CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
            cc.codigo_comprobante,
            cc.fecha_emision,
            cc.observacion,
            cc.evidencias,
            cc.total,
            cc.con_detraccion,
            cc.porcentaje_detraccion,
            cc.monto_detraccion,
            cc.total_neto,
            cc.avance_pago_detraccion,
            cc.created_at,
            cc.estado,
            COALESCE((
                SELECT SUM(p.monto_pagado) FROM pago_compra_carbon p
                WHERE p.id_comprobante_compra_carbon = cc.id AND p.es_para_detraccion = 0
            ), 0) AS avance_pago_neto,
            COALESCE((
                SELECT SUM(p.monto_pagado) FROM pago_compra_carbon p
                WHERE p.id_comprobante_compra_carbon = cc.id AND p.es_para_detraccion = 1
            ), 0) AS avance_pago_detraccion_total
        FROM comprobante_compra_carbon cc
        INNER JOIN empleado er ON er.id = cc.id_empleado_registro
    ';

    private const SELECT_COMPROBANTE_TRANSPORTE = '
        SELECT
            ctc.id AS id_comprobante_transporte_carbon,
            ctc.id_compra_carbon,
            ctc.id_transportista,
            tr.razon_social AS transportista_razon_social,
            tr.tipo_entidad AS transportista_tipo_entidad,
            ctc.id_empleado_registro,
            CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
            ctc.codigo_comprobante,
            ctc.fecha_emision,
            ctc.observacion,
            ctc.evidencias,
            ctc.total,
            ctc.con_detraccion,
            ctc.porcentaje_detraccion,
            ctc.monto_detraccion,
            ctc.total_neto,
            ctc.avance_pago_detraccion,
            ctc.created_at,
            ctc.estado,
            COALESCE((
                SELECT SUM(p.monto_pagado) FROM pago_transporte_carbon p
                WHERE p.id_comprobante_transporte_carbon = ctc.id AND p.es_para_detraccion = 0
            ), 0) AS avance_pago_neto,
            COALESCE((
                SELECT SUM(p.monto_pagado) FROM pago_transporte_carbon p
                WHERE p.id_comprobante_transporte_carbon = ctc.id AND p.es_para_detraccion = 1
            ), 0) AS avance_pago_detraccion_total
        FROM comprobante_transporte_carbon ctc
        INNER JOIN transportista tr ON tr.id = ctc.id_transportista
        INNER JOIN empleado er ON er.id = ctc.id_empleado_registro
    ';

    /**
     * Los pagos de proveedor. El destino siempre es `cuenta_bancaria_proveedor`.
     */
    private const SELECT_PAGOS_COMPRA = "
        SELECT
            p.id AS id_pago,
            p.id_compra_carbon,
            p.id_comprobante_compra_carbon AS id_comprobante,
            p.id_cuenta_bancaria_empresa,
            bce.nombre AS banco_empresa,
            bce.abreviatura AS banco_empresa_abv,
            ce.numero_cuenta AS cuenta_empresa_numero,
            p.id_cuenta_bancaria_proveedor AS id_cuenta_bancaria_destino,
            bcp.nombre AS banco_destino,
            bcp.abreviatura AS banco_destino_abv,
            cp.numero_cuenta AS cuenta_destino_numero,
            p.id_empleado_registro,
            CONCAT(er.nombre, ' ', er.apellido) AS empleado_registro,
            p.medio_pago,
            p.numero_operacion,
            p.fecha_hora_pago,
            p.es_para_detraccion,
            p.observacion,
            p.evidencias,
            p.monto_pagado,
            p.created_at
        FROM pago_compra_carbon p
        LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = p.id_cuenta_bancaria_empresa
        LEFT JOIN banco bce ON bce.id = ce.id_banco
        LEFT JOIN cuenta_bancaria_proveedor cp ON cp.id = p.id_cuenta_bancaria_proveedor
        LEFT JOIN banco bcp ON bcp.id = cp.id_banco
        INNER JOIN empleado er ON er.id = p.id_empleado_registro
        WHERE 1 = 1
    ";

    /**
     * Los pagos de flete. El destino SIEMPRE es `cuenta_bancaria_transportista`.
     */
    private const SELECT_PAGOS_TRANSPORTE = "
        SELECT
            p.id AS id_pago,
            p.id_compra_carbon,
            p.id_comprobante_transporte_carbon AS id_comprobante,
            p.id_cuenta_bancaria_empresa,
            bce.nombre AS banco_empresa,
            bce.abreviatura AS banco_empresa_abv,
            ce.numero_cuenta AS cuenta_empresa_numero,
            p.id_cuenta_bancaria_transportista AS id_cuenta_bancaria_destino,
            bct.nombre AS banco_destino,
            bct.abreviatura AS banco_destino_abv,
            ct.numero_cuenta AS cuenta_destino_numero,
            p.id_empleado_registro,
            CONCAT(er.nombre, ' ', er.apellido) AS empleado_registro,
            p.medio_pago,
            p.numero_operacion,
            p.fecha_hora_pago,
            p.es_para_detraccion,
            p.observacion,
            p.evidencias,
            p.monto_pagado,
            p.created_at
        FROM pago_transporte_carbon p
        LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = p.id_cuenta_bancaria_empresa
        LEFT JOIN banco bce ON bce.id = ce.id_banco
        LEFT JOIN cuenta_bancaria_transportista ct ON ct.id = p.id_cuenta_bancaria_transportista
        LEFT JOIN banco bct ON bct.id = ct.id_banco
        INNER JOIN empleado er ON er.id = p.id_empleado_registro
        WHERE 1 = 1
    ";

    /**
     * Castea los TINYINT y decodifica el JSON de evidencias de un comprobante.
     */
    private static function hidratar_comprobante(object $row): object
    {
        $row->con_detraccion = (int) $row->con_detraccion === 1;
        $row->total = (float) $row->total;
        $row->porcentaje_detraccion = (float) $row->porcentaje_detraccion;
        $row->monto_detraccion = (float) $row->monto_detraccion;
        $row->total_neto = (float) $row->total_neto;
        $row->avance_pago_neto = (float) $row->avance_pago_neto;
        $row->avance_pago_detraccion_total = (float) $row->avance_pago_detraccion_total;
        $row->avance_pago_detraccion = (float) $row->avance_pago_detraccion;
        $row->evidencias = self::decodificar_json($row->evidencias ?? null);

        return $row;
    }

    /**
     * Castea los TINYINT y decodifica el JSON de evidencias de un pago.
     */
    private static function hidratar_pago(object $row): object
    {
        $row->es_para_detraccion = (int) $row->es_para_detraccion === 1;
        $row->monto_pagado = (float) $row->monto_pagado;
        $row->evidencias = self::decodificar_json($row->evidencias ?? null);

        return $row;
    }

    /**
     * @return array<int, mixed>
     */
    private static function decodificar_json(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
