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
        'id_usuario',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    public static function get_responsables_historial(int $id_almacen)
    {
        $sql = '
        SELECT
            ra.id AS id_asignacion,
            ra.id_usuario,
            emp.nombre AS nombres,
            emp.apellido AS apellidos,
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN usuario u ON u.id = ra.id_usuario
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE
            ra.id_almacen = :id_almacen
        ORDER BY ra.fecha_inicio DESC
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
