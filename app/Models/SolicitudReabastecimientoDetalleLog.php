<?php

namespace App\Models;

use App\Shared\Enums\SolicitudReabastecimiento\EstadoSolicitudDetalle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Tabla que registra la trazabilidad de cada producto de una solicitud de reabastecimiento
class SolicitudReabastecimientoDetalleLog extends Model
{
    protected $table = 'solicitud_reabastecimiento_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_solicitud_reabastecimiento_detalle',
        'id_empleado', // quien provoco el cambio
        //
        'descripcion', // descripcion del cambio
        //
        'created_at',
        'estado', // pendiente, aprobado, etc
    ];

    /**
     * Inserta un log de trazabilidad para un detalle de solicitud de reabastecimiento
     */
    public static function crear_log(
        int $id_solicitud_detalle,
        int $id_empleado,
        string $descripcion,
        EstadoSolicitudDetalle $estado,
        ?string $created_at = null
    ) {
        return self::insertGetId([
            'id_solicitud_reabastecimiento_detalle' => $id_solicitud_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'estado' => $estado->value,
            'created_at' => $created_at ?? now()
        ]);
    }

    /**
     * Obtiene los logs de trazabilidad de un detalle
     */
    public static function get_logs(
        ?int $id_log = null,
        ?int $id_solicitud_detalle = null
    ) {
        $sql = '
            SELECT DISTINCT
                srdl.id AS id_solicitud_detalle_log,
                CASE
                    WHEN srdl.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = srdl.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                srdl.descripcion,
                srdl.created_at,
                srdl.estado
            FROM
                solicitud_reabastecimiento_detalle_log srdl
            WHERE 1 = 1
        ';

        $params = [];

        if ($id_log !== null) {
            $sql .= ' AND srdl.id = :id_log';
            $params['id_log'] = $id_log;
            return DB::selectOne($sql, $params);
        }

        if ($id_solicitud_detalle !== null) {
            $sql .= ' AND srdl.id_solicitud_reabastecimiento_detalle = :id_solicitud_detalle';
            $params['id_solicitud_detalle'] = $id_solicitud_detalle;
        }

        $sql .= ' ORDER BY srdl.created_at DESC';
        return DB::select($sql, $params);
    }
}
