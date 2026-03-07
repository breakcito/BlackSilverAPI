<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacen extends Model
{
    protected $table = 'requerimiento_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado_solicitante',
        'id_mina',
        'id_almacen_destino',
        'correlativo',
        'numero_correlativo',
        'premura',
        'fecha_entrega_requerida',
        'created_at',
        'estado',
    ];

    public static function get_requerimiento_by_id(int $id)
    {
        $sql = "
        SELECT 
            ra.id AS id_requerimiento,
            ra.id_empleado_solicitante,
            CONCAT(emp.nombre, ' ', emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            ra.id_almacen_destino,
            alm.nombre AS almacen_destino,
            ra.correlativo AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM 
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
        WHERE 
            ra.id = :id
        ";

        return collect(DB::select($sql, ['id' => $id]))->first();
    }

    public static function get_requerimientos(
        ?int $id_mina = null,
        ?int $id_almacen_destino = null,
        ?string $estado = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null
    ) {
        $sql = '
        SELECT
            ra.id AS id_requerimiento,
            ra.id_empleado_solicitante,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            ra.id_almacen_destino,
            alm.nombre AS almacen_destino,
            ra.correlativo AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
        WHERE 1=1
        ';

        $params = [];

        if ($id_mina) {
            $sql .= ' AND ra.id_mina = :id_mina';
            $params['id_mina'] = $id_mina;
        }

        if ($id_almacen_destino) {
            $sql .= ' AND ra.id_almacen_destino = :id_almacen_destino';
            $params['id_almacen_destino'] = $id_almacen_destino;
        }

        if ($estado) {
            $sql .= ' AND ra.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($fecha_inicio && $fecha_fin) {
            $sql .= ' AND DATE(ra.created_at) BETWEEN :fecha_inicio AND :fecha_fin';
            $params['fecha_inicio'] = $fecha_inicio;
            $params['fecha_fin'] = $fecha_fin;
        }

        $sql .= ' ORDER BY ra.created_at DESC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener requerimientos filtrados para atención/despacho.
     */
    public static function obtener_requerimientos_atencion(int $id_almacen, ?string $estado = null, ?string $mes = null, ?string $anio = null)
    {
        $sql = "
        SELECT
            ra.id AS id_requerimiento,
            ra.id_empleado_solicitante,
            CONCAT(emp.nombre, ' ', emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            ra.correlativo AS codigo_requerimiento,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at,
            (SELECT COUNT(*) FROM requerimiento_almacen_detalle rad WHERE rad.id_requerimiento_almacen = ra.id) as total_items
        FROM
            requerimiento_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado_solicitante
        INNER JOIN mina m ON m.id = ra.id_mina
        WHERE
            ra.id_almacen_destino = :id_almacen
        ";

        $params = ['id_almacen' => $id_almacen];

        if ($estado) {
            $sql .= ' AND ra.estado = :estado';
            $params['estado'] = $estado;
        }

        if ($mes && $anio) {
            $sql .= ' AND MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :anio';
            $params['mes'] = $mes;
            $params['anio'] = $anio;
        } elseif ($mes) {
            $sql .= ' AND MONTH(ra.created_at) = :mes';
            $params['mes'] = $mes;
        } elseif ($anio) {
            $sql .= ' AND YEAR(ra.created_at) = :anio';
            $params['anio'] = $anio;
        }

        $sql .= " ORDER BY 
            CASE ra.premura 
                WHEN 'Emergencia' THEN 1 
                WHEN 'Urgente' THEN 2 
                WHEN 'Normal' THEN 3 
                ELSE 4 
            END ASC,
            ra.fecha_entrega_requerida ASC,
            ra.created_at ASC";

        return DB::select($sql, $params);
    }
}
