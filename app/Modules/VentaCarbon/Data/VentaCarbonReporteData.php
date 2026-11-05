<?php

namespace App\Modules\VentaCarbon\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VentaCarbonReporteData
{
    /** Listado de columnas de `carga_venta_carbon`, resuelto una sola vez. */
    private static ?array $columnasExistentes = null;

    /**
     * Expresiones SQL de las columnas de `carga_venta_carbon` que pueden no
     * existir todavia en el esquema.
     *
     * Mismo criterio que `VentaCarbonData`: si la columna no esta, se
     * devuelve un literal neutro en vez de romper la consulta.
     *
     * @return array<string, string>
     */
    private static function columnas_carga(): array
    {
        if (self::$columnasExistentes !== null) {
            $existentes = array_flip(self::$columnasExistentes);
        } else {
            $existentes = array_flip(Schema::getColumnListing('carga_venta_carbon'));
            self::$columnasExistentes = array_keys($existentes);
        }

        $resueltas = [];
        foreach ([
            'id_liquidacion_venta_carbon' => 'NULL AS id_liquidacion_venta_carbon',
            'precio_materia_prima' => '0 AS precio_materia_prima',
            'monto_igv' => '0 AS monto_igv',
            'total' => '0 AS total',
            'neto_a_pagar' => '0 AS neto_a_pagar',
        ] as $columna => $alternativa) {
            $resueltas[$columna] = isset($existentes[$columna])
                ? 'cv.' . $columna
                : $alternativa;
        }

        return $resueltas;
    }

    /**
     * Cargas de carbon que ingresaron en un anio, con todo lo necesario para
     * el "Registro de Ventas".
     *
     * El corte es por `YEAR(c.fecha_hora_ingreso)`: el reporte lista las
     * cargas que YA INGRESARON al almacen de destino, no las que siguen en
     * camino (esas todavia no tienen fecha de ingreso).
     *
     * Cada fila trae:
     *  - la carga con sus datos de ingreso, analisis e importes,
     *  - el cliente DESTINO (a donde llega el carbon) y su almacen,
     *  - la liquidacion de la carga (orden de compra y numero de reporte),
     *  - el comprobante de esa liquidacion (factura),
     *  - el PRIMER pago que NO es para detraccion (con su cuenta y banco).
     *
     * @return array<int, object>
     */
    public static function get_cargas_control_ventas(int $anio): array
    {
        $cols = self::columnas_carga();

        // El enlace carga -> liquidacion puede no existir todavia: sin el, la
        // carga sale sin orden de compra, sin reporte y sin factura.
        $colLiquidacion = $cols['id_liquidacion_venta_carbon'];
        $tieneLiquidacion = $colLiquidacion !== 'NULL AS id_liquidacion_venta_carbon'
            && Schema::hasTable('liquidacion_venta_carbon');

        $selLiquidacion = $tieneLiquidacion
            ? 'lv.codigo_orden_compra, lv.numero_reporte'
            : 'NULL AS codigo_orden_compra, NULL AS numero_reporte';

        $joinLiquidacion = $tieneLiquidacion
            ? 'LEFT JOIN liquidacion_venta_carbon lv ON lv.id = ' . $colLiquidacion
            : '';

        $selComprobante = $tieneLiquidacion
            ? 'cmp.codigo_comprobante AS comprobante_codigo,
               cmp.fecha_emision AS comprobante_fecha_emision,
               cmp.estado AS comprobante_estado'
            : 'NULL AS comprobante_codigo,
               NULL AS comprobante_fecha_emision,
               NULL AS comprobante_estado';

        $joinComprobante = $tieneLiquidacion
            ? 'LEFT JOIN comprobante_venta_carbon cmp ON cmp.id_liquidacion_venta_carbon = lv.id'
            : '';

        // El PRIMER pago que NO es para detraccion: el del neto. Es el que
        // lleva la fecha, el numero de operacion y la cuenta de la empresa.
        //
        // Depende del comprobante: sin liquidacion no hay comprobante y, por
        // lo tanto, tampoco pagos que listar.
        $joinPago = $tieneLiquidacion
            ? 'LEFT JOIN pago_venta_carbon pg ON pg.id = (
                SELECT p.id
                FROM pago_venta_carbon p
                WHERE p.es_para_detraccion = 0
                  AND p.id_comprobante_venta_carbon = cmp.id
                ORDER BY p.fecha_hora_pago ASC, p.id ASC
                LIMIT 1
            )'
            : 'LEFT JOIN pago_venta_carbon pg ON pg.id = NULL';

        $sql = '
            SELECT
                cv.id AS id_carga_venta_carbon,
                cv.fecha_hora_ingreso,
                cv.codigo_ticket_balanza,
                cv.segunda_placa,
                cv.guia_remitente,
                cv.guia_transportista,
                cv.cantidad_toneladas,
                cv.porcentaje_ceniza,
                cv.porcentaje_humedad,
                cv.porcentaje_azufre,
                cv.porcentaje_pizarra,
                cv.analisis_granulometria,
                cv.precio_base,
                cv.descuento,
                cv.bono,
                ' . $cols['precio_materia_prima'] . ',
                cv.subtotal,
                ' . $cols['monto_igv'] . ',
                ' . $cols['total'] . ',
                ' . $cols['neto_a_pagar'] . ',
                cv.observacion,
                cv.estado,

                tc.nombre AS tipo_carbon_nombre,
                tc.codigo AS tipo_carbon_codigo,

                veh.placa AS placa,
                acc.direccion AS almacen_cliente_llegada,

                cd.razon_social AS cliente_destino,
                cd.ruc AS cliente_destino_ruc,
                cd.dni AS cliente_destino_dni,

                vc.porcentaje_igv,
                vc.porcentaje_detraccion,
                vc.id_empresa,

                ' . $selLiquidacion . ',

                ' . $selComprobante . ',

                pg.fecha_hora_pago AS pago_fecha_hora,
                pg.numero_operacion AS pago_numero_operacion,
                pg.medio_pago AS pago_medio,
                cbe.numero_cuenta AS pago_numero_cuenta,
                b.nombre AS pago_banco
            FROM carga_venta_carbon cv
            INNER JOIN venta_carbon vc ON vc.id = cv.id_venta_carbon
            LEFT JOIN cliente cd ON cd.id = vc.id_cliente_destino
            LEFT JOIN tipo_carbon tc ON tc.id = cv.id_tipo_carbon
            LEFT JOIN vehiculo_carbon veh ON veh.id = cv.id_vehiculo_carbon
            LEFT JOIN almacen_carbon_cliente acc ON acc.id = vc.id_almacen_cliente_llegada
            ' . $joinLiquidacion . '
            ' . $joinComprobante . '
            ' . $joinPago . '
            LEFT JOIN cuenta_bancaria_empresa cbe ON cbe.id = pg.id_cuenta_bancaria_empresa
            LEFT JOIN banco b ON b.id = cbe.id_banco
            WHERE YEAR(cv.fecha_hora_ingreso) = :anio
            ORDER BY cv.fecha_hora_ingreso ASC, cv.id ASC
        ';

        $filas = DB::select($sql, ['anio' => $anio]);

        foreach ($filas as $fila) {
            $fila->analisis_granulometria = self::decodificar_json(
                $fila->analisis_granulometria ?? null
            );
        }

        return $filas;
    }

    /**
     * Decodifica un JSON almacenado en la base. Si no es valido, devuelve null.
     */
    private static function decodificar_json(mixed $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_array($valor)) {
            return $valor;
        }

        $decodificado = json_decode((string) $valor, true);

        return is_array($decodificado) ? $decodificado : null;
    }
}
