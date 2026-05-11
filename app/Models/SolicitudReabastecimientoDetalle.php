<?php

namespace App\Models;

use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SolicitudReabastecimientoDetalle extends Model
{
    protected $table = 'solicitud_reabastecimiento_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento',
        'id_requerimiento_almacen_detalle', // null - sirve para saber si fue generado por un requerimiento
        'id_empleado_atencion', // quien aprueba o rechaza
        'id_producto',
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

    // Funcion helpder que ayuda a crear un detalle de solicitud
    public static function crear_detalle(
        int $id_solicitud_reabastecimiento,
        int $id_producto,
        int $id_unidad_medida,
        float $cantidad_solicitada,
        float $cantidad_solicitada_base,
        float $contenido_por_presentacion,
        ?int $id_requerimiento_almacen_detalle,
        ?string $comentario = null
    ) {
        return SolicitudReabastecimientoDetalle::insertGetId([
            'id_solicitud_reabastecimiento' => $id_solicitud_reabastecimiento,
            'id_requerimiento_almacen_detalle' => $id_requerimiento_almacen_detalle,
            'id_producto' => $id_producto,
            'id_unidad_medida' => $id_unidad_medida,
            'cantidad_solicitada' => $cantidad_solicitada,
            'cantidad_solicitada_base' => $cantidad_solicitada_base,
            'contenido_por_presentacion' => $contenido_por_presentacion,
            'cantidad_entregada' => 0,
            'cantidad_entregada_base' => 0,
            'comentario' => $comentario,
            'estado' => EstadoSolicitudDetalle::EsperandoAprobacion->value,
        ]);
    }

    /**
     * Obtener los detalles de una solicitud de reabastecimiento, esto se utiliza desde
     * la vista de Solicitud de Reabastecimiento, que es la vista para el almacenero 
     * que ha hecho esta solicitud de reabastecimiento.
     */
    public static function get_detalles_solicitud(?int $id_solicitud_reabastecimiento = null)
    {
        $sql = '
        SELECT DISTINCT
            srd.id AS id_solicitud_detalle,
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            -- 
            pr.id AS id_producto,
            pr.nombre AS producto,
            pr.stock_minimo_base,
            pr.es_auditable,
            --
            -- que producto (tractor, carro, etc) va a consumir este item
            prdt.nombre as producto_destino, 
            --
            --
            -- segun la unidad base del producto
            pr.id_unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
           	srd.cantidad_solicitada_base,
            srd.cantidad_entregada_base,
            -- 
            -- cuantas unidades base hay en una unidad del detalle de la solicitud
            srd.contenido_por_presentacion,
            -- 
            -- segun la unidad del detalle de la solicitud
            srd.id_unidad_medida as id_unidad_medida_sol, 
            uni.abreviatura AS unidad_medida_sol_abv,
            srd.cantidad_solicitada,
            srd.cantidad_entregada,
            -- 
            -- el progreso que tiene este detalle segun lo entregado hasta el momento
            CASE 
                WHEN srd.cantidad_solicitada_base > 0 THEN 
                    ROUND(((srd.cantidad_entregada_base / srd.cantidad_solicitada_base) * 100 ), 0)
                ELSE 0 
            END AS porcentaje_progreso,
            -- 
            srd.comentario,
            srd.comentario_decision,
            --
            srd.estado
        FROM
            solicitud_reabastecimiento_detalle srd
        LEFT JOIN empleado emp ON emp.id = srd.id_empleado_atencion
        INNER JOIN producto pr ON pr.id = srd.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida uni ON uni.id = srd.id_unidad_medida
        LEFT JOIN requerimiento_almacen_detalle rqd on rqd.id = srd.id_requerimiento_almacen_detalle
        LEFT JOIN producto prdt on prdt.id = rqd.id_producto_destino
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_solicitud_reabastecimiento !== null) {
            $sql .= ' AND srd.id_solicitud_reabastecimiento = :id_solicitud_reabastecimiento';
            $params['id_solicitud_reabastecimiento'] = $id_solicitud_reabastecimiento;
        }

        return DB::select($sql, $params);
    }
}
