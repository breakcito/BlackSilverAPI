<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenDetalle extends Model
{
    protected $table = 'prestamo_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen',
        'id_solicitud_reabastecimiento_detalle',
        'id_producto',
        'id_unidad_medida', // unidad de medida del prestamo
        //
        // cuantas unidades de medida base hay en una unidad de medida del prestamo
        'contenido_por_presentacion',
        //
        // lo que le piden prestado
        'cantidad_solicitada',
        'cantidad_solicitada_base',
        //
        // lo que va prestando
        'cantidad_prestada',
        'cantidad_prestada_base',
        //
        // lo que va siendo repuesto por logistica
        'cantidad_repuesta',
        'cantidad_repuesta_base',
        //
        'comentario',
        'estado',
    ];

    /**
     * Obtiene uno o todos los detalles de un prestamo
     */
    public static function get_detalles(
        ?int $id_prestamo = null,
        ?int $id_prestamo_detalle = null
    ): array {
        $sql = '
        SELECT
            pad.id AS id_prestamo_detalle,
            pad.id_solicitud_reabastecimiento_detalle,
            pad.id_producto,
            prod.nombre AS producto,
            pad.cantidad_solicitada,
            pad.contenido_por_presentacion, -- cuantas unidades de medida base hay en una unidad de medida de la solicitud
            pad.cantidad_solicitada_base,
            pad.cantidad_prestada, -- lo que va prestando
            pad.cantidad_prestada_base,
            pad.cantidad_repuesta, -- lo que va siendo repuesto por logistica
            pad.cantidad_repuesta_base,
            pad.comentario,            
            um_pr.id as id_unidad_medida_pr, -- unidad de medida del prestamo
            um_pr.abreviatura AS unidad_medida_pr_abv,
            um_bs.id as id_unidad_medida_base, -- unidad de medida base del producto
            um_bs.abreviatura AS unidad_medida_base_abv,
            pad.estado
        FROM
            prestamo_almacen_detalle pad
        INNER JOIN producto prod ON prod.id = pad.id_producto
        INNER JOIN unidad_medida um_pr ON um_pr.id = pad.id_unidad_medida
        INNER JOIN unidad_medida um_bs ON um_bs.id = prod.id_unidad_medida_base
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_prestamo_detalle) {
            $sql .= " AND pad.id = :id_prestamo_detalle";
            $params['id_prestamo_detalle'] = $id_prestamo_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_prestamo) {
            $sql .= " AND pad.id_prestamo_almacen = :id_prestamo";
            $params['id_prestamo'] = $id_prestamo;
        }

        $sql .= " ORDER BY prod.nombre ASC";
        return DB::select($sql, $params);
    }
}
