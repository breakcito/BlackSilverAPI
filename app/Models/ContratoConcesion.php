<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public static function asignar_empresa(int $id_concesion, int $id_empresa, string $fecha_inicio, ?string $fecha_fin)
    {
        return self::insertGetId([
            'id_concesion' => $id_concesion,
            'id_empresa' => $id_empresa,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => $fecha_fin,
            'estado' => \App\Shared\Enums\EstadoBase::Activo->value,
        ]);
    }

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

    public static function verificar_asignacion_activa(int $id_concesion, int $id_empresa)
    {
        return self::where('id_concesion', $id_concesion)
            ->where('id_empresa', $id_empresa)
            ->where('estado', \App\Shared\Enums\EstadoBase::Activo->value)
            ->exists();
    }

    public static function desasignar_empresa(int $id_contrato)
    {
        return self::where('id', $id_contrato)
            ->update(['estado' => \App\Shared\Enums\EstadoBase::Inactivo->value]);
    }
}
