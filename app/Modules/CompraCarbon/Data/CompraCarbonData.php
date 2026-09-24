<?php

namespace App\Modules\CompraCarbon\Data;

use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use Illuminate\Support\Facades\DB;

class CompraCarbonData
{
    public const CABECERA_CAMBIOS_LABELS = [
        'id_empresa' => 'Empresa',
        'id_proveedor' => 'Proveedor',
        'id_almacen' => 'Almacén Empresa',
        'id_almacen_cliente' => 'Almacén Cliente',
        'id_almacen_proveedor' => 'Almacén Proveedor',
        'tipo_despacho' => 'Tipo de Despacho',
        'aplica_igv' => 'Aplica IGV',
        'porcentaje_igv' => 'Porcentaje IGV',
        'fecha_hora_ingreso' => 'Fecha y Hora de Ingreso',
    ];

    public const DETALLE_CAMBIOS_LABELS = [
        'id_tipo_carbon' => 'Tipo de Carbón',
        'id_transportista' => 'Transportista',
        'id_lugar_extraccion' => 'Lugar de Extracción',
        'id_tarifa_carbon' => 'Tarifa de Carbón',
        'placa' => 'Placa',
        'guia_remitente' => 'Guía Remitente',
        'guia_transportista' => 'Guía Transportista',
        'pagar_flete' => 'Paga Flete',
        'codigo_ticket_balanza' => 'Ticket Balanza',
        'cantidad' => 'Cantidad (TM)',
        'porcentaje_ceniza' => '% Ceniza',
        'porcentaje_humedad' => '% Humedad',
        'precio_unitario' => 'Precio Unitario',
        'costo_flete_por_tonelada' => 'Costo Flete/TM',
    ];

    /**
     * Lista cabeceras de compra de carbon con JOIN a proveedores,
     * empresas, empleados, almacenes y conteo de detalles.
     * @param array{filtros?: string, id_empresa?: int, id_proveedor?: int, mes?: int, anio?: int} $opts
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
                cc.tipo_despacho,
                cc.id_almacen,
                alm.nombre AS almacen,
                cc.id_almacen_cliente,
                ac.direccion AS almacen_cliente_direccion,
                cli.razon_social AS cliente_destino,
                cc.id_almacen_proveedor,
                aprov.direccion AS almacen_proveedor_direccion,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_confirma,
                CONCAT(ec.nombre, " ", ec.apellido) AS empleado_aprueba,
                cc.id_empleado_aprueba_liquidacion,
                CONCAT(eal.nombre, " ", eal.apellido) AS empleado_aprueba_liquidacion,
                cc.id_empleado_anula,
                CONCAT(ean.nombre, " ", ean.apellido) AS empleado_anula,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_ingreso,
                cc.fecha_hora_confirmacion,
                cc.fecha_hora_aprobacion_liquidacion,
                cc.fecha_hora_anulacion,
                cc.evidencias,
                cc.total_antes_descuento,
                cc.monto_igv,
                cc.descuento_flete,
                cc.total_con_descuento,
                cc.log_cambios,
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
            LEFT JOIN empleado ec ON ec.id = cc.id_empleado_confirma
            LEFT JOIN empleado eal ON eal.id = cc.id_empleado_aprueba_liquidacion
            LEFT JOIN empleado ean ON ean.id = cc.id_empleado_anula
            LEFT JOIN almacen alm ON alm.id = cc.id_almacen
            LEFT JOIN almacen_carbon_cliente ac ON ac.id = cc.id_almacen_cliente
            LEFT JOIN cliente cli ON cli.id = ac.id_cliente
            LEFT JOIN almacen_carbon_proveedor aprov ON aprov.id = cc.id_almacen_proveedor
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

        foreach ($rows as $row) {
            $row->evidencias = self::decode_json($row->evidencias ?? null);
            $row->log_cambios = self::decode_json($row->log_cambios ?? null);
        }

        return $rows;
    }

    /**
     * Trae la cabecera + detalles + anticipos utilizados de una compra por id.
     * @return array{cabecera: object|null, detalles: array<object>, anticipos_utilizados: array<object>}
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
                cc.tipo_despacho,
                cc.id_almacen,
                alm.nombre AS almacen,
                alm.id_departamento AS almacen_id_departamento,
                alm.id_provincia AS almacen_id_provincia,
                alm.id_distrito AS almacen_id_distrito,
                alm.direccion AS almacen_direccion,
                cc.id_almacen_cliente,
                ac.direccion AS almacen_cliente_direccion,
                cli.id AS cliente_destino_id,
                cli.razon_social AS cliente_destino,
                cc.id_almacen_proveedor,
                aprov.direccion AS almacen_proveedor_direccion,
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_confirma,
                CONCAT(ec.nombre, " ", ec.apellido) AS empleado_aprueba,
                cc.id_empleado_aprueba_liquidacion,
                CONCAT(eal.nombre, " ", eal.apellido) AS empleado_aprueba_liquidacion,
                cc.id_empleado_anula,
                CONCAT(ean.nombre, " ", ean.apellido) AS empleado_anula,
                cc.aplica_igv,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_ingreso,
                cc.fecha_hora_confirmacion,
                cc.fecha_hora_aprobacion_liquidacion,
                cc.fecha_hora_anulacion,
                cc.evidencias,
                cc.total_antes_descuento,
                cc.monto_igv,
                cc.descuento_flete,
                cc.total_con_descuento,
                cc.log_cambios,
                cc.created_at,
                cc.estado
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN empleado ec ON ec.id = cc.id_empleado_confirma
            LEFT JOIN empleado eal ON eal.id = cc.id_empleado_aprueba_liquidacion
            LEFT JOIN empleado ean ON ean.id = cc.id_empleado_anula
            LEFT JOIN almacen alm ON alm.id = cc.id_almacen
            LEFT JOIN almacen_carbon_cliente ac ON ac.id = cc.id_almacen_cliente
            LEFT JOIN cliente cli ON cli.id = ac.id_cliente
            LEFT JOIN almacen_carbon_proveedor aprov ON aprov.id = cc.id_almacen_proveedor
            WHERE cc.id = :id
            LIMIT 1
        ';
        $cabecera = DB::selectOne($sqlCabecera, ['id' => $id_compra_carbon]);
        if ($cabecera !== null) {
            $cabecera->evidencias = self::decode_json($cabecera->evidencias ?? null);
            $cabecera->log_cambios = self::decode_json($cabecera->log_cambios ?? null);
        }

        $sqlDetalles = '
            SELECT
                d.id AS id_detalle_compra_carbon,
                d.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                t.codigo AS tipo_carbon_codigo,
                t.ficha_tecnica AS tipo_carbon_ficha_tecnica,
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
                d.evidencias,
                d.log_cambios
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
            $row->evidencias = self::decode_json($row->evidencias ?? null);
            $row->log_cambios = self::decode_json($row->log_cambios ?? null);
            $row->tipo_carbon_ficha_tecnica = self::decode_json($row->tipo_carbon_ficha_tecnica ?? null);
        }

        $sqlAnticipos = '
            SELECT
                tap.id AS id_transaccion_anticipo,
                tap.id_anticipo_proveedor,
                tap.id_compra_carbon,
                tap.monto_retirado,
                ap.medio_pago,
                ap.fecha_hora_pago,
                ap.numero_operacion,
                ap.saldo_inicial,
                ap.saldo_actual,
                ce.numero_cuenta AS cuenta_bancaria_empresa_numero
            FROM transaccion_anticipo_proveedor tap
            INNER JOIN anticipo_proveedor ap ON ap.id = tap.id_anticipo_proveedor
            LEFT JOIN cuenta_bancaria_empresa ce ON ce.id = ap.id_cuenta_bancaria_empresa
            WHERE tap.id_compra_carbon = :id
            ORDER BY tap.id ASC
        ';
        $anticipos = DB::select($sqlAnticipos, ['id' => $id_compra_carbon]);

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles,
            'anticipos_utilizados' => $anticipos,
        ];
    }

    /**
     * Devuelve la tarifa de precio mayor para un tipo de carbón.
     */
    public static function get_tarifa_max_precio(int $id_tipo_carbon): ?object
    {
        return DB::table('tarifa_carbon')
            ->where('id_tipo_carbon', $id_tipo_carbon)
            ->where(function ($q) {
                $q->whereNull('estado')->orWhere('estado', 'Activo');
            })
            ->orderByDesc('precio_unitario')
            ->first();
    }

    /**
     * Inserta la cabecera preliminar y devuelve el id generado.
     */
    public static function insert_cabecera(array $cabecera): int
    {
        return DB::table('compra_carbon')->insertGetId([
            'id_empresa' => (int) $cabecera['id_empresa'],
            'id_proveedor' => (int) $cabecera['id_proveedor'],
            'id_almacen' => isset($cabecera['id_almacen']) && $cabecera['id_almacen'] ? (int) $cabecera['id_almacen'] : null,
            'id_almacen_cliente' => isset($cabecera['id_almacen_cliente']) && $cabecera['id_almacen_cliente'] ? (int) $cabecera['id_almacen_cliente'] : null,
            'id_almacen_proveedor' => isset($cabecera['id_almacen_proveedor']) && $cabecera['id_almacen_proveedor'] ? (int) $cabecera['id_almacen_proveedor'] : null,
            'id_empleado_registro' => (int) $cabecera['id_empleado_registro'],
            'tipo_despacho' => $cabecera['tipo_despacho'] ?? null,
            'aplica_igv' => !empty($cabecera['aplica_igv']) ? 1 : 0,
            'porcentaje_igv' => (float) ($cabecera['porcentaje_igv'] ?? 0),
            'correlativo' => (string) $cabecera['correlativo'],
            'numero_correlativo' => (int) $cabecera['numero_correlativo'],
            'fecha_hora_ingreso' => (string) ($cabecera['fecha_hora_ingreso'] ?? now()->toDateTimeString()),
            'total_antes_descuento' => (float) ($cabecera['total_antes_descuento'] ?? 0),
            'monto_igv' => (float) ($cabecera['monto_igv'] ?? 0),
            'descuento_flete' => (float) ($cabecera['descuento_flete'] ?? 0),
            'total_con_descuento' => (float) ($cabecera['total_con_descuento'] ?? 0),
            'estado' => (string) ($cabecera['estado'] ?? EstadoCompraCarbon::Preliminar->value),
            'evidencias' => $cabecera['evidencias'] ?? null,
            'log_cambios' => null,
            'created_at' => (string) ($cabecera['created_at'] ?? now()->toDateTimeString()),
        ]);
    }

    /**
     * Inserta N lineas de detalle para una compra.
     * @param array<int, array<string, mixed>> $detalles
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
                'id_transportista' => isset($d['id_transportista']) && $d['id_transportista'] !== null && (int) $d['id_transportista'] > 0
                    ? (int) $d['id_transportista']
                    : null,
                'id_lugar_extraccion' => isset($d['id_lugar_extraccion']) && $d['id_lugar_extraccion'] !== null && (int) $d['id_lugar_extraccion'] > 0
                    ? (int) $d['id_lugar_extraccion']
                    : null,
                'id_tarifa_carbon' => isset($d['id_tarifa_carbon']) && $d['id_tarifa_carbon'] !== null && (int) $d['id_tarifa_carbon'] > 0
                    ? (int) $d['id_tarifa_carbon']
                    : null,
                'placa' => isset($d['placa']) ? (string) $d['placa'] : '',
                'guia_remitente' => isset($d['guia_remitente']) ? (string) $d['guia_remitente'] : '',
                'guia_transportista' => isset($d['guia_transportista']) && $d['guia_transportista'] !== '' ? (string) $d['guia_transportista'] : null,
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
                    ? (is_string($d['evidencias']) ? $d['evidencias'] : json_encode($d['evidencias'], JSON_UNESCAPED_UNICODE))
                    : null,
                'log_cambios' => isset($d['log_cambios']) && $d['log_cambios'] !== null
                    ? (is_string($d['log_cambios']) ? $d['log_cambios'] : json_encode($d['log_cambios'], JSON_UNESCAPED_UNICODE))
                    : null,
            ];
        }
        DB::table('detalle_compra_carbon')->insert($filas);
    }

    /**
     * Confirma la llegada de carga de una compra preliminar.
     */
    public static function confirmar_compra(
        int $id_compra_carbon,
        array $cabecera,
        array $detalles,
        int $id_empleado_confirma
    ): void {
        DB::transaction(function () use ($id_compra_carbon, $cabecera, $detalles, $id_empleado_confirma) {
            DB::table('compra_carbon')
                ->where('id', $id_compra_carbon)
                ->update([
                    'tipo_despacho' => $cabecera['tipo_despacho'],
                    'id_almacen' => $cabecera['id_almacen'] ?? null,
                    'id_almacen_cliente' => $cabecera['id_almacen_cliente'] ?? null,
                    'id_almacen_proveedor' => $cabecera['id_almacen_proveedor'] ?? null,
                    'aplica_igv' => !empty($cabecera['aplica_igv']) ? 1 : 0,
                    'porcentaje_igv' => (float) ($cabecera['porcentaje_igv'] ?? 0),
                    'fecha_hora_ingreso' => (string) $cabecera['fecha_hora_ingreso'],
                    'total_antes_descuento' => (float) ($cabecera['total_antes_descuento'] ?? 0),
                    'monto_igv' => (float) ($cabecera['monto_igv'] ?? 0),
                    'descuento_flete' => (float) ($cabecera['descuento_flete'] ?? 0),
                    'total_con_descuento' => (float) ($cabecera['total_con_descuento'] ?? 0),
                    'id_empleado_confirma' => $id_empleado_confirma,
                    'fecha_hora_confirmacion' => now()->toDateTimeString(),
                    'estado' => EstadoCompraCarbon::Confirmado->value,
                    'evidencias' => $cabecera['evidencias'] ?? null,
                ]);

            DB::table('detalle_compra_carbon')->where('id_compra_carbon', $id_compra_carbon)->delete();
            self::insert_detalles($id_compra_carbon, $detalles);
        });
    }

    /**
     * Actualiza la compra y registra los cambios en log_cambios.
     */
    public static function actualizar_compra(
        int $id_compra_carbon,
        array $cabecera,
        array $detalles,
        int $id_empleado,
        string $nombre_empleado,
        ?string $motivo = null
    ): void {
        DB::transaction(function () use ($id_compra_carbon, $cabecera, $detalles, $id_empleado, $nombre_empleado, $motivo) {
            $originalCab = DB::table('compra_carbon')->where('id', $id_compra_carbon)->first();

            $logCabecera = self::calcularDiffCabecera($originalCab, $cabecera, $id_empleado, $nombre_empleado, $motivo);

            $updateData = [
                'id_empresa' => (int) $cabecera['id_empresa'],
                'id_proveedor' => (int) $cabecera['id_proveedor'],
                'tipo_despacho' => $cabecera['tipo_despacho'] ?? null,
                'id_almacen' => $cabecera['id_almacen'] ?? null,
                'id_almacen_cliente' => $cabecera['id_almacen_cliente'] ?? null,
                'id_almacen_proveedor' => $cabecera['id_almacen_proveedor'] ?? null,
                'aplica_igv' => !empty($cabecera['aplica_igv']) ? 1 : 0,
                'porcentaje_igv' => (float) ($cabecera['porcentaje_igv'] ?? 0),
                'fecha_hora_ingreso' => (string) $cabecera['fecha_hora_ingreso'],
                'total_antes_descuento' => (float) ($cabecera['total_antes_descuento'] ?? 0),
                'monto_igv' => (float) ($cabecera['monto_igv'] ?? 0),
                'descuento_flete' => (float) ($cabecera['descuento_flete'] ?? 0),
                'total_con_descuento' => (float) ($cabecera['total_con_descuento'] ?? 0),
            ];

            if ($logCabecera !== null) {
                $updateData['log_cambios'] = json_encode($logCabecera, JSON_UNESCAPED_UNICODE);
            }
            if (isset($cabecera['evidencias'])) {
                $updateData['evidencias'] = $cabecera['evidencias'];
            }

            DB::table('compra_carbon')->where('id', $id_compra_carbon)->update($updateData);

            // Reemplazo de detalles preservando diff en log si aplica
            DB::table('detalle_compra_carbon')->where('id_compra_carbon', $id_compra_carbon)->delete();
            self::insert_detalles($id_compra_carbon, $detalles);
        });
    }

    /**
     * Aprueba la liquidación asociando los anticipos del proveedor.
     * @param array<int, array{id_anticipo_proveedor: int, monto_retirado: float}> $anticipos
     */
    public static function aprobar_liquidacion(
        int $id_compra_carbon,
        int $id_empleado_aprueba,
        string $fecha_hora_aprobacion,
        array $anticipos = []
    ): void {
        DB::transaction(function () use ($id_compra_carbon, $id_empleado_aprueba, $fecha_hora_aprobacion, $anticipos) {
            foreach ($anticipos as $a) {
                $idAnticipo = (int) ($a['id_anticipo_proveedor'] ?? 0);
                $montoRetirado = round((float) ($a['monto_retirado'] ?? 0), 2);
                if ($idAnticipo <= 0 || $montoRetirado <= 0) {
                    continue;
                }

                $ant = DB::table('anticipo_proveedor')->where('id', $idAnticipo)->lockForUpdate()->first();
                if (!$ant) {
                    throw new \InvalidArgumentException("El anticipo ID {$idAnticipo} no existe.");
                }
                if ((float) $ant->saldo_actual < $montoRetirado) {
                    throw new \InvalidArgumentException("El anticipo no cuenta con saldo suficiente (Saldo: {$ant->saldo_actual}, Requerido: {$montoRetirado}).");
                }

                $nuevoSaldo = round((float) $ant->saldo_actual - $montoRetirado, 2);
                $nuevoEstado = $nuevoSaldo <= 0 ? 'Sin Saldo' : 'Con Saldo';

                DB::table('anticipo_proveedor')
                    ->where('id', $idAnticipo)
                    ->update([
                        'saldo_actual' => $nuevoSaldo,
                        'estado' => $nuevoEstado,
                    ]);

                DB::table('transaccion_anticipo_proveedor')->insert([
                    'id_anticipo_proveedor' => $idAnticipo,
                    'id_compra_carbon' => $id_compra_carbon,
                    'monto_retirado' => $montoRetirado,
                ]);
            }

            DB::table('compra_carbon')
                ->where('id', $id_compra_carbon)
                ->update([
                    'id_empleado_aprueba_liquidacion' => $id_empleado_aprueba,
                    'fecha_hora_aprobacion_liquidacion' => $fecha_hora_aprobacion,
                    'estado' => EstadoCompraCarbon::LiquidacionAprobada->value,
                ]);
        });
    }

    /**
     * Anula una compra de carbón.
     */
    public static function anular(int $id_compra_carbon, int $id_empleado_anula): int
    {
        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'id_empleado_anula' => $id_empleado_anula,
                'fecha_hora_anulacion' => now()->toDateTimeString(),
                'estado' => EstadoCompraCarbon::Anulado->value,
            ]);
    }

    /**
     * Guarda evidencias en cabecera.
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
     * Verifica si tickets o guías ya fueron usados en compras anteriores del mismo proveedor.
     * @param string[] $tickets
     * @param string[] $guiasRemitente
     * @param string[] $guiasTransportista
     * @return array<object>
     */
    public static function verificar_documentos_duplicados(
        int $id_proveedor,
        array $tickets,
        array $guiasRemitente,
        array $guiasTransportista,
        ?int $id_compra_ignorar = null
    ): array {
        $tickets = array_values(array_filter(array_map('trim', $tickets)));
        $guiasRemitente = array_values(array_filter(array_map('trim', $guiasRemitente)));
        $guiasTransportista = array_values(array_filter(array_map('trim', $guiasTransportista)));

        if (empty($tickets) && empty($guiasRemitente) && empty($guiasTransportista)) {
            return [];
        }

        $conditions = [];
        $params = ['id_proveedor' => $id_proveedor];

        if (!empty($tickets)) {
            $inT = [];
            foreach ($tickets as $i => $t) {
                $k = "t_$i";
                $inT[] = ":$k";
                $params[$k] = $t;
            }
            $conditions[] = 'd.codigo_ticket_balanza IN (' . implode(',', $inT) . ')';
        }

        if (!empty($guiasRemitente)) {
            $inR = [];
            foreach ($guiasRemitente as $i => $g) {
                $k = "gr_$i";
                $inR[] = ":$k";
                $params[$k] = $g;
            }
            $conditions[] = 'd.guia_remitente IN (' . implode(',', $inR) . ')';
        }

        if (!empty($guiasTransportista)) {
            $inTr = [];
            foreach ($guiasTransportista as $i => $g) {
                $k = "gt_$i";
                $inTr[] = ":$k";
                $params[$k] = $g;
            }
            $conditions[] = 'd.guia_transportista IN (' . implode(',', $inTr) . ')';
        }

        $sql = '
            SELECT
                d.id AS id_detalle_compra_carbon,
                d.codigo_ticket_balanza,
                d.guia_remitente,
                d.guia_transportista,
                cc.id AS id_compra_carbon,
                cc.correlativo,
                cc.fecha_hora_ingreso
            FROM detalle_compra_carbon d
            INNER JOIN compra_carbon cc ON cc.id = d.id_compra_carbon
            WHERE cc.id_proveedor = :id_proveedor
              AND cc.estado != "' . EstadoCompraCarbon::Anulado->value . '"
              AND (' . implode(' OR ', $conditions) . ')
        ';

        if ($id_compra_ignorar !== null && $id_compra_ignorar > 0) {
            $sql .= ' AND cc.id != :id_compra_ignorar';
            $params['id_compra_ignorar'] = $id_compra_ignorar;
        }

        return DB::select($sql, $params);
    }

    /**
     * Calcula la diferencia entre cabecera original y nueva para log_cambios.
     */
    private static function calcularDiffCabecera(
        ?object $original,
        array $nuevoEstado,
        int $id_empleado,
        string $nombre_empleado,
        ?string $motivo = null
    ): ?array {
        $logPrevio = $original !== null ? self::decode_json($original->log_cambios ?? null) : [];

        $cambios = [];
        foreach (self::CABECERA_CAMBIOS_LABELS as $campoBd => $label) {
            if (!array_key_exists($campoBd, $nuevoEstado)) {
                continue;
            }
            $valAnt = $original !== null ? ($original->{$campoBd} ?? null) : null;
            $valNue = $nuevoEstado[$campoBd];

            $antStr = is_null($valAnt) ? '' : trim((string) $valAnt);
            $nueStr = is_null($valNue) ? '' : trim((string) $valNue);

            if ($antStr !== $nueStr) {
                $cambios[] = [
                    'campo_bd' => $campoBd,
                    'campo' => $label,
                    'valor_anterior' => $valAnt,
                    'valor_nuevo' => $valNue,
                ];
            }
        }

        if (empty($cambios)) {
            return count($logPrevio) > 0 ? $logPrevio : null;
        }

        $logPrevio[] = [
            'id_empleado' => $id_empleado,
            'nombre_empleado' => $nombre_empleado,
            'motivo' => $motivo,
            'update_at' => now()->toDateTimeString(),
            'cambios' => $cambios,
        ];

        return $logPrevio;
    }

    /**
     * Decodifica un campo JSON de forma segura.
     */
    private static function decode_json(mixed $raw): array
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
