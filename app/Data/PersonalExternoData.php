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
        ?string $nombre = null,
        ?string $apellido = null,
        ?string $dni = null,
    ) {
        return PersonalExterno::insertGetId([
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
        ?EstadoBase $estado = null
    ) {
        $sql = '
        SELECT
            pr.id AS id_personal,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(pr.nombre), ""), NULLIF(TRIM(pr.apellido), ""))) AS nombre_completo,
            pr.dni
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

        if ($estado) {
            $sql .= ' AND pr.estado = :estado';
            $params['estado'] = $estado->value;
        }

        return DB::select($sql, $params);
    }
}
