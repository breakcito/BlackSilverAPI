<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenEntrega extends Model
{
    protected $table = 'prestamo_almacen_entrega';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_empleado_entrega', // quien registra la entrega
        'id_empleado_recibe', // el empleado que recibe los productos para el envio
        'correlativo',
        'numero_correlativo',
        'fecha_hora_entrega',
        'observacion',
        'evidencias',
        'created_at',
        'estado',
    ];

    /**
     * Consulta generica para obtener el registro de una entrega por prestamo
     * o todo el historial de entregas de un prestamo
     */
    public static function get_entregas(
        ?int $id_entrega = null,
        ?int $id_prestamo = null,
        ?int $id_solicitud_reabastecimiento = null
    ) {
        $sql = '
        SELECT
            pae.id AS id_prestamo_entrega,
            pae.id_prestamo_almacen,
            pa.id_solicitud_reabastecimiento,
            --
            pa.id_almacen_prestamista as id_almacen_entrega,
            alm.nombre as almacen_entrega,
            --
            CONCAT(emp_ent.nombre, " ", emp_ent.apellido) AS empleado_entrega,
            TRIM(CONCAT_WS(" ", NULLIF(TRIM(emp_rec.nombre), ""), NULLIF(TRIM(emp_rec.apellido), ""))) AS empleado_recibe,
            --
            pae.correlativo,
            pae.fecha_hora_entrega,
            pae.observacion,
            pae.evidencias,
            pae.created_at,
            pae.estado
        FROM
            prestamo_almacen_entrega pae
        INNER JOIN empleado emp_ent ON emp_ent.id = pae.id_empleado_entrega
        INNER JOIN empleado emp_rec ON emp_rec.id = pae.id_empleado_recibe
        INNER JOIN prestamo_almacen pa ON pa.id = pae.id_prestamo_almacen
        INNER JOIN almacen alm on alm.id = pa.id_almacen_prestamista
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_entrega) {
            $sql .= ' AND pae.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_solicitud_reabastecimiento) {
            $sql .= ' AND pa.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud_reabastecimiento;
        }

        if ($id_prestamo) {
            $sql .= ' AND pae.id_prestamo_almacen = :id_prestamo';
            $params['id_prestamo'] = $id_prestamo;
        }

        $sql .= ' ORDER BY pae.created_at DESC;';
        return DB::select($sql, $params);
    }
}
