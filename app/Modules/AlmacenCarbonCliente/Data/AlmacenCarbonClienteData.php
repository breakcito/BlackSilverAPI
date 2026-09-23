<?php

namespace App\Modules\AlmacenCarbonCliente\Data;

use Illuminate\Support\Facades\DB;

class AlmacenCarbonClienteData
{
    /**
     * Lista los almacenes de carbon ACTIVOS de un cliente, con nombres
     * de departamento / provincia / distrito. Excluye los Inactivos (soft delete).
     *
     * @return array<object>
     */
    public static function get_por_cliente(int $id_cliente): array
    {
        $sql = '
            SELECT
                a.id AS id_almacen,
                a.id_cliente,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_cliente a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id_cliente = :id_cliente
              AND IFNULL(a.estado, "Activo") = "Activo"
            ORDER BY a.direccion ASC
        ';

        return DB::select($sql, ['id_cliente' => $id_cliente]);
    }

    /**
     * Lista los almacenes de carbon de varios clientes en una sola consulta.
     * Pensado para alimentar `get_clientes` con el JOIN de almacenes.
     *
     * @param int[] $ids_cliente
     * @return array<object>
     */
    public static function get_por_clientes(array $ids_cliente): array
    {
        if (empty($ids_cliente)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids_cliente as $i => $id) {
            $key = "id_cliente_$i";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $inClause = implode(',', $placeholders);

        $sql = "
            SELECT
                a.id_cliente,
                a.id AS id_almacen,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_cliente a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id_cliente IN ($inClause)
              AND IFNULL(a.estado, 'Activo') = 'Activo'
            ORDER BY a.id_cliente, a.direccion ASC
        ";

        return DB::select($sql, $params);
    }

    /**
     * Devuelve un almacen por id (o null si no existe).
     */
    public static function get_por_id(int $id_almacen): ?object
    {
        $sql = '
            SELECT
                a.id AS id_almacen,
                a.id_cliente,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_cliente a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id = :id
            LIMIT 1
        ';
        return DB::selectOne($sql, ['id' => $id_almacen]);
    }

    /**
     * Inserta un nuevo almacen de carbon para un cliente.
     * Direccion es obligatoria; los ids de ubigeo son opcionales.
     */
    public static function insertar(
        int $id_cliente,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): int {
        return DB::table('almacen_carbon_cliente')->insertGetId([
            'id_cliente' => $id_cliente,
            'id_departamento' => $id_departamento && $id_departamento > 0 ? $id_departamento : null,
            'id_provincia' => $id_provincia && $id_provincia > 0 ? $id_provincia : null,
            'id_distrito' => $id_distrito && $id_distrito > 0 ? $id_distrito : null,
            'direccion' => trim($direccion),
            'estado' => 'Activo',
        ]);
    }

    /**
     * Actualiza un almacen existente del cliente.
     */
    public static function actualizar(
        int $id_almacen,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): int {
        return DB::table('almacen_carbon_cliente')
            ->where('id', $id_almacen)
            ->update([
                'id_departamento' => $id_departamento && $id_departamento > 0 ? $id_departamento : null,
                'id_provincia' => $id_provincia && $id_provincia > 0 ? $id_provincia : null,
                'id_distrito' => $id_distrito && $id_distrito > 0 ? $id_distrito : null,
                'direccion' => trim($direccion),
            ]);
    }

    /**
     * Desactivar (soft delete) un almacen del cliente.
     */
    public static function eliminar(int $id_almacen): int
    {
        return DB::table('almacen_carbon_cliente')
            ->where('id', $id_almacen)
            ->update(['estado' => 'Inactivo']);
    }
}