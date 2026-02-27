<?php

namespace App\Modules\PrestamosAlmacen\Models;

use App\Shared\Enums\EstadoDetallePrestamo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PrestamoAlmacenDetalleLog extends Model
{
    protected $table = 'prestamo_almacen_detalle_log';

    public static function registrar_log(
        int $id_prestamo_detalle,
        int $id_usuario,
        EstadoDetallePrestamo $estado,
        ?string $dinamico = null
    ) {
        return DB::table('prestamo_almacen_detalle_log')->insert([
            'id_prestamo_almacen_detalle' => $id_prestamo_detalle,
            'id_usuario'                  => $id_usuario,
            'glosa'                       => $estado->getGlosa($dinamico),
            'estado'                      => $estado->value,
            'created_at'                  => now()
        ]);
    }

    public static function get_trazabilidad(int $id_prestamo_detalle)
    {
        return DB::table('prestamo_almacen_detalle_log AS rl')
            ->leftJoin('usuario AS u', 'u.id', '=', 'rl.id_usuario')
            ->leftJoin('empleado AS e', 'e.id', '=', 'u.id_empleado')
            ->where('rl.id_prestamo_almacen_detalle', $id_prestamo_detalle)
            ->select(
                'rl.id',
                'rl.glosa',
                'rl.estado',
                'rl.created_at',
                DB::raw("IFNULL(CONCAT(e.nombre, ' ', e.apellido), 'Usuario Sistema') AS usuario")
            )
            ->orderBy('rl.created_at', 'DESC')
            ->orderBy('rl.id', 'DESC')
            ->get();
    }
}
