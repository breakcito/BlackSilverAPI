<?php

namespace App\Models;

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
    public static function get_almacenes(?int $id_almacen = null)
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
                INNER JOIN empleado emp ON emp.id = ra.id_empleado
                WHERE ra.id_almacen = a.id AND ra.estado = "Activo"
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
            1 = 1
        ';

        $bindings = [];
        if ($id_almacen !== null) {
            $sql .= ' AND a.id = :id_almacen';
            $bindings['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY a.es_principal DESC, a.nombre ASC';
        return DB::select($sql, $bindings);
    }
}
