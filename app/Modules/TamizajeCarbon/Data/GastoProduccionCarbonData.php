<?php

namespace App\Modules\TamizajeCarbon\Data;

use App\Models\GastoProduccionCarbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GastoProduccionCarbonData
{
    /**
     * Expresiones SQL de las columnas de `gasto_produccion_carbon` que pueden
     * no existir todavia en el esquema.
     *
     * Mismo criterio que el resto del modulo: si la columna no esta, se
     * devuelve un literal neutro en vez de romper la consulta.
     *
     * @return array<string, string>
     */
    private static function columnas_gasto(): array
    {
        static $resueltas = null;

        if ($resueltas !== null) {
            return $resueltas;
        }

        $existentes = array_flip(Schema::getColumnListing('gasto_produccion_carbon'));

        $resueltas = [];
        foreach ([
            'esta_pagado' => '0 AS esta_pagado',
            'fecha_pago' => 'NULL AS fecha_pago',
        ] as $columna => $alternativa) {
            $resueltas[$columna] = isset($existentes[$columna])
                ? 'g.' . $columna
                : $alternativa;
        }

        return $resueltas;
    }

    /**
     * Lista los gastos de producción de un zarandeo o de un blending.
     *
     * Un gasto pertenece a UN destino: o a un zarandeo o a un blending. Por
     * eso el filtro se arma con el destino recibido, nunca con un nombre de
     * columna dinamico.
     *
     * @param ?int $id_gasto Si se pasa, devuelve solo ese gasto (con la misma
     *   forma que el listado) en lugar de la lista completa.
     * @return array<object>|object|null
     */
    public static function get_gastos(
        ?int $id_zarandeo_carbon = null,
        ?int $id_blending_carbon = null,
        ?int $id_gasto = null
    ) {
        $cols = self::columnas_gasto();

        $sql = '
            SELECT
                g.id AS id_gasto_produccion_carbon,
                g.id_zarandeo_carbon,
                g.id_blending_carbon,
                g.id_empleado_registro,
                CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
                g.id_proveedor,
                p.razon_social AS proveedor_razon_social,
                g.gasto,
                g.otro_gasto,
                g.descripcion,
                g.fecha,
                g.numero_reporte,
                g.hora_inicio,
                g.hora_fin,
                g.cantidad,
                g.precio_unitario,
                g.total,
                ' . $cols['esta_pagado'] . ',
                ' . $cols['fecha_pago'] . ',
                g.created_at
            FROM gasto_produccion_carbon g
            INNER JOIN empleado e ON e.id = g.id_empleado_registro
            LEFT JOIN proveedor p ON p.id = g.id_proveedor
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_zarandeo_carbon !== null) {
            $sql .= ' AND g.id_zarandeo_carbon = :id_zarandeo_carbon';
            $params['id_zarandeo_carbon'] = $id_zarandeo_carbon;
        }

        if ($id_blending_carbon !== null) {
            $sql .= ' AND g.id_blending_carbon = :id_blending_carbon';
            $params['id_blending_carbon'] = $id_blending_carbon;
        }

        if ($id_gasto !== null) {
            $sql .= ' AND g.id = :id_gasto';
            $params['id_gasto'] = $id_gasto;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY g.fecha ASC, g.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtiene un gasto de producción por su id, con la misma forma que el
     * listado (para que el front lo inserte en su lista sin volver a listar).
     */
    public static function get_gasto_by_id(
        ?int $id_zarandeo_carbon,
        ?int $id_blending_carbon,
        int $id_gasto
    ) {
        return self::get_gastos($id_zarandeo_carbon, $id_blending_carbon, $id_gasto);
    }

    /**
     * Verifica que el zarandeo exista.
     */
    public static function existe_zarandeo(int $id_zarandeo_carbon): bool
    {
        return DB::table('zarandeo_carbon')
            ->where('id', $id_zarandeo_carbon)
            ->exists();
    }

    /**
     * Verifica que el blending exista.
     */
    public static function existe_blending(int $id_blending_carbon): bool
    {
        return DB::table('blending_carbon')
            ->where('id', $id_blending_carbon)
            ->exists();
    }

    /**
     * Inserta un gasto de producción.
     *
     * El gasto apunta a un zarandeo O a un blending (solo uno de los dos).
     *
     * @param array<string, mixed> $d
     */
    public static function insert_gasto(array $d): int
    {
        return GastoProduccionCarbon::insertGetId([
            'id_empleado_registro' => (int) $d['id_empleado_registro'],
            'id_zarandeo_carbon' => isset($d['id_zarandeo_carbon']) && (int) $d['id_zarandeo_carbon'] > 0
                ? (int) $d['id_zarandeo_carbon']
                : null,
            'id_blending_carbon' => isset($d['id_blending_carbon']) && (int) $d['id_blending_carbon'] > 0
                ? (int) $d['id_blending_carbon']
                : null,
            'id_proveedor' => isset($d['id_proveedor']) && (int) $d['id_proveedor'] > 0
                ? (int) $d['id_proveedor']
                : null,
            'gasto' => (string) $d['gasto'],
            'otro_gasto' => isset($d['otro_gasto']) && $d['otro_gasto'] !== ''
                ? (string) $d['otro_gasto']
                : null,
            'descripcion' => isset($d['descripcion']) && $d['descripcion'] !== ''
                ? (string) $d['descripcion']
                : null,
            'fecha' => (string) $d['fecha'],
            'numero_reporte' => isset($d['numero_reporte']) && $d['numero_reporte'] !== ''
                ? (string) $d['numero_reporte']
                : null,
            'hora_inicio' => isset($d['hora_inicio']) && $d['hora_inicio'] !== null && $d['hora_inicio'] !== ''
                ? (string) $d['hora_inicio']
                : null,
            'hora_fin' => isset($d['hora_fin']) && $d['hora_fin'] !== null && $d['hora_fin'] !== ''
                ? (string) $d['hora_fin']
                : null,
            'cantidad' => (float) ($d['cantidad'] ?? 0),
            'precio_unitario' => (float) ($d['precio_unitario'] ?? 0),
            'total' => (float) ($d['total'] ?? 0),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Gastos de produccion listos para exportar.
     *
     * Ademas de los datos del gasto, trae lo que el reporte necesita y que
     * vive en otras tablas:
     *  - la FECHA INICIO del destino (zarandeo o blending),
     *  - el MES de esa fecha,
     *  - el REGISTRO al que pertenece (correlativo del blending o el id del
     *    zarandeo),
     *  - el RUC y la razon social del PROVEEDOR.
     *
     * El filtro puede ser por destino (un solo zarandeo o blending), por
     * tipo (todos los blendings o todos los zarandeos) o por rango de
     * fechas de la fecha inicio.
     *
     * @param ?string $tipo "blending" o "zarandeo": de que tipo se buscan
     *   los gastos. Null = de los dos.
     * @return array<object>
     */
    public static function get_gastos_exportar(
        ?int $id_zarandeo_carbon = null,
        ?int $id_blending_carbon = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null,
        ?string $tipo = null
    ) {
        $cols = self::columnas_gasto();

        $sql = '
            SELECT
                g.id AS id_gasto_produccion_carbon,
                CASE
                    WHEN g.id_blending_carbon IS NOT NULL THEN "blending"
                    ELSE "zarandeo"
                END AS origen,
                COALESCE(
                    b.correlativo,
                    CONCAT("Zarandeo #", COALESCE(tz.id, 0))
                ) AS registro,
                COALESCE(b.fecha_hora_blending, tz.fecha_hora_zarandeo) AS fecha_inicio,
                g.gasto,
                g.otro_gasto,
                g.descripcion,
                g.numero_reporte,
                g.fecha,
                p.ruc AS proveedor_ruc,
                p.dni AS proveedor_dni,
                p.razon_social AS proveedor_razon_social,
                g.cantidad,
                g.precio_unitario,
                g.total,
                ' . $cols['esta_pagado'] . ',
                ' . $cols['fecha_pago'] . '
            FROM gasto_produccion_carbon g
            LEFT JOIN blending_carbon b ON b.id = g.id_blending_carbon
            LEFT JOIN zarandeo_carbon tz ON tz.id = g.id_zarandeo_carbon
            LEFT JOIN proveedor p ON p.id = g.id_proveedor
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_zarandeo_carbon !== null) {
            $sql .= ' AND g.id_zarandeo_carbon = :id_zarandeo_carbon';
            $params['id_zarandeo_carbon'] = $id_zarandeo_carbon;
        }

        if ($id_blending_carbon !== null) {
            $sql .= ' AND g.id_blending_carbon = :id_blending_carbon';
            $params['id_blending_carbon'] = $id_blending_carbon;
        }

        // El export general de cada vista (zarandeo / blending) trae SOLO los
        // gastos de ese tipo.
        if ($tipo === 'blending') {
            $sql .= ' AND g.id_blending_carbon IS NOT NULL';
        }

        if ($tipo === 'zarandeo') {
            $sql .= ' AND g.id_zarandeo_carbon IS NOT NULL';
        }

        // El rango se aplica sobre la fecha inicio del destino (la que sale
        // en la primera columna del reporte).
        if ($fecha_inicio !== null) {
            $sql .= ' AND DATE(COALESCE(b.fecha_hora_blending, tz.fecha_hora_zarandeo)) >= :fecha_inicio';
            $params['fecha_inicio'] = $fecha_inicio;
        }

        if ($fecha_fin !== null) {
            $sql .= ' AND DATE(COALESCE(b.fecha_hora_blending, tz.fecha_hora_zarandeo)) <= :fecha_fin';
            $params['fecha_fin'] = $fecha_fin;
        }

        $sql .= ' ORDER BY fecha_inicio ASC, g.id ASC';

        return DB::select($sql, $params);
    }

    /**
     * Verifica que el gasto exista.
     */
    public static function existe_gasto(int $id_gasto): bool
    {
        return DB::table('gasto_produccion_carbon')
            ->where('id', $id_gasto)
            ->exists();
    }

    /**
     * Marca un gasto como pagado.
     *
     * La fecha de pago puede ser retroactiva: se guarda tal como la digita
     * el usuario.
     */
    public static function actualizar_pago_gasto(int $id_gasto, string $fecha_pago): bool
    {
        $columnas = self::columnas_gasto();

        // Si el esquema todavia no tiene las columnas, no se puede marcar el
        // pago: se avisa en vez de fallar en silencio.
        if ($columnas['esta_pagado'] !== 'g.esta_pagado' || $columnas['fecha_pago'] !== 'g.fecha_pago') {
            return false;
        }

        $filas = DB::table('gasto_produccion_carbon')
            ->where('id', $id_gasto)
            ->update([
                'esta_pagado' => 1,
                'fecha_pago' => $fecha_pago,
            ]);

        return $filas > 0;
    }
}
