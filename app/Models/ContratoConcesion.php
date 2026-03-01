<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ContratoConcesion extends Model
{
    protected $table = 'contrato_concesion';

    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_concesion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function get_empresas_asignadas(int $id_concesion)
    {
        $sql = '
        SELECT
            cc.id AS id_contrato,
            cc.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo,
            cc.fecha_inicio,
            cc.fecha_fin,
            cc.estado
        FROM
            contrato_concesion cc
        INNER JOIN empresa e ON e.id = cc.id_empresa
        WHERE
            cc.id_concesion = :id_concesion AND
            cc.estado = :estado
        ORDER BY cc.fecha_inicio DESC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, [
            'id_concesion' => $id_concesion,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);
    }

    public static function get_empresas_historial(int $id_concesion)
    {
        $sql = '
        SELECT
            cc.id AS id_contrato,
            cc.id_empresa,
            e.nombre_comercial,
            e.ruc,
            e.path_logo,
            cc.fecha_inicio,
            cc.fecha_fin,
            cc.estado
        FROM
            contrato_concesion cc
        INNER JOIN empresa e ON e.id = cc.id_empresa
        WHERE
            cc.id_concesion = :id_concesion
        ORDER BY cc.estado ASC, cc.fecha_inicio DESC
        ';

        return \Illuminate\Support\Facades\DB::select($sql, [
            'id_concesion' => $id_concesion,
        ]);
    }

    public static function desasignar_empresa(int $id_contrato)
    {
        return self::where('id', $id_contrato)
            ->update(['estado' => \App\Shared\Enums\EstadoBase::Inactivo->value]);
    }

    // obtener las concesiones donde trabaja una empresa (a través de contrato)
    public static function get_concesiones_by_empresa(int $id_empresa)
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
            cc.id AS id_contrato,
            cc.fecha_inicio,
            cc.fecha_fin
        FROM
            concesion cn
        INNER JOIN contrato_concesion cc ON cc.id_concesion = cn.id
        WHERE
            cc.id_empresa = :id_empresa AND
            cn.estado = :estado
        ';

        return DB::select($sql, [
            'id_empresa' => $id_empresa,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
