<?php

namespace App\Models;

use App\Shared\Enums\EstadoBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Almacen extends Model
{
    protected $table = 'almacen';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'es_principal',
        'estado',
    ];

    /**
     * Listar todos los almacenes.
     */
    public static function get_almacenes()
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.estado,
            (
                SELECT CONCAT(emp.nombre, " ", emp.apellido)
                FROM responsable_almacen ra
                INNER JOIN usuario u ON u.id = ra.id_usuario
                INNER JOIN empleado emp ON emp.id = u.id_empleado
                WHERE ra.id_almacen = a.id AND ra.estado = :estado_activo
                LIMIT 1
            ) AS responsable_actual,
            (
                SELECT COUNT(*)
                FROM almacen_mina am
                WHERE am.id_almacen = a.id
            ) AS minas_count
        FROM
            almacen a
        WHERE
            a.estado = :estado
        ORDER BY a.es_principal DESC, a.nombre ASC
        ';

        return DB::select($sql, [
            'estado' => EstadoBase::Activo->value,
            'estado_activo' => EstadoBase::Activo->value,
        ]);
    }
}
