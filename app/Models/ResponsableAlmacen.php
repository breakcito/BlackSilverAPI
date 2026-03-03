<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResponsableAlmacen extends Model
{
    protected $table = 'responsable_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen',
        'id_empleado',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function get_responsables_historial(?int $id_almacen = null, ?int $id_responsable_almacen = null)
    {
        $sql = '
        SELECT
            ra.id AS id_responsable_almacen,
            ra.id_empleado,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado
        WHERE
            1 = 1
        ';

        $params = [];

        if ($id_almacen != null) {
            $sql .= ' AND ra.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }
        if ($id_responsable_almacen != null) {
            $sql .= ' AND ra.id = :id_responsable_almacen';
            $params['id_responsable_almacen'] = $id_responsable_almacen;
        }

        $sql .= ' ORDER BY ra.fecha_inicio DESC';

        return DB::select($sql, $params);
    }
}
