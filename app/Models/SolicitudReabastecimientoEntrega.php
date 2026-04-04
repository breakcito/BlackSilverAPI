<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoEntrega extends Model
{
    protected $table = 'solicitud_reabastecimiento_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_almacen_entrega', // un almacen principal
        'id_empleado_entrega',
        'id_empleado_recibe',
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];

    /**
     * Obtener el historial de entregas en base a una solicitud
     */
    public static function get_entregas(
        ?int $id_entrega = null,
        ?int $id_solicitud = null
    ) {
        $sql = '
        SELECT DISTINCT
            ent.id AS id_reabastecimiento_entrega,
            ent.id_solicitud_reabastecimiento,
            --
            ent.id_almacen_entrega,
            alm.nombre as almacen_entrega,
            --
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            --
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            solicitud_reabastecimiento_entrega ent
        INNER JOIN almacen alm on alm.id = ent.id_almacen_entrega
        INNER JOIN empleado emp_ent ON
            emp_ent.id = ent.id_empleado_entrega
        INNER JOIN empleado emp_rec ON
            emp_rec.id = ent.id_empleado_recibe
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_entrega) {
            $sql .= ' AND ent.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_solicitud) {
            $sql .= ' AND ent.id_solicitud_reabastecimiento = :id_solicitud';
            $params['id_solicitud'] = $id_solicitud;
        }

        $sql .= ' ORDER BY ent.correlativo DESC;';

        return DB::select($sql, $params);
    }
}
