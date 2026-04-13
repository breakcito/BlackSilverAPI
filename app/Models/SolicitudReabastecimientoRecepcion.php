<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoRecepcion extends Model
{
    protected $table = 'solicitud_reabastecimiento_recepcion';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_entrega',
        'id_empleado_registro',
        'observacion',
        'fecha_hora_recepcion',
        'evidencias',
        'con_incidencia',
        'created_at',
        'estado',
    ];

    /**
     * Obtener el historial de recepciones de una entrega logística
     */
    public static function get_recepciones(?int $id_recepcion = null, ?int $id_entrega = null)
    {
        $sql = '
        SELECT 
            r.id as id_recepcion,
            r.id_solicitud_reabastecimiento_entrega,
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
            solicitud_reabastecimiento_recepcion r
        INNER JOIN empleado e ON e.id = r.id_empleado_registro
        WHERE 
            1 = 1
        ';

        $params = [];
        if ($id_recepcion !== null) {
            $sql .= " AND r.id = :id_recepcion";
            $params['id_recepcion'] = $id_recepcion;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega !== null) {
            $sql .= " AND r.id_solicitud_reabastecimiento_entrega = :id_entrega";
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= " ORDER BY r.fecha_hora_recepcion DESC;";

        return DB::select($sql, $params);
    }
}
