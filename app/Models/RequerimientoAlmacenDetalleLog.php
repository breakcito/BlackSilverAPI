<?php

namespace App\Modules\RequerimientosAlmacen\Models;

use App\Shared\Enums\EstadoDetalleRequerimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenDetalleLog extends Model
{
    protected $table = 'requerimiento_almacen_detalle_log';

    public static function registrar_log(
        int $id_requerimiento_almacen_detalle,
        int $id_usuario,
        EstadoDetalleRequerimiento $estado,
        ?string $dinamico = null
    ) {
        return DB::table('requerimiento_almacen_detalle_log')->insert([
            'id_requerimiento_almacen_detalle' => $id_requerimiento_almacen_detalle,
            'id_usuario'                       => $id_usuario,
            'glosa'                             => $estado->getGlosa($dinamico),
            'estado'                            => $estado->value,
            'created_at'                        => now()
        ]);
    }

    public static function get_trazabilidad(int $id_requerimiento_almacen_detalle)
    {
        return DB::table('requerimiento_almacen_detalle_log as rl')
            ->leftJoin('usuario as u', 'u.id', '=', 'rl.id_usuario')
            ->leftJoin('empleado as e', 'e.id', '=', 'u.id_empleado')
            ->where('rl.id_requerimiento_almacen_detalle', $id_requerimiento_almacen_detalle)
            ->select(
                'rl.id',
                'rl.glosa',
                'rl.estado',
                'rl.created_at',
                DB::raw("IFNULL(CONCAT(e.nombre, ' ', e.apellido), 'Usuario Sistema') as usuario")
            )
            ->orderBy('rl.created_at', 'DESC')
            ->orderBy('rl.id', 'DESC')
            ->get();
    }
}
