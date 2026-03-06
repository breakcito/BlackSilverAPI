<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Concesion extends Model
{
    protected $table = 'concesion';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'codigo_concesion',
        'codigo_reinfo',
        'ubigeo', // coordenadas
        'tipo_mineral', // TipoMineral
        'estado',
    ];

    // obtener la lista de concesiones con conteo de empresas asignadas
    public static function get_concesiones(?int $id_concesion = null)
    {
        $sql = '
        SELECT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado,
            (
                SELECT 
                    COUNT(*) 
                FROM contrato_concesion cc 
                WHERE 
                    cc.id_concesion = cn.id AND 
                    cc.estado = "Activo"
            ) as empresas_asignadas
        FROM
            concesion cn
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_concesion) {
            $sql .= ' AND cn.id = :id_concesion';
            $params['id_concesion'] = $id_concesion;
        }

        return DB::select($sql, $params);
    }

    // obtener las concesiones donde trabaja un usuario (a través de empresa asignada)
    public static function get_concesiones_by_usuario(int $id_usuario)
    {
        $sql = '
        SELECT DISTINCT
            cn.id AS id_concesion,
            cn.nombre,
            cn.codigo_concesion,
            cn.codigo_reinfo,
            cn.ubigeo,
            cn.tipo_mineral,
            cn.estado
        FROM
            concesion cn
        INNER JOIN contrato_concesion cc ON cc.id_concesion = cn.id
        INNER JOIN usuario_empresa ue ON ue.id_empresa = cc.id_empresa
        WHERE
            ue.id_usuario = :id_usuario AND
            cn.estado = :estado AND
            cc.estado = :estado
        ORDER BY cn.nombre ASC
        ';

        return DB::select($sql, [
            'id_usuario' => $id_usuario,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
