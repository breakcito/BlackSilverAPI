<?php

namespace App\Models;

use App\Shared\Enums\OrdenCompra\EstadoOrdenCompraDetalleLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenCompraDetalleLog extends Model
{
    protected $table = 'orden_compra_detalle_log';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_compra_detalle',
        'id_empleado',
        'descripcion',
        'created_at',
        'estado',
    ];

    // Crea una entrada de log para un detalle de OC
    public static function crear_log(
        int $id_orden_compra_detalle,
        int $id_empleado,
        EstadoOrdenCompraDetalleLog $estado,
        ?string $dinamico = null,
    ): int {
        return self::insertGetId([
            'id_orden_compra_detalle' => $id_orden_compra_detalle,
            'id_empleado' => $id_empleado,
            'descripcion' => $estado->getGlosa($dinamico),
            'created_at' => now(),
            'estado' => $estado->value,
        ]);
    }

    /**
     * Obtiene la trazabilidad de un detalle de OC
     */
    public static function get_logs(int $id_orden_compra_detalle): array
    {
        return DB::select('
            SELECT
                log.id as id_log,
                log.descripcion,
                log.created_at,
                CONCAT(e.nombre, " ", e.apellido) AS empleado,
                e.url_foto,
                log.estado
            FROM orden_compra_detalle_log log
            INNER JOIN empleado e ON e.id = log.id_empleado
            WHERE log.id_orden_compra_detalle = :id_orden_compra_detalle
            ORDER BY log.id DESC
        ', ['id_orden_compra_detalle' => $id_orden_compra_detalle]);
    }
}
