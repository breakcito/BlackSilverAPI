<?php

namespace App\Models;

use App\Shared\Enums\PrestamoAlmacen\EstadoDetallePrestamo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenDetalleLog extends Model
{
    protected $table = 'prestamo_almacen_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_prestamo_almacen_detalle',
        'id_empleado',
        'descripcion',
        'created_at',
        'estado',
    ];

    public static function crear_log(
        int $id_prestamo_almacen_detalle,
        int $id_empleado,
        string $descripcion,
        ?string $created_at = null,
        EstadoDetallePrestamo $estado,
    ) {
        return self::insertGetId([
            'id_prestamo_almacen_detalle' => $id_prestamo_almacen_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $descripcion,
            'created_at' => $created_at ?? now(),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Obtiene un registro o la trazabilidad completa de un detalle de prestamo
     */
    public static function get_logs(
        ?int $id_log = null,
        ?int $id_prestamo_detalle = null
    ): array {
        $sql = '
        SELECT
            log.id as id_log,
            log.estado,
            log.descripcion,
            log.created_at,
            CONCAT(e.nombre, " ", e.apellido) AS empleado,
            e.path_foto
        FROM
            prestamo_almacen_detalle_log log
        INNER JOIN empleado e ON e.id = log.id_empleado
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_log) {
            $sql .= " AND log.id = :id_log";
            $params['id_log'] = $id_log;
            return DB::selectOne($sql, $params);
        }

        if ($id_prestamo_detalle) {
            $sql .= " AND log.id_prestamo_almacen_detalle = :id_prestamo_detalle";
            $params['id_prestamo_detalle'] = $id_prestamo_detalle;
        }

        $sql .= " ORDER BY log.created_at DESC";
        return DB::select($sql, $params);
    }
}
