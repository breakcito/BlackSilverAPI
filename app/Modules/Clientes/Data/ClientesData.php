<?php

namespace App\Modules\Clientes\Data;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;

class ClientesData
{
    /** Obtiene la lista de clientes o uno en específico por su id. */
    public static function get_clientes(?int $id_cliente = null)
    {
        $sql = '
        SELECT
            cl.id AS id_cliente,
            cl.tipo_entidad,
            cl.dni,
            cl.ruc,
            cl.razon_social,
            cl.direccion,
            cl.telefono,
            cl.correo,
            cl.estado,
            cl.created_at,
            (SELECT COUNT(*) FROM cuenta_bancaria_cliente cbc WHERE cbc.id_cliente = cl.id) AS cantidad_cuentas_bancarias
        FROM
            cliente cl
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_cliente) {
            $sql .= ' AND cl.id = :id_cliente';
            $params['id_cliente'] = $id_cliente;
            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY cl.razon_social ASC';
        return DB::select($sql, $params);
    }

    /** Obtiene un cliente por su id. */
    public static function get_cliente_by_id(int $id_cliente)
    {
        return self::get_clientes(id_cliente: $id_cliente);
    }

    /** Inserta un nuevo cliente y retorna su id generado. */
    public static function crear_cliente(
        ?string $tipoEntidad,
        ?string $dni,
        ?string $ruc,
        string $razonSocial,
        ?string $direccion,
        ?string $telefono,
        ?string $correo
    ): int {
        return Cliente::insertGetId([
            'tipo_entidad'      => $tipoEntidad,
            'dni'               => $dni,
            'ruc'               => $ruc,
            'razon_social'      => $razonSocial,
            'direccion'         => $direccion,
            'telefono'          => $telefono,
            'correo'            => $correo,
            'estado'            => 'Activo',
            'created_at'        => now(),
        ]);
    }
}
