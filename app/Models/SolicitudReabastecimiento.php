<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimiento extends Model
{
    protected $table = 'solicitud_reabastecimiento';

    public $timestamps = false;

    protected $fillable = [
        'id_almacen_solicitante',
        'id_empleado_solicitante',
        'correlativo',
        'numero_correlativo',
        'observacion',
        'premura',
        'fecha_hora_entrega_requerida',
        'created_at',
        'estado',
    ];


    public static function get_solicitudes(?int $id_almacen_solicitante = null, ?int $id_solicitud_reabastecimiento = null)
    {
        $sql = "
        SELECT
            sr.id AS id_solicitud_reabastecimiento,
            sr.id_almacen_solicitante,
            alm.nombre AS almacen_solicitante,
            CONCAT(em.nombre, ' ', em.apellido) AS empleado_solicitante,
            sr.correlativo,
            sr.premura,
            sr.fecha_hora_entrega_requerida,
            sr.created_at,
            sr.estado
        FROM
            solicitud_reabastecimiento sr
        INNER JOIN empleado em ON
            em.id = sr.id_empleado_solicitante
        INNER JOIN almacen alm ON
            alm.id = sr.id_almacen_solicitante
        WHERE
            1 = 1
        ";

        $params = [];
        if ($id_solicitud_reabastecimiento !== null) {
            $sql .= ' AND sr.id = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud_reabastecimiento;
        }
        if ($id_almacen_solicitante !== null) {
            $sql .= ' AND sr.id_almacen_solicitante = :id_almacen_solicitante';
            $params['id_almacen_solicitante'] = $id_almacen_solicitante;
        }

        $sql .= ' ORDER BY sr.created_at DESC';

        return DB::select($sql, $params);
    }
}
