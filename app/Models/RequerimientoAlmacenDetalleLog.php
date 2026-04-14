<?php

namespace App\Models;

use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Tabla que registra la trazabilidad de cada producto de un requerimiento de almacen
class RequerimientoAlmacenDetalleLog extends Model
{
    protected $table = 'requerimiento_almacen_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen_detalle',
        'id_empleado', // quien provoco el cambio
        //
        'descripcion', // descripcion del cambio
        //
        'created_at',
        'estado', // pendiente, aprobado, etc
    ];

    public static function crear_log(
        int $id_requerimiento_detalle,
        string $descripcion,
        EstadoRequerimientoDetalleLog $estado,
        ?int $id_empleado = null
    ) {
        return self::create([
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Obtiene un registro o la trazabilidad completa de un detalle de requerimiento de almacen
     */
    public static function get_logs(
        ?int $id_log = null,
        ?int $id_requerimiento_detalle = null
    ): array {
        $sql = '
            SELECT DISTINCT
                trz.id AS id_trazabilidad,
                CASE
                    WHEN trz.id_empleado IS NOT NULL THEN (
                        SELECT CONCAT(emp.nombre, " ", emp.apellido)
                        FROM empleado emp
                        WHERE emp.id = trz.id_empleado
                    )
                    ELSE NULL
                END AS empleado,
                trz.descripcion,
                trz.created_at,
                trz.estado
            FROM
                requerimiento_almacen_detalle_log trz
            WHERE
            1=1
        ';

        $params = [];
        if ($id_log !== null) {
            $sql .= ' AND trz.id = :id_log';
            $params['id_log'] = $id_log;
            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento_detalle !== null) {
            $sql .= ' AND trz.id_requerimiento_almacen_detalle = :id_requerimiento_detalle';
            $params['id_requerimiento_detalle'] = $id_requerimiento_detalle;
        }

        $sql .= ' ORDER BY trz.created_at DESC';

        return DB::select($sql, $params);
    }
}
