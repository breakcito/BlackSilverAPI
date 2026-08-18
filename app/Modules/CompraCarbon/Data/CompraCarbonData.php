<?php

namespace App\Modules\CompraCarbon\Data;

use App\Shared\Enums\CompraCarbon\EstadoCompraCarbon;
use Illuminate\Support\Facades\DB;

class CompraCarbonData
{
    /**
     * Lista cabeceras de compra de carbon con JOIN a proveedores,
     * empresas, empleados (registro y aprobacion) y conteo de detalles.
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
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_aprueba,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_aprueba,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_compra,
                cc.fecha_hora_aprobacion,
                cc.evidencias_aprobacion,
                cc.total,
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

        // Filtro por periodo (mes + año) sobre fecha_hora_compra.
        $mes = isset($opts['mes']) ? (int) $opts['mes'] : 0;
        $anio = isset($opts['anio']) ? (int) $opts['anio'] : 0;
        if ($mes > 0 && $anio > 0) {
            $sql .= ' AND MONTH(cc.fecha_hora_compra) = :mes AND YEAR(cc.fecha_hora_compra) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($anio > 0) {
            $sql .= ' AND YEAR(cc.fecha_hora_compra) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= ' ORDER BY cc.id DESC';

        $rows = DB::select($sql, $params);

        // Decodificar evidencias_aprobacion (JSON) para cada fila.
        foreach ($rows as $row) {
            $row->evidencias_aprobacion = self::decode_evidencias($row->evidencias_aprobacion ?? null);
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
                cc.id_empleado_registro,
                CONCAT(er.nombre, " ", er.apellido) AS empleado_registro,
                cc.id_empleado_aprueba,
                CONCAT(ea.nombre, " ", ea.apellido) AS empleado_aprueba,
                cc.porcentaje_igv,
                cc.correlativo,
                cc.numero_correlativo,
                cc.fecha_hora_compra,
                cc.fecha_hora_aprobacion,
                cc.total,
                cc.created_at,
                cc.estado
            FROM compra_carbon cc
            INNER JOIN empresa e ON e.id = cc.id_empresa
            INNER JOIN proveedor p ON p.id = cc.id_proveedor
            INNER JOIN empleado er ON er.id = cc.id_empleado_registro
            LEFT JOIN empleado ea ON ea.id = cc.id_empleado_aprueba
            WHERE cc.id = :id
            LIMIT 1
        ';
        $cabecera = DB::selectOne($sqlCabecera, ['id' => $id_compra_carbon]);
        if ($cabecera !== null) {
            $cabecera->evidencias_aprobacion = self::decode_evidencias(
                $cabecera->evidencias_aprobacion ?? null,
            );
        }

        $sqlDetalles = '
            SELECT
                d.id AS id_detalle_compra_carbon,
                d.id_tipo_carbon,
                t.nombre AS tipo_carbon_nombre,
                t.codigo AS tipo_carbon_codigo,
                d.cantidad,
                d.precio_unitario,
                d.subtotal
            FROM detalle_compra_carbon d
            INNER JOIN tipo_carbon t ON t.id = d.id_tipo_carbon
            WHERE d.id_compra_carbon = :id
            ORDER BY d.id ASC
        ';
        $detalles = DB::select($sqlDetalles, ['id' => $id_compra_carbon]);

        return [
            'cabecera' => $cabecera,
            'detalles' => $detalles,
        ];
    }

    /**
     * Inserta la cabecera y devuelve el id generado.
     * @param array $cabecera claves: id_empresa, id_proveedor, id_empleado_registro,
     *                                porcentaje_igv, correlativo, numero_correlativo,
     *                                fecha_hora_compra, total, created_at.
     */
    public static function insert_cabecera(array $cabecera): int
    {
        return DB::table('compra_carbon')->insertGetId([
            'id_empresa'           => (int) $cabecera['id_empresa'],
            'id_proveedor'         => (int) $cabecera['id_proveedor'],
            'id_empleado_registro' => (int) $cabecera['id_empleado_registro'],
            'porcentaje_igv'       => (float) $cabecera['porcentaje_igv'],
            'correlativo'          => (string) $cabecera['correlativo'],
            'numero_correlativo'   => (int) $cabecera['numero_correlativo'],
            'fecha_hora_compra'    => (string) $cabecera['fecha_hora_compra'],
            'total'                => (float) $cabecera['total'],
            'estado'               => (string) ($cabecera['estado'] ?? 'Pendiente'),
            'created_at'           => (string) $cabecera['created_at'],
        ]);
    }

    /**
     * Inserta N lineas de detalle para una compra.
     * @param array<int, array{id_tipo_carbon:int, cantidad:float, precio_unitario:float, subtotal:float}> $detalles
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
                'id_tipo_carbon'   => (int) $d['id_tipo_carbon'],
                'cantidad'         => (float) $d['cantidad'],
                'precio_unitario'  => (float) $d['precio_unitario'],
                'subtotal'         => (float) $d['subtotal'],
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
     * Guarda (reemplaza) las evidencias de aprobacion en formato JSON.
     * @param array<int, array{url:string, path_relativo:string, nombre_original:?string, extension:?string}> $evidencias
     */
    public static function set_evidencias(int $id_compra_carbon, array $evidencias): int
    {
        return DB::table('compra_carbon')
            ->where('id', $id_compra_carbon)
            ->update([
                'evidencias_aprobacion' => json_encode($evidencias, JSON_UNESCAPED_UNICODE),
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
     * Decodifica el JSON de evidencias_aprobacion (puede venir null, string JSON o array ya decodificado).
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
