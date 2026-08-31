<?php

namespace App\Modules\CompraCarbon\Data;

use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use Illuminate\Support\Facades\DB;

class CompraCarbonData
{
    /**
     * Lista cabeceras de compra de carbon con JOIN a proveedores,
     * empresas, empleados (registro y aprobacion), almacen y conteo de detalles.
     * @param array{filtros?: string, id_empresa?: int, id_proveedor?: int} $opts
     * @return array<object>
     */
    public static function get_compras(array $opts = []): array
    {
        $sql = '
            SELECT
                cc.id AS id_compra_carbon,
                cc.id_empresa,
                e.razon_social AS empresa,
                cc.id_proveedor,
                p.razon_social AS proveedor,
                p.tipo_entidad AS proveedor_tipo_entidad,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                cc.id_almacen,
                alm.nombre AS almacen,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_aprueba,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_aprueba,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_ingreso,
                cc.fecha_hora_aprobacion,
                cc.evidencias,
                cc.total_antes_descuento,
                cc.monto_igv,
                cc.descuento_flete,
                cc.total_con_descuento,
                cc.estado_pago,
                cc.created_at,
                cc.estado,
                (
                    SELECT COUNT(*)
                    FROM detalle_compra_carbon d
                    WHERE d.id_compra_carbon = cc.id
                ) AS cantidad_items
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = cc.id_empleado_aprueba
            LEFT JOIN almacen alm ON alm.id = cc.id_almacen
            WHERE 1 = 1
        ';

        $params = [];

        if (!empty($opts['id_empresa'])) {
            $sql .= ' AND cc.id_empresa = :id_empresa';
            $params['id_empresa'] = (int) $opts['id_empresa'];
        }
        if (!empty($opts['id_proveedor'])) {
            $sql .= ' AND cc.id_proveedor = :id_proveedor';
            $params['id_proveedor'] = (int) $opts['id_proveedor'];
        }

        $filtros = trim((string) ($opts['filtros'] ?? ''));
        if ($filtros !== '') {
            $sql .= ' AND (cc.correlativo LIKE :q OR p.razon_social LIKE :q OR e.razon_social LIKE :q)';
            $params['q'] = '%' . $filtros . '%';
        }

        // Filtro por periodo (mes + año) sobre fecha_hora_ingreso.
        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(cc.fecha_hora_ingreso) = :mes AND YEAR(cc.fecha_hora_ingreso) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(cc.fecha_hora_ingreso) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY cc.id DESC';

        $rows = DB::select($sql, $params);

        // Decodificar evidencias (JSON) para cada fila.
        foreach ($rows as $row) {
            $row->evidencias = self::decode_evidencias($row->evidencias ?? null);
        }

        return $rows;
    }

    /**
     * Trae la cabecera + detalles (items) de una compra por id.
     * @return array{cabecera: object|null, detalles: array<object>}
     */
    public static function get_compra_con_detalles(int $id_compra_carbon): array
    {
        $sqlCabecera = '
            SELECT
                cc.id AS id_compra_carbon,
                cc.id_empresa,
                e.razon_social AS empresa,
                cc.id_proveedor,
                p.razon_social AS proveedor,
                p.tipo_entidad AS proveedor_tipo_entidad,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                cc.id_almacen,
                alm.nombre AS almacen,
                alm.id_departamento AS almacen_id_departamento,
                alm.id_provincia AS almacen_id_provincia,
                alm.id_distrito AS almacen_id_distrito,
                alm.direccion AS almacen_direccion,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_aprueba,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_aprueba,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_ingreso,
                cc.fecha_hora_aprobacion,
                cc.evidencias,
                cc.total_antes_descuento,
                cc.monto_igv,
                cc.descuento_flete,
                cc.total_con_descuento,
                cc.estado_pago,
                cc.created_at,
                cc.estado
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = cc.id_empleado_aprueba
            LEFT JOIN almacen alm ON alm.id = cc.id_almacen
            WHERE cc.id = :id
            LIMIT 1
        ';
        $cabecera = DB::selectOne($sqlCabecera, ['id' => $id_compra_carbon]);
        if ($cabecera !== null) {
            $cabecera->evidencias = self::decode_evidencias(
                $cabecera->evidencias ?? null,
            );
        }

        $sqlDetalles = '
            SELECT
                d.id AS id_detalle_compra_carbon,
                d.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                t.codigo AS tipo_carbon_codigo,
                d.id_transportista,
                tr.razon_social AS transportista_razon_social,
                tr.tipo_entidad AS transportista_tipo_entidad,
                d.id_lugar_extraccion,
                le.id_departamento AS lugar_id_departamento,
                dpto.nombre AS lugar_departamento,
                le.id_provincia AS lugar_id_provincia,
                prov.nombre AS lugar_provincia,
                le.id_distrito AS lugar_id_distrito,
                dist.nombre AS lugar_distrito,
                le.direccion AS lugar_direccion,
                d.id_tarifa_carbon,
                tc.inicio_porcentaje_ceniza AS tarifa_inicio_ceniza,
                tc.fin_porcentaje_ceniza AS tarifa_fin_ceniza,
                tc.precio_unitario AS tarifa_precio_unitario,
                d.placa,
                d.guia_remitente,
                d.guia_transportista,
                d.pagar_flete,
                d.codigo_ticket_balanza,
                d.cantidad,
                d.porcentaje_ceniza,
                d.porcentaje_humedad,
                d.precio_unitario,
                d.costo_flete_por_tonelada,
                d.subtotal_antes_descuento,
                d.descuento_flete,
                d.subtotal_con_descuento,
                d.evidencias
            FROM detalle_compra_carbon d
            INNER JOIN tipo_carbon t ON t.id = d.id_tipo_carbon
            LEFT JOIN transportista tr ON tr.id = d.id_transportista
            LEFT JOIN lugar_extraccion_carbon le ON le.id = d.id_lugar_extraccion
            LEFT JOIN departamento dpto ON dpto.id = le.id_departamento
            LEFT JOIN provincia prov ON prov.id = le.id_provincia
            LEFT JOIN distrito dist ON dist.id = le.id_distrito
            LEFT JOIN tarifa_carbon tc ON tc.id = d.id_tarifa_carbon
            WHERE d.id_compra_carbon = :id
            ORDER BY d.id ASC
        ';
        $detalles = DB::select($sqlDetalles, ['id' => $id_compra_carbon]);

        foreach ($detalles as $row) {
            $row->evidencias = self::decode_evidencias($row->evidencias ?? null);
        }

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles,
        ];
    }

    /**
     * Inserta la cabecera y devuelve el id generado.
     * @param array $cabecera claves: id_empresa, id_proveedor, id_almacen,
     *                                id_empleado_registro, aplica_igv, porcentaje_igv,
     *                                correlativo, numero_correlativo,
     *                                fecha_hora_ingreso, total_antes_descuento,
     *                                monto_igv, descuento_flete, total_con_descuento,
     *                                estado_pago, estado, created_at.
     */
    public static function insert_cabecera(array $cabecera): int
    {
        return DB::table('compra_carbon')->insertGetId([
            'id_empresa' => (int) $cabecera['id_empresa'],
            'id_proveedor' => (int) $cabecera['id_proveedor'],
            'id_almacen' => isset($cabecera['id_almacen']) ? (int) $cabecera['id_almacen'] : null,
            'id_empleado_registro' => (int) $cabecera['id_empleado_registro'],
            'aplica_igv' => !empty($cabecera['aplica_igv']) ? 1 : 0,
            'porcentaje_igv' => (float) ($cabecera['porcentaje_igv'] ?? 0),
            'correlativo' => (string) $cabecera['correlativo'],
            'numero_correlativo' => (int) $cabecera['numero_correlativo'],
            'fecha_hora_ingreso' => (string) $cabecera['fecha_hora_ingreso'],
            'total_antes_descuento' => (float) ($cabecera['total_antes_descuento'] ?? 0),
            'monto_igv' => (float) ($cabecera['monto_igv'] ?? 0),
            'descuento_flete' => (float) ($cabecera['descuento_flete'] ?? 0),
            'total_con_descuento' => (float) ($cabecera['total_con_descuento'] ?? 0),
            'estado_pago' => isset($cabecera['estado_pago']) ? (string) $cabecera['estado_pago'] : null,
            'estado' => (string) ($cabecera['estado'] ?? EstadoCompraCarbon::Pendiente->value),
            'evidencias' => $cabecera['evidencias'] ?? null,
            'created_at' => (string) $cabecera['created_at'],
        ]);
    }

    /**
     * Inserta N lineas de detalle para una compra.
     * @param array<int, array<string, mixed>> $detalles claves esperadas:
     *   id_tipo_carbon, id_transportista?, id_lugar_extraccion?, id_tarifa_carbon?,
     *   placa?, guia_remitente?, guia_transportista?, pagar_flete, codigo_ticket_balanza?,
     *   cantidad, porcentaje_ceniza, porcentaje_humedad, precio_unitario,
     *   costo_flete_por_tonelada, subtotal_antes_descuento, descuento_flete,
     *   subtotal_con_descuento, evidencias?
     */
    public static function insert_detalles(int $id_compra_carbon, array $detalles): void
    {
        if (empty($detalles)) {
            return;
        }
        $filas = [];
        foreach ($detalles as $d) {
            $filas[] = [
                'id_compra_carbon' => $id_compra_carbon,
                'id_tipo_carbon' => (int) $d['id_tipo_carbon'],
                'id_transportista' => isset($d['id_transportista']) && $d['id_transportista'] !== null
                    ? (int) $d['id_transportista']
                    : null,
                'id_lugar_extraccion' => isset($d['id_lugar_extraccion']) && $d['id_lugar_extraccion'] !== null
                    ? (int) $d['id_lugar_extraccion']
                    : null,
                'id_tarifa_carbon' => isset($d['id_tarifa_carbon']) && $d['id_tarifa_carbon'] !== null
                    ? (int) $d['id_tarifa_carbon']
                    : null,
                'placa' => isset($d['placa']) ? (string) $d['placa'] : '',
                'guia_remitente' => isset($d['guia_remitente']) ? (string) $d['guia_remitente'] : '',
                'guia_transportista' => isset($d['guia_transportista']) ? (string) $d['guia_transportista'] : null,
                'pagar_flete' => !empty($d['pagar_flete']) ? 1 : 0,
                'codigo_ticket_balanza' => isset($d['codigo_ticket_balanza']) ? (string) $d['codigo_ticket_balanza'] : '',
                'cantidad' => (float) $d['cantidad'],
                'porcentaje_ceniza' => (float) ($d['porcentaje_ceniza'] ?? 0),
                'porcentaje_humedad' => (float) ($d['porcentaje_humedad'] ?? 0),
                'precio_unitario' => (float) $d['precio_unitario'],
                'costo_flete_por_tonelada' => (float) ($d['costo_flete_por_tonelada'] ?? 0),
                'subtotal_antes_descuento' => (float) $d['subtotal_antes_descuento'],
                'descuento_flete' => (float) $d['descuento_flete'],
                'subtotal_con_descuento' => (float) $d['subtotal_con_descuento'],
                'evidencias' => isset($d['evidencias']) && $d['evidencias'] !== null
                    ? json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE)
                    : null,
            ];
        }
        DB::table('detalle_compra_carbon')->insert($filas);
    }

    /**
     * Marca la compra como aprobada.
     */
    public static function aprobar(
        int $id_compra_carbon,
        int $id_empleado_aprueba,
        string $fecha_hora_aprobacion,
    ): int {
        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'id_empleado_aprueba' => $id_empleado_aprueba,
                'fecha_hora_aprobacion' => $fecha_hora_aprobacion,
                'estado' => EstadoCompraCarbon::Aprobado->value,
            ]);
    }

    /**
     * Guarda (reemplaza) las evidencias de la compra en formato JSON.
     * @param array<int, array{url:string, path_relativo:string, nombre_original:?string, extension:?string}> $evidencias
     */
    public static function set_evidencias(int $id_compra_carbon, array $evidencias): int
    {
        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'evidencias' => json_encode($evidencias, JSON_UNESCAPED_UNICODE),
            ]);
    }

    /**
     * Cambia el estado de la compra a Anulado.
     */
    public static function anular(int $id_compra_carbon): int
    {
        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'estado' => EstadoCompraCarbon::Anulado->value,
            ]);
    }

    /**
     * Decodifica el JSON de evidencias (puede venir null, string JSON o array ya decodificado).
     */
    private static function decode_evidencias(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
