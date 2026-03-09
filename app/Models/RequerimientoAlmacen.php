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
        'observacion',
        'fecha_entrega_requerida',
        'created_at',
        'estado',
    ];

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
