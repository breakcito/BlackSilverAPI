<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_producto',
        'id_empleado_atencion', // quien aprueba o rechaza
        'id_unidad_medida', // bolsa
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        'contenido_por_presentacion',
        'cantidad_entregada',
        'cantidad_entregada_base',
        'comentario',
        'comentario_decision',
        'estado',
    ];

    public static function get_detalles_solicitud(int $id_solicitud_reabastecimiento)
    {
        $sql = '
        SELECT
            srd.id AS id_requerimiento_almacen_detalle,
            srd.id_producto,
            pr.nombre as producto,
            srd.id_unidad_medida,
            uni.abreviatura as unidad_medida,
            uni_p.abreviatura as unidad_medida_base_abreviatura,
            pr.es_fiscalizado,
            pr.es_perecible,
            srd.cantidad_solicitada,
            srd.cantidad_solicitada_base,
            srd.contenido_por_presentacion,
            srd.cantidad_entregada,
            srd.cantidad_entregada_base,
            srd.comentario,
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        INNER JOIN producto pr ON
            pr.id = srd.id_producto
        INNER JOIN unidad_medida uni ON
            uni.id = srd.id_unidad_medida
        INNER JOIN unidad_medida uni_p ON
            uni_p.id = pr.id_unidad_medida_base
        WHERE srd.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento
        ';

        return DB::select($sql, ['id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento]);
    }
}
