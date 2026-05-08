<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacen extends Model
{
    protected $table = 'requerimiento_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_solicitante', // el responsable de la mina que solicita
        'id_empleado_registro',  // el almacenero que registra el requerimiento
        'id_mina', // la mina que solicita
        'id_almacen_destino', // el almacen que recibe el requerimiento
        'correlativo',
        'numero_correlativo',
        'premura',
        'observacion',
        'evidencias',
        'fecha_entrega_requerida',
        'created_at',
        'estado',
    ];

    /**
     * Obtiene los requerimientos de almacen
     */
    public static function get_requerimientos(
        ?int $id_requerimiento = null,
        ?int $id_almacen_destino = null,
        ?int $id_empleado_solicitante = null,
        ?string $mes = null,
        ?string $yearcito = null
    ) {
        $sql = '
        SELECT
            ra.id AS id_requerimiento,
            --
            ra.id_almacen_destino,
            alm.nombre AS almacen_destino,
            --
            ra.id_empleado_solicitante,
            CONCAT(COALESCE(con.nombre, emp.nombre), " ", COALESCE(con.apellido, emp.apellido)) AS solicitante,
            CONCAT(empr.nombre, " ", empr.apellido) AS responsable,
            --
            ra.id_mina,
            m.nombre AS mina,
            --
            ra.correlativo,
            ra.evidencias,
            ra.observacion,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN mina m ON m.id = ra.id_mina
        INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
        LEFT JOIN contratista con ON con.id = ra.id_empleado_solicitante
        LEFT JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN empleado empr ON empr.id = ra.id_empleado_registro
        WHERE 1=1
        ';

        $params = [];

        if ($id_requerimiento !== null) {
            $sql .= ' AND ra.id = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;

            return DB::selectOne($sql, $params);
        }

        if ($id_empleado_solicitante !== null) {
            $sql .= ' AND ra.id_empleado_solicitante = :id_empleado_solicitante';
            $params['id_empleado_solicitante'] = $id_empleado_solicitante;
        }

        if ($id_almacen_destino !== null) {
            $sql .= ' AND ra.id_almacen_destino = :id_almacen_destino';
            $params['id_almacen_destino'] = $id_almacen_destino;
        }

        if ($mes && $yearcito) {
            $sql .= ' AND MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :yearcito';
            $params['mes'] = $mes;
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' 
        ORDER BY 
        	CASE ra.estado
                WHEN "Generado"  THEN 1
                WHEN "En Proceso" THEN 2
                WHEN "Cerrado" THEN 3
                WHEN "Anulado" THEN 4
            	ELSE 5 
            END ASC,
        	ra.created_at DESC
        ';

        return DB::select($sql, $params);
    }
}
