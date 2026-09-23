<?php

namespace App\Modules\AlmacenCarbonProveedor\Data;

use Illuminate\Support\Facades\DB;

class AlmacenCarbonProveedorData
{
    /**
     * Lista los almacenes de carbon ACTIVOS de un proveedor, con nombres
     * de departamento / provincia / distrito. Excluye los Inactivos (soft delete).
     *
     * @return array<object>
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $sql = '
            SELECT
                a.id AS id_almacen,
                a.id_proveedor,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_proveedor a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id_proveedor = :id_proveedor
              AND IFNULL(a.estado, "Activo") = "Activo"
            ORDER BY a.direccion ASC
        ';

        return DB::select($sql, ['id_proveedor' => $id_proveedor]);
    }

    /**
     * Lista los almacenes de carbon de varios proveedores en una sola consulta.
     * Pensado para alimentar `get_proveedores` con el JOIN de almacenes.
     *
     * @param int[] $ids_proveedor
     * @return array<object>
     */
    public static function get_por_proveedores(array $ids_proveedor): array
    {
        if (empty($ids_proveedor)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids_proveedor as $i => $id) {
            $key = "id_proveedor_$i";
            $placeholders[] = ":$key";
            $params[$key] = $id;
        }
        $inClause = implode(',', $placeholders);

        $sql = "
            SELECT
                a.id_proveedor,
                a.id AS id_almacen,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_proveedor a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id_proveedor IN ($inClause)
              AND IFNULL(a.estado, 'Activo') = 'Activo'
            ORDER BY a.id_proveedor, a.direccion ASC
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
                a.id_proveedor,
                a.id_departamento,
                d.nombre AS departamento_nombre,
                a.id_provincia,
                p.nombre AS provincia_nombre,
                a.id_distrito,
                di.nombre AS distrito_nombre,
                a.direccion,
                a.estado
            FROM almacen_carbon_proveedor a
            LEFT JOIN departamento d ON d.id = a.id_departamento
            LEFT JOIN provincia p ON p.id = a.id_provincia
            LEFT JOIN distrito di ON di.id = a.id_distrito
            WHERE a.id = :id
            LIMIT 1
        ';
        return DB::selectOne($sql, ['id' => $id_almacen]);
    }

    /**
     * Inserta un nuevo almacen de carbon para un proveedor.
     * Direccion es obligatoria; los ids de ubigeo son opcionales.
     */
    public static function insertar(
        int $id_proveedor,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): int {
        return DB::table('almacen_carbon_proveedor')->insertGetId([
            'id_proveedor' => $id_proveedor,
            'id_departamento' => $id_departamento && $id_departamento > 0 ? $id_departamento : null,
            'id_provincia' => $id_provincia && $id_provincia > 0 ? $id_provincia : null,
            'id_distrito' => $id_distrito && $id_distrito > 0 ? $id_distrito : null,
            'direccion' => trim($direccion),
            'estado' => 'Activo',
        ]);
    }

    /**
     * Actualiza un almacen existente del proveedor.
     */
    public static function actualizar(
        int $id_almacen,
        ?int $id_departamento,
        ?int $id_provincia,
        ?int $id_distrito,
        string $direccion
    ): int {
        return DB::table('almacen_carbon_proveedor')
            ->where('id', $id_almacen)
            ->update([
                'id_departamento' => $id_departamento && $id_departamento > 0 ? $id_departamento : null,
                'id_provincia' => $id_provincia && $id_provincia > 0 ? $id_provincia : null,
                'id_distrito' => $id_distrito && $id_distrito > 0 ? $id_distrito : null,
                'direccion' => trim($direccion),
            ]);
    }

    /**
     * Desactivar (soft delete) un almacen del proveedor.
     */
    public static function eliminar(int $id_almacen): int
    {
        return DB::table('almacen_carbon_proveedor')
            ->where('id', $id_almacen)
            ->update(['estado' => 'Inactivo']);
    }
}
