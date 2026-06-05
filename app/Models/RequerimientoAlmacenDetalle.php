<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenDetalle extends Model
{
    protected $table = 'requerimiento_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_producto', // manzana - kilos
        'id_unidad_medida', // caja
        'id_empleado_atencion', // quien decide aprobar/rechazar el producto del requerimiento
        //
        'contenido_por_presentacion', // 10kg por caja
        'cantidad_solicitada', // 3 cajas
        'cantidad_solicitada_base', // 30kg
        'cantidad_entregada', // 2 cajas
        'cantidad_entregada_base', // 20kg
        'comentario',
        'comentario_decision', // luego de aprobar/rechazar, podran brindar algun comentario adicional
        //
        'estado',
    ];

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles(
        ?int $id_detalle = null,
        ?int $id_requerimiento = null,
    ) {
        // 1. Definimos la base de la consulta (sin WHERE ni ORDER BY aún)
        $sql = '
        SELECT 
            rad.id AS id_requerimiento_almacen_detalle,
            
            CONCAT(emp.nombre, " ", emp.apellido) AS empleado_atencion,
            
            pr.id AS id_producto,
            pr.nombre AS producto,
            pr.stock_minimo_base,
            pr.es_auditable,
            cat.clasificacion_bien as tipo_bien,
            
            -- unidad base y cantidades en base a esa unidad base del producto
            pr.id_unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            rad.contenido_por_presentacion, -- cuantas unidades base hay en una unidad del detalle del requerimiento
            rad.cantidad_solicitada_base,
            rad.cantidad_entregada_base,
            
            -- unidad del requerimiento y cantidades en base a esa unidad
            rad.id_unidad_medida as id_unidad_medida_req, 
            uni.abreviatura AS unidad_medida_req_abv,
            rad.cantidad_solicitada,
            rad.cantidad_entregada,
            
            
            CASE 
                WHEN rad.cantidad_solicitada_base > 0 THEN 
                    ROUND(((rad.cantidad_entregada_base / rad.cantidad_solicitada_base) * 100 ), 0)
                ELSE 0 
            END AS porcentaje_progreso,
            
            -- stock disponible de ese producto del almacen que atendera el requerimiento
            CASE
            	-- si se pidio un activo fijo
                WHEN cat.clasificacion_bien = "Activo Fijo" THEN (
                    SELECT
                    	COUNT(atf.id)
                    FROM activo_fijo atf 
                    WHERE 
                    	atf.id_producto = pr.id AND
                    	atf.id_almacen = alm.id
                )
                -- para todos los demas productos
                ELSE (
                    SELECT
                    	SUM(lot.stock_actual_base)
                    FROM lote_producto lot
                    WHERE
                        lot.id_almacen = alm.id AND
                        lot.id_producto = pr.id AND 
                        lot.estado = "Activo" AND 
                        lot.stock_actual_base > 0 AND
                        (lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL)
				) 
            END as stock_disponible_base,
            
            -- comentario al registrar y comentario luego del rechazo/aprobacion
            rad.comentario,
            rad.comentario_decision,
            
            rad.estado
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN producto pr ON pr.id = rad.id_producto
        INNER JOIN categoria cat on cat.id = pr.id_categoria
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
        
        INNER JOIN requerimiento_almacen req on req.id = rad.id_requerimiento_almacen
        INNER JOIN almacen alm on alm.id = req.id_almacen_destino
        
        LEFT JOIN empleado emp ON emp.id = rad.id_empleado_atencion
        WHERE 1=1
        ';

        $params = [];

        if ($id_detalle !== null) {
            $sql .= ' AND rad.id = :id_detalle';
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento !== null) {
            $sql .= ' AND rad.id_requerimiento_almacen = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;
        }

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }
}
