<?php

namespace App\Modules\ProveedorCarbon\Data;

use Illuminate\Support\Facades\DB;

class ProveedorCarbonData
{
    /**
     * Lista los tipos de carbon asociados a un proveedor, con nombre y codigo.
     * @return array<object>
     */
    public static function get_tipos_por_proveedor(int $id_proveedor): array
    {
        $sql = '
            SELECT
                pc.id_tipo_carbon,
                t.nombre,
                t.codigo
            FROM proveedor_carbon pc
            INNER JOIN tipo_carbon t ON t.id = pc.id_tipo_carbon
            WHERE pc.id_proveedor = :id_proveedor
            ORDER BY t.nombre ASC
        ';
        return DB::select($sql, ['id_proveedor' => $id_proveedor]);
    }

    /**
     * Lista los tipos de carbon asociados a varios proveedores en una sola consulta.
     * Cada fila lleva el id_proveedor para que el caller pueda reagrupar.
     * @param int[] $ids_proveedor
     * @return array<object>
     */
    public static function get_tipos_por_proveedores(array $ids_proveedor): array
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
                pc.id_proveedor,
                pc.id_tipo_carbon,
                t.nombre,
                t.codigo
            FROM proveedor_carbon pc
            INNER JOIN tipo_carbon t ON t.id = pc.id_tipo_carbon
            WHERE pc.id_proveedor IN ($inClause)
            ORDER BY pc.id_proveedor, t.nombre ASC
        ";
        return DB::select($sql, $params);
    }

    /**
     * Set masivo: borra las asociaciones existentes y crea las nuevas
     * en una sola transaccion.
     * @param int[] $ids_tipo_carbon
     */
    public static function set_tipos_por_proveedor(int $id_proveedor, array $ids_tipo_carbon): void
    {
        DB::transaction(function () use ($id_proveedor, $ids_tipo_carbon) {
            DB::table('proveedor_carbon')
                ->where('id_proveedor', $id_proveedor)
                ->delete();

            $filas = [];
            foreach ($ids_tipo_carbon as $id_tipo_carbon) {
                $id = (int) $id_tipo_carbon;
                if ($id <= 0) {
                    continue;
                }
                $filas[] = [
                    'id_proveedor' => $id_proveedor,
                    'id_tipo_carbon' => $id,
                ];
            }
            if (!empty($filas)) {
                DB::table('proveedor_carbon')->insert($filas);
            }
        });
    }
}