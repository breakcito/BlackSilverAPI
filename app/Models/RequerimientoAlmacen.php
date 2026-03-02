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
        'id_mina', // la mina que necesita los productos
        'id_almacen_destino', // a que almacen se hace el requerimiento
        //
        'correlativo',
        'numero_correlativo',
        'premura',
        'fecha_entrega_requerida',
        //
        'created_at',
        'estado',
    ];

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
            ra.id_usuario_solicitante,
            CONCAT(emp.nombre, " ", emp.apellido) AS solicitante,
            ra.id_mina,
            m.nombre AS mina,
            ra.id_almacen_destino,
            alm.nombre AS almacen_destino,
            ra.correlativo,
            ra.premura,
            ra.fecha_entrega_requerida,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN usuario u ON u.id = ra.id_usuario_solicitante
        INNER JOIN empleado emp ON emp.id = u.id_empleado
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
}
