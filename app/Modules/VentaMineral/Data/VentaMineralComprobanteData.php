<?php

namespace App\Modules\VentaMineral\Data;

use App\Shared\Enums\VentaMineral\EstadoComprobanteDespacho;
use Illuminate\Support\Facades\DB;

/**
 * Data de los comprobantes de despacho y de sus pagos.
 *
 * Los cuatro tipos de comprobante (flete, molienda, analisis y liquidacion)
 * comparten la misma forma de tabla, pero cada uno tiene su propia regla de
 * negocio. Por eso cada metodo es explicito: no hay un "comprobante generico"
 * con un tipo de tabla coming de la request.
 */
class VentaMineralComprobanteData
{
    /** Tabla de cada tipo de comprobante. */
    public const TABLA = [
        'flete' => 'comprobante_flete_despacho',
        'molienda' => 'comprobante_molienda_despacho',
        'analisis' => 'comprobante_analisis_despacho',
        'liquidacion' => 'comprobante_liquidacion_despacho',
    ];

    /** Tabla de pagos de cada tipo de comprobante. */
    public const TABLA_PAGO = [
        'flete' => 'pago_comprobante_flete_despacho',
        'molienda' => 'pago_comprobante_molienda_despacho',
        'analisis' => 'pago_comprobante_analisis_despacho',
        'liquidacion' => 'pago_comprobante_liquidacion_despacho',
    ];

    /** Columna que enlaza el pago con su comprobante. */
    public const COLUMNA_FK_PAGO = [
        'flete' => 'id_comprobante_flete_despacho',
        'molienda' => 'id_comprobante_molienda_despacho',
        'analisis' => 'id_comprobante_analisis_despacho',
        'liquidacion' => 'id_comprobante_liquidacion_despacho',
    ];

    /** Columna PRIMARY KEY de cada tipo de comprobante. */
    public const COLUMNA_ID = [
        'flete' => 'id_comprobante_flete_despacho',
        'molienda' => 'id_comprobante_molienda_despacho',
        'analisis' => 'id_comprobante_analisis_despacho',
        'liquidacion' => 'id_comprobante_liquidacion_despacho',
    ];

    /**
     * Columnas extra de cada tipo. Se completan con NULL para que la
     * respuesta tenga siempre la misma forma y el front no tenga que
     * adivinar el tipo.
     */
    private const EXTRAS = [
        'flete' => '
            c.id_transportista_mineral,
            c.toneladas_cobradas,
            c.precio_por_tonelada,
            t.razon_social AS transportista,
            NULL AS id_liquidacion_mineral,
            NULL AS codigo_liquidacion,
            NULL AS cliente',
        'molienda' => '
            NULL AS id_transportista_mineral,
            NULL AS toneladas_cobradas,
            NULL AS precio_por_tonelada,
            NULL AS transportista,
            NULL AS id_liquidacion_mineral,
            NULL AS codigo_liquidacion,
            NULL AS cliente',
        'analisis' => '
            NULL AS id_transportista_mineral,
            NULL AS toneladas_cobradas,
            NULL AS precio_por_tonelada,
            NULL AS transportista,
            NULL AS id_liquidacion_mineral,
            NULL AS codigo_liquidacion,
            NULL AS cliente',
        'liquidacion' => '
            NULL AS id_transportista_mineral,
            NULL AS toneladas_cobradas,
            NULL AS precio_por_tonelada,
            NULL AS transportista,
            c.id_liquidacion_mineral,
            li.codigo_liquidacion,
            cli.razon_social AS cliente',
    ];

    /**
     * JOINs adicionales de cada tipo.
     */
    private const JOINS = [
        'flete' => 'LEFT JOIN transportista_mineral t ON t.id = c.id_transportista_mineral',
        'molienda' => '',
        'analisis' => '',
        'liquidacion' => '
            LEFT JOIN liquidacion_mineral li ON li.id = c.id_liquidacion_mineral
            LEFT JOIN cliente cli ON cli.id = li.id_cliente',
    ];

    /**
     * Comprueba que el tipo de comprobante sea uno de los conocidos.
     */
    public static function tipo_valido(?string $tipo): bool
    {
        return $tipo !== null && array_key_exists($tipo, self::TABLA);
    }

    /**
     * SELECT base de un comprobante, con sus extras.
     */
    private static function select_base(string $tipo): string
    {
        return '
        SELECT
            c.id AS id_comprobante,
            c.id_despacho_mineral,
            c.id_empleado_registro,
            c.codigo_comprobante,
            c.fecha_emision,
            c.con_detraccion,
            c.porcentaje_detraccion,
            c.porcentaje_igv,
            c.subtotal,
            c.igv,
            c.total,
            c.monto_detraccion,
            c.neto_a_pagar,
            COALESCE(c.avance_detraccion, 0) AS avance_detraccion,
            COALESCE(c.avance_neto, 0) AS avance_neto,
            c.evidencias,
            c.created_at,
            c.estado,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro,
            ' . self::EXTRAS[$tipo] . '
        FROM ' . self::TABLA[$tipo] . ' c
        LEFT JOIN empleado e ON e.id = c.id_empleado_registro
        ' . self::JOINS[$tipo] . '
        ';
    }

    /**
     * Normaliza una fila de comprobante para la respuesta.
     */
    private static function normalizar(object $row, string $tipo): object
    {
        $row->id_comprobante = (int) $row->id_comprobante;
        $row->id_despacho_mineral = (int) $row->id_despacho_mineral;
        $row->con_detraccion = (int) ($row->con_detraccion ?? 0) === 1;
        $row->porcentaje_detraccion = (float) $row->porcentaje_detraccion;
        $row->porcentaje_igv = (float) $row->porcentaje_igv;
        $row->subtotal = (float) $row->subtotal;
        $row->igv = (float) $row->igv;
        $row->total = (float) $row->total;
        $row->monto_detraccion = (float) $row->monto_detraccion;
        $row->neto_a_pagar = (float) $row->neto_a_pagar;
        $row->avance_detraccion = (float) $row->avance_detraccion;
        $row->avance_neto = (float) $row->avance_neto;
        $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);

        if ($tipo === 'flete') {
            $row->id_transportista_mineral = $row->id_transportista_mineral !== null
                ? (int) $row->id_transportista_mineral
                : null;
            $row->toneladas_cobradas = (float) $row->toneladas_cobradas;
            $row->precio_por_tonelada = (float) $row->precio_por_tonelada;
        }

        if ($tipo === 'liquidacion') {
            $row->id_liquidacion_mineral = $row->id_liquidacion_mineral !== null
                ? (int) $row->id_liquidacion_mineral
                : null;
        }

        return $row;
    }

    /**
     * Comprobantes de un despacho de un tipo dado.
     */
    public static function get_comprobantes(string $tipo, int $id_despacho_mineral): array
    {
        if (!self::tipo_valido($tipo)) {
            return [];
        }

        $sql = self::select_base($tipo)
            . ' WHERE c.id_despacho_mineral = :id_despacho_mineral ORDER BY c.id ASC';

        $rows = DB::select($sql, ['id_despacho_mineral' => $id_despacho_mineral]);

        return array_map(fn ($row) => self::normalizar($row, $tipo), $rows);
    }

    /**
     * Un comprobante.
     */
    public static function get_comprobante(string $tipo, int $id_comprobante): ?object
    {
        if (!self::tipo_valido($tipo)) {
            return null;
        }

        $sql = self::select_base($tipo)
            . ' WHERE c.id = :id LIMIT 1';

        $row = DB::selectOne($sql, ['id' => $id_comprobante]);

        if ($row === null) {
            return null;
        }

        return self::normalizar($row, $tipo);
    }

    /**
     * Indica si el despacho ya tiene un comprobante de ese tipo.
     *
     * @param int|null $excepto_id Comprobante que se esta editando (se ignora).
     */
    public static function existe_comprobante(string $tipo, int $id_despacho_mineral, ?int $excepto_id = null): bool
    {
        if (!self::tipo_valido($tipo)) {
            return false;
        }

        $query = DB::table(self::TABLA[$tipo])
            ->where('id_despacho_mineral', $id_despacho_mineral);

        if ($excepto_id !== null) {
            $query->where('id', '!=', $excepto_id);
        }

        return $query->exists();
    }

    /**
     * Registra un comprobante de despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_comprobante(string $tipo, array $d): int
    {
        if (!self::tipo_valido($tipo)) {
            throw new \RuntimeException('Tipo de comprobante no valido');
        }

        $tabla = self::TABLA[$tipo];

        return DB::table($tabla)->insertGetId([
            'id_despacho_mineral' => (int) $d['id_despacho_mineral'],
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'codigo_comprobante' => (string) $d['codigo_comprobante'],
            'fecha_emision' => (string) $d['fecha_emision'],
            'con_detraccion' => !empty($d['con_detraccion']) ? 1 : 0,
            'porcentaje_detraccion' => (float) $d['porcentaje_detraccion'],
            'porcentaje_igv' => (float) $d['porcentaje_igv'],
            'subtotal' => round((float) $d['subtotal'], 2),
            'igv' => round((float) $d['igv'], 2),
            'total' => round((float) $d['total'], 2),
            'monto_detraccion' => round((float) $d['monto_detraccion'], 2),
            'neto_a_pagar' => round((float) $d['neto_a_pagar'], 2),
            'avance_detraccion' => 0.0,
            'avance_neto' => 0.0,
            'evidencias' => VentaMineralData::encode_json($d['evidencias'] ?? null),
            'created_at' => now()->toDateTimeString(),
            'estado' => EstadoComprobanteDespacho::PendienteDePago->value,
        ] + self::extras_registro($tipo, $d));
    }

    /**
     * Actualiza un comprobante de despacho.
     *
     * @param array<string, mixed> $d
     */
    public static function actualizar_comprobante(string $tipo, array $d): void
    {
        if (!self::tipo_valido($tipo)) {
            throw new \RuntimeException('Tipo de comprobante no valido');
        }

        $actualizaciones = [
            'codigo_comprobante' => (string) $d['codigo_comprobante'],
            'fecha_emision' => (string) $d['fecha_emision'],
            'con_detraccion' => !empty($d['con_detraccion']) ? 1 : 0,
            'porcentaje_detraccion' => (float) $d['porcentaje_detraccion'],
            'porcentaje_igv' => (float) $d['porcentaje_igv'],
            'subtotal' => round((float) $d['subtotal'], 2),
            'igv' => round((float) $d['igv'], 2),
            'total' => round((float) $d['total'], 2),
            'monto_detraccion' => round((float) $d['monto_detraccion'], 2),
            'neto_a_pagar' => round((float) $d['neto_a_pagar'], 2),
        ];

        if (array_key_exists('evidencias', $d)) {
            $actualizaciones['evidencias'] = VentaMineralData::encode_json($d['evidencias'] ?? null);
        }

        $actualizaciones += self::extras_registro($tipo, $d);

        DB::table(self::TABLA[$tipo])
            ->where('id', (int) $d['id_comprobante'])
            ->update($actualizaciones);
    }

    /**
     * Columnas propias de cada tipo de comprobante.
     *
     * @param array<string, mixed> $d
     * @return array<string, mixed>
     */
    private static function extras_registro(string $tipo, array $d): array
    {
        if ($tipo === 'flete') {
            return [
                'id_transportista_mineral' => (int) $d['id_transportista_mineral'],
                'toneladas_cobradas' => round((float) $d['toneladas_cobradas'], 6),
                'precio_por_tonelada' => round((float) $d['precio_por_tonelada'], 6),
            ];
        }

        if ($tipo === 'liquidacion') {
            return [
                'id_liquidacion_mineral' => (int) $d['id_liquidacion_mineral'],
            ];
        }

        return [];
    }

    /**
     * Elimina un comprobante de despacho.
     */
    public static function eliminar_comprobante(string $tipo, int $id_comprobante): void
    {
        if (!self::tipo_valido($tipo)) {
            return;
        }

        DB::transaction(function () use ($tipo, $id_comprobante) {
            DB::table(self::TABLA_PAGO[$tipo])
                ->where(self::COLUMNA_FK_PAGO[$tipo], $id_comprobante)
                ->delete();

            DB::table(self::TABLA[$tipo])
                ->where('id', $id_comprobante)
                ->delete();
        });
    }

    /**
     * Pagos de varios comprobantes de un tipo, agrupados por comprobante.
     *
     * @param array<int, mixed> $ids_comprobantes
     * @return array<int, array<object>>
     */
    public static function get_pagos(string $tipo, array $ids_comprobantes): array
    {
        if (!self::tipo_valido($tipo) || empty($ids_comprobantes)) {
            return [];
        }

        $sql = '
        SELECT
            p.id AS id_pago,
            p.' . self::COLUMNA_FK_PAGO[$tipo] . ' AS id_comprobante,
            p.id_cuenta_bancaria_empresa,
            p.id_empleado_registro,
            p.medio_pago,
            p.numero_operacion,
            p.fecha_hora_pago,
            p.es_para_detraccion,
            p.observacion,
            p.evidencias,
            p.monto_pagado,
            p.created_at,
            CONCAT(e.nombre, " ", COALESCE(e.apellido, "")) AS empleado_registro,
            cbe.numero_cuenta AS empresa_numero_cuenta,
            cbe.cci AS empresa_cci,
            b.nombre AS empresa_banco,
            b.abreviatura AS empresa_banco_abv
        FROM ' . self::TABLA_PAGO[$tipo] . ' p
        LEFT JOIN empleado e ON e.id = p.id_empleado_registro
        LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = p.id_cuenta_bancaria_empresa
        LEFT JOIN banco b ON b.id = cbe.id_banco
        WHERE p.' . self::COLUMNA_FK_PAGO[$tipo] . ' IN (' . implode(',', array_fill(0, count($ids_comprobantes), '?')) . ')
        ORDER BY p.id ASC
        ';

        $rows = DB::select($sql, array_map('intval', array_values($ids_comprobantes)));

        $resultado = [];
        foreach ($rows as $row) {
            $row->id_pago = (int) $row->id_pago;
            $row->id_comprobante = (int) $row->id_comprobante;
            $row->es_para_detraccion = (int) ($row->es_para_detraccion ?? 0) === 1;
            $row->monto_pagado = (float) $row->monto_pagado;
            $row->evidencias = VentaMineralData::decode_json($row->evidencias ?? null);
            $resultado[(int) $row->id_comprobante][] = $row;
        }

        return $resultado;
    }

    /**
     * Registra un pago sobre un comprobante y recalcula su estado.
     *
     * @param array<string, mixed> $d
     */
    public static function registrar_pago(string $tipo, array $d): int
    {
        if (!self::tipo_valido($tipo)) {
            throw new \RuntimeException('Tipo de comprobante no valido');
        }

        return DB::transaction(function () use ($tipo, $d) {
            $tabla = self::TABLA[$tipo];
            $idComprobante = (int) $d['id_comprobante'];

            $comprobante = DB::table($tabla)
                ->where('id', $idComprobante)
                ->lockForUpdate()
                ->first();

            if ($comprobante === null) {
                throw new \RuntimeException('El comprobante no existe');
            }

            $esDetraccion = !empty($d['es_para_detraccion']);
            $montoPagado = round((float) $d['monto_pagado'], 2);

            $avanceNeto = (float) ($comprobante->avance_neto ?? 0);
            $avanceDetraccion = (float) ($comprobante->avance_detraccion ?? 0);

            $pendiente = $esDetraccion
                ? round((float) $comprobante->monto_detraccion - $avanceDetraccion, 2)
                : round((float) $comprobante->neto_a_pagar - $avanceNeto, 2);
            $pendiente = max(0.0, $pendiente);

            if (round($montoPagado - $pendiente, 2) > 0.01) {
                throw new \RuntimeException(
                    'El monto a pagar supera el saldo pendiente del comprobante (S/ '
                    . number_format($pendiente, 2) . ')'
                );
            }

            $idPago = DB::table(self::TABLA_PAGO[$tipo])->insertGetId([
                self::COLUMNA_FK_PAGO[$tipo] => $idComprobante,
                'id_cuenta_bancaria_empresa' => isset($d['id_cuenta_bancaria_empresa'])
                    && (int) $d['id_cuenta_bancaria_empresa'] > 0
                        ? (int) $d['id_cuenta_bancaria_empresa']
                        : null,
                'id_empleado_registro' => (int) $d['id_empleado_registro'],
                'medio_pago' => (string) $d['medio_pago'],
                'numero_operacion' => isset($d['numero_operacion']) && $d['numero_operacion'] !== ''
                    ? (string) $d['numero_operacion']
                    : null,
                'fecha_hora_pago' => (string) $d['fecha_hora_pago'],
                'es_para_detraccion' => $esDetraccion ? 1 : 0,
                'observacion' => isset($d['observacion']) && $d['observacion'] !== ''
                    ? (string) $d['observacion']
                    : null,
                'evidencias' => VentaMineralData::encode_json($d['evidencias'] ?? null),
                'monto_pagado' => $montoPagado,
                'created_at' => now()->toDateTimeString(),
            ]);

            if ($esDetraccion) {
                $avanceDetraccion = round($avanceDetraccion + $montoPagado, 2);
            } else {
                $avanceNeto = round($avanceNeto + $montoPagado, 2);
            }

            $estado = EstadoComprobanteDespacho::resolver(
                netoAPagar: (float) $comprobante->neto_a_pagar,
                montoDetraccion: (float) $comprobante->monto_detraccion,
                netoPagado: $avanceNeto,
                detraccionPagada: $avanceDetraccion,
            );

            DB::table($tabla)
                ->where('id', $idComprobante)
                ->update([
                    'avance_neto' => $avanceNeto,
                    'avance_detraccion' => $avanceDetraccion,
                    'estado' => $estado->value,
                ]);

            return $idPago;
        });
    }

    /**
     * Cantidad de pagos registrados sobre un comprobante.
     */
    public static function contar_pagos(string $tipo, int $id_comprobante): int
    {
        if (!self::tipo_valido($tipo)) {
            return 0;
        }

        return (int) DB::table(self::TABLA_PAGO[$tipo])
            ->where(self::COLUMNA_FK_PAGO[$tipo], $id_comprobante)
            ->count();
    }
}
