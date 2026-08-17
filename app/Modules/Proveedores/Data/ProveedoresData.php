<?php

namespace App\Modules\Proveedores\Data;

use Illuminate\Support\Facades\DB;

class ProveedoresData
{
    public static function get_proveedores(?int $id_proveedor = null, ?bool $paraCarbon = null)
    {
        $sql = '
        SELECT
            pr.id AS id_proveedor,
            pr.tipo_entidad,
            pr.para_mantenimiento,
            pr.para_transporte,
            pr.para_carbon,
            pr.id_departamento,
            pr.id_provincia,
            pr.id_distrito,
            d.nombre AS departamento_nombre,
            p.nombre AS provincia_nombre,
            di.nombre AS distrito_nombre,
            pr.dni,
            pr.ruc,
            pr.razon_social,
            pr.direccion,
            pr.telefono,
            pr.correo,
            pr.estado,
            (
                SELECT
                    COUNT(*)
                FROM
                    cuenta_bancaria_proveedor cn
                WHERE
                    cn.id_proveedor = pr.id AND
                    cn.estado = "Activo"
            ) as cantidad_cuentas_bancarias
        FROM
            proveedor pr
        LEFT JOIN departamento d ON d.id = pr.id_departamento
        LEFT JOIN provincia p ON p.id = pr.id_provincia
        LEFT JOIN distrito di ON di.id = pr.id_distrito
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_proveedor) {
            $sql .= ' AND pr.id = :id_proveedor';
            $params['id_proveedor'] = $id_proveedor;
            return DB::selectOne($sql, $params);
        }

        if ($paraCarbon !== null) {
            $sql .= ' AND pr.para_carbon = :paraCarbon';
            $params['paraCarbon'] = $paraCarbon ? 1 : 0;
        }

        $sql .= ' ORDER BY pr.razon_social ASC;';
        return DB::select($sql, $params);
    }

    public static function get_proveedor_by_id(int $id_proveedor)
    {
        return self::get_proveedores(id_proveedor: $id_proveedor);
    }

    /** Lista cuentas bancarias de varios proveedores en una sola consulta. */
    public static function get_cuentas_bancarias_por_proveedores(array $ids_proveedor): array
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
            cn.id_proveedor AS id_proveedor,
            cn.id AS id_cuenta_bancaria,
            cn.id_banco,
            bc.nombre AS banco,
            bc.abreviatura AS banco_abv,
            bc.es_nacional,
            cn.moneda,
            cn.numero_cuenta,
            cn.cci,
            cn.es_para_detraccion,
            cn.estado
        FROM cuenta_bancaria_proveedor cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE cn.id_proveedor IN ($inClause)
        ORDER BY cn.id_proveedor, cn.es_para_detraccion DESC, cn.moneda, cn.numero_cuenta
        ";

        return DB::select($sql, $params);
    }

}
