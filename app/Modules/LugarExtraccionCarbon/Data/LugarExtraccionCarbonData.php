<?php

namespace App\Modules\LugarExtraccionCarbon\Data;

use Illuminate\Support\Facades\DB;

class LugarExtraccionCarbonData
{
    /**
     * Lista los lugares de extraccion activos de un proveedor, con nombre
     * de departamento / provincia / distrito para evitar joins en el front.
     * @return array<object>
     */
    public static function get_por_proveedor(int $id_proveedor): array
    {
        $sql = '
            SELECT
                le.id_proveedor,
                le.id_departamento,
                d.nombre AS departamento_nombre,
                le.id_provincia,
                p.nombre AS provincia_nombre,
                le.id_distrito,
                di.nombre AS distrito_nombre,
                le.direccion
            FROM lugar_extraccion_carbon le
            INNER JOIN departamento d ON d.id = le.id_departamento
            INNER JOIN provincia p ON p.id = le.id_provincia
            INNER JOIN distrito di ON di.id = le.id_distrito
            WHERE le.id_proveedor = :id_proveedor
              AND le.estado = "Activo"
            ORDER BY d.nombre ASC, p.nombre ASC, di.nombre ASC
        ';
        return DB::select($sql, ['id_proveedor' => $id_proveedor]);
    }

    /**
     * Lista los lugares de extraccion de varios proveedores en una sola
     * consulta. Cada fila lleva el id_proveedor para que el caller pueda
     * reagrupar.
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
                le.id_proveedor,
                le.id_departamento,
                d.nombre AS departamento_nombre,
                le.id_provincia,
                p.nombre AS provincia_nombre,
                le.id_distrito,
                di.nombre AS distrito_nombre,
                le.direccion
            FROM lugar_extraccion_carbon le
            INNER JOIN departamento d ON d.id = le.id_departamento
            INNER JOIN provincia p ON p.id = le.id_provincia
            INNER JOIN distrito di ON di.id = le.id_distrito
            WHERE le.id_proveedor IN ($inClause)
              AND le.estado = 'Activo'
            ORDER BY le.id_proveedor, d.nombre ASC, p.nombre ASC, di.nombre ASC
        ";
        return DB::select($sql, $params);
    }

    /**
     * Reemplaza todos los lugares de extraccion de un proveedor.
     * Marca los anteriores como Inactivo e inserta los nuevos en estado Activo.
     * @param int $id_proveedor
     * @param array<int, array{id_departamento:int, id_provincia:int, id_distrito:int, direccion:string|null}> $lugares
     */
    public static function set_para_proveedor(int $id_proveedor, array $lugares): void
    {
        DB::transaction(function () use ($id_proveedor, $lugares) {
            DB::table('lugar_extraccion_carbon')
                ->where('id_proveedor', $id_proveedor)
                ->update(['estado' => 'Inactivo']);

            $filas = [];
            foreach ($lugares as $l) {
                $idDep = (int) ($l['id_departamento'] ?? 0);
                $idProv = (int) ($l['id_provincia'] ?? 0);
                $idDist = (int) ($l['id_distrito'] ?? 0);
                $dir = trim((string) ($l['direccion'] ?? ''));
                if ($idDep <= 0 || $idProv <= 0 || $idDist <= 0 || $dir === '') {
                    continue;
                }
                $filas[] = [
                    'id_proveedor' => $id_proveedor,
                    'id_departamento' => $idDep,
                    'id_provincia' => $idProv,
                    'id_distrito' => $idDist,
                    'direccion' => $dir,
                    'estado' => 'Activo',
                ];
            }
            if (!empty($filas)) {
                DB::table('lugar_extraccion_carbon')->insert($filas);
            }
        });
    }
}