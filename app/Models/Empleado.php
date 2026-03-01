<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Empleado extends Model
{
    protected $table = 'empleado';

    public $timestamps = false;

    protected $fillable = [
        'id_cargo',
        'id_empresa',
        'nombre',
        'apellido',
        'dni',
        'ruc',
        'carnet_extranjeria',
        'pasaporte',
        'fecha_nacimiento',
        'path_foto',
        'estado',
    ];

    /**
     * Obtener listado de empleados con información de cargo y empresa.
     */
    public static function get_empleados()
    {
        $sql = '
        SELECT
            e.id as id_empleado,
            e.id_cargo,
            c.nombre as cargo,
            e.id_empresa,
            em.nombre_comercial as empresa,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.path_foto,
            e.estado
        FROM
            empleado e
        INNER JOIN cargo c ON c.id = e.id_cargo
        INNER JOIN empresa em ON em.id = e.id_empresa
        ORDER BY e.apellido ASC
        ';

        return DB::select($sql);
    }

    /**
     * Obtener empleado por ID (para retorno post-creación).
     */
    public static function get_empleado_by_id(int $id)
    {
        $sql = '
        SELECT
            e.id as id_empleado,
            e.id_cargo,
            c.nombre as cargo,
            e.id_empresa,
            em.nombre_comercial as empresa,
            e.nombre,
            e.apellido,
            e.dni,
            e.ruc,
            e.carnet_extranjeria,
            e.pasaporte,
            e.fecha_nacimiento,
            e.path_foto,
            e.estado
        FROM
            empleado e
        INNER JOIN cargo c ON c.id = e.id_cargo
        INNER JOIN empresa em ON em.id = e.id_empresa
        WHERE
            e.id = :id
        ';

        return DB::selectOne($sql, ['id' => $id]);
    }
}
