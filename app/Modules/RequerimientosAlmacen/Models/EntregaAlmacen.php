<?php

namespace App\Modules\RequerimientosAlmacen\Models;

use App\Shared\Enums\EstadoRequerimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EntregaAlmacen extends Model
{
    protected $table = 'entrega_almacen';

    public static function crear_entrega(
        string $correlativo,
        int $numero_correlativo,
        int $id_usuario_entrega,
        int $id_requerimiento,
        string $fecha_entrega,
        ?string $observacion = null,
        ?string $evidencias = null
    ) {
        return DB::table('entrega_almacen')->insertGetId([
            'correlativo'        => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'id_usuario_entrega' => $id_usuario_entrega,
            'id_requerimiento'   => $id_requerimiento,
            'fecha_entrega'      => $fecha_entrega,
            'observacion'        => $observacion,
            'evidencias'         => $evidencias,
            'created_at'         => now(),
            'estado'             => EstadoRequerimiento::Generada->value
        ]);
    }
}
