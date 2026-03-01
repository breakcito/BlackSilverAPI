<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Tabla que registra la trazabilidad de cada producto de un requerimiento de almacen
class RequerimientoAlmacenDetalleLog extends Model
{
    protected $table = 'requerimiento_almacen_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_detalle',
        'id_empleado', // quien provoco el cambio
        //
        'tipo_origen', // el "por que" del registro
        'descripcion', // descripcion del cambio
        //
        'created_at',
    ];

    public static function get_trazabilidad(int $id_requerimiento_almacen_detalle)
    {
        $sql = "
        SELECT 
            rl.id,
            rl.glosa,
            rl.estado,
            rl.created_at,
            IFNULL(CONCAT(e.nombre, ' ', e.apellido), 'Usuario Sistema') AS usuario
        FROM 
            requerimiento_almacen_detalle_log AS rl
        LEFT JOIN usuario AS u ON u.id = rl.id_usuario
        LEFT JOIN empleado AS e ON e.id = u.id_empleado
        WHERE
            rl.id_requerimiento_almacen_detalle = :id_detalle
        ORDER BY 
            rl.created_at DESC, 
            rl.id DESC
        ";

        return DB::select($sql, ['id_detalle' => $id_requerimiento_almacen_detalle]);
    }
}
