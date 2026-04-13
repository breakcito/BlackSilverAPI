<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo que representa a la tabla utilizada para registrar la RECEPCION de 
 * una entrega hecha por un PRESTAMO, es decir, una entrega hecha por el almacen
 * prestamista al almacen solicitante
 */
class PrestamoAlmacenEntregaRecepcion extends Model
{
    protected $table = 'prestamo_almacen_entrega_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_entrega', // de que entrega/envio se esta recepcionando
        'id_empleado_registro', // el empleado que recepciona/registra
        'observacion',
        'fecha_hora_recepcion',
        'evidencias',
        'con_incidencia',
        'created_at',
        'estado',
    ];

    public static function get_recepciones(?int $id_recepcion = null, ?int $id_entrega = null)
    {
        $sql = '
        SELECT
            r.id as id_recepcion,
            r.id_prestamo_almacen_entrega,
            --
            CONCAT(e.nombre, " ", e.apellido) AS empleado_registro,
            --
            r.observacion,
            r.fecha_hora_recepcion,
            r.evidencias,
            r.con_incidencia,
            r.created_at,
            r.estado
        FROM
            prestamo_almacen_entrega_recepcion r
        JOIN empleado e ON
            e.id = r.id_empleado_registro
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_recepcion !== null) {
            $sql .= " AND r.id = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega !== null) {
            $sql .= " AND r.id_prestamo_almacen_entrega = :id_entrega";
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= " ORDER BY r.created_at DESC";

        return DB::select($sql, $params);
    }
}
