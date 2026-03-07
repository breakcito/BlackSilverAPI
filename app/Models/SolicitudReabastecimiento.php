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
        //
        'correlativo',
        'numero_correlativo',
        'observacion',
        'premura',
        'fecha_hora_entrega_requerida',
        //
        'created_at',
        'estado',
    ];

    public static function get_solicitudes(?int $id_almacen_solicitante = null, ?int $id_solicitud_reabastecimiento = null)
    {
        $sql = "
        SELECT
            sr.id AS id_solicitud_reabastecimiento,
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
        WHERE
            1 = 1
        ";

        $params = [];
        if ($id_solicitud_reabastecimiento !== null) {
            $sql .= ' AND sr.id = :id_solicitud_reabastecimiento';
            $$params['id_solicitud_reabastecimiento'] = $id_solicitud_reabastecimiento;
        }
        if ($id_almacen_solicitante !== null) {
            $sql .= ' AND sr.id_almacen_solicitante = :id_almacen_solicitante';
            $$params['id_almacen_solicitante'] = $id_almacen_solicitante;
        }

        return DB::select($sql, $params);
    }

    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = "
        SELECT
            srd.id AS id_requerimiento_almacen_detalle,
            pr.nombre as producto,
            uni.abreviatura as unidad_medida,
            pr.es_fiscalizado,
            pr.es_perecible,
            srd.cantidad_solicitada,
            srd.cantidad_solicitada_base,
            srd.comentario,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        INNER JOIN producto pr ON
            pr.id = srd.id_producto
        INNER JOIN unidad_medida uni ON
            uni.id = srd.id_unidad_medida
        WHERE srd.id_solicitud_reabastecimiento = 1
        ";

        return DB::select($sql, ['id_solicitud_reabastecimiento ' => $id_solicitud_reabastecimiento]);
    }
}
