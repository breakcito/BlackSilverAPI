<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenEntrega extends Model
{
    protected $table = 'requerimiento_almacen_entrega'; // entrega_almacen

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_empleado_entrega', // quien entrega los productos
        'id_empleado_recibe', // quien recibe los productos
        //
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias', // json con el formato [{ "path": "...", "nombre": "...", "extension": "..." }]
        //
        'created_at',
        'estado',
    ];

    public static function get_entregas_by_detalle(int $id_detalle)
    {
        $sql = "
        SELECT 
            ea.id AS id_entrega,
            ea.correlativo,
            ea.fecha_entrega,
            ead.cantidad,
            CONCAT(emp.nombre, ' ', emp.apellido) AS usuario_entrega
        FROM 
            entrega_almacen_detalle ead
        INNER JOIN entrega_almacen ea ON ea.id = ead.id_entrega_almacen
        INNER JOIN usuario u ON u.id = ea.id_usuario_entrega
        INNER JOIN empleado emp ON emp.id = u.id_empleado
        WHERE 
            ead.id_requerimiento_almacen_detalle = :id_detalle
        ORDER BY 
            ea.fecha_entrega DESC
        ";

        return DB::select($sql, ['id_detalle' => $id_detalle]);
    }
}
