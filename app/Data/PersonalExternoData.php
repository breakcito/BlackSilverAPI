<?php

namespace App\Data;

use App\Models\PersonalExterno;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class PersonalExternoData
{

    /**
     * Permitir el registro para el personal externo
     */
    public static function crear_personal(
        ?int $id_proveedor = null,
        bool $es_representante = false,
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $dni = null,
    ) {
        return PersonalExterno::insertGetId([
            'id_proveedor' => $id_proveedor,
            'es_representante' => $es_representante,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Listar el personal externo
     */
    public static function get_personal(
        ?int $id_personal = null,
        ?int $id_proveedor = null,
        ?EstadoBase $estado = null
    ) {
        $sql = '
        SELECT
            pr.id AS id_personal,
            pr.id_proveedor,
            pr.es_representante,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(pr.nombre), ""), NULLIF(TRIM(pr.apellido), ""))) AS nombre_completo,
            pr.dni,
            pr.estado
        FROM
            personal_externo pr
        WHERE 1=1
        ';

        $params = [];

        if ($id_personal) {
            $sql .= ' AND pr.id = :id_personal';
            $params['id_personal'] = $id_personal;
            return DB::selectOne($sql, $params);
        }

        if ($id_proveedor) {
            $sql .= ' AND pr.id_proveedor = :id_proveedor';
            $params['id_proveedor'] = $id_proveedor;
        }

        if ($estado) {
            $sql .= ' AND pr.estado = :estado';
            $params['estado'] = $estado->value;
        }

        return DB::select($sql, $params);
    }
}
